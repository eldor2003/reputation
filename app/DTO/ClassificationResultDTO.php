<?php

namespace App\DTO;

readonly class ClassificationResultDTO
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $summary,
        public string $sentiment,
        public int $severity,
        public string $language,
        public string $category,
        public string $person,
        public int $confidence,
        public string $reasoning,
        public array $rawResponse,
    ) {}
}
