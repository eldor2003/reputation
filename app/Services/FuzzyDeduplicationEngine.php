<?php

namespace App\Services;

use App\Contracts\DeduplicationEngineInterface;
use App\Contracts\FuzzyMatchingStrategyInterface;
use App\Contracts\MentionClusterRepositoryInterface;
use App\Contracts\SimilarityCalculatorInterface;
use App\DTO\DeduplicationResultDTO;
use App\DTO\MentionFingerprintDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\DeduplicationMatchMethod;
use App\Models\Mention;
use App\Models\MentionCluster;

class FuzzyDeduplicationEngine implements DeduplicationEngineInterface
{
    public function __construct(
        private readonly ExactDeduplicationEngine $exactDeduplicationEngine,
        private readonly SimilarityCalculatorInterface $similarityCalculator,
        private readonly FuzzyMatchingStrategyInterface $matchingStrategy,
        private readonly MentionClusterRepositoryInterface $clusterRepository,
    ) {}

    public function check(NormalizedMentionDTO $mention): DeduplicationResultDTO
    {
        if ((bool) config('deduplication.exact_fallback.enabled', true)) {
            $exactResult = $this->exactDeduplicationEngine->check($mention);

            if ($exactResult->isDuplicate) {
                return new DeduplicationResultDTO(
                    isDuplicate: true,
                    originalMentionId: $exactResult->originalMentionId,
                    dedupHash: $exactResult->dedupHash,
                    matchMethod: DeduplicationMatchMethod::Exact,
                );
            }
        }

        if (! (bool) config('deduplication.fuzzy.enabled', true)) {
            return $this->exactDeduplicationEngine->check($mention);
        }

        $fingerprint = $this->buildFingerprint($mention);
        $contentDuplicate = $this->findExactContentDuplicate($mention, $fingerprint);

        if ($contentDuplicate !== null) {
            return new DeduplicationResultDTO(
                isDuplicate: true,
                originalMentionId: $contentDuplicate->id,
                dedupHash: $fingerprint->dedupHash,
                clusterId: $contentDuplicate->mention_cluster_id,
                matchMethod: DeduplicationMatchMethod::Exact,
                fingerprint: $fingerprint,
            );
        }

        $bestMatch = $this->findBestFuzzyMatch($mention, $fingerprint);

        if ($bestMatch === null) {
            return new DeduplicationResultDTO(
                isDuplicate: false,
                originalMentionId: null,
                dedupHash: $fingerprint->dedupHash,
                matchMethod: null,
                fingerprint: $fingerprint,
            );
        }

        return new DeduplicationResultDTO(
            isDuplicate: true,
            originalMentionId: $bestMatch['mention_id'],
            dedupHash: $fingerprint->dedupHash,
            clusterId: $bestMatch['cluster_id'],
            similarityScore: $bestMatch['score'],
            matchMethod: DeduplicationMatchMethod::Fuzzy,
            fingerprint: $fingerprint,
        );
    }

    private function buildFingerprint(NormalizedMentionDTO $mention): MentionFingerprintDTO
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

    /**
     * @return array{mention_id: int, cluster_id: int|null, score: float}|null
     */
    private function findBestFuzzyMatch(NormalizedMentionDTO $mention, MentionFingerprintDTO $fingerprint): ?array
    {
        $candidateMentions = $this->findCandidateMentions($mention, $fingerprint);
        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($candidateMentions as $candidate) {
            $candidateDto = $this->toNormalizedMentionDto($candidate);
            $score = $this->similarityCalculator->score($mention, $candidateDto);

            if (! $this->similarityCalculator->isDuplicate($score)) {
                continue;
            }

            if ($score->total > $bestScore) {
                $bestScore = $score->total;
                $bestMatch = [
                    'mention_id' => $candidate->id,
                    'cluster_id' => $candidate->mention_cluster_id,
                    'score' => $score->total,
                ];
            }
        }

        if ($bestMatch !== null && $bestMatch['cluster_id'] === null) {
            $clusters = $this->clusterRepository->findCandidateClusters($mention->projectId, $fingerprint);
            $bestMatch['cluster_id'] = $clusters->first()?->id;
        }

        return $bestMatch;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Mention>
     */
    private function findCandidateMentions(NormalizedMentionDTO $mention, MentionFingerprintDTO $fingerprint)
    {
        $windowHours = (int) config('deduplication.time_window_hours', 72);
        $maxCandidates = (int) config('deduplication.candidate_limit', 500);
        $publishedAt = $mention->publishedAt;

        $query = Mention::query()
            ->where('project_id', $mention->projectId)
            ->where('is_duplicate', false)
            ->where(function ($builder): void {
                $builder->whereNotNull('simhash')
                    ->orWhereNotNull('content_fingerprint');
            });

        if ($publishedAt !== null) {
            $query->whereBetween('published_at', [
                $publishedAt->copy()->subHours($windowHours),
                $publishedAt->copy()->addHours($windowHours),
            ]);
        } else {
            $query->where('received_at', '>=', now()->subHours($windowHours));
        }

        return $query
            ->latest('published_at')
            ->limit($maxCandidates)
            ->get()
            ->filter(function (Mention $candidate) use ($fingerprint): bool {
                if ($candidate->content_fingerprint === $fingerprint->contentFingerprint) {
                    return true;
                }

                if ($candidate->simhash === null || $fingerprint->simhash === '') {
                    return false;
                }

                return $this->matchingStrategy->signaturesAreSimilar($candidate->simhash, $fingerprint->simhash);
            });
    }

    private function toNormalizedMentionDto(Mention $mention): NormalizedMentionDTO
    {
        return new NormalizedMentionDTO(
            projectId: $mention->project_id,
            sourceId: $mention->source_id,
            externalId: $mention->external_id,
            author: $mention->author,
            authorId: $mention->author_id,
            language: $mention->language,
            text: $mention->content,
            title: $mention->title,
            url: $mention->url,
            publishedAt: $mention->published_at,
            receivedAt: $mention->received_at,
            metadata: $mention->metadata,
        );
    }

    private function findExactContentDuplicate(
        NormalizedMentionDTO $mention,
        MentionFingerprintDTO $fingerprint,
    ): ?Mention {
        $existing = Mention::query()
            ->where('project_id', $mention->projectId)
            ->where('is_duplicate', false)
            ->where('content_fingerprint', $fingerprint->contentFingerprint)
            ->oldest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $normalizedContent = $this->normalizeContent($mention->text);

        if ($normalizedContent === '') {
            return null;
        }

        return Mention::query()
            ->where('project_id', $mention->projectId)
            ->where('is_duplicate', false)
            ->whereNull('content_fingerprint')
            ->where('content', '!=', '')
            ->latest('id')
            ->limit(500)
            ->get()
            ->first(fn (Mention $candidate): bool => $this->normalizeContent((string) $candidate->content) === $normalizedContent);
    }

    private function normalizeContent(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
