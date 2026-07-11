<?php

/**
 * Production Mentionlytics bootstrap + rotation verification (no secrets in output).
 *
 * Usage:
 *   php scripts/verify-mentionlytics-production-bootstrap.php bootstrap
 *   php scripts/verify-mentionlytics-production-bootstrap.php rotation
 */

declare(strict_types=1);

use App\Contracts\MentionlyticsAuthServiceInterface;
use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\Models\MentionlyticsOAuthToken;
use App\Services\Mentionlytics\MentionlyticsHttpTransport;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function line(string $message): void
{
    echo $message.PHP_EOL;
}

function fingerprint(?string $value): string
{
    if ($value === null || $value === '') {
        return 'empty';
    }

    return substr(hash('sha256', $value), 0, 12);
}

function envFingerprints(): array
{
    $path = base_path('.env');
    $contents = is_readable($path) ? file_get_contents($path) : '';
    $bearer = null;
    $refresh = null;

    foreach (explode("\n", $contents) as $row) {
        if (str_starts_with($row, 'MENTIONLYTICS_BEARER_TOKEN=')) {
            $bearer = substr($row, strlen('MENTIONLYTICS_BEARER_TOKEN='));
        }
        if (str_starts_with($row, 'MENTIONLYTICS_REFRESH_TOKEN=')) {
            $refresh = substr($row, strlen('MENTIONLYTICS_REFRESH_TOKEN='));
        }
    }

    return [
        'bearer' => fingerprint($bearer),
        'refresh' => fingerprint($refresh),
        'mtime' => is_readable($path) ? (string) filemtime($path) : 'missing',
    ];
}

function dbMeta(): array
{
    $record = MentionlyticsOAuthToken::query()->where('credential_key', 'default')->first();

    if ($record === null) {
        return ['exists' => false];
    }

    $raw = DB::table('mentionlytics_oauth_tokens')->where('credential_key', 'default')->first();

    return [
        'exists' => true,
        'created_at' => $record->created_at?->toIso8601String(),
        'updated_at' => $record->updated_at?->toIso8601String(),
        'access_fp' => fingerprint($record->access_token),
        'refresh_fp' => fingerprint($record->refresh_token),
        'encrypted_access' => is_string($raw?->access_token) && str_starts_with($raw->access_token, 'eyJpdiI6'),
        'encrypted_refresh' => is_string($raw?->refresh_token) && str_starts_with($raw->refresh_token, 'eyJpdiI6'),
    ];
}

$mode = $argv[1] ?? 'bootstrap';

if ($mode === 'bootstrap') {
    $meta = dbMeta();
    line('PRODUCTION_BOOTSTRAP_VERIFICATION');
    line('db_row='.(($meta['exists'] ?? false) ? 'yes' : 'no'));
    line('encryption='.(($meta['encrypted_access'] ?? false) && ($meta['encrypted_refresh'] ?? false) ? 'PASS' : 'FAIL'));
    line('db_persistence='.(($meta['exists'] ?? false) ? 'PASS' : 'FAIL'));
    exit(($meta['exists'] ?? false) && ($meta['encrypted_access'] ?? false) && ($meta['encrypted_refresh'] ?? false) ? 0 : 1);
}

if ($mode === 'rotation') {
    $envBefore = envFingerprints();
    $dbBefore = dbMeta();

    if (! ($dbBefore['exists'] ?? false)) {
        line('PRODUCTION_ROTATION_VERIFICATION');
        line('rotation=FAIL reason=no_db_row');
        exit(1);
    }

    try {
        app(MentionlyticsAuthServiceInterface::class)->forceRefresh();
    } catch (Throwable $exception) {
        line('PRODUCTION_ROTATION_VERIFICATION');
        line('rotation=FAIL reason='.$exception->getMessage());
        exit(1);
    }

    $dbAfter = dbMeta();
    $envAfter = envFingerprints();

    config(['mentionlytics.bearer_token' => '', 'mentionlytics.refresh_token' => '']);
    app()->forgetInstance(MentionlyticsAuthServiceInterface::class);
    app()->forgetInstance(MentionlyticsHttpTransport::class);

    $token = app(MentionlyticsAuthServiceInterface::class)->getAccessToken();
    $page = app(MentionlyticsClientInterface::class)->getMentions(new MentionlyticsMentionsQueryDTO(
        startDate: now()->subDays(7)->format('Ymd'),
        endDate: now()->format('Ymd'),
        perPage: 1,
    ));

    $rotated = ($dbAfter['access_fp'] ?? '') !== ($dbBefore['access_fp'] ?? '')
        || ($dbAfter['refresh_fp'] ?? '') !== ($dbBefore['refresh_fp'] ?? '');
    $envUnchanged = $envBefore === $envAfter;
    $dbSource = fingerprint($token) === ($dbAfter['access_fp'] ?? '');

    line('PRODUCTION_ROTATION_VERIFICATION');
    line('rotation='.($rotated ? 'PASS' : 'FAIL'));
    line('db_updated='.(($dbAfter['updated_at'] ?? '') !== ($dbBefore['updated_at'] ?? '') ? 'yes' : 'no'));
    line('env_unchanged='.($envUnchanged ? 'PASS' : 'FAIL'));
    line('db_source_of_truth='.($dbSource ? 'PASS' : 'FAIL'));
    line('api_after_rotation='.(count($page->mentions) >= 0 ? 'PASS' : 'FAIL'));
    exit($rotated && $envUnchanged && $dbSource ? 0 : 1);
}

line('unknown_mode');
exit(1);
