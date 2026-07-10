<?php

namespace App\Contracts;

use App\DTO\NormalizedMentionDTO;
use App\DTO\PromptGuardResultDTO;

interface PromptInjectionGuardInterface
{
    public function scan(NormalizedMentionDTO $mention): PromptGuardResultDTO;
}
