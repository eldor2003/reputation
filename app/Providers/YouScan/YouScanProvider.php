<?php

namespace App\Providers\YouScan;

use App\Contracts\ProviderInterface;
use App\DTO\NormalizedMentionDTO;
use App\Enums\SourceType;

class YouScanProvider implements ProviderInterface
{
    public function __construct(
        private readonly YouScanNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): NormalizedMentionDTO
    {
        return $this->normalizer->normalize($payload);
    }

    public function supports(SourceType $type): bool
    {
        return $type === SourceType::YouScan;
    }

    public function name(): string
    {
        return SourceType::YouScan->value;
    }
}
