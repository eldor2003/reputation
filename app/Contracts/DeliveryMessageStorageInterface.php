<?php

namespace App\Contracts;

use App\DTO\DeliveryCardDTO;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryMessageStatus;
use App\Enums\DigestType;
use App\Models\DeliveryDigest;
use App\Models\DeliveryDigestItem;
use App\Models\DeliveryMessage;

interface DeliveryMessageStorageInterface
{
    public function createPending(
        int $projectId,
        DeliveryChannel $channel,
        DeliveryCardDTO $card,
        string $messageText,
        ?int $mentionId = null,
        ?int $moderationLogId = null,
        ?int $deliveryDigestId = null,
    ): DeliveryMessage;

    public function markSent(DeliveryMessage $message, string $chatId, string $telegramMessageId): DeliveryMessage;

    public function markFailed(DeliveryMessage $message, string $errorMessage): DeliveryMessage;

    public function markQueued(DeliveryMessage $message): DeliveryMessage;
}
