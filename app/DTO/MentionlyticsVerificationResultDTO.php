<?php

namespace App\DTO;

readonly class MentionlyticsVerificationResultDTO
{
    public function __construct(
        public string $queryStartDate,
        public string $queryEndDate,
        public int $mentionsOnPage,
        public ?int $totalMentionsInPeriod,
        public bool $hasMorePages,
        public ?string $paginationCursor,
        public bool $paginationVerified,
        public ?string $lastMentionTimestamp,
        public ?string $lastMentionId,
        public bool $tokenRefreshUsed,
        public string $authenticationMethod,
    ) {}
}
