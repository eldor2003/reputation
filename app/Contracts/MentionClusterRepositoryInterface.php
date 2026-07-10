<?php

namespace App\Contracts;

use App\DTO\ClusterAssignmentDTO;
use App\DTO\DeduplicationResultDTO;
use App\DTO\MentionFingerprintDTO;
use App\DTO\NormalizedMentionDTO;
use App\Models\MentionCluster;
use Illuminate\Support\Collection;

interface MentionClusterRepositoryInterface
{
    public function createCluster(
        int $projectId,
        int $canonicalMentionId,
        MentionFingerprintDTO $fingerprint,
    ): MentionCluster;

    public function addMentionToCluster(
        MentionCluster $cluster,
        int $mentionId,
        bool $isCanonical,
        ?float $similarityScore,
    ): ClusterAssignmentDTO;

    public function mergeClusters(MentionCluster $target, MentionCluster $source): MentionCluster;

    /**
     * @return Collection<int, MentionCluster>
     */
    public function findCandidateClusters(int $projectId, MentionFingerprintDTO $fingerprint): Collection;

    public function findClusterForMention(int $mentionId): ?MentionCluster;
}
