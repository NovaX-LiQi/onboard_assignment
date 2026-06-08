<?php

use App\Jobs\RunFacebookDailySyncJob;
use App\Models\ExternalAccount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Tenant;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {

    //每次拿出 100
    Tenant::query()->chunkById(100, function ($tenants) {

        foreach ($tenants as $tenant) {

            //是不是有facebook
            $hasFacebook = $tenant->run(function () {
                return ExternalAccount::where('provider', 'facebook')->exists();
            });

            if ($hasFacebook) {
                RunFacebookDailySyncJob::dispatch($tenant->id);
            }
        }
    });

})
// ->dailyAt('00:10');
->everyMinute();//为了schedule run后方便test，暂时给每分钟跑一次
