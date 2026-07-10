<?php

namespace App\Services;

use App\Contracts\DeliveryDigestStorageInterface;
use App\DTO\DeliveryCardDTO;
use App\Enums\DeliveryDigestItemStatus;
use App\Enums\DeliveryDigestStatus;
use App\Enums\DigestType;
use App\Models\DeliveryDigest;
use App\Models\DeliveryDigestItem;
use Illuminate\Support\Collection;

class DeliveryDigestStorage implements DeliveryDigestStorageInterface
{
    public function queueItem(
        int $mentionId,
        int $projectId,
        DigestType $digestType,
        DeliveryCardDTO $card,
    ): DeliveryDigestItem {
        return DeliveryDigestItem::query()->create([
            'mention_id' => $mentionId,
            'project_id' => $projectId,
            'digest_type' => $digestType,
            'status' => DeliveryDigestItemStatus::Queued,
            'card_payload' => $card->toArray(),
            'sort_order' => 0,
            'queued_at' => now(),
        ]);
    }

    public function queuedItems(int $projectId, DigestType $digestType): Collection
    {
        return DeliveryDigestItem::query()
            ->where('project_id', $projectId)
            ->where('digest_type', $digestType)
            ->where('status', DeliveryDigestItemStatus::Queued)
            ->whereNull('delivery_digest_id')
            ->orderBy('queued_at')
            ->get();
    }

    public function createDigest(int $projectId, DigestType $digestType, int $itemCount): DeliveryDigest
    {
        return DeliveryDigest::query()->create([
            'project_id' => $projectId,
            'digest_type' => $digestType,
            'status' => DeliveryDigestStatus::Pending,
            'item_count' => $itemCount,
            'scheduled_for' => now(),
        ]);
    }

    public function attachItemsToDigest(DeliveryDigest $digest, Collection $items): void
    {
        foreach ($items->values() as $index => $item) {
            if (! $item instanceof DeliveryDigestItem) {
                continue;
            }

            $item->update([
                'delivery_digest_id' => $digest->id,
                'status' => DeliveryDigestItemStatus::Included,
                'sort_order' => $index + 1,
            ]);
        }
    }

    public function markDigestGenerated(DeliveryDigest $digest, string $messageText): DeliveryDigest
    {
        $digest->update([
            'status' => DeliveryDigestStatus::Generated,
            'message_text' => $messageText,
            'generated_at' => now(),
        ]);

        return $digest->refresh();
    }

    public function markDigestSent(DeliveryDigest $digest, string $chatId, string $telegramMessageId): DeliveryDigest
    {
        $digest->update([
            'status' => DeliveryDigestStatus::Sent,
            'chat_id' => $chatId,
            'telegram_message_id' => $telegramMessageId,
            'sent_at' => now(),
            'error_message' => null,
        ]);

        return $digest->refresh();
    }

    public function markDigestFailed(DeliveryDigest $digest, string $errorMessage): DeliveryDigest
    {
        $digest->update([
            'status' => DeliveryDigestStatus::Failed,
            'error_message' => $errorMessage,
        ]);

        return $digest->refresh();
    }

    public function markItemStatus(DeliveryDigestItem $item, DeliveryDigestItemStatus $status, ?int $deliveryMessageId = null): DeliveryDigestItem
    {
        $item->update([
            'status' => $status,
            'delivery_message_id' => $deliveryMessageId,
        ]);

        return $item->refresh();
    }

    public function hasQueuedItem(int $mentionId, DigestType $digestType): bool
    {
        return DeliveryDigestItem::query()
            ->where('mention_id', $mentionId)
            ->where('digest_type', $digestType)
            ->where('status', DeliveryDigestItemStatus::Queued)
            ->exists();
    }
}
