<?php

namespace Tests\Unit\Services\Mentionlytics;

use App\Contracts\MentionlyticsAuthServiceInterface;
use App\Contracts\MentionlyticsRefreshServiceInterface;
use App\Contracts\MentionlyticsTokenStorageInterface;
use App\DTO\MentionlyticsTokenPairDTO;
use App\Exceptions\MentionlyticsApiException;
use App\Models\MentionlyticsOAuthToken;
use App\Services\Mentionlytics\DatabaseMentionlyticsTokenStorage;
use App\Services\Mentionlytics\MentionlyticsAuthService;
use App\Services\Mentionlytics\MentionlyticsHttpTransport;
use App\Services\Mentionlytics\MentionlyticsRateLimiter;
use App\Services\Mentionlytics\MentionlyticsRefreshService;
use App\Services\Mentionlytics\MentionlyticsResponseCache;
use App\Services\Mentionlytics\MentionlyticsTokenResponseParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsAuthInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'mentionlytics.base_url' => 'https://api.mentionlytics.com/v2',
            'mentionlytics.timeout' => 5,
            'mentionlytics.access_token_ttl_seconds' => 3600,
            'mentionlytics.refresh_token_ttl_seconds' => 2592000,
            'mentionlytics.proactive_refresh_buffer_seconds' => 300,
            'mentionlytics.response_cache_seconds' => 15,
            'mentionlytics.refresh_lock_seconds' => 5,
            'mentionlytics.refresh_lock_wait_seconds' => 2,
            'mentionlytics.rate_limit.per_second' => 20,
            'mentionlytics.rate_limit.per_minute' => 100,
            'mentionlytics.retry.max_attempts' => 5,
            'mentionlytics.retry.base_delay_ms' => 0,
            'mentionlytics.retry.max_delay_ms' => 0,
        ]);
    }

    #[Test]
    public function refresh_service_parses_rotated_token_pair(): void
    {
        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'refresh_expires_in' => 2592000,
            ], 200),
        ]);

        $pair = $this->app->make(MentionlyticsRefreshService::class)->refresh('old-refresh-token');

        $this->assertSame('new-access-token', $pair->accessToken);
        $this->assertSame('new-refresh-token', $pair->refreshToken);
    }

    #[Test]
    public function refresh_service_requires_rotated_refresh_token_in_response(): void
    {
        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'access_token' => 'new-access-token',
            ], 200),
        ]);

        $this->expectException(MentionlyticsApiException::class);
        $this->expectExceptionMessage('missing refresh token');

        $this->app->make(MentionlyticsRefreshService::class)->refresh('old-refresh-token');
    }

    #[Test]
    public function refresh_service_parses_bearer_field_from_api_response(): void
    {
        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'bearer' => 'new-bearer-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $pair = $this->app->make(MentionlyticsRefreshService::class)->refresh('old-refresh-token');

        $this->assertSame('new-bearer-token', $pair->accessToken);
        $this->assertSame('new-refresh-token', $pair->refreshToken);
    }

    #[Test]
    public function auth_service_prefers_database_tokens_over_stale_env_after_refresh(): void
    {
        config([
            'mentionlytics.bearer_token' => 'env-access',
            'mentionlytics.refresh_token' => 'env-refresh',
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'bearer' => 'db-access-after-refresh',
                'refresh_token' => 'db-refresh-after-refresh',
                'expires_in' => 3600,
            ], 200),
        ]);

        $authService = $this->app->make(MentionlyticsAuthServiceInterface::class);
        $authService->forceRefresh();

        $this->app->forgetInstance(MentionlyticsAuthServiceInterface::class);

        $token = $this->app->make(MentionlyticsAuthServiceInterface::class)->getAccessToken();

        $this->assertSame('db-access-after-refresh', $token);

        $stored = MentionlyticsOAuthToken::query()->first();
        $this->assertSame('db-refresh-after-refresh', $stored?->refresh_token);
    }

    #[Test]
    public function auth_service_persists_rotated_tokens_to_database(): void
    {
        config([
            'mentionlytics.bearer_token' => 'initial-access',
            'mentionlytics.refresh_token' => 'initial-refresh',
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'access_token' => 'rotated-access',
                'refresh_token' => 'rotated-refresh',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = $this->app->make(MentionlyticsAuthServiceInterface::class)->forceRefresh();

        $this->assertSame('rotated-access', $token);
        $this->assertDatabaseHas('mentionlytics_oauth_tokens', [
            'credential_key' => 'default',
        ]);

        $stored = MentionlyticsOAuthToken::query()->first();
        $this->assertSame('rotated-access', $stored?->access_token);
        $this->assertSame('rotated-refresh', $stored?->refresh_token);
    }

    #[Test]
    public function auth_service_bootstraps_from_env_only_when_database_is_empty(): void
    {
        config([
            'mentionlytics.bearer_token' => 'bootstrap-access',
            'mentionlytics.refresh_token' => 'bootstrap-refresh',
        ]);

        $token = $this->app->make(MentionlyticsAuthServiceInterface::class)->getAccessToken();

        $this->assertSame('bootstrap-access', $token);

        $stored = MentionlyticsOAuthToken::query()->first();
        $this->assertSame('bootstrap-access', $stored?->access_token);
        $this->assertSame('bootstrap-refresh', $stored?->refresh_token);
    }

    #[Test]
    public function auth_service_refreshes_proactively_before_expiration(): void
    {
        CarbonImmutable::setTestNow('2026-07-11 12:00:00');

        $this->app->make(MentionlyticsTokenStorageInterface::class)->store(
            new MentionlyticsTokenPairDTO(
                accessToken: 'expiring-access',
                refreshToken: 'current-refresh',
                expiresAt: CarbonImmutable::now()->addMinutes(4),
            ),
        );

        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'access_token' => 'proactive-access',
                'refresh_token' => 'proactive-refresh',
                'expires_in' => 3600,
            ], 200),
        ]);

        $authService = $this->app->make(MentionlyticsAuthServiceInterface::class);
        $token = $authService->getAccessToken();

        $this->assertSame('proactive-access', $token);
        $this->assertTrue($authService->wasRefreshUsedOnLastOperation());

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function http_transport_retries_after_unauthorized_and_uses_refreshed_token(): void
    {
        config([
            'mentionlytics.bearer_token' => 'stale-access',
            'mentionlytics.refresh_token' => 'valid-refresh',
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'access_token' => 'fresh-access',
                'refresh_token' => 'fresh-refresh',
                'expires_in' => 3600,
            ], 200),
            'api.mentionlytics.com/v2/mentions*' => Http::sequence()
                ->push(['message' => 'Unauthorized'], 401)
                ->push(['mentions' => [], 'results_after' => null], 200),
        ]);

        $payload = $this->app->make(MentionlyticsHttpTransport::class)->get('/mentions', [
            'startDate' => '20260701',
            'endDate' => '20260711',
        ]);

        $this->assertSame([], $payload['mentions']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/mentions')
                && $request->hasHeader('Authorization', 'Bearer fresh-access');
        });
    }

    #[Test]
    public function http_transport_applies_exponential_backoff_for_rate_limit_responses(): void
    {
        config([
            'mentionlytics.bearer_token' => 'access-token',
            'mentionlytics.refresh_token' => 'refresh-token',
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::sequence()
                ->push(['message' => 'Too Many Requests'], 429, ['Retry-After' => '0'])
                ->push(['mentions' => [['id' => '1', 'ftext' => 'Hello']], 'results_after' => null], 200),
        ]);

        $payload = $this->app->make(MentionlyticsHttpTransport::class)->get('/mentions', [
            'startDate' => '20260701',
            'endDate' => '20260711',
        ]);

        $this->assertCount(1, $payload['mentions']);
        $this->assertSame(2, count(Http::recorded()));
    }

    #[Test]
    public function response_cache_avoids_duplicate_identical_requests(): void
    {
        config([
            'mentionlytics.bearer_token' => 'access-token',
            'mentionlytics.refresh_token' => 'refresh-token',
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [],
                'results_after' => null,
            ], 200, [
                'RateLimit-Limit' => '100',
                'RateLimit-Remaining' => '99',
                'RateLimit-Reset' => (string) now()->addMinute()->timestamp,
            ]),
        ]);

        $transport = $this->app->make(MentionlyticsHttpTransport::class);
        $query = ['startDate' => '20260701', 'endDate' => '20260711'];

        $transport->get('/mentions', $query);
        $transport->get('/mentions', $query);

        Http::assertSentCount(1);
    }

    #[Test]
    public function rate_limiter_reads_rate_limit_headers(): void
    {
        config([
            'mentionlytics.retry.base_delay_ms' => 500,
            'mentionlytics.retry.max_delay_ms' => 30000,
        ]);

        Http::fake([
            'example.com/*' => function ($request) {
                if (str_contains($request->url(), 'rate-limited')) {
                    return Http::response([], 429);
                }

                return Http::response([], 200, [
                    'RateLimit-Limit' => '100',
                    'RateLimit-Remaining' => '10',
                    'RateLimit-Reset' => (string) now()->addSeconds(30)->timestamp,
                ]);
            },
        ]);

        $limiter = $this->app->make(MentionlyticsRateLimiter::class);

        $limiter->recordResponse(Http::get('https://example.com/test'));

        $this->assertSame(500, $limiter->delayForRateLimitResponse(
            Http::get('https://example.com/rate-limited'),
            1,
        ));
    }

    #[Test]
    public function token_storage_survives_reload_from_database(): void
    {
        $storage = new DatabaseMentionlyticsTokenStorage;

        $storage->store(new MentionlyticsTokenPairDTO(
            accessToken: 'persisted-access',
            refreshToken: 'persisted-refresh',
            expiresAt: CarbonImmutable::now()->addHour(),
        ));

        $loaded = $storage->load();

        $this->assertNotNull($loaded);
        $this->assertSame('persisted-access', $loaded->accessToken);
        $this->assertSame('persisted-refresh', $loaded->refreshToken);
    }
}
