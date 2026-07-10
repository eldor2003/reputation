<?php

namespace App\Contracts;

use App\DTO\LlmCascadeResultDTO;
use App\DTO\NormalizedMentionDTO;

interface LLMCascadeInterface
{
    public function classify(string $prompt, NormalizedMentionDTO $mention, int $mentionId): LlmCascadeResultDTO;
}
