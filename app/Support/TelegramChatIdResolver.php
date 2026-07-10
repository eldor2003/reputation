<?php

namespace App\Support;

final class TelegramChatIdResolver
{
    /**
     * @return list<string>
     */
    public static function resolve(?string $chatIds = null, ?string $legacyChatId = null): array
    {
        $resolved = [];

        if (is_string($chatIds) && $chatIds !== '') {
            $resolved = array_merge($resolved, explode(',', $chatIds));
        }

        if (is_string($legacyChatId) && $legacyChatId !== '') {
            $resolved[] = $legacyChatId;
        }

        $resolved = array_map(trim(...), $resolved);

        return array_values(array_unique(array_filter(
            $resolved,
            fn (string $chatId): bool => $chatId !== '',
        )));
    }

    /**
     * @return list<string>
     */
    public static function fromConfig(): array
    {
        /** @var list<string> $chatIds */
        $chatIds = config('telegram.chat_ids', []);

        if ($chatIds !== []) {
            return $chatIds;
        }

        $legacyChatId = config('telegram.chat_id');

        return self::resolve(
            legacyChatId: is_string($legacyChatId) ? $legacyChatId : null,
        );
    }
}
