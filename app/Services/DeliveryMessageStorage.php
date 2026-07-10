<?php

namespace App\Services;

use App\Contracts\DeliveryMessageStorageInterface;
use App\DTO\DeliveryCardDTO;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryMessageStatus;
use App\Models\DeliveryMessage;

class DeliveryMessageStorage implements DeliveryMessageStorageInterface
{
    public function createPending(
        int $projectId,
        DeliveryChannel $channel,
        DeliveryCardDTO $card,
        string $messageText,
        ?int $mentionId = null,
        ?int $moderationLogId = null,
        ?int $deliveryDigestId = null,
    ): DeliveryMessage {
        return DeliveryMessage::query()->create([
            'mention_id' => $mentionId,
            'delivery_digest_id' => $deliveryDigestId,
            'project_id' => $projectId,
            'moderation_log_id' => $moderationLogId,
            'channel' => $channel,
            'status' => DeliveryMessageStatus::Pending,
            'card_payload' => $card->toArray(),
            'message_text' => $messageText,
        ]);
    }

    public function markSent(DeliveryMessage $message, string $chatId, string $telegramMessageId): DeliveryMessage
    {
        $message->update([
            'status' => DeliveryMessageStatus::Sent,
            'chat_id' => $chatId,
            'telegram_message_id' => $telegramMessageId,
            'sent_at' => now(),
            'error_message' => null,
        ]);

        return $message->refresh();
    }

    public function markFailed(DeliveryMessage $message, string $errorMessage): DeliveryMessage
    {
        $message->update([
            'status' => DeliveryMessageStatus::Failed,
            'error_message' => $errorMessage,
        ]);

        return $message->refresh();
    }

    public function markQueued(DeliveryMessage $message): DeliveryMessage
    {
        $message->update([
            'status' => DeliveryMessageStatus::Queued,
        ]);

        return $message->refresh();
    }
}
