<?php

namespace App\Services\Deduplication;

use App\Contracts\FuzzyMatchingStrategyInterface;
use App\Enums\FuzzyMatchingAlgorithm;

class MinHashMatchingStrategy implements FuzzyMatchingStrategyInterface
{
    public function __construct(
        private readonly MinHashGenerator $minHashGenerator,
    ) {}

    public function algorithm(): FuzzyMatchingAlgorithm
    {
        return FuzzyMatchingAlgorithm::MinHash;
    }

    public function generateSignature(string $text): string
    {
        return json_encode($this->minHashGenerator->generateSignature($text), JSON_THROW_ON_ERROR);
    }

    public function signatureDistance(string $left, string $right): int
    {
        $leftSignature = json_decode($left, true);
        $rightSignature = json_decode($right, true);

        if (! is_array($leftSignature) || ! is_array($rightSignature)) {
            return PHP_INT_MAX;
        }

        $similarity = $this->minHashGenerator->estimateJaccardSimilarity($leftSignature, $rightSignature);
        $threshold = (float) config('deduplication.similarity.minimum', 0.70);

        return $similarity >= $threshold ? 0 : (int) ceil((1 - $similarity) * 100);
    }

    public function signaturesAreSimilar(string $left, string $right): bool
    {
        return $this->signatureDistance($left, $right) === 0;
    }
}
