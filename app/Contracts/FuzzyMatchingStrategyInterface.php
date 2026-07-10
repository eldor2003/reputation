<?php

namespace App\Contracts;

interface FuzzyMatchingStrategyInterface
{
    public function algorithm(): \App\Enums\FuzzyMatchingAlgorithm;

    public function generateSignature(string $text): string;

    public function signatureDistance(string $left, string $right): int;

    public function signaturesAreSimilar(string $left, string $right): bool;
}
