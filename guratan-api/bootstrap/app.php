<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LogActivity;
use App\Http\Middleware\LogReportAccess;
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
        $middleware->alias([
            'log.report_access' => LogReportAccess::class,
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->appendToGroup('api', LogActivity::class);

        // API-only app: no "login" route exists. Laravel's default middleware
        // config redirects guests to route('login'), which throws
        // RouteNotFoundException here. Disable the redirect so unauthenticated
        // requests fall through to a plain 401 JSON response instead.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Sentry: no-op bila SENTRY_LARAVEL_DSN kosong. Dijaga class_exists supaya aplikasi tetap jalan
        // di mesin yang belum menjalankan `composer install` setelah paket ini ditambahkan.
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }
    })->create();
