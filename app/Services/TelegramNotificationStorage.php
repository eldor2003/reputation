<?php

namespace App\Services;

use App\Contracts\TelegramNotificationStorageInterface;
use App\Enums\TelegramNotificationStatus;
use App\Models\TelegramNotification;

class TelegramNotificationStorage implements TelegramNotificationStorageInterface
{
    public function createPending(int $mentionId, string $chatId): TelegramNotification
    {
        return TelegramNotification::query()->create([
            'mention_id' => $mentionId,
            'status' => TelegramNotificationStatus::Pending,
            'chat_id' => $chatId,
        ]);
    }

    public function markSent(TelegramNotification $notification, string $messageId): TelegramNotification
    {
        $notification->update([
            'status' => TelegramNotificationStatus::Sent,
            'message_id' => $messageId,
            'sent_at' => now(),
        ]);

        return $notification->refresh();
    }

    public function markFailed(TelegramNotification $notification): TelegramNotification
    {
        $notification->update([
            'status' => TelegramNotificationStatus::Failed,
        ]);

        return $notification->refresh();
    }
}
