<?php

namespace App\Integrations\Facebook;

use Illuminate\Support\Facades\Http;
use App\Dtos\FacebookInsightsRequestDTO;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FacebookClient
{
    protected string $baseUrl = 'https://graph.facebook.com/v25.0'; // 规范化指定 API 版本
    protected string $cbKey = 'cb:facebook:status';
    protected string $failCountKey = 'cb:facebook:fail_count';

    public function validateAdsReadPermission(string $accessToken): bool
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
                ->get("{$this->baseUrl}/me/permissions")
                ->throw()
                ->json();

            foreach ($response['data'] ?? [] as $permission) {
                if (($permission['permission'] ?? '') === 'ads_read' && ($permission['status'] ?? '') === 'granted') {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Facebook permission check failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    //利用 PHP 生成器 `\Generator` 和 `yield` 来按需拉取 Facebook 广告数据。
    //根据 level (如 ad, adset) 动态追加必须携带的外部 ID 字段。
    //`do-while` 循环：只要 Facebook 返回的数据结构里含有 `paging.next` 的 URL，就代表还有下一页。
    //`yield $response`：每次抓完一页数据，立刻吐出去给外层处理，接着清除 queryParams 换成 next 的 url 继续抓。
    //`usleep(200000)`：强制每页间歇 0.2 秒。
    //* 极其节省内存：哪怕某个租户的 Facebook 广告数据有几万条、分几百页，使用 Generator 意味着在内存中**永远只保留当前一页的数据**，绝对不会撑爆服务器内存。
    //* 频率保护：`usleep` 有效防范了因为高频疯狂请求而被 Facebook 官方 API 限流（Rate Limit）甚至封锁 Token 的惨剧。
    public function getInsights(string $accessToken, string $adAccountId, FacebookInsightsRequestDTO $dto): \Generator
    {
        //Circuit Breaker
        //一旦系统发现由于网络超时、官方 5xx 崩溃或 429 频率限流导致连续失败达到 5 次，熔断器立刻弹开，并在接下来 30 秒内，任何租户企图请求 Facebook 接口，都会在本地被直接拦截拒绝（不再发起真正的 HTTP 网络请求）。
        //30 秒冷却时间后，如果下一次任务完整、顺利地跑完了所有分页，才会执行 resetFailure() 闭合开关。
        $this->checkCircuitBreaker();

        $url = "{$this->baseUrl}/act_{$adAccountId}/insights";
        $fields = $dto->fields;

        $levelFieldMap = [
            'ad' => ['ad_id'],
            'adset' => ['adset_id'],
            'campaign' => ['campaign_id'],
        ];

        if (isset($levelFieldMap[$dto->level])) {
            $fields = array_merge($fields, $levelFieldMap[$dto->level]);
        }

        $queryParams = [
            'level' => $dto->level,
            'fields' => implode(',', array_unique($fields)),
            'time_range' => json_encode([
                'since' => $dto->dateFrom,
                'until' => $dto->dateTo,
            ]),
            'limit' => 100,
        ];

        do {
            try {
                $response = Http::timeout(30)
                    ->retry(3, 200) //遭遇网络波动自动重试 3 次，每次间隔 200 毫秒
                    ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
                    ->get($url, $queryParams)
                    ->throw()
                    ->json();

                yield $response;

                $queryParams = []; 
                $url = $response['paging']['next'] ?? null;

                if ($url !== null) {
                    usleep(200000);
                }

            } catch (\Throwable $e) {
                //记录失败并可能触发熔断
                $this->trackFailure($e);
                throw $e;
            }

        } while ($url !== null);

        //整个 do-while 循环安全走完了，说明所有分页全部同步成功，此时再重置计数器！
        $this->resetFailure();
    }

    protected function checkCircuitBreaker()
    {
        $status = Redis::get($this->cbKey);
        if ($status === 'open') {
            //熔断器处于开启状态，直接拒绝请求
            throw new \RuntimeException("Circuit breaker is OPEN for Facebook API. Request blocked.");
        }
    }

    protected function trackFailure(\Throwable $e)
    {
        //尝试获取真正的 HTTP 状态码
        $statusCode = 0;
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            $statusCode = $e->response->status();
        } else {
            $statusCode = $e->getCode();
        }

        //如果是标准的 4xx 业务错误（除了 429 频率限制），不属于系统级故障，不触发熔断
        if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
            return; 
        }

        //补全：真正的计数与状态转化逻辑
        $fails = Redis::incr($this->failCountKey);
        if ($fails === 1) {
            Redis::expire($this->failCountKey, 60); //1分钟内统计窗口
        }

        //连续失败 5 次，开启熔断
        if ($fails >= 5) {
            //SET with EXpiration
            Redis::setex($this->cbKey, 30, 'open'); //熔断 30 秒，拒绝任何网络请求
            Log::warning('Circuit breaker flipped to OPEN for Facebook API. Direct block enabled for 30s.');
        }
    }

    protected function resetFailure()
    {
        Redis::del($this->failCountKey);
        Redis::del($this->cbKey);
    }
}