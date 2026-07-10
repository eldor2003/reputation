<?php

namespace App\Console\Commands;

use App\Actions\GenerateProjectDigestAction;
use App\Enums\DigestType;
use Illuminate\Console\Command;

class GenerateDeliveryDigestCommand extends Command
{
    protected $signature = 'delivery:generate-digest
                            {type : Digest type: morning, evening, or manual}
                            {--project= : Limit digest generation to a project ID}';

    protected $description = 'Generate and send a delivery digest for one or all active projects';

    public function handle(GenerateProjectDigestAction $action): int
    {
        $digestType = DigestType::tryFrom($this->argument('type'));

        if ($digestType === null) {
            $this->error('Invalid digest type. Use morning, evening, or manual.');

            return self::FAILURE;
        }

        $projectId = $this->option('project');
        $parsedProjectId = is_numeric($projectId) ? (int) $projectId : null;

        $result = $action->execute($digestType, $parsedProjectId);

        if (! $result->success) {
            $this->error($result->errorMessage ?? 'Digest generation failed.');

            return self::FAILURE;
        }

        if ($result->digest === null) {
            $this->info('No queued digest items found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Digest #%d sent with %d items.',
            $result->digest->id,
            $result->digest->item_count,
        ));

        return self::SUCCESS;
    }
}
