<?php

use App\Models\ExternalAccount;
use App\Models\IntegrationJob;
use App\Jobs\SyncFacebookInsightsJob;
use App\Jobs\RunFacebookDailySyncJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/** @var \Tests\TenantTestCase $this */

test('it processes sync job and saves generator data to db', function () {
    $this->tenant->run(function () {
        ExternalAccount::create([
            'provider' => 'facebook',
            'access_token' => 'valid_token',
            'ad_account_id' => '9999',
        ]);
    });

    Http::fake([
        'graph.facebook.com/v25.0/act_9999/insights*' => Http::sequence()
            ->push([
                'data' => [['campaign_id' => 'camp_001', 'date_start' => '2026-06-01', 'impressions' => '1000', 'clicks' => '50', 'spend' => '10.5']],
                'paging' => ['next' => 'https://graph.facebook.com/v25.0/act_9999/insights?after=nextpage']
            ])
            ->push([
                'data' => [['campaign_id' => 'camp_002', 'date_start' => '2026-06-01', 'impressions' => '2000', 'clicks' => '120', 'spend' => '30.0']],
                'paging' => []
            ]),
    ]);

    $job = new SyncFacebookInsightsJob($this->tenant->id, [
        'provider' => 'facebook',
        'level' => 'campaign',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-01',
    ]);

    app()->call([$job, 'handle']);

    $this->tenant->run(function () {
        $jobRecord = IntegrationJob::where('provider', 'facebook')->latest()->first();
        expect($jobRecord->status)->toBe('success');

        $this->assertDatabaseHas('insight_records', ['external_id' => 'camp_001', 'impressions' => 1000]);
        $this->assertDatabaseHas('insight_records', ['external_id' => 'camp_002', 'impressions' => 2000]);
    });
});

test('it can trigger daily sync from scheduler', function () {
    $this->tenant->run(function () {
        ExternalAccount::create([
            'provider' => 'facebook',
            'access_token' => 'token',
            'ad_account_id' => '123',
        ]);
    });

    Queue::fake();

    Artisan::call('schedule:run');

    Queue::assertPushed(RunFacebookDailySyncJob::class, function ($job) {
        return $job->tenantId === $this->tenant->id;
    });
});

test('circuit breaker flips to open after 5 consecutive systemic failures and blocks subsequent requests', function () {
    $this->tenant->run(function () {
        ExternalAccount::create([
            'provider' => 'facebook',
            'access_token' => 'token',
            'ad_account_id' => '9999',
        ]);
    });

    //模拟 Facebook 彻底瘫痪，连续返回 500 服务器错误
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => 'Internal Server Error'], 500)
    ]);

    $job = new SyncFacebookInsightsJob($this->tenant->id, [
        'provider' => 'facebook',
        'level' => 'campaign',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-01',
    ]);

    //连续手动执行 5 次，推高失败计数器到临界值
    for ($i = 0; $i < 5; $i++) {
        try {
            app()->call([$job, 'handle']);
        } catch (\Throwable $e) {
            //捕获预期的 Facebook 500 异常，让循环继续
        }
    }

    //此时熔断器应该已经是 OPEN 状态了。我们发起第 6 次执行：
    //如果熔断器生效，它应该抛出我们自定义的 \RuntimeException，且错误信息包含 "Circuit breaker is OPEN"
    expect(function () use ($job) {
        app()->call([$job, 'handle']);
    })->toThrow(\RuntimeException::class, 'Circuit breaker is OPEN for Facebook API');
    
    //并且验证 Redis 的状态确实被标记为了 open
    expect(\Illuminate\Support\Facades\Redis::get('cb:facebook:status'))->toBe('open');
});

test('circuit breaker does not open for 4xx client errors except 429', function () {
    $this->tenant->run(function () {
        ExternalAccount::create([
            'provider' => 'facebook',
            'access_token' => 'token',
            'ad_account_id' => '9999',
        ]);
    });

    //模拟由于前端或用户参数传错导致的 400 报错
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => 'Invalid Parameter'], 400)
    ]);

    $job = new SyncFacebookInsightsJob($this->tenant->id, [
        'provider' => 'facebook',
        'level' => 'campaign',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-01',
    ]);

    //连续发生 5 次 400 错误
    for ($i = 0; $i < 5; $i++) {
        try {
            app()->call([$job, 'handle']);
        } catch (\Throwable $e) {
            // 捕获异常
        }
    }

    //熔断器绝对不应该被打开，状态应该为 null（或者不等于 'open'）
    expect(Redis::get('cb:facebook:status'))->toBeNull();
});

test('it updates tenant job status to failed with DLQ prefix when job permanently fails', function () {
    $this->tenant->run(function () {
        ExternalAccount::create([
            'provider' => 'facebook',
            'access_token' => 'token',
            'ad_account_id' => '9999',
        ]);

        //先预埋一条处于运行中 (running) 的作业日志
        IntegrationJob::create([
            'provider' => 'facebook',
            'status' => 'running',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-01',
            'payload' => ['level' => 'campaign'],
        ]);
    });

    $job = new SyncFacebookInsightsJob($this->tenant->id, [
        'provider' => 'facebook',
        'level' => 'campaign',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-01',
    ]);

    //模拟触发 Laravel 队列底层的失败回调机制
    $exception = new \Exception('Connection timeout');
    $job->failed($exception);

    //切入租户库检查：由于触发了 DLQ 逻辑，运行中的日志状态必须被强制闭环修改为 failed 终态
    $this->tenant->run(function () {
        $this->assertDatabaseHas('integration_jobs', [
            'provider' => 'facebook',
            'status' => 'failed',
            'error' => '[DLQ Max Tries Exceeded] Connection timeout',
        ]);
    });
});