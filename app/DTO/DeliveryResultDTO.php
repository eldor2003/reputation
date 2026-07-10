<?php

namespace App\DTO;

use App\Models\DeliveryDigest;
use App\Models\DeliveryMessage;

readonly class DeliveryResultDTO
{
    public function __construct(
        public bool $success,
        public ?DeliveryMessage $message = null,
        public ?DeliveryDigest $digest = null,
        public bool $queuedForDigest = false,
        public ?string $errorMessage = null,
    ) {}
}
