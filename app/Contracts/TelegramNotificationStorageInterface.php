<?php

namespace App\Contracts;

use App\Models\TelegramNotification;

interface TelegramNotificationStorageInterface
{
    public function createPending(int $mentionId, string $chatId): TelegramNotification;

    public function markSent(TelegramNotification $notification, string $messageId): TelegramNotification;

    public function markFailed(TelegramNotification $notification): TelegramNotification;
}
