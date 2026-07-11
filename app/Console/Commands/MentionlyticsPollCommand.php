<?php

namespace App\Console\Commands;

use App\Actions\PollMentionlyticsMentionsAction;
use App\Enums\SourceType;
use App\Exceptions\MentionlyticsApiException;
use App\Models\Source;
use Illuminate\Console\Command;

class MentionlyticsPollCommand extends Command
{
    protected $signature = 'mentionlytics:poll {--source= : Limit polling to a source ID}';

    protected $description = 'Poll Mentionlytics for new mentions across all active sources';

    public function handle(PollMentionlyticsMentionsAction $pollAction): int
    {
        $sources = $this->resolveSources();

        if ($sources->isEmpty()) {
            $this->warn('No active Mentionlytics sources found.');

            return self::SUCCESS;
        }

        $totalIngested = 0;
        $totalSkipped = 0;
        $failures = 0;

        foreach ($sources as $source) {
            try {
                $result = $pollAction->execute($source);
            } catch (MentionlyticsApiException $exception) {
                $failures++;
                $this->error(sprintf(
                    'Source #%d (%s): %s',
                    $source->id,
                    $source->name,
                    $exception->getMessage(),
                ));

                continue;
            }

            $totalIngested += $result['ingested'];
            $totalSkipped += $result['skipped'];

            $this->line(sprintf(
                'Source #%d (%s): mode=%s ingested=%d skipped=%d skipped_checkpoint=%d pages=%d%s',
                $source->id,
                $source->name,
                $result['mode'],
                $result['ingested'],
                $result['skipped'],
                $result['skipped_checkpoint'],
                $result['pages'],
                $result['checkpoint_established'] ? ' checkpoint_established=1' : '',
            ));
        }

        $this->info(sprintf(
            'Polling complete: ingested=%d skipped=%d failures=%d',
            $totalIngested,
            $totalSkipped,
            $failures,
        ));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Source>
     */
    private function resolveSources()
    {
        $sourceId = $this->option('source');

        $query = Source::query()
            ->where('type', SourceType::Mentionlytics)
            ->where('is_active', true);

        if (is_numeric($sourceId)) {
            $query->whereKey((int) $sourceId);
        }

        return $query->get();
    }
}
