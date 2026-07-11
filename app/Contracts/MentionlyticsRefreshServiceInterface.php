<?php

namespace App\Contracts;

use App\DTO\MentionlyticsTokenPairDTO;

interface MentionlyticsRefreshServiceInterface
{
    public function refresh(string $refreshToken): MentionlyticsTokenPairDTO;
}
