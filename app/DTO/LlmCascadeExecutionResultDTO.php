<?php

namespace App\DTO;

readonly class LlmCascadeExecutionResultDTO
{
    public function __construct(
        public LlmCascadeResultDTO $cascadeResult,
        public PromptGuardResultDTO $guardResult,
    ) {}
}
