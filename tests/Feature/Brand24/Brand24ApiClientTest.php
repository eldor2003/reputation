<?php

namespace Tests\Feature\Brand24;

use App\Contracts\Brand24ClientInterface;
use App\DTO\Brand24MentionsQueryDTO;
use App\Exceptions\Brand24ApiException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Brand24ApiClientTest extends TestCase
{
    #[Test]
    public function it_verifies_api_connectivity_and_returns_account_usage(): void
    {
        $this->configureBrand24Client();

        Http::fake([
            'api-data.brand24.com/api-data/v1/account/mentions-usage-estimation' => Http::response([
                'status' => 'success',
                'message' => [
                    'mentions_usage_estimation_at_the_end' => 14820,
                ],
            ], 200),
        ]);

        $accountInfo = $this->app->make(Brand24ClientInterface::class)->testConnection();

        $this->assertSame(14820, $accountInfo->mentionsUsageEstimationAtTheEnd);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/api-data/v1/account/mentions-usage-estimation')
                && $request->hasHeader('X-Api-Key', 'test-brand24-api-key');
        });
    }

    #[Test]
    public function it_retrieves_available_projects_for_an_account(): void
    {
        $this->configureBrand24Client();

        Http::fake([
            'api-data.brand24.com/api-data/v1/account/123456789/projects_list/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'projects_list' => [
                        '123456789' => 'Brand Monitoring',
                        '234567891' => 'Competitor Analysis',
                    ],
                ],
            ], 200),
        ]);

        $projects = $this->app->make(Brand24ClientInterface::class)->getProjects(123456789);

        $this->assertCount(2, $projects->projects);
        $this->assertSame('123456789', $projects->projects[0]->id);
        $this->assertSame('Brand Monitoring', $projects->projects[0]->name);
        $this->assertSame('234567891', $projects->projects[1]->id);
        $this->assertSame('Competitor Analysis', $projects->projects[1]->name);
    }

    #[Test]
    public function it_retrieves_projects_when_api_returns_a_flat_data_map(): void
    {
        $this->configureBrand24Client();

        Http::fake([
            'api-data.brand24.com/api-data/v1/account/595993514/projects_list/*' => Http::response([
                'status' => 'success',
                'data' => [
                    1397567729 => 'Tokaev',
                ],
            ], 200),
        ]);

        $projects = $this->app->make(Brand24ClientInterface::class)->getProjects(595993514);

        $this->assertCount(1, $projects->projects);
        $this->assertSame('1397567729', $projects->projects[0]->id);
        $this->assertSame('Tokaev', $projects->projects[0]->name);
    }

    #[Test]
    public function it_retrieves_project_mentions_with_query_parameters(): void
    {
        $this->configureBrand24Client();

        Http::fake([
            'api-data.brand24.com/api-data/v1/project/123456789/mentions*' => Http::response([
                'status' => 'success',
                'message' => [
                    'results' => [
                        [
                            'date' => '2026-06-01',
                            'time' => '14:32',
                            'title' => 'Bad review',
                            'content' => 'The product quality is unacceptable.',
                            'source' => 'twitter.com',
                            'host' => 'twitter.com',
                            'category' => 'x',
                            'sentiment' => -1,
                            'tags' => ['complaint'],
                        ],
                    ],
                    'has_more_mentions' => true,
                    'cursor' => 'cursor-page-2',
                ],
            ], 200),
        ]);

        $page = $this->app->make(Brand24ClientInterface::class)->getMentions(
            new Brand24MentionsQueryDTO(
                projectId: 123456789,
                dateFrom: '2026-06-01',
                dateTo: '2026-06-30',
                limit: 100,
                sentiment: 'negative',
                category: 'x',
            ),
        );

        $this->assertTrue($page->hasMoreMentions);
        $this->assertSame('cursor-page-2', $page->cursor);
        $this->assertCount(1, $page->results);
        $this->assertSame('Bad review', $page->results[0]->title);
        $this->assertSame('The product quality is unacceptable.', $page->results[0]->content);
        $this->assertSame(-1, $page->results[0]->sentiment);
        $this->assertSame(['complaint'], $page->results[0]->tags);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/api-data/v1/project/123456789/mentions')
                && $request['date_from'] === '2026-06-01'
                && $request['date_to'] === '2026-06-30'
                && $request['limit'] === 100
                && $request['sentiment'] === 'negative'
                && $request['category'] === 'x';
        });
    }

    #[Test]
    public function it_throws_when_api_key_is_not_configured(): void
    {
        config([
            'brand24.api_key' => '',
            'brand24.base_url' => 'https://api-data.brand24.com',
        ]);

        $this->expectException(Brand24ApiException::class);
        $this->expectExceptionMessage('Brand24 API key is not configured.');

        $this->app->make(Brand24ClientInterface::class)->testConnection();
    }

    #[Test]
    public function it_retries_on_rate_limit_and_server_errors(): void
    {
        $this->configureBrand24Client([
            'brand24.retry.times' => 3,
            'brand24.retry.sleep_ms' => 0,
        ]);

        Http::fake([
            'api-data.brand24.com/api-data/v1/account/mentions-usage-estimation' => Http::sequence()
                ->push(['status' => 'error'], 429)
                ->push(['status' => 'error'], 503)
                ->push([
                    'status' => 'success',
                    'message' => [
                        'mentions_usage_estimation_at_the_end' => 900,
                    ],
                ], 200),
        ]);

        $accountInfo = $this->app->make(Brand24ClientInterface::class)->testConnection();

        $this->assertSame(900, $accountInfo->mentionsUsageEstimationAtTheEnd);
        $this->assertSame(3, count(Http::recorded()));
    }

    #[Test]
    public function brand24_test_command_verifies_connectivity_and_lists_projects(): void
    {
        $this->configureBrand24Client([
            'brand24.account_id' => 123456789,
        ]);

        Http::fake([
            'api-data.brand24.com/api-data/v1/account/mentions-usage-estimation' => Http::response([
                'status' => 'success',
                'message' => [
                    'mentions_usage_estimation_at_the_end' => 14820,
                ],
            ], 200),
            'api-data.brand24.com/api-data/v1/account/123456789/projects_list/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'projects_list' => [
                        '123456789' => 'Brand Monitoring',
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('brand24:test')
            ->expectsOutputToContain('API Connection Status: OK')
            ->expectsOutputToContain('Account Information')
            ->expectsOutputToContain('14820')
            ->expectsOutputToContain('Available Projects')
            ->expectsOutputToContain('Brand Monitoring')
            ->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureBrand24Client(array $overrides = []): void
    {
        config(array_merge([
            'brand24.api_key' => 'test-brand24-api-key',
            'brand24.base_url' => 'https://api-data.brand24.com',
            'brand24.timeout' => 5,
            'brand24.retry.times' => 0,
            'brand24.retry.sleep_ms' => 0,
        ], $overrides));
    }
}
