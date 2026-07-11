<?php

namespace App\Services\Mentionlytics;

use App\DTO\MentionlyticsMentionDTO;
use App\DTO\MentionlyticsPollingCheckpointDTO;
use App\Models\Mention;
use App\Models\Source;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class MentionlyticsPollingCheckpointService
{
    private const CONFIG_KEY = 'mentionlytics_polling';

    public function hasCheckpoint(Source $source): bool
    {
        $checkpoint = $this->get($source);

        return $checkpoint !== null && $checkpoint->bootstrapCompletedAt !== null;
    }

    public function get(Source $source): ?MentionlyticsPollingCheckpointDTO
    {
        /** @var array<string, mixed>|null $config */
        $config = is_array($source->config) ? $source->config : null;

        if ($config === null) {
            return null;
        }

        /** @var array<string, mixed>|null $polling */
        $polling = is_array($config[self::CONFIG_KEY] ?? null) ? $config[self::CONFIG_KEY] : null;

        if ($polling === null) {
            return null;
        }

        return MentionlyticsPollingCheckpointDTO::fromArray($polling);
    }

    public function save(Source $source, MentionlyticsPollingCheckpointDTO $checkpoint): void
    {
        /** @var array<string, mixed> $config */
        $config = is_array($source->config) ? $source->config : [];
        $config[self::CONFIG_KEY] = $checkpoint->toArray();

        $source->forceFill(['config' => $config])->save();
        $source->refresh();
    }

    public function establishFromExistingMentions(Source $source): bool
    {
        $latestMention = Mention::query()
            ->where('source_id', $source->id)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first(['external_id', 'published_at']);

        if ($latestMention === null || $latestMention->published_at === null) {
            return false;
        }

        $this->save($source, new MentionlyticsPollingCheckpointDTO(
            lastProcessedAt: $latestMention->published_at->toIso8601String(),
            lastProcessedMentionId: (string) $latestMention->external_id,
            bootstrapCompletedAt: now()->toIso8601String(),
        ));

        return true;
    }

    public function isNewerThanCheckpoint(
        MentionlyticsMentionDTO $mention,
        MentionlyticsPollingCheckpointDTO $checkpoint,
    ): bool {
        $publishedAt = $this->resolvePublishedAt($mention);

        if ($publishedAt === null) {
            return true;
        }

        $checkpointAt = Carbon::parse($checkpoint->lastProcessedAt);

        if ($publishedAt->gt($checkpointAt)) {
            return true;
        }

        if ($publishedAt->lt($checkpointAt)) {
            return false;
        }

        $mentionId = $mention->uuid ?? $mention->id;

        return $mentionId !== $checkpoint->lastProcessedMentionId;
    }

    public function advanceCheckpoint(
        MentionlyticsPollingCheckpointDTO $checkpoint,
        MentionlyticsMentionDTO $mention,
    ): MentionlyticsPollingCheckpointDTO {
        $publishedAt = $this->resolvePublishedAt($mention);

        if ($publishedAt === null) {
            return $checkpoint;
        }

        $checkpointAt = Carbon::parse($checkpoint->lastProcessedAt);
        $mentionId = $mention->uuid ?? $mention->id;

        if ($publishedAt->gt($checkpointAt)) {
            return new MentionlyticsPollingCheckpointDTO(
                lastProcessedAt: $publishedAt->toIso8601String(),
                lastProcessedMentionId: $mentionId,
                bootstrapCompletedAt: $checkpoint->bootstrapCompletedAt,
            );
        }

        if ($publishedAt->equalTo($checkpointAt) && $mentionId > $checkpoint->lastProcessedMentionId) {
            return new MentionlyticsPollingCheckpointDTO(
                lastProcessedAt: $publishedAt->toIso8601String(),
                lastProcessedMentionId: $mentionId,
                bootstrapCompletedAt: $checkpoint->bootstrapCompletedAt,
            );
        }

        return $checkpoint;
    }

    public function initialCheckpointFrom(MentionlyticsMentionDTO $mention): MentionlyticsPollingCheckpointDTO
    {
        $publishedAt = $this->resolvePublishedAt($mention) ?? now();

        return new MentionlyticsPollingCheckpointDTO(
            lastProcessedAt: $publishedAt->toIso8601String(),
            lastProcessedMentionId: $mention->uuid ?? $mention->id,
            bootstrapCompletedAt: null,
        );
    }

    public function markBootstrapCompleted(MentionlyticsPollingCheckpointDTO $checkpoint): MentionlyticsPollingCheckpointDTO
    {
        return new MentionlyticsPollingCheckpointDTO(
            lastProcessedAt: $checkpoint->lastProcessedAt,
            lastProcessedMentionId: $checkpoint->lastProcessedMentionId,
            bootstrapCompletedAt: now()->toIso8601String(),
        );
    }

    private function resolvePublishedAt(MentionlyticsMentionDTO $mention): ?CarbonInterface
    {
        if (! is_string($mention->publishedAt) || trim($mention->publishedAt) === '') {
            return null;
        }

        return Carbon::parse($mention->publishedAt);
    }
}
