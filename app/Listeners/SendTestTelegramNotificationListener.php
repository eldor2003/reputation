<?php

namespace App\Listeners;

use App\Contracts\TestTelegramNotifierInterface;
use App\Events\MentionProcessingCompleted;
use App\Exceptions\TelegramApiException;
use App\Models\AiResult;
use App\Models\Mention;
use App\Services\Telegram\TelegramCardMessageLayout;
use App\Services\TelegramNotificationMessageBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTestTelegramNotificationListener
{
    public function __construct(
        private readonly TestTelegramNotifierInterface $testTelegramNotifier,
        private readonly TelegramNotificationMessageBuilder $messageBuilder,
        private readonly TelegramCardMessageLayout $layout,
    ) {}

    public function handle(MentionProcessingCompleted $event): void
    {
        if (! config('test_telegram.enabled')) {
            return;
        }

        /** @var list<string> $chatIds */
        $chatIds = config('test_telegram.chat_ids', []);

        if ($chatIds === []) {
            Log::warning('Test Telegram is enabled but chat IDs are not configured.', [
                'mention_id' => $event->mentionId,
            ]);

            return;
        }

        $mention = Mention::query()
            ->with(['source', 'project', 'person'])
            ->find($event->mentionId);

        if ($mention === null) {
            Log::error('Test Telegram notification skipped: mention not found.', [
                'mention_id' => $event->mentionId,
            ]);

            return;
        }

        $classification = AiResult::query()
            ->where('mention_id', $event->mentionId)
            ->latest('processed_at')
            ->first();

        $message = $classification !== null
            ? $this->messageBuilder->build($mention, $classification)
            : $this->buildFallbackMessage($mention);

        $message = implode("\n", [
            '🧪 Acceptance Test',
            TelegramCardMessageLayout::SEPARATOR,
            '',
            $message,
        ]);

        foreach ($chatIds as $chatId) {
            $this->sendWithRetry($chatId, $message, $event->mentionId);
        }
    }

    private function buildFallbackMessage(Mention $mention): string
    {
        $lines = [
            '📋 Обработано без классификации',
            TelegramCardMessageLayout::SEPARATOR,
        ];

        if ($mention->is_duplicate) {
            $lines[] = '♻️ Дубликат';
        }

        $person = $this->layout->resolveDisplayPerson($mention);
        if (is_string($person) && trim($person) !== '') {
            $lines[] = '👤 '.$person;
        }

        if (is_string($mention->url) && trim($mention->url) !== '') {
            $lines[] = TelegramCardMessageLayout::SEPARATOR;
            $lines[] = '🔗 URL';
            $lines[] = '';
            $lines[] = trim($mention->url);
        }

        $content = trim((string) $mention->content);
        if ($content !== '') {
            $lines[] = TelegramCardMessageLayout::SEPARATOR;
            $lines[] = '📝 Content';
            $lines[] = '';
            $lines[] = Str::limit($content, 500);
        }

        $lines[] = TelegramCardMessageLayout::SEPARATOR;
        $lines[] = '#M-'.$mention->id;

        if ($mention->project?->name) {
            $lines[] = 'Проект: '.$mention->project->name;
        }

        return implode("\n", $lines);
    }

    private function sendWithRetry(string $chatId, string $message, int $mentionId): void
    {
        try {
            $result = $this->testTelegramNotifier->send($chatId, $message);

            Log::info('Test Telegram notification sent.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'message_id' => $result->messageId,
            ]);

            return;
        } catch (TelegramApiException $exception) {
            Log::warning('Test Telegram notification failed, retrying once.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'exception' => $exception->getMessage(),
            ]);
        }

        try {
            $result = $this->testTelegramNotifier->send($chatId, $message);

            Log::info('Test Telegram notification sent.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'message_id' => $result->messageId,
            ]);
        } catch (TelegramApiException $exception) {
            Log::error('Test Telegram notification failed after retry.', [
                'mention_id' => $mentionId,
                'chat_id' => $chatId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
