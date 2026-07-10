<?php

namespace App\Contracts;

use App\DTO\DeliveryResultDTO;
use App\Enums\DigestType;

interface DigestEngineInterface
{
    public function generate(DigestType $digestType, ?int $projectId = null): DeliveryResultDTO;
}
