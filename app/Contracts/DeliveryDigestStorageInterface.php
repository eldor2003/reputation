<?php

namespace App\Contracts;

use App\DTO\DeliveryCardDTO;
use App\Enums\DeliveryDigestItemStatus;
use App\Enums\DeliveryDigestStatus;
use App\Enums\DigestType;
use App\Models\DeliveryDigest;
use App\Models\DeliveryDigestItem;
use Illuminate\Support\Collection;

interface DeliveryDigestStorageInterface
{
    public function queueItem(
        int $mentionId,
        int $projectId,
        DigestType $digestType,
        DeliveryCardDTO $card,
    ): DeliveryDigestItem;

    /**
     * @return Collection<int, DeliveryDigestItem>
     */
    public function queuedItems(int $projectId, DigestType $digestType): Collection;

    public function createDigest(int $projectId, DigestType $digestType, int $itemCount): DeliveryDigest;

    public function attachItemsToDigest(DeliveryDigest $digest, Collection $items): void;

    public function markDigestGenerated(DeliveryDigest $digest, string $messageText): DeliveryDigest;

    public function markDigestSent(DeliveryDigest $digest, string $chatId, string $telegramMessageId): DeliveryDigest;

    public function markDigestFailed(DeliveryDigest $digest, string $errorMessage): DeliveryDigest;

    public function markItemStatus(DeliveryDigestItem $item, DeliveryDigestItemStatus $status, ?int $deliveryMessageId = null): DeliveryDigestItem;

    public function hasQueuedItem(int $mentionId, DigestType $digestType): bool;
}
