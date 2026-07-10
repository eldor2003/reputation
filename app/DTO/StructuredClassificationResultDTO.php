<?php

namespace App\DTO;

use App\Enums\ClassificationValidationStatus;

readonly class StructuredClassificationResultDTO
{
    public function __construct(
        public ClassificationResultDTO $classification,
        public ClassificationValidationStatus $validationStatus,
        public int $validationRetryCount,
        public bool $injectionDetected,
        public ?string $guardReason,
    ) {}
}
