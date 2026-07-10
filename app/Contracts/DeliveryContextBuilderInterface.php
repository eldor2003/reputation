<?php

namespace App\Contracts;

use App\DTO\DeliveryContextDTO;

interface DeliveryContextBuilderInterface
{
    public function buildForApproval(int $mentionId): DeliveryContextDTO;

    public function buildForMention(int $mentionId): DeliveryContextDTO;
}
