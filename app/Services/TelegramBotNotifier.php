<?php

namespace App\Services;

use App\Contracts\TelegramNotifierInterface;
use App\DTO\TelegramReplyMarkupDTO;
use App\DTO\TelegramSendResultDTO;
use App\Exceptions\TelegramApiException;
use App\Support\LogSanitizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotNotifier implements TelegramNotifierInterface
{
    public function send(
        string $chatId,
        string $message,
        ?TelegramReplyMarkupDTO $replyMarkup = null,
    ): TelegramSendResultDTO {
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup->toArray();
        }

        return $this->postTelegram('sendMessage', $payload, 'message_id');
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        $this->postTelegram('answerCallbackQuery', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postTelegram(string $method, array $payload, ?string $resultKey = null): TelegramSendResultDTO
    {
        $botToken = config('telegram.bot_token');

        if (! is_string($botToken) || $botToken === '') {
            throw new TelegramApiException('Telegram bot token is not configured.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('telegram.base_url'), '/'))
                ->timeout((int) config('telegram.timeout'))
                ->retry(
                    (int) config('telegram.retry.times'),
                    (int) config('telegram.retry.sleep_ms'),
                    fn (?\Exception $exception) => $this->shouldRetry($exception),
                )
                ->post("/bot{$botToken}/{$method}", $payload)
                ->throw();

            /** @var array<string, mixed> $body */
            $body = $response->json();

            if (($body['ok'] ?? false) !== true) {
                throw new TelegramApiException('Telegram API returned an unsuccessful response.');
            }

            if ($resultKey === null) {
                return new TelegramSendResultDTO(
                    messageId: '',
                    chatId: (string) ($payload['chat_id'] ?? ''),
                );
            }

            $result = $body['result'] ?? null;

            if (! is_array($result) || ! isset($result[$resultKey])) {
                throw new TelegramApiException("Telegram API response is missing {$resultKey}.");
            }

            return new TelegramSendResultDTO(
                messageId: (string) $result[$resultKey],
                chatId: (string) ($result['chat']['id'] ?? $payload['chat_id'] ?? ''),
            );
        } catch (RequestException $exception) {
            Log::error('Telegram API request failed.', [
                'method' => $method,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
            ]);

            throw new TelegramApiException('Telegram API request failed.', $exception);
        }
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
