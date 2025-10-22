<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel; // ← add this use

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // keep whatever you already have
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // keep whatever you already have
    })
    ->withBindings([
        HttpKernel::class => \App\Http\Kernel::class,
        ConsoleKernel::class => \App\Console\Kernel::class, // ← REQUIRED
        // \Illuminate\Contracts\Debug\ExceptionHandler::class => \App\Exceptions\Handler::class,
    ])
    ->create();
