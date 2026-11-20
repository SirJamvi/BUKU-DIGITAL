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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \App\Http\Middleware\BranchContextMiddleware::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'kasir' => \App\Http\Middleware\KasirMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'session.timeout' => \App\Http\Middleware\SessionTimeout::class,
            'activity.logger' => \App\Http\Middleware\ActivityLogger::class,
            'branch.context' => \App\Http\Middleware\BranchContextMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Tangani error '419 | Page Expired' (CSRF Token Mismatch) agar tidak memunculkan error mati
        $exceptions->renderable(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Sesi Anda sempat kedaluwarsa. Token keamanan telah diperbarui, silakan ulangi.',
                    'csrf_expired' => true,
                ], 419);
            }

            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                ->with('warning', 'Sesi halaman Anda sempat kedaluwarsa. Token keamanan telah otomatis diperbarui, silakan klik simpan kembali.');
        });

        $exceptions->renderable(function (\App\Exceptions\InsufficientStockException $e, $request) {
            return $e->render($request);
        });

        $exceptions->renderable(function (\App\Exceptions\UnauthorizedException $e, $request) {
            return $e->render($request);
        });

        $exceptions->renderable(function (\App\Exceptions\BranchRequiredException $e, $request) {
            return $e->render($request);
        });
    })->create();
