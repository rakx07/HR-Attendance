<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Http\Kernel as HttpKernel; // 👈 add this

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
    // 👇 ARRAY, not a closure
    ->withBindings([
        HttpKernel::class => \App\Http\Kernel::class,
        // You could also add console kernel / exception handler here if needed
        // \Illuminate\Contracts\Console\Kernel::class => \App\Console\Kernel::class,
        // \Illuminate\Contracts\Debug\ExceptionHandler::class => \App\Exceptions\Handler::class,
    ])
    ->create();
