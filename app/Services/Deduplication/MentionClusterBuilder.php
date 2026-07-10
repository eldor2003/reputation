<?php

namespace App\Services\Deduplication;

use App\Contracts\FuzzyMatchingStrategyInterface;
use App\Contracts\MentionClusterBuilderInterface;
use App\Contracts\MentionClusterRepositoryInterface;
use App\DTO\DeduplicationResultDTO;
use App\DTO\MentionFingerprintDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\DeduplicationMatchMethod;
use App\Models\Mention;
use App\Models\MentionCluster;

class MentionClusterBuilder implements MentionClusterBuilderInterface
{
    public function __construct(
        private readonly FuzzyMatchingStrategyInterface $matchingStrategy,
        private readonly MentionClusterRepositoryInterface $clusterRepository,
    ) {}

    public function buildFingerprint(NormalizedMentionDTO $mention): MentionFingerprintDTO
    {
        $normalizedContent = $this->normalizeContent($mention->text);
        $simhash = $this->matchingStrategy->generateSignature($normalizedContent);
        $contentFingerprint = hash('sha256', $normalizedContent);

        return new MentionFingerprintDTO(
            simhash: $simhash,
            contentFingerprint: $contentFingerprint,
            dedupHash: $contentFingerprint,
        );
    }

    public function assign(
        int $mentionId,
        NormalizedMentionDTO $mention,
        DeduplicationResultDTO $result,
    ): DeduplicationResultDTO {
        $fingerprint = $result->fingerprint ?? $this->buildFingerprint($mention);

        if (
            $result->isDuplicate
            && $result->originalMentionId !== null
            && (int) $result->originalMentionId !== $mentionId
        ) {
            return $this->assignDuplicate($mentionId, $mention, $result, $fingerprint);
        }

        $existingCluster = $this->clusterRepository->findClusterForMention($mentionId);

        if ($existingCluster !== null) {
            return new DeduplicationResultDTO(
                isDuplicate: false,
                originalMentionId: null,
                dedupHash: $result->dedupHash,
                clusterId: $existingCluster->id,
                similarityScore: $result->similarityScore,
                matchMethod: $result->matchMethod,
                fingerprint: $fingerprint,
            );
        }

        $cluster = $this->clusterRepository->createCluster(
            projectId: $mention->projectId,
            canonicalMentionId: $mentionId,
            fingerprint: $fingerprint,
        );

        return new DeduplicationResultDTO(
            isDuplicate: false,
            originalMentionId: null,
            dedupHash: $result->dedupHash !== '' ? $result->dedupHash : $fingerprint->dedupHash,
            clusterId: $cluster->id,
            similarityScore: $result->similarityScore,
            matchMethod: $result->matchMethod,
            fingerprint: $fingerprint,
        );
    }

    private function assignDuplicate(
        int $mentionId,
        NormalizedMentionDTO $mention,
        DeduplicationResultDTO $result,
        MentionFingerprintDTO $fingerprint,
    ): DeduplicationResultDTO {
        $cluster = $this->resolveCluster($result, $mention, $fingerprint);

        if ($cluster === null) {
            return $result;
        }

        $this->clusterRepository->addMentionToCluster(
            cluster: $cluster,
            mentionId: $mentionId,
            isCanonical: false,
            similarityScore: $result->similarityScore,
        );

        Mention::query()
            ->whereKey($mentionId)
            ->update([
                'simhash' => $fingerprint->simhash,
                'content_fingerprint' => $fingerprint->contentFingerprint,
                'dedup_hash' => $result->dedupHash !== '' ? $result->dedupHash : $fingerprint->dedupHash,
            ]);

        return new DeduplicationResultDTO(
            isDuplicate: true,
            originalMentionId: $result->originalMentionId,
            dedupHash: $result->dedupHash !== '' ? $result->dedupHash : $fingerprint->dedupHash,
            clusterId: $cluster->id,
            similarityScore: $result->similarityScore,
            matchMethod: $result->matchMethod ?? DeduplicationMatchMethod::Fuzzy,
            fingerprint: $fingerprint,
        );
    }

    private function resolveCluster(
        DeduplicationResultDTO $result,
        NormalizedMentionDTO $mention,
        MentionFingerprintDTO $fingerprint,
    ): ?MentionCluster {
        if ($result->clusterId !== null) {
            return MentionCluster::query()->find($result->clusterId);
        }

        $originalCluster = $this->clusterRepository->findClusterForMention((int) $result->originalMentionId);

        if ($originalCluster !== null) {
            return $originalCluster;
        }

        return $this->clusterRepository->createCluster(
            projectId: $mention->projectId,
            canonicalMentionId: (int) $result->originalMentionId,
            fingerprint: $fingerprint,
        );
    }

    private function normalizeContent(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
