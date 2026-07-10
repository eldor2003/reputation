<?php

namespace App\Contracts;

use App\DTO\NormalizedMentionDTO;
use App\Enums\SourceType;

interface ProviderInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): NormalizedMentionDTO;

    public function supports(SourceType $type): bool;

    public function name(): string;
}
