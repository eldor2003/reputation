<?php

namespace App\DTO;

readonly class Brand24MentionDTO
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $date,
        public string $time,
        public ?string $title,
        public ?string $content,
        public ?string $source,
        public string $host,
        public string $category,
        public int $sentiment,
        public array $tags,
    ) {}
}
