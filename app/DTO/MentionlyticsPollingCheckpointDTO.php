<?php

namespace App\DTO;

readonly class MentionlyticsPollingCheckpointDTO
{
    public function __construct(
        public string $lastProcessedAt,
        public string $lastProcessedMentionId,
        public ?string $bootstrapCompletedAt = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'last_processed_at' => $this->lastProcessedAt,
            'last_processed_mention_id' => $this->lastProcessedMentionId,
            'bootstrap_completed_at' => $this->bootstrapCompletedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $lastProcessedAt = $data['last_processed_at'] ?? null;
        $lastProcessedMentionId = $data['last_processed_mention_id'] ?? null;

        if (! is_string($lastProcessedAt) || $lastProcessedAt === '') {
            return null;
        }

        if (! is_string($lastProcessedMentionId) || $lastProcessedMentionId === '') {
            return null;
        }

        $bootstrapCompletedAt = $data['bootstrap_completed_at'] ?? null;

        return new self(
            lastProcessedAt: $lastProcessedAt,
            lastProcessedMentionId: $lastProcessedMentionId,
            bootstrapCompletedAt: is_string($bootstrapCompletedAt) && $bootstrapCompletedAt !== ''
                ? $bootstrapCompletedAt
                : null,
        );
    }
}
