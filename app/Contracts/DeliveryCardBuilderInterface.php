<?php

namespace App\Contracts;

use App\DTO\DeliveryCardDTO;
use App\DTO\DeliveryContextDTO;

interface DeliveryCardBuilderInterface
{
    public function build(DeliveryContextDTO $context): DeliveryCardDTO;

    public function formatCard(DeliveryCardDTO $card): string;

    /**
     * @param  list<DeliveryCardDTO>  $cards
     */
    public function formatDigest(string $title, array $cards): string;
}
