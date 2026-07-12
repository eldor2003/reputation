<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ingest.token' => \App\Http\Middleware\AuthenticateIngestApiToken::class,
            'telegram.webhook' => \App\Http\Middleware\AuthenticateTelegramWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\App\Exceptions\SourceNotAvailableException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }
        });

        $exceptions->render(function (\App\Exceptions\IngestMentionException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 500);
            }
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $timezone = (string) config('routing.timezone');

        $schedule->command('delivery:generate-digest morning')
            ->dailyAt(sprintf(
                '%02d:%02d',
                (int) config('delivery.digest.morning.hour'),
                (int) config('delivery.digest.morning.minute'),
            ))
            ->timezone($timezone)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('delivery:generate-digest evening')
            ->dailyAt(sprintf(
                '%02d:%02d',
                (int) config('delivery.digest.evening.hour'),
                (int) config('delivery.digest.evening.minute'),
            ))
            ->timezone($timezone)
            ->withoutOverlapping()
            ->onOneServer();

        $mentionlyticsInterval = max(1, (int) config('mentionlytics.polling.interval_minutes'));

        $mentionlyticsSchedule = $schedule->command('mentionlytics:poll')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->onOneServer();

        if ($mentionlyticsInterval < 60) {
            $mentionlyticsSchedule->cron(sprintf('*/%d * * * *', $mentionlyticsInterval));
        } else {
            $mentionlyticsHours = max(1, (int) round($mentionlyticsInterval / 60));
            $mentionlyticsSchedule->cron(sprintf('0 */%d * * *', $mentionlyticsHours));
        }

        $serpInterval = max(1, (int) config('serpapi.snapshots.interval_minutes'));

        $serpSchedule = $schedule->command('serp:snapshot')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->onOneServer();

        if ($serpInterval < 60) {
            $serpSchedule->cron(sprintf('*/%d * * * *', $serpInterval));
        } else {
            $serpHours = max(1, (int) round($serpInterval / 60));
            $serpSchedule->cron(sprintf('0 */%d * * *', $serpHours));
        }
    })
    ->create();
