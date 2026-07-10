<?php

use App\Support\TelegramChatIdResolver;

$chatIds = TelegramChatIdResolver::resolve(
    chatIds: env('TELEGRAM_CHAT_IDS'),
    legacyChatId: env('TELEGRAM_CHAT_ID'),
);

return [

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'chat_ids' => $chatIds,

    'chat_id' => $chatIds[0] ?? env('TELEGRAM_CHAT_ID'),

    'base_url' => env('TELEGRAM_BASE_URL', 'https://api.telegram.org'),

    'timeout' => (int) env('TELEGRAM_TIMEOUT', 30),

    'retry' => [
        'times' => (int) env('TELEGRAM_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('TELEGRAM_RETRY_SLEEP_MS', 500),
    ],

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

];
