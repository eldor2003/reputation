<?php

namespace App\DTO;

use App\Http\Requests\YouScanIngestRequest;

readonly class YouScanIngestData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $sourceUuid,
        public string $externalId,
        public ?string $idempotencyKey,
        public array $payload,
    ) {}

    public static function fromRequest(YouScanIngestRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            sourceUuid: $validated['source_uuid'],
            externalId: $validated['id'],
            idempotencyKey: $validated['idempotency_key'] ?? null,
            payload: $request->all(),
        );
    }
}
