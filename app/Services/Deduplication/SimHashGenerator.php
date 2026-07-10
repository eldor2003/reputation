<?php

namespace App\Services\Deduplication;

class SimHashGenerator
{
    public function generate(string $text, ?int $bits = null): string
    {
        $bits ??= (int) config('deduplication.simhash.bits', 64);
        $tokens = $this->tokenize($text);

        if ($tokens === []) {
            return str_repeat('0', (int) ceil($bits / 4));
        }

        $vector = array_fill(0, $bits, 0);

        foreach ($tokens as $token => $weight) {
            $hash = hash('sha256', $token, true);
            $hashBits = $this->bytesToBits($hash, $bits);

            foreach ($hashBits as $index => $bit) {
                $vector[$index] += $bit === 1 ? $weight : -$weight;
            }
        }

        $binary = '';

        foreach ($vector as $value) {
            $binary .= $value >= 0 ? '1' : '0';
        }

        return $this->binaryToHex($binary);
    }

    public function hammingDistance(string $left, string $right): int
    {
        $leftBinary = $this->hexToBinary($left);
        $rightBinary = $this->hexToBinary($right);
        $length = min(strlen($leftBinary), strlen($rightBinary));
        $distance = 0;

        for ($index = 0; $index < $length; $index++) {
            if ($leftBinary[$index] !== $rightBinary[$index]) {
                $distance++;
            }
        }

        return $distance + abs(strlen($leftBinary) - strlen($rightBinary));
    }

    /**
     * @return array<string, int>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($words as $word) {
            $tokens[$word] = ($tokens[$word] ?? 0) + 1;
        }

        for ($index = 0; $index < count($words) - 1; $index++) {
            $shingle = $words[$index].' '.$words[$index + 1];
            $tokens[$shingle] = ($tokens[$shingle] ?? 0) + 1;
        }

        return $tokens;
    }

    /**
     * @return list<int>
     */
    private function bytesToBits(string $bytes, int $bits): array
    {
        $binary = '';

        foreach (str_split($bytes) as $byte) {
            $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return array_map(
            fn (string $character): int => (int) $character,
            str_split(substr($binary, 0, $bits)),
        );
    }

    private function binaryToHex(string $binary): string
    {
        $binary = str_pad($binary, (int) (ceil(strlen($binary) / 4) * 4), '0', STR_PAD_RIGHT);
        $hex = '';

        for ($index = 0; $index < strlen($binary); $index += 4) {
            $hex .= dechex(bindec(substr($binary, $index, 4)));
        }

        return $hex;
    }

    private function hexToBinary(string $hex): string
    {
        $binary = '';

        for ($index = 0; $index < strlen($hex); $index++) {
            $binary .= str_pad(decbin(hexdec($hex[$index])), 4, '0', STR_PAD_LEFT);
        }

        return $binary;
    }
}
