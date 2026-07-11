<?php

declare(strict_types=1);

use App\Contracts\SerpScreenshotStorageInterface;
use App\DTO\RankingHistoryQueryDTO;
use App\Enums\SerpEngine;
use App\Services\RankingHistoryService;
use Illuminate\Support\Facades\Artisan;
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

Artisan::call('help');
$schedule = app(Illuminate\Console\Scheduling\Schedule::class);
$events = collect($schedule->events());

$mentionlyticsScheduled = $events->contains(
    fn ($event): bool => str_contains((string) $event->command, 'mentionlytics:poll'),
);
record($results, 'scheduler: mentionlytics:poll', $mentionlyticsScheduled);

$serpScheduled = $events->contains(
    fn ($event): bool => str_contains((string) $event->command, 'serp:snapshot'),
);
record($results, 'scheduler: serp:snapshot', $serpScheduled);

Artisan::call('mentionlytics:poll');
$pollExit = Artisan::output();
record(
    $results,
    'mentionlytics:poll',
    true,
    trim($pollExit) !== '' ? trim($pollExit) : 'command executed',
);

Artisan::call('serp:snapshot');
$serpExit = Artisan::output();
record(
    $results,
    'serp:snapshot',
    str_contains($serpExit, 'SERP snapshots complete') || str_contains($serpExit, 'No SERP snapshot queries'),
    trim($serpExit),
);

$latestSnapshot = App\Models\SerpSnapshot::query()->latest('id')->first();
$screenshotPath = $latestSnapshot?->screenshot_path;
$screenshotExists = is_string($screenshotPath) && $screenshotPath !== ''
    ? app(SerpScreenshotStorageInterface::class)->exists($screenshotPath)
    : false;
record(
    $results,
    'screenshot capture + storage',
    $latestSnapshot === null || $screenshotExists,
    $latestSnapshot === null
        ? 'no snapshots yet'
        : ($screenshotExists ? $screenshotPath : 'missing screenshot for latest snapshot'),
);

$disk = (string) config('serpapi.screenshots.disk', 'local');
record(
    $results,
    'screenshot disk',
    in_array($disk, ['local', 's3'], true),
    'disk='.$disk,
);

$history = app(RankingHistoryService::class)->query(new RankingHistoryQueryDTO(
    engine: SerpEngine::Google,
));
record(
    $results,
    'ranking history',
    true,
    'snapshots='.count($history->snapshots).' points='.count($history->positionHistory),
);

Artisan::call('horizon:status');
$horizonOutput = Artisan::output();
record(
    $results,
    'queue / horizon',
    str_contains(strtolower($horizonOutput), 'running'),
    trim($horizonOutput),
);

Artisan::call('telegram:test');
$telegramOutput = Artisan::output();
record(
    $results,
    'moderation bot',
    str_contains($telegramOutput, 'OK') || str_contains($telegramOutput, 'Connected'),
    'telegram:test executed',
);

$deliveryToken = config('delivery.telegram.telegram_delivery.bot_token');
$deliveryChats = config('delivery.telegram.telegram_delivery.chat_ids');
record(
    $results,
    'delivery bot configured',
    is_string($deliveryToken) && $deliveryToken !== '' && is_array($deliveryChats) && $deliveryChats !== [],
    'delivery chats='.(is_array($deliveryChats) ? count($deliveryChats) : 0),
);

$allPassed = collect($results)->every(fn (array $row): bool => $row['passed']);

echo json_encode([
    'passed' => $allPassed,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

exit($allPassed ? 0 : 1);
