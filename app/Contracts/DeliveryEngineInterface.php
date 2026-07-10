<?php

namespace App\Contracts;

use App\DTO\DeliveryContextDTO;
use App\DTO\DeliveryResultDTO;

interface DeliveryEngineInterface
{
    public function deliverApproved(int $mentionId): DeliveryResultDTO;

    public function queueForDigest(int $mentionId, ?string $digestType = null, bool $fromApproval = false): DeliveryResultDTO;
}
