<?php

namespace App\DTO;

readonly class PromptGuardResultDTO
{
    public function __construct(
        public bool $injectionDetected,
        public ?string $reason,
    ) {}
}
