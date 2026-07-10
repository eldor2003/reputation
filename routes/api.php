<?php

use App\Http\Controllers\Api\V1\Brand24IngestController;
use App\Http\Controllers\Api\V1\MentionlyticsIngestController;
use App\Http\Controllers\Api\V1\TelegramWebhookController;
use App\Http\Controllers\Api\V1\YouScanIngestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/ingest')
    ->middleware('ingest.token')
    ->group(function (): void {
        Route::post('youscan', YouScanIngestController::class);
        Route::post('brand24', Brand24IngestController::class);
        Route::post('mentionlytics', MentionlyticsIngestController::class);
    });

Route::post('v1/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.webhook');
