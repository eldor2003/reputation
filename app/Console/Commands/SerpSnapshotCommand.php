<?php

namespace App\Console\Commands;

use App\Actions\TakeSerpSnapshotAction;
use App\DTO\SerpSearchRequestDTO;
use App\Enums\SerpEngine;
use App\Exceptions\SerpApiException;
use App\Services\SerpSnapshotQueryResolver;
use Illuminate\Console\Command;

class SerpSnapshotCommand extends Command
{
    protected $signature = 'serp:snapshot
                            {--engine= : Limit to one engine (google, yandex, bing, baidu)}
                            {--query= : Limit to one search query}';

    protected $description = 'Capture SERP snapshots for all configured engines and queries';

    public function handle(
        TakeSerpSnapshotAction $takeSnapshotAction,
        SerpSnapshotQueryResolver $queryResolver,
    ): int {
        if (! $this->credentialsConfigured()) {
            $this->error('Configure SERPAPI_API_KEY before running scheduled snapshots.');

            return self::FAILURE;
        }

        if (! (bool) config('serpapi.snapshots.enabled', true)) {
            $this->warn('SERP snapshots are disabled in configuration.');

            return self::SUCCESS;
        }

        $engines = $this->resolveEngines();
        $targets = $this->resolveTargets($queryResolver);

        if ($targets === []) {
            $this->warn('No SERP snapshot queries configured.');

            return self::SUCCESS;
        }

        $created = 0;
        $failures = 0;

        foreach ($engines as $engine) {
            foreach ($targets as $target) {
                try {
                    $takeSnapshotAction->execute(
                        new SerpSearchRequestDTO(
                            query: $target['query'],
                            engine: $engine,
                            num: (int) config('serpapi.snapshots.results_per_page', 10),
                        ),
                        $target['person_id'],
                    );
                    $created++;
                } catch (SerpApiException $exception) {
                    $failures++;
                    $this->error(sprintf(
                        '%s / "%s": %s',
                        $engine->value,
                        $target['query'],
                        $exception->getMessage(),
                    ));
                }
            }
        }

        $this->info(sprintf(
            'SERP snapshots complete: created=%d failures=%d',
            $created,
            $failures,
        ));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<SerpEngine>
     */
    private function resolveEngines(): array
    {
        $option = $this->option('engine');

        if (is_string($option) && $option !== '') {
            return [SerpEngine::from($option)];
        }

        /** @var list<string>|string|null $configured */
        $configured = config('serpapi.snapshots.engines');

        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        if (! is_array($configured) || $configured === []) {
            return SerpEngine::cases();
        }

        return array_map(
            fn (string $engine): SerpEngine => SerpEngine::from($engine),
            $configured,
        );
    }

    /**
     * @return list<array{query: string, person_id: int|null}>
     */
    private function resolveTargets(SerpSnapshotQueryResolver $queryResolver): array
    {
        $option = $this->option('query');

        if (is_string($option) && trim($option) !== '') {
            return [[
                'query' => trim($option),
                'person_id' => null,
            ]];
        }

        return $queryResolver->resolve();
    }

    private function credentialsConfigured(): bool
    {
        $apiKey = config('serpapi.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }
}
