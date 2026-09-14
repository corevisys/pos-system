<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.store' => \App\Http\Middleware\EnsureUserHasStore::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'report.export' => \App\Http\Middleware\EnsureReportExport::class,
            'store.context' => \App\Http\Middleware\SetCurrentStore::class,
        ]);

        // Resolve the acting store for every web request, before any controller or
        // StoreScoped query runs. Runs after StartSession (so the session value is
        // readable) and no-ops for guests / unauthenticated requests, which lets
        // current_store_id() fall back to its legacy behaviour.
        $middleware->appendToGroup('web', \App\Http\Middleware\SetCurrentStore::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
