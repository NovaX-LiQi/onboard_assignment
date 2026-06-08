<?php

namespace App\Jobs;

use App\Dtos\FacebookInsightsRequestDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RunFacebookDailySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $tenantId) {}

    public function handle(): void
    {
        try {
            //切换进该租户
            tenancy()->initialize($this->tenantId);

            $date = now()->subDay();
            $levels = ['ad', 'adset', 'campaign'];
            $fields = ['impressions', 'clicks', 'spend'];

            foreach ($levels as $level) {
                $dto = FacebookInsightsRequestDTO::make([
                    'provider' => 'facebook',
                    'level' => $level,
                    'fields' => $fields,
                    'date_from' => $date->toDateString(),
                    'date_to' => $date->toDateString(),
                ]);
                
                SyncFacebookInsightsJob::dispatch($this->tenantId, $dto->toArray());
            }
        } finally {
            tenancy()->end();
        }
    }
}