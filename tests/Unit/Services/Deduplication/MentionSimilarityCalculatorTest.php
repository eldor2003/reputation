<?php

namespace Tests\Unit\Services\Deduplication;

use App\Services\Deduplication\MentionSimilarityCalculator;
use App\Services\Deduplication\SimHashGenerator;
use App\DTO\NormalizedMentionDTO;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionSimilarityCalculatorTest extends TestCase
{
    #[Test]
    public function it_weights_content_highest_in_total_score(): void
    {
        config([
            'deduplication.similarity.weights' => [
                'content' => 0.50,
                'title' => 0.15,
                'url' => 0.20,
                'author' => 0.10,
                'published_at' => 0.05,
            ],
            'deduplication.similarity.threshold' => 0.85,
            'deduplication.similarity.minimum' => 0.70,
            'deduplication.time_window_hours' => 72,
            'deduplication.simhash.bits' => 64,
        ]);

        $calculator = new MentionSimilarityCalculator(new SimHashGenerator);
        $publishedAt = Carbon::parse('2026-07-09 12:00:00');

        $left = new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 1,
            externalId: 'left',
            author: 'Reporter',
            authorId: null,
            language: 'en',
            text: 'Acme Corp launched a new sustainability initiative for global operations.',
            title: 'Acme sustainability push',
            url: 'https://news.example.com/acme-sustainability',
            publishedAt: $publishedAt,
            receivedAt: $publishedAt,
        );

        $right = new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 2,
            externalId: 'right',
            author: 'Reporter',
            authorId: null,
            language: 'en',
            text: 'Acme Corp launched a new sustainability initiative for global operations.',
            title: 'Acme sustainability push',
            url: 'https://news.example.com/acme-sustainability',
            publishedAt: $publishedAt,
            receivedAt: $publishedAt,
        );

        $score = $calculator->score($left, $right);

        $this->assertSame(1.0, $score->total);
        $this->assertTrue($calculator->isDuplicate($score));
    }

    #[Test]
    public function it_rejects_mentions_below_similarity_threshold(): void
    {
        config([
            'deduplication.similarity.weights' => [
                'content' => 0.50,
                'title' => 0.15,
                'url' => 0.20,
                'author' => 0.10,
                'published_at' => 0.05,
            ],
            'deduplication.similarity.threshold' => 0.85,
            'deduplication.similarity.minimum' => 0.70,
            'deduplication.time_window_hours' => 72,
            'deduplication.simhash.bits' => 64,
        ]);

        $calculator = new MentionSimilarityCalculator(new SimHashGenerator);
        $publishedAt = Carbon::parse('2026-07-09 12:00:00');

        $left = new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 1,
            externalId: 'left',
            author: 'Sports Editor',
            authorId: null,
            language: 'en',
            text: 'Local sports team wins championship after overtime thriller.',
            title: 'Sports headline',
            url: 'https://news.example.com/sports',
            publishedAt: $publishedAt,
            receivedAt: $publishedAt,
        );

        $right = new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 2,
            externalId: 'right',
            author: 'Finance Editor',
            authorId: null,
            language: 'en',
            text: 'Central bank raises interest rates to combat inflation.',
            title: 'Economy headline',
            url: 'https://news.example.com/economy',
            publishedAt: $publishedAt,
            receivedAt: $publishedAt,
        );

        $score = $calculator->score($left, $right);

        $this->assertFalse($calculator->isDuplicate($score));
    }
}
