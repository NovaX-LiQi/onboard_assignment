<?php

use App\Models\ExternalAccount;
use App\Models\IntegrationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SyncFacebookInsightsJob;

/** @var \Tests\TenantTestCase $this */

test('it can connect facebook integration successfully', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [['permission' => 'ads_read', 'status' => 'granted']]
        ], 200)
    ]);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->tenantToken}",
    ])->postJson("http://{$this->domain}/api/integrations/connect", [
        'provider' => 'facebook',
        'credentials' => [
            'access_token' => 'mock_fb_token',
            'ad_account_id' => 'act_123456789',
        ]
    ])->assertStatus(200)->assertJson(['message' => 'connected']);

    $this->tenant->run(function () {
        $account = ExternalAccount::where('provider', 'facebook')->first();
        expect($account)->not->toBeNull();
        expect($account->ad_account_id)->toBe('act_123456789');
        expect($account->access_token)->toBe('mock_fb_token');
    });
});

test('it fails connection if facebook permission is missing', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => [['permission' => 'ads_read', 'status' => 'declined']]
        ], 200)
    ]);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->tenantToken}",
    ])->postJson("http://{$this->domain}/api/integrations/connect", [
        'provider' => 'facebook',
        'credentials' => [
            'access_token' => 'invalid_token',
            'ad_account_id' => 'act_123456789',
        ]
    ])->assertStatus(422)->assertJsonValidationErrors(['credentials.access_token']);
});

test('it can dispatch sync job', function () {
    Queue::fake();

    $this->withHeaders([
        'Authorization' => "Bearer {$this->tenantToken}",
    ])->postJson("http://{$this->domain}/api/integrations/sync", [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-07',
        'level' => 'campaign',
    ])->assertStatus(202);

    Queue::assertPushed(SyncFacebookInsightsJob::class, function ($job) {
        return $job->tenantId === $this->tenant->id && $job->dtoData['level'] === 'campaign';
    });
});

test('it can get latest job status', function () {
    $this->tenant->run(function () {
        IntegrationJob::create([
            'provider' => 'facebook',
            'status' => 'success',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-02',
            'payload' => [],
        ]);
    });

    $this->withHeaders([
        'Authorization' => "Bearer {$this->tenantToken}",
    ])->getJson("http://{$this->domain}/api/integrations/status?provider=facebook")
      ->assertStatus(200)
      ->assertJsonPath('status', 'success');
});