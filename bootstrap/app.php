<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'kasir' => \App\Http\Middleware\KasirMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'session.timeout' => \App\Http\Middleware\SessionTimeout::class,
            'activity.logger' => \App\Http\Middleware\ActivityLogger::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\App\Exceptions\InsufficientStockException $e, $request) {
            return $e->render($request);
        });

        $exceptions->renderable(function (\App\Exceptions\UnauthorizedException $e, $request) {
            return $e->render($request);
        });
    })->create();
