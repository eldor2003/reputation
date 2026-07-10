<?php

namespace App\DTO;

use Carbon\Carbon;

readonly class NormalizedMentionDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $projectId,
        public int $sourceId,
        public string $externalId,
        public ?string $author,
        public ?string $authorId,
        public ?string $language,
        public string $text,
        public ?string $title,
        public ?string $url,
        public ?Carbon $publishedAt,
        public Carbon $receivedAt,
        public ?array $metadata = null,
    ) {}
}
