<?php

namespace App\Services;

use App\Contracts\TelegramDestinationNotifierInterface;
use App\DTO\TelegramSendResultDTO;
use App\Enums\TelegramDestination;
use App\Exceptions\TelegramApiException;
use App\Support\LogSanitizer;
use App\Support\TelegramDestinationConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramDestinationNotifier implements TelegramDestinationNotifierInterface
{
    public function send(
        TelegramDestination $destination,
        string $chatId,
        string $message,
    ): TelegramSendResultDTO {
        $botToken = TelegramDestinationConfig::botToken($destination);

        if (! is_string($botToken) || $botToken === '') {
            throw new TelegramApiException("Telegram bot token is not configured for {$destination->value}.");
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        try {
            $response = Http::baseUrl(rtrim((string) config('telegram.base_url'), '/'))
                ->timeout((int) config('telegram.timeout'))
                ->retry(
                    (int) config('telegram.retry.times'),
                    (int) config('telegram.retry.sleep_ms'),
                    fn (?\Exception $exception) => $this->shouldRetry($exception),
                )
                ->post("/bot{$botToken}/sendMessage", $payload)
                ->throw();

            /** @var array<string, mixed> $body */
            $body = $response->json();

            if (($body['ok'] ?? false) !== true) {
                throw new TelegramApiException('Telegram API returned an unsuccessful response.');
            }

            $result = $body['result'] ?? null;

            if (! is_array($result) || ! isset($result['message_id'])) {
                throw new TelegramApiException('Telegram API response is missing message_id.');
            }

            return new TelegramSendResultDTO(
                messageId: (string) $result['message_id'],
                chatId: (string) ($result['chat']['id'] ?? $chatId),
            );
        } catch (RequestException $exception) {
            Log::error('Telegram destination API request failed.', [
                'destination' => $destination->value,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
            ]);

            throw new TelegramApiException('Telegram API request failed.', $exception);
        }
    }

    public function chatIds(TelegramDestination $destination): array
    {
        return TelegramDestinationConfig::chatIds($destination);
    }

    private function shouldRetry(?\Exception $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return true;
        }

        $status = $exception->response?->status();

        if ($status === null) {
            return true;
        }

        return $status === 429 || $status >= 500;
    }
}
