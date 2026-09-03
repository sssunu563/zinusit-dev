<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\ValidateGrafanaApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'grafana.api' => ValidateGrafanaApiKey::class,
            'password.change' => RequirePasswordChange::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // ── Auto fetch semua report setiap jam 00:00 (data kemarin) ──────────
        $schedule->command('reports:fetch-all')->dailyAt('00:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/fetch-all-reports.log'));

        // ── Tugas rutin lainnya ───────────────────────────────────────────────
        $schedule->command('loan:reminders')->dailyAt('08:00');
        $schedule->command('storage:cleanup')->dailyAt('01:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
