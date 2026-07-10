<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryCardBuilderInterface;
use App\Contracts\DeliveryDigestStorageInterface;
use App\Contracts\DeliveryMessageStorageInterface;
use App\Contracts\DigestEngineInterface;
use App\Contracts\TelegramDestinationNotifierInterface;
use App\DTO\DeliveryCardDTO;
use App\DTO\DeliveryResultDTO;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryDigestItemStatus;
use App\Enums\DeliveryMessageStatus;
use App\Enums\DigestType;
use App\Enums\TelegramDestination;
use App\Events\DigestDelivered;
use App\Exceptions\DeliveryConfigurationException;
use App\Exceptions\TelegramApiException;
use App\Models\DeliveryDigestItem;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

class DigestEngine implements DigestEngineInterface
{
    public function __construct(
        private readonly DeliveryDigestStorageInterface $digestStorage,
        private readonly DeliveryMessageStorageInterface $messageStorage,
        private readonly DeliveryCardBuilderInterface $cardBuilder,
        private readonly TelegramDestinationNotifierInterface $telegramNotifier,
    ) {}

    public function generate(DigestType $digestType, ?int $projectId = null): DeliveryResultDTO
    {
        $projectIds = $projectId === null
            ? Project::query()->where('is_active', true)->pluck('id')->all()
            : [$projectId];

        $lastResult = new DeliveryResultDTO(success: true);

        foreach ($projectIds as $id) {
            $result = $this->generateForProject((int) $id, $digestType);

            if (! $result->success) {
                $lastResult = $result;
            } elseif ($result->digest !== null) {
                $lastResult = $result;
            }
        }

        return $lastResult;
    }

    private function generateForProject(int $projectId, DigestType $digestType): DeliveryResultDTO
    {
        $items = $this->digestStorage->queuedItems($projectId, $digestType);

        if ($items->isEmpty()) {
            return new DeliveryResultDTO(success: true);
        }

        $cards = $items
            ->map(function (DeliveryDigestItem $item): DeliveryCardDTO {
                $payload = is_array($item->card_payload) ? $item->card_payload : [];

                return DeliveryCardDTO::fromPayload($payload);
            })
            ->all();

        $digest = $this->digestStorage->createDigest($projectId, $digestType, $items->count());
        $this->digestStorage->attachItemsToDigest($digest, $items);

        $title = $this->digestTitle($digestType, $projectId);
        $messageText = $this->cardBuilder->formatDigest($title, $cards);
        $digest = $this->digestStorage->markDigestGenerated($digest, $messageText);

        $aggregateCard = new DeliveryCardDTO(
            mentionId: 0,
            projectId: $projectId,
            person: 'digest',
            threatLevel: '—',
            threatScore: 0,
            source: 'digest',
            summary: $title,
            url: null,
            sentiment: 'neutral',
            severity: 0,
            serpPosition: null,
            clusterSize: $items->count(),
            publishedAt: null,
            processedAt: now(),
        );

        $deliveryMessage = $this->messageStorage->createPending(
            projectId: $projectId,
            channel: DeliveryChannel::TelegramDelivery,
            card: $aggregateCard,
            messageText: $messageText,
            deliveryDigestId: $digest->id,
        );

        $chatIds = $this->telegramNotifier->chatIds(TelegramDestination::Delivery);

        if ($chatIds === []) {
            $failedDigest = $this->digestStorage->markDigestFailed($digest, 'Telegram delivery chat IDs are not configured.');
            $this->messageStorage->markFailed($deliveryMessage, 'Telegram delivery chat IDs are not configured.');

            throw new DeliveryConfigurationException('Telegram delivery chat IDs are not configured.');
        }

        $lastError = null;

        foreach ($chatIds as $chatId) {
            try {
                $result = $this->sendWithRetry($chatId, $messageText);
                $this->messageStorage->markSent($deliveryMessage, $chatId, $result->messageId);
                $sentDigest = $this->digestStorage->markDigestSent($digest, $chatId, $result->messageId);

                foreach ($items as $item) {
                    if ($item instanceof DeliveryDigestItem) {
                        $this->digestStorage->markItemStatus(
                            $item,
                            DeliveryDigestItemStatus::Sent,
                            $deliveryMessage->id,
                        );
                    }
                }

                DigestDelivered::dispatch($projectId, $digestType->value, $items->count(), now());

                Log::info('Digest delivered to Telegram.', [
                    'project_id' => $projectId,
                    'digest_type' => $digestType->value,
                    'item_count' => $items->count(),
                    'chat_id' => $chatId,
                ]);

                return new DeliveryResultDTO(success: true, digest: $sentDigest, message: $deliveryMessage);
            } catch (TelegramApiException $exception) {
                $lastError = $exception->getMessage();
            }
        }

        $failedDigest = $this->digestStorage->markDigestFailed($digest, (string) $lastError);
        $failedMessage = $this->messageStorage->markFailed($deliveryMessage, (string) $lastError);

        foreach ($items as $item) {
            if ($item instanceof DeliveryDigestItem) {
                $this->digestStorage->markItemStatus($item, DeliveryDigestItemStatus::Failed, $deliveryMessage->id);
            }
        }

        return new DeliveryResultDTO(
            success: false,
            digest: $failedDigest,
            message: $failedMessage,
            errorMessage: $lastError,
        );
    }

    private function digestTitle(DigestType $digestType, int $projectId): string
    {
        $project = Project::query()->find($projectId);
        $projectName = $project?->name ?? 'Project #'.$projectId;
        $label = config('delivery.digest.'.$digestType->value.'.label', $digestType->label());

        return $label.' — '.$projectName;
    }

    private function sendWithRetry(string $chatId, string $messageText): \App\DTO\TelegramSendResultDTO
    {
        try {
            return $this->telegramNotifier->send(TelegramDestination::Delivery, $chatId, $messageText);
        } catch (TelegramApiException $exception) {
            Log::warning('Digest Telegram send failed, retrying once.', [
                'chat_id' => $chatId,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $this->telegramNotifier->send(TelegramDestination::Delivery, $chatId, $messageText);
    }
}
