<?php

namespace App\DTO;

readonly class ValidationMetadataDTO
{
    public function __construct(
        public string $validationStatus,
        public int $validationRetryCount,
        public bool $injectionDetected,
        public ?string $guardReason,
    ) {}
}
