<?php

use App\Models\ExternalAccount;
use App\Models\IntegrationJob;
use App\Jobs\SyncFacebookInsightsJob;
use App\Jobs\RunFacebookDailySyncJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

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