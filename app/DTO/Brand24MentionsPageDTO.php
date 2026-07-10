<?php

namespace App\DTO;

readonly class Brand24MentionsPageDTO
{
    /**
     * @param  list<Brand24MentionDTO>  $results
     */
    public function __construct(
        public array $results,
        public bool $hasMoreMentions,
        public ?string $cursor,
    ) {}
}
