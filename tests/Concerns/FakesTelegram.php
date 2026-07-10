<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

trait FakesTelegram
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    protected function fakeTelegramApi(?array $response = null): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100123456'],
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response($response ?? [
                'ok' => true,
                'result' => [
                    'message_id' => 42,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);
    }
}
