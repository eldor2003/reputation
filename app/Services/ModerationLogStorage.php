<?php

namespace App\Services;

use App\Contracts\ModerationLogStorageInterface;
use App\Enums\ModerationAction;
use App\Models\ModerationLog;

class ModerationLogStorage implements ModerationLogStorageInterface
{
    public function store(
        int $mentionId,
        ModerationAction $action,
        string $moderatorId,
        ?string $moderatorUsername,
        string $telegramChatId,
        ?string $telegramMessageId,
        ?string $callbackQueryId,
    ): ModerationLog {
        return ModerationLog::query()->create([
            'mention_id' => $mentionId,
            'action' => $action,
            'moderator_id' => $moderatorId,
            'moderator_username' => $moderatorUsername,
            'telegram_chat_id' => $telegramChatId,
            'telegram_message_id' => $telegramMessageId,
            'callback_query_id' => $callbackQueryId,
        ]);
    }

    public function existsForMention(int $mentionId): bool
    {
        return ModerationLog::query()
            ->where('mention_id', $mentionId)
            ->exists();
    }
}
