<?php

namespace App\DTO;

readonly class Brand24AccountInfoDTO
{
    public function __construct(
        public int $mentionsUsageEstimationAtTheEnd,
    ) {}
}
