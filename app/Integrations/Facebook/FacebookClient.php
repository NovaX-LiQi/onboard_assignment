<?php

namespace App\Integrations\Facebook;

use Illuminate\Support\Facades\Http;
use App\Integrations\Facebook\Dto\FacebookInsightsRequestDTO;
use Illuminate\Support\Facades\Log;

class FacebookClient
{
    protected string $baseUrl = 'https://graph.facebook.com/v25.0'; // 规范化指定 API 版本

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
    //   * 极其节省内存：哪怕某个租户的 Facebook 广告数据有几万条、分几百页，使用 Generator 意味着在内存中**永远只保留当前一页的数据**，绝对不会撑爆服务器内存。
    //   * 频率保护：`usleep` 有效防范了因为高频疯狂请求而被 Facebook 官方 API 限流（Rate Limit）甚至封锁 Token 的惨剧。
    public function getInsights(string $accessToken, string $adAccountId, FacebookInsightsRequestDTO $dto): \Generator
    {
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

        } while ($url !== null);
    }
}