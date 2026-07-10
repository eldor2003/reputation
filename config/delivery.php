<?php

use App\Support\TelegramChatIdResolver;

return [

    'engine' => App\Services\Delivery\DeliveryEngine::class,

    'digest_engine' => App\Services\Delivery\DigestEngine::class,

    'context_builder' => App\Services\Delivery\DeliveryContextBuilder::class,

    'card_builder' => App\Services\Delivery\DeliveryCardBuilder::class,

    'telegram' => [
        'telegram_moderation' => [
            'bot_token' => env('TELEGRAM_MODERATION_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN')),
            'chat_ids' => TelegramChatIdResolver::resolve(
                chatIds: env('TELEGRAM_MODERATION_CHAT_IDS', env('TELEGRAM_CHAT_IDS')),
                legacyChatId: env('TELEGRAM_CHAT_ID'),
            ),
        ],
        'telegram_delivery' => [
            'bot_token' => env('TELEGRAM_DELIVERY_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN')),
            'chat_ids' => TelegramChatIdResolver::resolve(
                chatIds: env('TELEGRAM_DELIVERY_CHAT_IDS'),
            ),
        ],
    ],

    'digest' => [
        'morning' => [
            'hour' => (int) env('DELIVERY_DIGEST_MORNING_HOUR', 8),
            'minute' => (int) env('DELIVERY_DIGEST_MORNING_MINUTE', 0),
            'label' => 'Утренний дайджест',
        ],
        'evening' => [
            'hour' => (int) env('DELIVERY_DIGEST_EVENING_HOUR', 18),
            'minute' => (int) env('DELIVERY_DIGEST_EVENING_MINUTE', 0),
            'label' => 'Вечерний дайджест',
        ],
        'manual' => [
            'label' => 'Ручной дайджест',
        ],
        'default_type_for_routing_digest' => env('DELIVERY_DEFAULT_DIGEST_TYPE', 'morning'),
        'default_type_for_routing_deferred' => env('DELIVERY_DEFERRED_DIGEST_TYPE', 'evening'),
    ],

];
