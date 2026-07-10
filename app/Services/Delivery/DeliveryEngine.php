<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryCardBuilderInterface;
use App\Contracts\DeliveryContextBuilderInterface;
use App\Contracts\DeliveryDigestStorageInterface;
use App\Contracts\DeliveryEngineInterface;
use App\Contracts\DeliveryMessageStorageInterface;
use App\Contracts\TelegramDestinationNotifierInterface;
use App\DTO\DeliveryCardDTO;
use App\DTO\DeliveryContextDTO;
use App\DTO\DeliveryResultDTO;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryMessageStatus;
use App\Enums\DeliveryDigestItemStatus;
use App\Enums\DigestType;
use App\Enums\RoutingDeliveryMode;
use App\Enums\TelegramDestination;
use App\Events\MentionDelivered;
use App\Exceptions\DeliveryConfigurationException;
use App\Exceptions\TelegramApiException;
use App\Models\DeliveryMessage;
use Illuminate\Support\Facades\Log;

class DeliveryEngine implements DeliveryEngineInterface
{
    public function __construct(
        private readonly DeliveryContextBuilderInterface $contextBuilder,
        private readonly DeliveryCardBuilderInterface $cardBuilder,
        private readonly DeliveryMessageStorageInterface $messageStorage,
        private readonly DeliveryDigestStorageInterface $digestStorage,
        private readonly TelegramDestinationNotifierInterface $telegramNotifier,
    ) {}

    public function deliverApproved(int $mentionId): DeliveryResultDTO
    {
        $existing = DeliveryMessage::query()
            ->where('mention_id', $mentionId)
            ->where('channel', DeliveryChannel::TelegramDelivery)
            ->whereIn('status', [
                DeliveryMessageStatus::Pending,
                DeliveryMessageStatus::Queued,
                DeliveryMessageStatus::Sent,
            ])
            ->first();

        if ($existing !== null) {
            return new DeliveryResultDTO(
                success: $existing->status !== DeliveryMessageStatus::Failed,
                message: $existing,
                queuedForDigest: $existing->status === DeliveryMessageStatus::Queued,
            );
        }

        $context = $this->contextBuilder->buildForApproval($mentionId);
        $card = $this->cardBuilder->build($context);

        return match ($context->deliveryMode()) {
            RoutingDeliveryMode::Digest => $this->queueForDigest($mentionId, $this->digestTypeForRoutingDigest()->value, true),
            RoutingDeliveryMode::Deferred => $this->queueForDigest($mentionId, $this->digestTypeForRoutingDeferred()->value, true),
            RoutingDeliveryMode::Skip => new DeliveryResultDTO(success: true),
            default => $this->sendImmediate($context, $card),
        };
    }

    public function queueForDigest(int $mentionId, ?string $digestType = null, bool $fromApproval = false): DeliveryResultDTO
    {
        $context = $fromApproval
            ? $this->contextBuilder->buildForApproval($mentionId)
            : $this->contextBuilder->buildForMention($mentionId);
        $card = $this->cardBuilder->build($context);
        $type = DigestType::tryFrom((string) ($digestType ?? $this->digestTypeForRoutingDigest()->value))
            ?? $this->digestTypeForRoutingDigest();

        if ($this->digestStorage->hasQueuedItem($mentionId, $type)) {
            return new DeliveryResultDTO(success: true, queuedForDigest: true);
        }

        $item = $this->digestStorage->queueItem(
            mentionId: $mentionId,
            projectId: $context->mention->project_id,
            digestType: $type,
            card: $card,
        );

        $message = $this->messageStorage->createPending(
            projectId: $context->mention->project_id,
            channel: DeliveryChannel::TelegramDelivery,
            card: $card,
            messageText: $this->cardBuilder->formatCard($card),
            mentionId: $mentionId,
            moderationLogId: $context->moderationLog?->id,
        );

        $this->messageStorage->markQueued($message);
        $this->digestStorage->markItemStatus($item, DeliveryDigestItemStatus::Queued, $message->id);

        Log::info('Mention queued for digest delivery.', [
            'mention_id' => $mentionId,
            'digest_type' => $type->value,
            'project_id' => $context->mention->project_id,
        ]);

        return new DeliveryResultDTO(
            success: true,
            message: $message,
            queuedForDigest: true,
        );
    }

    private function sendImmediate(DeliveryContextDTO $context, DeliveryCardDTO $card): DeliveryResultDTO
    {
        $chatIds = $this->telegramNotifier->chatIds(TelegramDestination::Delivery);

        if ($chatIds === []) {
            throw new DeliveryConfigurationException('Telegram delivery chat IDs are not configured.');
        }

        $messageText = $this->cardBuilder->formatCard($card);
        $message = $this->messageStorage->createPending(
            projectId: $context->mention->project_id,
            channel: DeliveryChannel::TelegramDelivery,
            card: $card,
            messageText: $messageText,
            mentionId: $context->mention->id,
            moderationLogId: $context->moderationLog?->id,
        );

        $lastError = null;

        foreach ($chatIds as $chatId) {
            try {
                $result = $this->sendWithRetry($chatId, $messageText);
                $stored = $this->messageStorage->markSent($message, $chatId, $result->messageId);

                MentionDelivered::dispatch(
                    $context->mention->id,
                    $context->mention->project_id,
                    $context->mention->source_id,
                    now(),
                );

                Log::info('Delivery card sent to Telegram.', [
                    'mention_id' => $context->mention->id,
                    'chat_id' => $chatId,
                    'message_id' => $result->messageId,
                ]);

                return new DeliveryResultDTO(success: true, message: $stored);
            } catch (TelegramApiException $exception) {
                $lastError = $exception->getMessage();
            }
        }

        $failed = $this->messageStorage->markFailed($message, (string) $lastError);

        return new DeliveryResultDTO(
            success: false,
            message: $failed,
            errorMessage: $lastError,
        );
    }

    private function sendWithRetry(string $chatId, string $messageText): \App\DTO\TelegramSendResultDTO
    {
        try {
            return $this->telegramNotifier->send(TelegramDestination::Delivery, $chatId, $messageText);
        } catch (TelegramApiException $exception) {
            Log::warning('Delivery Telegram send failed, retrying once.', [
                'chat_id' => $chatId,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $this->telegramNotifier->send(TelegramDestination::Delivery, $chatId, $messageText);
    }

    private function digestTypeForRoutingDigest(): DigestType
    {
        $configured = config('delivery.digest.default_type_for_routing_digest', 'morning');

        return DigestType::tryFrom((string) $configured) ?? DigestType::Morning;
    }

    private function digestTypeForRoutingDeferred(): DigestType
    {
        $configured = config('delivery.digest.default_type_for_routing_deferred', 'evening');

        return DigestType::tryFrom((string) $configured) ?? DigestType::Evening;
    }
}
