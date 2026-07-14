<?php

declare(strict_types=1);

use App\DTO\NormalizedMentionDTO;
use App\Enums\DeduplicationMatchMethod;
use App\Services\Deduplication\SimHashGenerator;
use App\Services\FuzzyDeduplicationEngine;
use Carbon\Carbon;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$originalContent = 'Company X issued a product recall for batch 2026-A due to safety concerns.';
$rewrittenContent = 'Company X issued a product recall for batch 2026-A because of safety concerns.';
$url = 'https://news.example.com/task41-recall';
$publishedAt = Carbon::parse('2026-07-09 13:00:00');

$project = App\Models\Project::query()->where('is_active', true)->first();
$brand24 = App\Models\Source::query()->where('type', App\Enums\SourceType::Brand24)->first();
$mentionlytics = App\Models\Source::query()->where('type', App\Enums\SourceType::Mentionlytics)->first();

if ($project === null || $brand24 === null || $mentionlytics === null) {
    echo json_encode(['passed' => false, 'detail' => 'missing project or sources'], JSON_PRETTY_PRINT).PHP_EOL;
    exit(1);
}

$simHash = app(SimHashGenerator::class);
$hamming = $simHash->hammingDistance(
    $simHash->generate($originalContent),
    $simHash->generate($rewrittenContent),
);

$original = App\Models\Mention::query()->create([
    'project_id' => $project->id,
    'source_id' => $brand24->id,
    'external_id' => 'task41-dedup-original-'.time(),
    'content' => $originalContent,
    'title' => 'Product recall announced',
    'url' => $url,
    'author' => 'News Desk',
    'published_at' => $publishedAt,
    'received_at' => now(),
    'status' => App\Enums\MentionStatus::Completed,
    'dedup_hash' => hash('sha256', 'task41-original'),
    'content_fingerprint' => hash('sha256', mb_strtolower(trim($originalContent))),
    'simhash' => $simHash->generate($originalContent),
    'is_duplicate' => false,
]);

$engine = app(FuzzyDeduplicationEngine::class);
$result = $engine->check(new NormalizedMentionDTO(
    projectId: $project->id,
    sourceId: $mentionlytics->id,
    externalId: 'task41-dedup-rewrite-'.time(),
    author: 'News Desk',
    authorId: null,
    language: 'en',
    text: $rewrittenContent,
    title: 'Product recall announced',
    url: $url,
    publishedAt: $publishedAt->copy()->addHour(),
    receivedAt: now(),
));

$passed = $result->isDuplicate
    && $result->originalMentionId === $original->id
    && $result->matchMethod === DeduplicationMatchMethod::Fuzzy;

echo json_encode([
    'passed' => $passed,
    'hamming_distance' => $hamming,
    'original_mention_id' => $original->id,
    'match_method' => $result->matchMethod?->value,
    'is_duplicate' => $result->isDuplicate,
    'original_mention_id_result' => $result->originalMentionId,
], JSON_PRETTY_PRINT).PHP_EOL;

exit($passed ? 0 : 1);
