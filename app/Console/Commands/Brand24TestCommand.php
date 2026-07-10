<?php

namespace App\Console\Commands;

use App\Contracts\Brand24ClientInterface;
use App\Exceptions\Brand24ApiException;
use Illuminate\Console\Command;

class Brand24TestCommand extends Command
{
    protected $signature = 'brand24:test {--account-id= : Brand24 account ID used to list projects}';

    protected $description = 'Verify Brand24 API connectivity and display account projects';

    public function handle(Brand24ClientInterface $client): int
    {
        try {
            $accountInfo = $client->testConnection();
        } catch (Brand24ApiException $exception) {
            $this->components->error('API Connection Status: FAILED');
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('API Connection Status: OK');
        $this->newLine();

        $this->components->info('Account Information');
        $this->components->twoColumnDetail(
            'Projected mentions usage (end of billing period)',
            (string) $accountInfo->mentionsUsageEstimationAtTheEnd,
        );

        $accountId = $this->resolveAccountId();

        if ($accountId === null) {
            $this->newLine();
            $this->components->warn('Available Projects: skipped.');
            $this->line('Set BRAND24_ACCOUNT_ID in .env or pass --account-id.');
            $this->line('Find your account ID in Brand24: Settings → Integrations → API Data.');

            return self::SUCCESS;
        }

        try {
            $projects = $client->getProjects($accountId);
        } catch (Brand24ApiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Available Projects');

        if ($projects->projects === []) {
            $this->line('No projects found.');

            return self::SUCCESS;
        }

        $rows = array_map(
            fn ($project): array => [$project->id, $project->name],
            $projects->projects,
        );

        $this->table(['Project ID', 'Name'], $rows);

        return self::SUCCESS;
    }

    private function resolveAccountId(): ?int
    {
        $option = $this->option('account-id');

        if (is_string($option) && $option !== '' && is_numeric($option)) {
            return (int) $option;
        }

        $configuredAccountId = config('brand24.account_id');

        if (is_numeric($configuredAccountId)) {
            return (int) $configuredAccountId;
        }

        return null;
    }
}
