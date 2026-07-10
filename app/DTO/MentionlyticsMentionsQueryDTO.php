<?php

namespace App\DTO;

readonly class MentionlyticsMentionsQueryDTO
{
    /**
     * @param  list<int>|null  $channels
     */
    public function __construct(
        public string $startDate,
        public string $endDate,
        public ?int $pageNo = null,
        public ?int $perPage = null,
        public ?string $resultsAfter = null,
        public ?string $sentiment = null,
        public ?array $channels = null,
        public ?string $commtracks = null,
        public ?string $country = null,
        public ?string $language = null,
    ) {}
}
