<?php

namespace App\Support;

final class ClaudeApiErrorExplainer
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function explain(int $status, ?array $body = null): string
    {
        $apiMessage = self::extractApiMessage($body);

        $explanation = match (true) {
            $status === 200 => 'Request succeeded.',
            $status === 401 => 'Authentication failed. The API key is invalid or has been revoked.',
            $status === 402 => 'The API key is valid, but the account has no available API credits. Add billing credits to continue.',
            $status === 403 => 'Access forbidden. The API key may lack permission for this model or endpoint.',
            $status === 429 => 'Rate limit exceeded. Too many requests were sent — wait and retry.',
            $status >= 500 => 'Anthropic server error. The API is temporarily unavailable — retry later.',
            default => "Unexpected API response (HTTP {$status}).",
        };

        if ($apiMessage !== null && $apiMessage !== '') {
            return $explanation.' API message: '.$apiMessage;
        }

        return $explanation;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private static function extractApiMessage(?array $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $error = $body['error'] ?? null;

        if (is_array($error) && is_string($error['message'] ?? null)) {
            return $error['message'];
        }

        if (is_string($body['message'] ?? null)) {
            return $body['message'];
        }

        return null;
    }
}
