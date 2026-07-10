<?php

namespace App\DTO;

readonly class SerpPositionDTO
{
    public function __construct(
        public int $position,
        public string $title,
        public string $url,
        public ?string $snippet,
    ) {}
}
