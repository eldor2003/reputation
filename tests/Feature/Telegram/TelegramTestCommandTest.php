<?php

namespace Tests\Feature\Telegram;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramTestCommandTest extends TestCase
{
    #[Test]
    public function telegram_test_command_sends_connectivity_message(): void
    {
        config([
            'app.env' => 'local',
            'brand24.api_key' => 'test-brand24-api-key',
            'brand24.base_url' => 'https://api-data.brand24.com',
            'brand24.timeout' => 5,
            'brand24.retry.times' => 0,
            'brand24.retry.sleep_ms' => 0,
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100123456'],
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
            'telegram.retry.sleep_ms' => 0,
        ]);

        Http::fake([
            'api-data.brand24.com/api-data/v1/account/mentions-usage-estimation' => Http::response([
                'status' => 'success',
                'message' => [
                    'mentions_usage_estimation_at_the_end' => 14820,
                ],
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 42,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);

        $this->artisan('telegram:test')
            ->expectsOutputToContain('Telegram: подключение OK для чата -100123456')
            ->expectsOutputToContain('Тест Telegram завершён: 1/1 чатов получили сообщение.')
            ->assertSuccessful();

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $text = $request->data()['text'] ?? '';

            return str_contains($text, '✅ Система мониторинга репутации')
                && str_contains($text, 'Brand24: Подключён')
                && str_contains($text, 'Telegram: Подключён')
                && str_contains($text, 'Окружение: Local');
        });
    }

    #[Test]
    public function telegram_test_command_reports_brand24_failure_in_message(): void
    {
        config([
            'app.env' => 'local',
            'brand24.api_key' => '',
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100123456'],
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
            'telegram.retry.sleep_ms' => 0,
        ]);

        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 43,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);

        $this->artisan('telegram:test')->assertSuccessful();

        Http::assertSent(function ($request): bool {
            $text = $request->data()['text'] ?? '';

            return str_contains($text, 'Brand24: Ошибка')
                && str_contains($text, 'Telegram: Подключён');
        });
    }

    #[Test]
    public function telegram_test_command_fails_when_chat_id_is_not_configured(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => [],
            'telegram.chat_id' => '',
        ]);

        $this->artisan('telegram:test')
            ->expectsOutputToContain('Идентификаторы чатов Telegram не настроены.')
            ->assertFailed();
    }
}
