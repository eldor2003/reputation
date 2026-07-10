<?php

namespace App\DTO;

use Carbon\Carbon;
use Carbon\CarbonInterface;

readonly class DeliveryCardDTO
{
    public function __construct(
        public int $mentionId,
        public int $projectId,
        public string $person,
        public string $threatLevel,
        public float $threatScore,
        public string $source,
        public string $summary,
        public ?string $url,
        public string $sentiment,
        public int $severity,
        public ?int $serpPosition,
        public int $clusterSize,
        public ?CarbonInterface $publishedAt,
        public CarbonInterface $processedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mention_id' => $this->mentionId,
            'project_id' => $this->projectId,
            'person' => $this->person,
            'threat_level' => $this->threatLevel,
            'threat_score' => $this->threatScore,
            'source' => $this->source,
            'summary' => $this->summary,
            'url' => $this->url,
            'sentiment' => $this->sentiment,
            'severity' => $this->severity,
            'serp_position' => $this->serpPosition,
            'cluster_size' => $this->clusterSize,
            'published_at' => $this->publishedAt?->toIso8601String(),
            'processed_at' => $this->processedAt->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $publishedAt = isset($payload['published_at']) && is_string($payload['published_at'])
            ? Carbon::parse($payload['published_at'])
            : null;

        $processedAtRaw = $payload['processed_at'] ?? $payload['timestamp'] ?? null;
        $processedAt = is_string($processedAtRaw)
            ? Carbon::parse($processedAtRaw)
            : now();

        return new self(
            mentionId: (int) ($payload['mention_id'] ?? 0),
            projectId: (int) ($payload['project_id'] ?? 0),
            person: (string) ($payload['person'] ?? 'неизвестно'),
            threatLevel: (string) ($payload['threat_level'] ?? 'P4'),
            threatScore: (float) ($payload['threat_score'] ?? 0),
            source: (string) ($payload['source'] ?? 'unknown'),
            summary: (string) ($payload['summary'] ?? ''),
            url: isset($payload['url']) ? (string) $payload['url'] : null,
            sentiment: (string) ($payload['sentiment'] ?? 'unknown'),
            severity: (int) ($payload['severity'] ?? 0),
            serpPosition: isset($payload['serp_position']) ? (int) $payload['serp_position'] : null,
            clusterSize: (int) ($payload['cluster_size'] ?? 1),
            publishedAt: $publishedAt,
            processedAt: $processedAt,
        );
    }
}
