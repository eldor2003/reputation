<?php

namespace App\Actions;

use App\Contracts\ModerationLogStorageInterface;
use App\Contracts\TelegramNotifierInterface;
use App\Enums\ModerationAction;
use App\Events\MentionApproved;
use App\Events\MentionRejected;
use App\Events\MentionSkipped;
use App\Exceptions\InvalidTelegramCallbackException;
use App\Models\Mention;
use App\Services\TelegramCallbackDataParser;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessTelegramCallbackAction
{
    public function __construct(
        private readonly TelegramCallbackDataParser $callbackDataParser,
        private readonly ModerationLogStorageInterface $moderationLogStorage,
        private readonly TelegramNotifierInterface $telegramNotifier,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function execute(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;

        if (! is_array($callbackQuery)) {
            return;
        }

        $callbackData = $callbackQuery['data'] ?? null;
        $callbackQueryId = $callbackQuery['id'] ?? null;
        $from = $callbackQuery['from'] ?? null;
        $message = $callbackQuery['message'] ?? null;

        if (! is_string($callbackData) || ! is_string($callbackQueryId) || ! is_array($from)) {
            throw new InvalidTelegramCallbackException('Telegram callback query payload is invalid.');
        }

        $parsed = $this->callbackDataParser->parse($callbackData);

        if ($this->moderationLogStorage->existsForMention($parsed->mentionId)) {
            $this->telegramNotifier->answerCallbackQuery($callbackQueryId, 'Это упоминание уже было обработано.');

            return;
        }

        $mention = Mention::query()->find($parsed->mentionId);

        if ($mention === null) {
            throw new RuntimeException("Mention [{$parsed->mentionId}] not found for moderation.");
        }

        $moderatorId = (string) ($from['id'] ?? '');
        $moderatorUsername = isset($from['username']) && is_string($from['username']) ? $from['username'] : null;
        $chatId = is_array($message) ? (string) ($message['chat']['id'] ?? '') : '';
        $messageId = is_array($message) && isset($message['message_id']) ? (string) $message['message_id'] : null;

        $this->moderationLogStorage->store(
            mentionId: $mention->id,
            action: $parsed->action,
            moderatorId: $moderatorId,
            moderatorUsername: $moderatorUsername,
            telegramChatId: $chatId,
            telegramMessageId: $messageId,
            callbackQueryId: $callbackQueryId,
        );

        $this->dispatchModerationEvent($parsed->action, $mention);
        $this->telegramNotifier->answerCallbackQuery(
            $callbackQueryId,
            $this->confirmationText($parsed->action),
        );

        Log::info('Telegram moderation action recorded.', [
            'mention_id' => $mention->id,
            'action' => $parsed->action->value,
            'moderator_id' => $moderatorId,
        ]);
    }

    private function dispatchModerationEvent(ModerationAction $action, Mention $mention): void
    {
        match ($action) {
            ModerationAction::Approve => MentionApproved::dispatch(
                $mention->id,
                $mention->project_id,
                $mention->source_id,
                now(),
            ),
            ModerationAction::Reject => MentionRejected::dispatch(
                $mention->id,
                $mention->project_id,
                $mention->source_id,
                now(),
            ),
            ModerationAction::Skip => MentionSkipped::dispatch(
                $mention->id,
                $mention->project_id,
                $mention->source_id,
                now(),
            ),
        };
    }

    private function confirmationText(ModerationAction $action): string
    {
        return match ($action) {
            ModerationAction::Approve => 'Упоминание одобрено.',
            ModerationAction::Reject => 'Упоминание отклонено.',
            ModerationAction::Skip => 'Упоминание пропущено.',
        };
    }
}
