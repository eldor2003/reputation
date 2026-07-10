<?php

namespace App\Contracts;

use App\DTO\MentionlyticsConnectionInfoDTO;
use App\DTO\MentionlyticsMentionsPageDTO;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\DTO\MentionlyticsVerificationResultDTO;

interface MentionlyticsClientInterface
{
    public function testConnection(): MentionlyticsConnectionInfoDTO;

    public function verify(): MentionlyticsVerificationResultDTO;

    public function getMentions(MentionlyticsMentionsQueryDTO $query): MentionlyticsMentionsPageDTO;
}
