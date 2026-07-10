<?php

namespace App\DTO;

readonly class Brand24MentionsQueryDTO
{
    public function __construct(
        public int $projectId,
        public string $dateFrom,
        public string $dateTo,
        public ?int $limit = null,
        public ?string $cursor = null,
        public ?string $sentiment = null,
        public ?string $category = null,
    ) {}
}
