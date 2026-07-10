<?php

namespace App\Contracts;

use App\DTO\NormalizedMentionDTO;
use App\DTO\PromptGuardResultDTO;

interface PromptBuilderInterface
{
    public function build(NormalizedMentionDTO $mention, ?PromptGuardResultDTO $guardResult = null): string;
}
