<?php

namespace App\Console\Commands;

use App\Contracts\SerpApiClientInterface;
use App\DTO\SerpSearchRequestDTO;
use App\Enums\SerpEngine;
use App\Exceptions\SerpApiException;
use Illuminate\Console\Command;

class SerpTestCommand extends Command
{
    protected $signature = 'serp:test {--engine= : Search engine (google, yandex, bing, baidu)} {--query= : Search query}';

    protected $description = 'Verify SerpApi connectivity and perform a sample SERP search';

    public function handle(SerpApiClientInterface $client): int
    {
        if (! $this->credentialsConfigured()) {
            $this->components->error('API Connection Status: FAILED');
            $this->line('Configure SERPAPI_API_KEY in .env.');

            return self::FAILURE;
        }

        try {
            $accountInfo = $client->testConnection();
        } catch (SerpApiException $exception) {
            $this->components->error('API Connection Status: FAILED');
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('✓ SerpApi Connected');
        $this->newLine();
        $this->components->info('API Connection Status: OK');

        $this->newLine();
        $this->components->info('Account Information');
        $this->components->twoColumnDetail('Plan', $accountInfo->planName);
        $this->components->twoColumnDetail('Monthly quota', (string) $accountInfo->searchesPerMonth);
        $this->components->twoColumnDetail('Searches left (plan)', (string) $accountInfo->planSearchesLeft);
        $this->components->twoColumnDetail('Total searches left', (string) $accountInfo->totalSearchesLeft);
        $this->components->twoColumnDetail('This month usage', (string) $accountInfo->thisMonthUsage);
        $this->components->twoColumnDetail('Hourly rate limit', (string) $accountInfo->accountRateLimitPerHour);

        $engine = $this->resolveEngine();
        $query = $this->resolveQuery();

        try {
            $searchResult = $client->search(new SerpSearchRequestDTO(
                query: $query,
                engine: $engine,
                num: 10,
            ));
        } catch (SerpApiException $exception) {
            $this->newLine();
            $this->components->error('Sample search failed.');
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Sample Search');
        $this->components->twoColumnDetail('Engine', $engine->label());
        $this->components->twoColumnDetail('Query', $query);
        $this->components->twoColumnDetail('SerpApi search ID', $searchResult->serpApiSearchId);
        $this->components->twoColumnDetail('Response time', $searchResult->responseTimeMs.' ms');
        $this->components->twoColumnDetail('Organic results', (string) count($searchResult->positions));

        if ($searchResult->positions === []) {
            $this->components->warn('No organic results returned for this query.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info('Top Positions');

        $rows = array_map(
            fn ($position): array => [
                $position->position,
                mb_substr($position->title, 0, 60),
                mb_substr($position->url, 0, 80),
            ],
            array_slice($searchResult->positions, 0, 10),
        );

        $this->table(['Position', 'Title', 'URL'], $rows);

        return self::SUCCESS;
    }

    private function credentialsConfigured(): bool
    {
        $apiKey = config('serpapi.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    private function resolveEngine(): SerpEngine
    {
        $option = $this->option('engine');
        $configured = config('serpapi.test.engine');

        $value = is_string($option) && $option !== ''
            ? $option
            : (is_string($configured) ? $configured : SerpEngine::Google->value);

        return SerpEngine::from($value);
    }

    private function resolveQuery(): string
    {
        $option = $this->option('query');
        $configured = config('serpapi.test.query');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        return is_string($configured) && $configured !== ''
            ? $configured
            : 'reputation monitoring';
    }
}
