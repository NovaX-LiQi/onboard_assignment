<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('tenancy.domain', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class, //subdomain
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class, //防止不使用subdomain
        ]);
        $middleware->appendToGroup('tenancy.request', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByRequestData::class, //X-Tenant
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
