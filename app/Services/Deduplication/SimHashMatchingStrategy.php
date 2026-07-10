<?php

namespace App\Services\Deduplication;

use App\Contracts\FuzzyMatchingStrategyInterface;
use App\Enums\FuzzyMatchingAlgorithm;

class SimHashMatchingStrategy implements FuzzyMatchingStrategyInterface
{
    public function __construct(
        private readonly SimHashGenerator $simHashGenerator,
    ) {}

    public function algorithm(): FuzzyMatchingAlgorithm
    {
        return FuzzyMatchingAlgorithm::SimHash;
    }

    public function generateSignature(string $text): string
    {
        return $this->simHashGenerator->generate($text);
    }

    public function signatureDistance(string $left, string $right): int
    {
        return $this->simHashGenerator->hammingDistance($left, $right);
    }

    public function signaturesAreSimilar(string $left, string $right): bool
    {
        $maxDistance = (int) config('deduplication.simhash.max_hamming_distance', 8);

        return $this->signatureDistance($left, $right) <= $maxDistance;
    }
}
