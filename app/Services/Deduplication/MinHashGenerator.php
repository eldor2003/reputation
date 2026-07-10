<?php

namespace App\Services\Deduplication;

/**
 * Foundation implementation for future MinHash-based fuzzy matching.
 */
class MinHashGenerator
{
    /**
     * @return list<int>
     */
    public function generateSignature(string $text): array
    {
        $permutations = (int) config('deduplication.minhash.permutations', 128);
        $tokens = $this->tokenize($text);
        $signature = [];

        for ($seed = 0; $seed < $permutations; $seed++) {
            $minimum = PHP_INT_MAX;

            foreach ($tokens as $token) {
                $hash = crc32($seed.'|'.$token);

                if ($hash < $minimum) {
                    $minimum = $hash;
                }
            }

            $signature[] = $minimum === PHP_INT_MAX ? 0 : $minimum;
        }

        return $signature;
    }

    public function estimateJaccardSimilarity(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $matches = 0;
        $length = min(count($left), count($right));

        for ($index = 0; $index < $length; $index++) {
            if ($left[$index] === $right[$index]) {
                $matches++;
            }
        }

        return $length === 0 ? 0.0 : $matches / $length;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;

        return preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
