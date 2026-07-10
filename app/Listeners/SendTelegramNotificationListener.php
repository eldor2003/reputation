<?php

namespace App\Listeners;

use App\Contracts\TelegramNotificationStorageInterface;
use App\Contracts\TelegramNotifierInterface;
use App\Events\MentionRouted;
use App\DTO\TelegramReplyMarkupDTO;
use App\Exceptions\TelegramApiException;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\TelegramNotification;
use App\Services\TelegramModerationKeyboardBuilder;
use App\Services\TelegramNotificationMessageBuilder;
use App\Support\TelegramChatIdResolver;
use Illuminate\Support\Facades\Log;

class SendTelegramNotificationListener
{
    public function __construct(
        private readonly TelegramNotifierInterface $telegramNotifier,
        private readonly TelegramNotificationMessageBuilder $messageBuilder,
        private readonly TelegramModerationKeyboardBuilder $keyboardBuilder,
        private readonly TelegramNotificationStorageInterface $telegramNotificationStorage,
    ) {}

    public function handle(MentionRouted $event): void
    {
        $route = MentionRoute::query()
            ->where('mention_id', $event->mentionId)
            ->first();

        if ($route === null || ! $route->should_notify) {
            return;
        }

        $mention = Mention::query()->find($event->mentionId);
        $classification = AiResult::query()
            ->where('mention_id', $event->mentionId)
            ->latest('processed_at')
            ->first();

        if ($mention === null || $classification === null) {
            Log::error('Telegram notification skipped: mention or classification not found.', [
                'mention_id' => $event->mentionId,
            ]);

            return;
        }

        /** @var list<string> $chatIds */
        $chatIds = TelegramChatIdResolver::fromConfig();

        if ($chatIds === []) {
            Log::error('Telegram chat IDs are not configured.', [
                'mention_id' => $event->mentionId,
            ]);

            return;
        }

        $message = $this->messageBuilder->build($mention, $classification);
        $keyboard = $route->skip_moderation
            ? null
            : $this->keyboardBuilder->build($event->mentionId);

        foreach ($chatIds as $chatId) {
            if (TelegramNotification::query()
                ->where('mention_id', $event->mentionId)
                ->where('chat_id', $chatId)
                ->exists()) {
                continue;
            }

            $notification = $this->telegramNotificationStorage->createPending($event->mentionId, $chatId);

            $this->sendWithRetry($notification, $chatId, $message, $keyboard, $event->mentionId);
        }
    }

    private function sendWithRetry(
        TelegramNotification $notification,
        string $chatId,
        string $message,
        ?TelegramReplyMarkupDTO $keyboard,
        int $mentionId,
    ): void {
        try {
            $result = $this->telegramNotifier->send($chatId, $message, $keyboard);
            $this->telegramNotificationStorage->markSent($notification, $result->messageId);

            Log::info('Telegram notification sent.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'message_id' => $result->messageId,
            ]);

            return;
        } catch (TelegramApiException $exception) {
            Log::warning('Telegram notification failed, retrying once.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'exception' => $exception->getMessage(),
            ]);
        }

        try {
            $result = $this->telegramNotifier->send($chatId, $message, $keyboard);
            $this->telegramNotificationStorage->markSent($notification, $result->messageId);

            Log::info('Telegram notification sent.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'message_id' => $result->messageId,
            ]);
        } catch (TelegramApiException $exception) {
            $this->telegramNotificationStorage->markFailed($notification);

            Log::error('Telegram notification failed after retry.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
