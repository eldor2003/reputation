<?php

namespace Tests\Feature\Mentionlytics;

use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\Exceptions\MentionlyticsApiException;
use App\Services\MentionlyticsTokenManager;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsApiClientTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    #[Test]
    public function it_verifies_api_connectivity_with_a_sample_mentions_request(): void
    {
        $this->configureMentionlyticsClient();

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    [
                        'id' => '123',
                        'uu_id' => 'uuid-123',
                        'ftext' => 'Sample mention.',
                        'pub_datetime' => '2026-07-09 10:00:00',
                        'profile_name' => 'Jane',
                        'sentiment_text' => 'negative',
                        'mchannel' => 'twitter',
                        'mchannel_id' => 2,
                    ],
                ],
                'results_after' => null,
            ], 200),
        ]);

        $connectionInfo = $this->app->make(MentionlyticsClientInterface::class)->testConnection();

        $this->assertSame(1, $connectionInfo->mentionsOnPage);
        $this->assertFalse($connectionInfo->hasMoreMentions);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/v2/mentions')
                && $request->hasHeader('Authorization', 'Bearer test-bearer-token');
        });
    }

    #[Test]
    public function it_retrieves_mentions_with_query_parameters_and_pagination(): void
    {
        $this->configureMentionlyticsClient();

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    [
                        'id' => '456',
                        'ftext' => 'Negative mention text.',
                        'link' => 'https://example.com/post/456',
                        'profile_name' => 'John',
                        'uid' => 'uid-1',
                        'pub_date' => '2026-07-08',
                        'sentiment_text' => 'negative',
                    ],
                ],
                'results_after' => '789',
            ], 200),
        ]);

        $page = $this->app->make(MentionlyticsClientInterface::class)->getMentions(
            new MentionlyticsMentionsQueryDTO(
                startDate: '20260701',
                endDate: '20260709',
                pageNo: 0,
                perPage: 20,
                sentiment: 'negative',
                channels: [2],
                commtracks: '31016',
            ),
        );

        $this->assertTrue($page->hasMore);
        $this->assertSame('789', $page->resultsAfter);
        $this->assertCount(1, $page->mentions);
        $this->assertSame('456', $page->mentions[0]->id);
        $this->assertSame('Negative mention text.', $page->mentions[0]->text);
        $this->assertSame('negative', $page->mentions[0]->sentiment);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && $request['startDate'] === '20260701'
                && $request['endDate'] === '20260709'
                && $request['page_no'] === 0
                && $request['per_page'] === 20
                && $request['sentiment'] === 'negative'
                && $request['channels'] === '[2]'
                && $request['commtracks'] === '31016';
        });
    }

    #[Test]
    public function it_refreshes_bearer_token_when_access_token_is_near_expiry(): void
    {
        Carbon::setTestNow('2026-07-11 12:00:00');

        config([
            'mentionlytics.bearer_token' => 'expiring-bearer-token',
            'mentionlytics.refresh_token' => 'test-refresh-token',
            'mentionlytics.base_url' => 'https://api.mentionlytics.com/v2',
            'mentionlytics.timeout' => 5,
            'mentionlytics.proactive_refresh_buffer_seconds' => 300,
        ]);

        Cache::flush();

        $this->app->make(\App\Contracts\MentionlyticsTokenStorageInterface::class)->store(
            new \App\DTO\MentionlyticsTokenPairDTO(
                accessToken: 'expiring-bearer-token',
                refreshToken: 'test-refresh-token',
                expiresAt: CarbonImmutable::now()->addMinutes(2),
            ),
        );

        Http::fake([
            'api.mentionlytics.com/v2/auth/refresh' => Http::response([
                'access_token' => 'refreshed-bearer-token',
                'refresh_token' => 'refreshed-refresh-token',
            ], 200),
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [],
                'results_after' => null,
            ], 200),
        ]);

        $this->app->make(MentionlyticsClientInterface::class)->getMentions(
            new MentionlyticsMentionsQueryDTO('20260701', '20260711'),
        );

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/v2/auth/refresh')
                && $request['refresh_token'] === 'test-refresh-token';
        });

        Carbon::setTestNow();
    }

    #[Test]
    public function it_throws_when_refresh_token_is_not_configured(): void
    {
        config([
            'mentionlytics.bearer_token' => '',
            'mentionlytics.refresh_token' => '',
            'mentionlytics.base_url' => 'https://api.mentionlytics.com/v2',
        ]);

        Cache::flush();

        $this->expectException(MentionlyticsApiException::class);
        $this->expectExceptionMessage('Mentionlytics credentials are not configured.');

        $this->app->make(MentionlyticsTokenManager::class)->getBearerToken();
    }

    #[Test]
    public function it_retries_on_rate_limit_and_server_errors(): void
    {
        $this->configureMentionlyticsClient([
            'mentionlytics.retry.max_attempts' => 3,
            'mentionlytics.retry.base_delay_ms' => 0,
            'mentionlytics.retry.max_delay_ms' => 0,
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::sequence()
                ->push(['error' => ['message' => 'rate limit']], 429)
                ->push(['error' => ['message' => 'rate limit']], 429)
                ->push([
                    'mentions' => [],
                    'results_after' => null,
                ], 200),
        ]);

        $connectionInfo = $this->app->make(MentionlyticsClientInterface::class)->testConnection();

        $this->assertSame(0, $connectionInfo->mentionsOnPage);
        $this->assertSame(3, count(Http::recorded()));
    }

    #[Test]
    public function mentionlytics_test_command_verifies_connectivity(): void
    {
        $this->configureMentionlyticsClient();

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    ['id' => '1', 'ftext' => 'Test mention.'],
                ],
                'results_after' => '2',
            ], 200),
        ]);

        $this->artisan('mentionlytics:test')
            ->expectsOutputToContain('API Connection Status: OK')
            ->expectsOutputToContain('Mentions on page')
            ->expectsOutputToContain('Pagination')
            ->assertSuccessful();
    }

    #[Test]
    public function it_maps_api_v2_mention_payload_fields(): void
    {
        $this->configureMentionlyticsClient([
            'mentionlytics.refresh_token' => '',
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    [
                        'id' => 1924031548,
                        'uu_id' => 'uuid-reply-1',
                        'description' => 'Reply mention body.',
                        'title' => 'Fallback title',
                        'emotion_text' => 'anger',
                        'channel_name' => 'twitter',
                        'channel_id' => 2,
                        'language_code' => 'en',
                        'link' => 'https://example.com/post/reply',
                        'pub_datetime' => '2026-07-09 12:00:00',
                        'profile' => [
                            'name' => 'Jane Doe',
                            'uu_id' => 'profile-uuid-1',
                        ],
                        'metrics' => [
                            'engagement' => 42,
                        ],
                    ],
                ],
                'results_after' => null,
            ], 200),
        ]);

        $verification = $this->app->make(MentionlyticsClientInterface::class)->verify();

        $this->assertSame('account_access_token', $verification->authenticationMethod);
        $this->assertSame(1, $verification->mentionsOnPage);

        $mention = $this->app->make(MentionlyticsClientInterface::class)->getMentions(
            new MentionlyticsMentionsQueryDTO('20260701', '20260709'),
        )->mentions[0];

        $this->assertSame('Reply mention body.', $mention->text);
        $this->assertSame('anger', $mention->sentiment);
        $this->assertSame('twitter', $mention->channel);
        $this->assertSame(2, $mention->channelId);
        $this->assertSame('en', $mention->language);
        $this->assertSame('Jane Doe', $mention->authorName);
        $this->assertSame('profile-uuid-1', $mention->authorId);
        $this->assertSame(42, $mention->engagement);
    }

    #[Test]
    public function it_uses_updated_configured_token_when_cache_contains_a_previous_token(): void
    {
        config([
            'mentionlytics.bearer_token' => 'updated-bearer-token',
            'mentionlytics.refresh_token' => 'updated-refresh-token',
            'mentionlytics.base_url' => 'https://api.mentionlytics.com/v2',
            'mentionlytics.timeout' => 5,
        ]);

        $this->app->make(\App\Contracts\MentionlyticsTokenStorageInterface::class)->store(
            new \App\DTO\MentionlyticsTokenPairDTO(
                accessToken: 'stale-bearer-token',
                refreshToken: 'stale-refresh-token',
                expiresAt: \Carbon\CarbonImmutable::now()->addHour(),
            ),
        );

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [],
                'results_after' => null,
            ], 200),
        ]);

        $this->app->make(\App\Contracts\MentionlyticsAuthServiceInterface::class)->resetToEnvironmentCredentials();
        $this->app->make(MentionlyticsClientInterface::class)->getMentions(
            new MentionlyticsMentionsQueryDTO('20260701', '20260711'),
        );

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer updated-bearer-token');
        });
    }

    #[Test]
    public function mentionlytics_test_command_fails_when_credentials_are_missing(): void
    {
        config([
            'mentionlytics.bearer_token' => '',
            'mentionlytics.refresh_token' => '',
        ]);

        $this->artisan('mentionlytics:test')
            ->expectsOutputToContain('API Connection Status: FAILED')
            ->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureMentionlyticsClient(array $overrides = []): void
    {
        config(array_merge([
            'mentionlytics.bearer_token' => 'test-bearer-token',
            'mentionlytics.refresh_token' => 'test-refresh-token',
            'mentionlytics.base_url' => 'https://api.mentionlytics.com/v2',
            'mentionlytics.timeout' => 5,
            'mentionlytics.access_token_ttl_seconds' => 3600,
            'mentionlytics.proactive_refresh_buffer_seconds' => 300,
            'mentionlytics.retry.max_attempts' => 5,
            'mentionlytics.retry.base_delay_ms' => 0,
            'mentionlytics.retry.max_delay_ms' => 0,
        ], $overrides));

        Cache::flush();
        \App\Models\MentionlyticsOAuthToken::query()->delete();
    }
}
