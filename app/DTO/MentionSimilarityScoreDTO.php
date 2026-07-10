<?php

namespace App\DTO;

readonly class MentionSimilarityScoreDTO
{
    public function __construct(
        public float $total,
        public float $content,
        public float $title,
        public float $url,
        public float $author,
        public float $publishedAt,
    ) {}
}
