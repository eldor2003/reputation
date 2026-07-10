<?php

namespace App\Contracts;

use App\DTO\ClassificationResultDTO;
use App\DTO\LlmExecutionMetadataDTO;
use App\DTO\ValidationMetadataDTO;
use App\Models\AiResult;

interface AiResultStorageInterface
{
    public function store(
        int $mentionId,
        ClassificationResultDTO $result,
        string $model,
        ?LlmExecutionMetadataDTO $metadata = null,
        ?ValidationMetadataDTO $validationMetadata = null,
    ): AiResult;
}
