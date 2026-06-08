<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\InsightController;
use App\Http\Controllers\Api\IntegrationStatusController;

//创建tenant和生成token 是不需要subdomain和使用其它如x-tenant和sactum验证的，因为都还不存在
//for如果以后有account use
Route::middleware(['api'])->group(function () {
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::post('/tenants/token', [TenantController::class, 'issueToken']);
});


//以下2选1，能使用subdomain也能使用x-tenant
//   1. 'tenancy.domain'：根据域名自动把数据库切到该租户的独立数据库。
//   2. 'auth:sanctum'：在切换后的租户库中，校验 Bearer Token 是否合法。
Route::middleware([
    'api',
    'tenancy.domain',
    'auth:sanctum',
])->group(function () {

    Route::post('/integrations/connect', [IntegrationController::class, 'connect']);
    Route::post('/integrations/sync', [IntegrationController::class, 'sync']);
    Route::get('/insights', [InsightController::class, 'index']);
    Route::get('/integrations/status', [IntegrationStatusController::class, 'index']);

});

//http://localhost:8000/v1/app/integrations/connect
//使用X-Tenant
Route::prefix('v1/app')->middleware([
    'api',
    'tenancy.request',
    'auth:sanctum',
])->group(function () {
    Route::post('/integrations/connect', [IntegrationController::class, 'connect']);
    Route::post('/integrations/sync', [IntegrationController::class, 'sync']);
    Route::get('/insights', [InsightController::class, 'index']);
    Route::get('/integrations/status', [IntegrationStatusController::class, 'index']);

});