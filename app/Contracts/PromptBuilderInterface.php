<?php

namespace App\Contracts;

use App\DTO\NormalizedMentionDTO;
use App\DTO\PersonMatchResultDTO;
use App\DTO\PromptGuardResultDTO;

interface PromptBuilderInterface
{
    public function build(
        NormalizedMentionDTO $mention,
        ?PromptGuardResultDTO $guardResult = null,
        ?PersonMatchResultDTO $personMatch = null,
    ): string;
}
