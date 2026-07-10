<?php

namespace App\Contracts;

use App\DTO\DeduplicationResultDTO;
use App\DTO\NormalizedMentionDTO;

interface DeduplicationEngineInterface
{
    public function check(NormalizedMentionDTO $mention): DeduplicationResultDTO;
}
