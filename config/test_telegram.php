<?php

use App\Support\TelegramChatIdResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Acceptance Test Mode (temporary)
    |--------------------------------------------------------------------------
    |
    | When enabled, every successfully processed mention is additionally sent
    | to the configured test Telegram chat(s). Production moderation and
    | delivery flows are unchanged.
    |
    */

    'enabled' => filter_var(env('TEST_TELEGRAM_ENABLED', false), FILTER_VALIDATE_BOOL),

    'bot_token' => env('TEST_TELEGRAM_BOT_TOKEN'),

    'chat_ids' => TelegramChatIdResolver::resolve(
        chatIds: env('TEST_TELEGRAM_CHAT_IDS'),
    ),

];
