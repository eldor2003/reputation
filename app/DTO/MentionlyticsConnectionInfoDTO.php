<?php

namespace App\DTO;

readonly class MentionlyticsConnectionInfoDTO
{
    public function __construct(
        public int $mentionsOnPage,
        public bool $hasMoreMentions,
    ) {}
}
