<?php

namespace App\Support;

use App\Enums\TelegramDestination;

final class TelegramDestinationConfig
{
    /**
     * @return list<string>
     */
    public static function chatIds(TelegramDestination $destination): array
    {
        /** @var list<string>|string|null $configured */
        $configured = config("delivery.telegram.{$destination->value}.chat_ids");

        if (is_array($configured) && $configured !== []) {
            return self::normalize($configured);
        }

        if ($destination === TelegramDestination::Moderation) {
            return TelegramChatIdResolver::fromConfig();
        }

        return [];
    }

    public static function botToken(TelegramDestination $destination): ?string
    {
        $token = config("delivery.telegram.{$destination->value}.bot_token");

        if (is_string($token) && $token !== '') {
            return $token;
        }

        if ($destination === TelegramDestination::Moderation) {
            $legacy = config('telegram.bot_token');

            return is_string($legacy) && $legacy !== '' ? $legacy : null;
        }

        return null;
    }

    /**
     * @param  list<string>  $chatIds
     * @return list<string>
     */
    private static function normalize(array $chatIds): array
    {
        return array_values(array_filter(
            array_map(strval(...), $chatIds),
            fn (string $chatId): bool => $chatId !== '',
        ));
    }
}
