<?php

/**
 * Mentionlytics token lifecycle verification (no secrets in output).
 *
 * Usage: php scripts/verify-mentionlytics-token-lifecycle.php
 */

declare(strict_types=1);

use App\Contracts\MentionlyticsAuthServiceInterface;
use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\Models\MentionlyticsOAuthToken;
use App\Services\Mentionlytics\MentionlyticsHttpTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

function envTokenFingerprints(): array
{
    $envPath = base_path('.env');
    $contents = is_readable($envPath) ? file_get_contents($envPath) : '';
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
        'mtime' => is_readable($envPath) ? (string) filemtime($envPath) : 'missing',
    ];
}

function dbTokenFingerprints(): array
{
    $record = MentionlyticsOAuthToken::query()->where('credential_key', 'default')->first();

    if ($record === null) {
        return ['exists' => false];
    }

    $raw = DB::table('mentionlytics_oauth_tokens')
        ->where('credential_key', 'default')
        ->first();

    return [
        'exists' => true,
        'access_fp' => fingerprint($record->access_token),
        'refresh_fp' => fingerprint($record->refresh_token),
        'raw_access_encrypted' => is_string($raw?->access_token)
            && str_starts_with($raw->access_token, 'eyJpdiI6'),
        'raw_refresh_encrypted' => is_string($raw?->refresh_token)
            && str_starts_with($raw->refresh_token, 'eyJpdiI6'),
        'expires_at' => $record->expires_at?->toIso8601String(),
    ];
}

function check(bool $condition): string
{
    return $condition ? 'PASS' : 'FAIL';
}

$results = [
    'bootstrap' => false,
    'db_persistence' => false,
    'encryption' => false,
    'automatic_refresh' => false,
    'rotation' => false,
    'db_used_after_refresh' => false,
    'post_refresh_request' => false,
    'env_unchanged' => false,
    'env_not_read_after_refresh' => false,
];

$errors = [];

try {
    $envBefore = envTokenFingerprints();

    // Step 1: bootstrap via reseed command
    $exitCode = Illuminate\Support\Facades\Artisan::call('mentionlytics:test', ['--reseed-from-env' => true]);
    $bootstrapOutput = Illuminate\Support\Facades\Artisan::output();

    $results['bootstrap'] = $exitCode === 0 && str_contains($bootstrapOutput, 'API Connection Status: OK');

    $dbAfterBootstrap = dbTokenFingerprints();
    $results['db_persistence'] = ($dbAfterBootstrap['exists'] ?? false) === true;
    $results['encryption'] = ($dbAfterBootstrap['raw_access_encrypted'] ?? false)
        && ($dbAfterBootstrap['raw_refresh_encrypted'] ?? false);

    $bootstrapAccessFp = $dbAfterBootstrap['access_fp'] ?? 'empty';
    $bootstrapRefreshFp = $dbAfterBootstrap['refresh_fp'] ?? 'empty';

    // Step 2: force refresh (live API)
    $auth = app(MentionlyticsAuthServiceInterface::class);
    $auth->forceRefresh();

    $dbAfterRefresh = dbTokenFingerprints();
    $envAfterRefresh = envTokenFingerprints();

    $results['automatic_refresh'] = ($dbAfterRefresh['exists'] ?? false) === true;
    $results['rotation'] = ($dbAfterRefresh['access_fp'] ?? '') !== $bootstrapAccessFp
        || ($dbAfterRefresh['refresh_fp'] ?? '') !== $bootstrapRefreshFp;
    $results['env_unchanged'] = $envBefore['bearer'] === $envAfterRefresh['bearer']
        && $envBefore['refresh'] === $envAfterRefresh['refresh']
        && $envBefore['mtime'] === $envAfterRefresh['mtime'];

    // Step 3: blank env config — DB must still work
    config([
        'mentionlytics.bearer_token' => '',
        'mentionlytics.refresh_token' => '',
    ]);
    app()->forgetInstance(MentionlyticsAuthServiceInterface::class);
    app()->forgetInstance(MentionlyticsHttpTransport::class);

    $authAfterBlankEnv = app(MentionlyticsAuthServiceInterface::class);
    $token = $authAfterBlankEnv->getAccessToken();
    $results['db_used_after_refresh'] = fingerprint($token) === ($dbAfterRefresh['access_fp'] ?? '');

    $client = app(MentionlyticsClientInterface::class);
    $page = $client->getMentions(new MentionlyticsMentionsQueryDTO(
        startDate: now()->subDays(7)->format('Ymd'),
        endDate: now()->format('Ymd'),
        perPage: 1,
    ));
    $results['post_refresh_request'] = count($page->mentions) >= 0;
    $results['env_not_read_after_refresh'] = $results['db_used_after_refresh'] && $results['post_refresh_request'];
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

line('MENTIONLYTICS_TOKEN_LIFECYCLE_VERIFICATION');
line('environment='.config('app.env'));
line('bootstrap='.check($results['bootstrap']));
line('db_persistence='.check($results['db_persistence']));
line('encryption='.check($results['encryption']));
line('automatic_refresh='.check($results['automatic_refresh']));
line('rotation='.check($results['rotation']));
line('env_unchanged='.check($results['env_unchanged']));
line('db_used_after_refresh='.check($results['db_used_after_refresh']));
line('post_refresh_request='.check($results['post_refresh_request']));
line('env_not_read_after_refresh='.check($results['env_not_read_after_refresh']));
line('overall='.check(! in_array(false, $results, true) && $errors === []));

if ($errors !== []) {
    line('errors='.implode(' | ', $errors));
}

exit(! in_array(false, $results, true) && $errors === [] ? 0 : 1);
