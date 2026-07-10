<?php

namespace App\DTO;

use App\Http\Requests\Brand24IngestRequest;

readonly class Brand24IngestData
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

    public static function fromRequest(Brand24IngestRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            sourceUuid: $validated['source_uuid'],
            externalId: (string) $validated['mention_id'],
            idempotencyKey: $validated['idempotency_key'] ?? null,
            payload: $request->all(),
        );
    }
}
