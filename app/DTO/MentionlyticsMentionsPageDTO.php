<?php

namespace App\DTO;

readonly class MentionlyticsMentionsPageDTO
{
    /**
     * @param  list<MentionlyticsMentionDTO>  $mentions
     */
    public function __construct(
        public array $mentions,
        public bool $hasMore,
        public ?string $resultsAfter,
    ) {}
}
