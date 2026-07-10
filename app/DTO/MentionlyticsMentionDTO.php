<?php

namespace App\DTO;

readonly class MentionlyticsMentionDTO
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $uuid,
        public string $text,
        public ?string $url,
        public ?string $title,
        public ?string $authorName,
        public ?string $authorId,
        public ?string $publishedAt,
        public ?string $language,
        public ?string $sentiment,
        public ?string $channel,
        public ?int $channelId,
        public ?int $engagement,
        public array $raw,
    ) {}
}
