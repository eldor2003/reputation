<?php

namespace App\Console\Commands;

use App\Contracts\Brand24ClientInterface;
use App\Contracts\TelegramNotifierInterface;
use App\Exceptions\Brand24ApiException;
use App\Exceptions\TelegramApiException;
use App\Support\TelegramChatIdResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramTestCommand extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Отправить тестовое сообщение через настроенного Telegram-бота';

    public function handle(
        Brand24ClientInterface $brand24Client,
        TelegramNotifierInterface $telegramNotifier,
    ): int {
        $brand24Status = $this->resolveBrand24Status($brand24Client);

        /** @var list<string> $chatIds */
        $chatIds = TelegramChatIdResolver::fromConfig();

        if ($chatIds === []) {
            $this->components->error('Идентификаторы чатов Telegram не настроены.');

            return self::FAILURE;
        }

        $message = $this->buildMessage($brand24Status);
        $successCount = 0;

        foreach ($chatIds as $chatId) {
            try {
                $result = $telegramNotifier->send($chatId, $message);
                $successCount++;

                Log::info('Telegram test message sent.', [
                    'chat_id' => $chatId,
                    'message_id' => $result->messageId,
                ]);

                $this->components->info("Telegram: подключение OK для чата {$chatId}");
                $this->components->twoColumnDetail('ID сообщения', $result->messageId);
            } catch (TelegramApiException $exception) {
                Log::error('Telegram test message failed.', [
                    'chat_id' => $chatId,
                    'exception' => $exception->getMessage(),
                ]);

                $this->components->error("Telegram: ошибка подключения для чата {$chatId}: {$exception->getMessage()}");
            }
        }

        if ($successCount === 0) {
            return self::FAILURE;
        }

        $this->components->info("Тест Telegram завершён: {$successCount}/".count($chatIds).' чатов получили сообщение.');

        return self::SUCCESS;
    }

    private function resolveBrand24Status(Brand24ClientInterface $brand24Client): string
    {
        try {
            $brand24Client->testConnection();

            return 'Подключён';
        } catch (Brand24ApiException $exception) {
            Log::warning('Brand24 connectivity check failed during telegram:test.', [
                'exception' => $exception->getMessage(),
            ]);

            return 'Ошибка';
        }
    }

    private function buildMessage(string $brand24Status): string
    {
        $environment = ucfirst((string) config('app.env', 'local'));

        return implode("\n", [
            '✅ Система мониторинга репутации',
            '',
            "Brand24: {$brand24Status}",
            'Telegram: Подключён',
            "Окружение: {$environment}",
            now()->toDateTimeString(),
        ]);
    }
}
