<?php

namespace App\DTO;

readonly class SerpApiAccountInfoDTO
{
    public function __construct(
        public string $accountId,
        public string $planName,
        public int $searchesPerMonth,
        public int $planSearchesLeft,
        public int $totalSearchesLeft,
        public int $thisMonthUsage,
        public int $accountRateLimitPerHour,
    ) {}
}
