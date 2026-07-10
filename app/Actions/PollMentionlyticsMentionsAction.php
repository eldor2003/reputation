<?php

namespace App\Actions;

use App\DTO\MentionlyticsIngestData;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\Enums\SourceType;
use App\Exceptions\MentionlyticsApiException;
use App\Models\Source;
use App\Providers\Mentionlytics\MentionlyticsProvider;
use App\Services\IngestIdempotencyKeyBuilder;
use App\Contracts\IngestIdempotencyServiceInterface;
use App\Support\MentionlyticsApiMentionMapper;
use Carbon\Carbon;

class PollMentionlyticsMentionsAction
{
    public function __construct(
        private readonly MentionlyticsProvider $provider,
        private readonly IngestMentionlyticsMentionAction $ingestMentionlyticsMentionAction,
        private readonly IngestIdempotencyKeyBuilder $idempotencyKeyBuilder,
        private readonly IngestIdempotencyServiceInterface $ingestIdempotencyService,
    ) {}

    /**
     * @return array{ingested: int, skipped: int, pages: int}
     */
    public function execute(Source $source, ?MentionlyticsMentionsQueryDTO $query = null): array
    {
        if ($source->type !== SourceType::Mentionlytics) {
            throw new MentionlyticsApiException('Source is not configured for Mentionlytics polling.');
        }

        if (! $source->is_active) {
            throw new MentionlyticsApiException('Mentionlytics source is inactive.');
        }

        $query ??= $this->buildDefaultQuery($source);
        $ingested = 0;
        $skipped = 0;
        $pages = 0;

        do {
            $page = $this->provider->getMentions($query);
            $pages++;

            foreach ($page->mentions as $mention) {
                $payload = MentionlyticsApiMentionMapper::toIngestPayload($mention, $source->uuid);
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

        return [
            'ingested' => $ingested,
            'skipped' => $skipped,
            'pages' => $pages,
        ];
    }

    private function buildDefaultQuery(Source $source): MentionlyticsMentionsQueryDTO
    {
        $lookbackDays = (int) config('mentionlytics.polling.default_lookback_days');
        $perPage = (int) config('mentionlytics.polling.default_per_page');

        /** @var array<string, mixed>|null $config */
        $config = is_array($source->config) ? $source->config : null;

        if (isset($config['lookback_days']) && is_numeric($config['lookback_days'])) {
            $lookbackDays = (int) $config['lookback_days'];
        }

        if (isset($config['per_page']) && is_numeric($config['per_page'])) {
            $perPage = (int) $config['per_page'];
        }

        $commtracks = isset($config['commtracks']) && is_string($config['commtracks'])
            ? $config['commtracks']
            : null;

        return new MentionlyticsMentionsQueryDTO(
            startDate: Carbon::now()->subDays($lookbackDays)->format('Ymd'),
            endDate: Carbon::now()->format('Ymd'),
            perPage: $perPage,
            commtracks: $commtracks,
        );
    }
}
