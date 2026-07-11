<?php

namespace App\Actions;

use App\DTO\MentionlyticsIngestData;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\DTO\MentionlyticsPollingCheckpointDTO;
use App\Enums\MentionlyticsPollMode;
use App\Enums\SourceType;
use App\Exceptions\MentionlyticsApiException;
use App\Models\Source;
use App\Providers\Mentionlytics\MentionlyticsProvider;
use App\Services\IngestIdempotencyKeyBuilder;
use App\Contracts\IngestIdempotencyServiceInterface;
use App\Services\Mentionlytics\MentionlyticsPollingCheckpointService;
use App\Support\MentionlyticsApiMentionMapper;
use Carbon\Carbon;

class PollMentionlyticsMentionsAction
{
    public function __construct(
        private readonly MentionlyticsProvider $provider,
        private readonly IngestMentionlyticsMentionAction $ingestMentionlyticsMentionAction,
        private readonly IngestIdempotencyKeyBuilder $idempotencyKeyBuilder,
        private readonly IngestIdempotencyServiceInterface $ingestIdempotencyService,
        private readonly MentionlyticsPollingCheckpointService $checkpointService,
    ) {}

    /**
     * @return array{
     *     ingested: int,
     *     skipped: int,
     *     skipped_checkpoint: int,
     *     pages: int,
     *     mode: string,
     *     checkpoint_established: bool
     * }
     */
    public function execute(Source $source, ?MentionlyticsMentionsQueryDTO $query = null): array
    {
        if ($source->type !== SourceType::Mentionlytics) {
            throw new MentionlyticsApiException('Source is not configured for Mentionlytics polling.');
        }

        if (! $source->is_active) {
            throw new MentionlyticsApiException('Mentionlytics source is inactive.');
        }

        $checkpointEstablished = false;

        if (! $this->checkpointService->hasCheckpoint($source)) {
            $checkpointEstablished = $this->checkpointService->establishFromExistingMentions($source);
        }

        $mode = $this->checkpointService->hasCheckpoint($source)
            ? MentionlyticsPollMode::Incremental
            : MentionlyticsPollMode::Bootstrap;

        $filterCheckpoint = $this->checkpointService->get($source);

        if ($mode === MentionlyticsPollMode::Incremental && $filterCheckpoint === null) {
            throw new MentionlyticsApiException('Mentionlytics incremental poll is missing a checkpoint.');
        }

        $runningCheckpoint = $filterCheckpoint
            ?? new MentionlyticsPollingCheckpointDTO(
                lastProcessedAt: '1970-01-01T00:00:00+00:00',
                lastProcessedMentionId: '',
            );

        $query ??= $this->buildQuery(
            $source,
            $mode,
            $filterCheckpoint ?? $runningCheckpoint,
        );
        $ingested = 0;
        $skipped = 0;
        $skippedCheckpoint = 0;
        $pages = 0;

        do {
            $page = $this->provider->getMentions($query);
            $pages++;

            foreach ($page->mentions as $mention) {
                $runningCheckpoint = $this->checkpointService->advanceCheckpoint($runningCheckpoint, $mention);

                if ($mode === MentionlyticsPollMode::Incremental
                    && $filterCheckpoint !== null
                    && ! $this->checkpointService->isNewerThanCheckpoint($mention, $filterCheckpoint)) {
                    $skippedCheckpoint++;

                    continue;
                }

                $payload = MentionlyticsApiMentionMapper::toIngestPayload($mention, $source->uuid);

                if ($mode === MentionlyticsPollMode::Bootstrap) {
                    $payload['suppress_notifications'] = true;
                    $payload['mentionlytics_poll_mode'] = MentionlyticsPollMode::Bootstrap->value;
                }

                $externalId = (string) ($payload['mention_id'] ?? $mention->id);
                $idempotencyKey = $this->idempotencyKeyBuilder->build(
                    SourceType::Mentionlytics,
                    $source->uuid,
                    $externalId,
                );

                if ($this->ingestIdempotencyService->exists($idempotencyKey)) {
                    $skipped++;

                    continue;
                }

                $this->ingestMentionlyticsMentionAction->execute(new MentionlyticsIngestData(
                    sourceUuid: $source->uuid,
                    externalId: $externalId,
                    idempotencyKey: null,
                    payload: $payload,
                ));

                $ingested++;
            }

            if (! $page->hasMore || $page->resultsAfter === null) {
                break;
            }

            $query = new MentionlyticsMentionsQueryDTO(
                startDate: $query->startDate,
                endDate: $query->endDate,
                pageNo: $query->pageNo,
                perPage: $query->perPage,
                resultsAfter: $page->resultsAfter,
                sentiment: $query->sentiment,
                channels: $query->channels,
                commtracks: $query->commtracks,
                country: $query->country,
                language: $query->language,
            );
        } while ($page->hasMore);

        if ($mode === MentionlyticsPollMode::Bootstrap) {
            $runningCheckpoint = $this->checkpointService->markBootstrapCompleted($runningCheckpoint);
        }

        $this->checkpointService->save($source, $runningCheckpoint);

        return [
            'ingested' => $ingested,
            'skipped' => $skipped,
            'skipped_checkpoint' => $skippedCheckpoint,
            'pages' => $pages,
            'mode' => $mode->value,
            'checkpoint_established' => $checkpointEstablished,
        ];
    }

    private function buildQuery(
        Source $source,
        MentionlyticsPollMode $mode,
        MentionlyticsPollingCheckpointDTO $checkpoint,
    ): MentionlyticsMentionsQueryDTO {
        $perPage = (int) config('mentionlytics.polling.default_per_page');

        /** @var array<string, mixed>|null $config */
        $config = is_array($source->config) ? $source->config : null;

        if (isset($config['per_page']) && is_numeric($config['per_page'])) {
            $perPage = (int) $config['per_page'];
        }

        $commtracks = isset($config['commtracks']) && is_string($config['commtracks'])
            ? $config['commtracks']
            : null;

        if ($mode === MentionlyticsPollMode::Incremental) {
            $startDate = Carbon::parse($checkpoint->lastProcessedAt)->format('Ymd');
            $endDate = Carbon::now()->format('Ymd');
        } else {
            $bootstrapLookbackDays = (int) config('mentionlytics.polling.bootstrap_lookback_days');
            $startDate = Carbon::now()->subDays($bootstrapLookbackDays)->format('Ymd');
            $endDate = Carbon::now()->format('Ymd');
        }

        return new MentionlyticsMentionsQueryDTO(
            startDate: $startDate,
            endDate: $endDate,
            perPage: $perPage,
            commtracks: $commtracks,
        );
    }
}
