<?php

namespace App\Services\Deduplication;

use App\Contracts\SimilarityCalculatorInterface;
use App\DTO\MentionSimilarityScoreDTO;
use App\DTO\NormalizedMentionDTO;
use Carbon\Carbon;

class MentionSimilarityCalculator implements SimilarityCalculatorInterface
{
    public function __construct(
        private readonly SimHashGenerator $simHashGenerator,
    ) {}

    public function score(NormalizedMentionDTO $left, NormalizedMentionDTO $right): MentionSimilarityScoreDTO
    {
        $contentScore = $this->contentSimilarity($left->text, $right->text);
        $titleScore = $this->textSimilarity($left->title, $right->title);
        $urlScore = $this->urlSimilarity($left->url, $right->url);
        $authorScore = $this->textSimilarity($left->author, $right->author);
        $publishedAtScore = $this->publishedAtSimilarity($left->publishedAt, $right->publishedAt);

        $weights = config('deduplication.similarity.weights');
        $total = ($contentScore * (float) $weights['content'])
            + ($titleScore * (float) $weights['title'])
            + ($urlScore * (float) $weights['url'])
            + ($authorScore * (float) $weights['author'])
            + ($publishedAtScore * (float) $weights['published_at']);

        return new MentionSimilarityScoreDTO(
            total: round($total, 4),
            content: round($contentScore, 4),
            title: round($titleScore, 4),
            url: round($urlScore, 4),
            author: round($authorScore, 4),
            publishedAt: round($publishedAtScore, 4),
        );
    }

    public function isDuplicate(MentionSimilarityScoreDTO $score): bool
    {
        return $score->total >= (float) config('deduplication.similarity.threshold', 0.85)
            && $score->total >= (float) config('deduplication.similarity.minimum', 0.70);
    }

    private function contentSimilarity(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($this->normalizeText($left) === $this->normalizeText($right)) {
            return 1.0;
        }

        $leftSimhash = $this->simHashGenerator->generate($left);
        $rightSimhash = $this->simHashGenerator->generate($right);
        $maxDistance = (int) config('deduplication.simhash.bits', 64);
        $distance = $this->simHashGenerator->hammingDistance($leftSimhash, $rightSimhash);

        return max(0.0, 1 - ($distance / max(1, $maxDistance)));
    }

    private function textSimilarity(?string $left, ?string $right): float
    {
        if ($left === null || $right === null || trim($left) === '' || trim($right) === '') {
            return 0.0;
        }

        $normalizedLeft = $this->normalizeText($left);
        $normalizedRight = $this->normalizeText($right);

        if ($normalizedLeft === $normalizedRight) {
            return 1.0;
        }

        similar_text($normalizedLeft, $normalizedRight, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }

    private function urlSimilarity(?string $left, ?string $right): float
    {
        if ($left === null || $right === null || trim($left) === '' || trim($right) === '') {
            return 0.0;
        }

        return $this->normalizeUrl($left) === $this->normalizeUrl($right) ? 1.0 : 0.0;
    }

    private function publishedAtSimilarity(?Carbon $left, ?Carbon $right): float
    {
        if ($left === null || $right === null) {
            return 0.0;
        }

        $windowHours = (int) config('deduplication.time_window_hours', 72);
        $differenceHours = abs($left->diffInMinutes($right)) / 60;

        if ($differenceHours <= $windowHours) {
            return 1.0;
        }

        return max(0.0, 1 - ($differenceHours / max(1, $windowHours * 4)));
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url(mb_strtolower(trim($url)));

        if ($parts === false) {
            return mb_strtolower(trim($url));
        }

        $host = $parts['host'] ?? '';
        $path = rtrim($parts['path'] ?? '', '/');

        return $host.$path;
    }
}
