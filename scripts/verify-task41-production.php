<?php

declare(strict_types=1);

use App\Contracts\SerpScreenshotStorageInterface;
use App\DTO\NormalizedMentionDTO;
use App\Enums\SourceType;
use App\Models\Mention;
use App\Models\SerpSnapshot;
use App\Services\Deduplication\SimHashGenerator;
use App\Services\FuzzyDeduplicationEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$results = [];

function record(array &$results, string $name, bool $passed, string $detail = ''): void
{
    $results[] = [
        'name' => $name,
        'passed' => $passed,
        'detail' => $detail,
    ];
}

$disk = (string) config('serpapi.screenshots.disk', 'local');
$s3AdapterInstalled = class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class);
record(
    $results,
    's3 adapter installed',
    $s3AdapterInstalled,
    $s3AdapterInstalled ? 'league/flysystem-aws-s3-v3 present' : 'missing package',
);

if ($disk === 's3') {
    $awsConfigured = is_string(config('filesystems.disks.s3.key'))
        && config('filesystems.disks.s3.key') !== ''
        && is_string(config('filesystems.disks.s3.bucket'))
        && config('filesystems.disks.s3.bucket') !== '';

    if (! $awsConfigured) {
        record($results, 's3 upload/read/delete', false, 'AWS credentials or bucket not configured in .env');
    } else {
        try {
            $probePath = trim((string) config('serpapi.screenshots.path'), '/').'/task41-probe.txt';
            $probeContents = 'task41-probe-'.now()->toIso8601String();
            $putOk = Storage::disk('s3')->put($probePath, $probeContents);
            $readBack = Storage::disk('s3')->get($probePath);
            Storage::disk('s3')->delete($probePath);
            record(
                $results,
                's3 upload/read/delete',
                $putOk === true && is_string($readBack) && $readBack === $probeContents,
                sprintf('put=%s read=%s path=%s', json_encode($putOk), json_encode($readBack === $probeContents), $probePath),
            );
        } catch (Throwable $exception) {
            record($results, 's3 upload/read/delete', false, $exception->getMessage());
        }
    }
} else {
    record($results, 's3 upload/read/delete', true, 'skipped: disk='.$disk);
}

$latestSnapshot = SerpSnapshot::query()->latest('id')->first();
if ($latestSnapshot === null) {
    record($results, 'serp screenshot integrity', true, 'no snapshots yet');
} else {
    $metadata = is_array($latestSnapshot->metadata) ? $latestSnapshot->metadata : [];
    $screenshotAvailable = $metadata['screenshot_available'] ?? null;
    $path = $latestSnapshot->screenshot_path;
    $archivePath = is_string($metadata['archive_path'] ?? null) ? $metadata['archive_path'] : null;
    $storage = app(SerpScreenshotStorageInterface::class);

    if (is_string($path) && $path !== '') {
        $exists = $storage->exists($path);
        $size = $exists ? strlen((string) Storage::disk($disk)->get($path)) : 0;
        $isPlaceholder = $size > 0 && $size < 4000;
        record(
            $results,
            'serp screenshot integrity',
            $exists && ! $isPlaceholder,
            sprintf('path=%s size=%d screenshot_available=%s', $path, $size, json_encode($screenshotAvailable)),
        );
    } elseif ($archivePath !== null) {
        record(
            $results,
            'serp screenshot integrity',
            $storage->exists($archivePath) && ($screenshotAvailable === false),
            'archive='.$archivePath,
        );
    } else {
        record(
            $results,
            'serp screenshot integrity',
            $screenshotAvailable === false,
            'no screenshot/archive stored',
        );
    }
}

$originalContent = 'Company X issued a product recall for batch 2026-A due to safety concerns.';
$rewrittenContent = 'Company X issued a product recall for batch 2026-A because of safety concerns.';
$simHash = app(SimHashGenerator::class);
$hamming = $simHash->hammingDistance(
    $simHash->generate($originalContent),
    $simHash->generate($rewrittenContent),
);
record(
    $results,
    'fuzzy dedup sample hamming distance',
    $hamming <= (int) config('deduplication.simhash.max_hamming_distance', 8),
    'distance='.$hamming,
);

$sampleMention = Mention::query()
    ->where('is_duplicate', false)
    ->whereNotNull('simhash')
    ->latest('id')
    ->first();

if ($sampleMention !== null) {
    $engine = app(FuzzyDeduplicationEngine::class);
    $dto = new NormalizedMentionDTO(
        projectId: $sampleMention->project_id,
        sourceId: $sampleMention->source_id,
        externalId: 'task41-probe',
        author: $sampleMention->author,
        authorId: $sampleMention->author_id,
        language: $sampleMention->language,
        text: (string) $sampleMention->content,
        title: $sampleMention->title,
        url: $sampleMention->url,
        publishedAt: $sampleMention->published_at,
        receivedAt: Carbon::now(),
    );
    $check = $engine->check($dto);
    record(
        $results,
        'fuzzy dedup engine executes',
        true,
        'mention_id='.$sampleMention->id.' duplicate='.($check->isDuplicate ? 'yes' : 'no'),
    );
} else {
    record($results, 'fuzzy dedup engine executes', true, 'no sample mention');
}

$resolvedMention = Mention::query()
    ->whereNotNull('person_id')
    ->whereHas('aiResults')
    ->latest('id')
    ->first();

if ($resolvedMention !== null) {
    $aiResult = $resolvedMention->aiResults()->latest('id')->first();
    $personName = $resolvedMention->person?->full_name;
    $classifiedPerson = $aiResult?->person;
    record(
        $results,
        'person resolver classification consistency',
        is_string($personName) && is_string($classifiedPerson) && $personName === $classifiedPerson,
        sprintf('mention_id=%d person=%s classified=%s', $resolvedMention->id, $personName, $classifiedPerson),
    );
} else {
    $builder = app(\App\Contracts\PromptBuilderInterface::class);
    $samplePerson = App\Models\Person::query()->whereNotNull('full_name')->first();
    $prompt = $builder->build(
        new NormalizedMentionDTO(
            projectId: $samplePerson?->project_id ?? 1,
            sourceId: 1,
            externalId: 'task41-person-probe',
            author: null,
            authorId: null,
            language: 'en',
            text: 'Sample mention for person prompt verification.',
            title: null,
            url: null,
            publishedAt: Carbon::now(),
            receivedAt: Carbon::now(),
        ),
        personMatch: $samplePerson !== null
            ? new \App\DTO\PersonMatchResultDTO(
                resolvedPerson: new \App\DTO\ResolvedPersonDTO(
                    personId: $samplePerson->id,
                    personUuid: (string) $samplePerson->uuid,
                    fullName: (string) $samplePerson->full_name,
                    matchedAlias: (string) $samplePerson->full_name,
                    matchType: \App\Enums\PersonAliasType::FullName,
                    confidence: 1.0,
                    matchedIn: 'text',
                ),
                isAmbiguous: false,
                candidates: [],
            )
            : null,
    );

    record(
        $results,
        'person resolver classification consistency',
        str_contains($prompt, '<person_candidates>')
            && ($samplePerson === null || str_contains($prompt, (string) $samplePerson->full_name)),
        $samplePerson === null
            ? 'no persons configured'
            : 'prompt includes person_candidates for '.$samplePerson->full_name,
    );
}

$allPassed = collect($results)->every(fn (array $row): bool => $row['passed']);

echo json_encode([
    'passed' => $allPassed,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

exit($allPassed ? 0 : 1);
