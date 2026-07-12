<?php

namespace Tests\Feature\Serp;

use App\Actions\TakeSerpSnapshotAction;
use App\Contracts\SerpApiClientInterface;
use App\DTO\SerpPositionDTO;
use App\DTO\SerpSearchRequestDTO;
use App\DTO\SerpSearchResultDTO;
use App\Enums\SerpEngine;
use App\Exceptions\SerpApiException;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpApiClientTest extends TestCase
{
    #[Test]
    public function it_verifies_api_connectivity_and_returns_account_info(): void
    {
        $this->configureSerpApiClient();

        Http::fake([
            'serpapi.com/account.json*' => Http::response([
                'account_id' => 'acc-123',
                'plan_name' => 'Developer Plan',
                'searches_per_month' => 5000,
                'plan_searches_left' => 4200,
                'total_searches_left' => 4200,
                'this_month_usage' => 800,
                'account_rate_limit_per_hour' => 1000,
            ], 200),
        ]);

        $accountInfo = $this->app->make(SerpApiClientInterface::class)->testConnection();

        $this->assertSame('acc-123', $accountInfo->accountId);
        $this->assertSame(4200, $accountInfo->planSearchesLeft);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/account.json')
                && str_contains($request->url(), 'api_key=test-serpapi-key');
        });
    }

    #[Test]
    public function it_performs_a_search_and_maps_organic_results(): void
    {
        $this->configureSerpApiClient();

        Http::fake([
            'serpapi.com/search.json*' => Http::response([
                'search_metadata' => [
                    'id' => 'search-123',
                    'status' => 'Success',
                    'total_time_taken' => 1.23,
                    'raw_html_file' => 'https://serpapi.com/searches/raw.html',
                ],
                'organic_results' => [
                    [
                        'position' => 1,
                        'title' => 'Reputation Monitoring Guide',
                        'link' => 'https://example.com/reputation',
                        'snippet' => 'Learn about reputation monitoring.',
                    ],
                ],
            ], 200),
        ]);

        $result = $this->app->make(SerpApiClientInterface::class)->search(
            new SerpSearchRequestDTO(
                query: 'reputation monitoring',
                engine: SerpEngine::Google,
            ),
        );

        $this->assertSame('search-123', $result->serpApiSearchId);
        $this->assertCount(1, $result->positions);
        $this->assertSame(1, $result->positions[0]->position);
        $this->assertSame('Reputation Monitoring Guide', $result->positions[0]->title);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/search.json')
                && $request['engine'] === 'google'
                && $request['q'] === 'reputation monitoring';
        });
    }

    #[Test]
    public function it_supports_multiple_search_engines(): void
    {
        $this->configureSerpApiClient();

        Http::fake([
            'serpapi.com/search.json*' => Http::response([
                'search_metadata' => ['id' => 'search-yandex', 'status' => 'Success'],
                'organic_results' => [
                    ['position' => 1, 'title' => 'Yandex result', 'link' => 'https://yandex.example', 'snippet' => 'text'],
                ],
            ], 200),
        ]);

        $result = $this->app->make(SerpApiClientInterface::class)->search(
            new SerpSearchRequestDTO('coffee', SerpEngine::Yandex),
        );

        Http::assertSent(fn ($request): bool => $request['engine'] === 'yandex'
            && $request['text'] === 'coffee');
        $this->assertSame(SerpEngine::Yandex, $result->engine);
    }

    #[Test]
    public function it_throws_when_api_key_is_not_configured(): void
    {
        config(['serpapi.api_key' => '']);

        $this->expectException(SerpApiException::class);
        $this->expectExceptionMessage('SerpApi API key is not configured.');

        $this->app->make(SerpApiClientInterface::class)->testConnection();
    }

    #[Test]
    public function serp_test_command_verifies_connectivity_and_displays_positions(): void
    {
        $this->configureSerpApiClient();

        Http::fake([
            'serpapi.com/account.json*' => Http::response([
                'account_id' => 'acc-123',
                'plan_name' => 'Developer Plan',
                'searches_per_month' => 5000,
                'plan_searches_left' => 4200,
                'total_searches_left' => 4200,
                'this_month_usage' => 800,
                'account_rate_limit_per_hour' => 1000,
            ], 200),
            'serpapi.com/search.json*' => Http::response([
                'search_metadata' => ['id' => 'search-123', 'status' => 'Success'],
                'organic_results' => [
                    ['position' => 1, 'title' => 'Result 1', 'link' => 'https://example.com/1', 'snippet' => 'Snippet 1'],
                ],
            ], 200),
        ]);

        $this->artisan('serp:test')
            ->expectsOutputToContain('API Connection Status: OK')
            ->expectsOutputToContain('Top Positions')
            ->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureSerpApiClient(array $overrides = []): void
    {
        config(array_merge([
            'serpapi.api_key' => 'test-serpapi-key',
            'serpapi.base_url' => 'https://serpapi.com',
            'serpapi.timeout' => 5,
            'serpapi.retry.times' => 0,
            'serpapi.retry.sleep_ms' => 0,
            'serpapi.test.query' => 'reputation monitoring',
            'serpapi.test.engine' => 'google',
        ], $overrides));
    }
}
