<?php

namespace App\Repositories;

use App\Contracts\MentionClusterRepositoryInterface;
use App\DTO\ClusterAssignmentDTO;
use App\DTO\MentionFingerprintDTO;
use App\Enums\DeduplicationMatchMethod;
use App\Models\Mention;
use App\Models\MentionCluster;
use App\Models\MentionClusterItem;
use App\Services\Deduplication\SimHashGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MentionClusterRepository implements MentionClusterRepositoryInterface
{
    public function __construct(
        private readonly SimHashGenerator $simHashGenerator,
    ) {}

    public function createCluster(
        int $projectId,
        int $canonicalMentionId,
        MentionFingerprintDTO $fingerprint,
    ): MentionCluster {
        return DB::transaction(function () use ($projectId, $canonicalMentionId, $fingerprint): MentionCluster {
            $cluster = MentionCluster::query()->create([
                'project_id' => $projectId,
                'canonical_mention_id' => $canonicalMentionId,
                'simhash' => $fingerprint->simhash,
                'content_fingerprint' => $fingerprint->contentFingerprint,
            ]);

            $this->addMentionToCluster($cluster, $canonicalMentionId, true, 1.0);

            Mention::query()
                ->whereKey($canonicalMentionId)
                ->update([
                    'mention_cluster_id' => $cluster->id,
                    'simhash' => $fingerprint->simhash,
                    'content_fingerprint' => $fingerprint->contentFingerprint,
                ]);

            return $cluster->fresh(['items']) ?? $cluster;
        });
    }

    public function addMentionToCluster(
        MentionCluster $cluster,
        int $mentionId,
        bool $isCanonical,
        ?float $similarityScore,
    ): ClusterAssignmentDTO {
        MentionClusterItem::query()->updateOrCreate(
            ['mention_id' => $mentionId],
            [
                'mention_cluster_id' => $cluster->id,
                'is_canonical' => $isCanonical,
                'similarity_score' => $similarityScore,
                'joined_at' => now(),
            ],
        );

        Mention::query()
            ->whereKey($mentionId)
            ->update([
                'mention_cluster_id' => $cluster->id,
                'simhash' => $cluster->simhash,
                'content_fingerprint' => $cluster->content_fingerprint,
            ]);

        return new ClusterAssignmentDTO(
            clusterId: $cluster->id,
            mentionId: $mentionId,
            isCanonical: $isCanonical,
            similarityScore: $similarityScore,
            matchMethod: DeduplicationMatchMethod::Fuzzy,
        );
    }

    public function mergeClusters(MentionCluster $target, MentionCluster $source): MentionCluster
    {
        return DB::transaction(function () use ($target, $source): MentionCluster {
            MentionClusterItem::query()
                ->where('mention_cluster_id', $source->id)
                ->update(['mention_cluster_id' => $target->id]);

            Mention::query()
                ->where('mention_cluster_id', $source->id)
                ->update(['mention_cluster_id' => $target->id]);

            $source->delete();

            return $target->fresh(['items']) ?? $target;
        });
    }

    public function findCandidateClusters(int $projectId, MentionFingerprintDTO $fingerprint): Collection
    {
        $clusters = MentionCluster::query()
            ->where('project_id', $projectId)
            ->whereNotNull('simhash')
            ->get();

        $maxDistance = (int) config('deduplication.simhash.max_hamming_distance', 8);

        return $clusters
            ->filter(function (MentionCluster $cluster) use ($fingerprint, $maxDistance): bool {
                if ($cluster->simhash === null) {
                    return false;
                }

                if ($cluster->content_fingerprint === $fingerprint->contentFingerprint) {
                    return true;
                }

                return $this->simHashGenerator->hammingDistance($cluster->simhash, $fingerprint->simhash) <= $maxDistance;
            })
            ->values();
    }

    public function findClusterForMention(int $mentionId): ?MentionCluster
    {
        $mention = Mention::query()->find($mentionId);

        if ($mention?->mention_cluster_id === null) {
            return null;
        }

        return MentionCluster::query()->find($mention->mention_cluster_id);
    }
}
