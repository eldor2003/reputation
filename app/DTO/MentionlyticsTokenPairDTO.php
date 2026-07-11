<?php

namespace App\DTO;

use Carbon\CarbonInterface;

readonly class MentionlyticsTokenPairDTO
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonInterface $expiresAt,
        public ?CarbonInterface $refreshExpiresAt = null,
    ) {}
}
