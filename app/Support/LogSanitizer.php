<?php

namespace App\Support;

final class LogSanitizer
{
    public static function redactSecrets(string $text): string
    {
        $text = preg_replace('#/bot[^/]+/#', '/bot[redacted]/', $text) ?? $text;
        $text = preg_replace('/api[_-]?key[=:\s]+[^\s&"\']+/i', 'api_key=[redacted]', $text) ?? $text;
        $text = preg_replace('/X-Api-Key:\s*\S+/i', 'X-Api-Key: [redacted]', $text) ?? $text;

        return $text;
    }
}
