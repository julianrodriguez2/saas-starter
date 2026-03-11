<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'resolve.organization' => \App\Http\Middleware\ResolveOrganizationFromSession::class,
            'org.role' => \App\Http\Middleware\EnsureOrganizationRole::class,
            'org.writable' => \App\Http\Middleware\EnsureOrganizationCanWrite::class,
            'super.admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'auth.org_api_key' => \App\Http\Middleware\AuthenticateOrganizationApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
