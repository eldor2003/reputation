<?php

namespace App\Actions;

use App\Contracts\DeliveryEngineInterface;
use App\DTO\DeliveryResultDTO;

class DeliverApprovedMentionAction
{
    public function __construct(
        private readonly DeliveryEngineInterface $deliveryEngine,
    ) {}

    public function execute(int $mentionId): DeliveryResultDTO
    {
        return $this->deliveryEngine->deliverApproved($mentionId);
    }
}
