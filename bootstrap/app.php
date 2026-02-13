<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SystemPasswordGate;
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
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            EnsureSetupComplete::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'system.password' => SystemPasswordGate::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
