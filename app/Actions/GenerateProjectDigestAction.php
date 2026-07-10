<?php

namespace App\Actions;

use App\Contracts\DigestEngineInterface;
use App\DTO\DeliveryResultDTO;
use App\Enums\DigestType;

class GenerateProjectDigestAction
{
    public function __construct(
        private readonly DigestEngineInterface $digestEngine,
    ) {}

    public function execute(DigestType $digestType, ?int $projectId = null): DeliveryResultDTO
    {
        return $this->digestEngine->generate($digestType, $projectId);
    }
}
