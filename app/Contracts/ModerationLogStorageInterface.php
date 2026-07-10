<?php

namespace App\Contracts;

use App\Enums\ModerationAction;
use App\Models\ModerationLog;

interface ModerationLogStorageInterface
{
    public function store(
        int $mentionId,
        ModerationAction $action,
        string $moderatorId,
        ?string $moderatorUsername,
        string $telegramChatId,
        ?string $telegramMessageId,
        ?string $callbackQueryId,
    ): ModerationLog;

    public function existsForMention(int $mentionId): bool;
}
