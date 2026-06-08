<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\SanctumToken;
use App\Models\Tenant;
use App\Policies\TenantIntegrationPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(\Laravel\Sanctum\SanctumServiceProvider::class)) {
            $this->app->register(\Laravel\Sanctum\SanctumServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //将 Tenant 模型与 TenantIntegrationPolicy 权限策略进行硬绑定。
        //此后在控制器里直接调用 `Gate::authorize('manageSettings', [tenant()])`，Laravel 会自动辨识并切入到此 Policy 进行鉴权。
        Gate::policy(Tenant::class, TenantIntegrationPolicy::class);

        if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
            \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\SanctumToken::class);
        }
    }
}
