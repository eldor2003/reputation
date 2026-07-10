<?php

namespace App\Services;

class PersonNameNormalizer
{
    public function normalize(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = preg_replace('/[\s\-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public function containsCyrillic(string $text): bool
    {
        return (bool) preg_match('/\p{Cyrillic}/u', $text);
    }

    public function containsLatin(string $text): bool
    {
        return (bool) preg_match('/\p{Latin}/u', $text);
    }
}
