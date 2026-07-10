<?php

namespace App\Console\Commands;

use App\Actions\IngestMentionlyticsMentionAction;
use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsIngestData;
use App\DTO\MentionlyticsMentionDTO;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Enums\TelegramNotificationStatus;
use App\Events\MentionClassified;
use App\Events\MentionDeduplicated;
use App\Events\MentionNormalized;
use App\Events\MentionRouted;
use App\Exceptions\MentionlyticsApiException;
use App\Jobs\ProcessMentionJob;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use App\Support\MentionlyticsApiMentionMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class MentionlyticsTestCommand extends Command
{
    protected $signature = 'mentionlytics:test {--pipeline : Run one real mention through the full pipeline}';

    protected $description = 'Verify Mentionlytics API connectivity and optionally run a live pipeline check';

    /** @var array<string, bool> */
    private array $stageResults = [];

    public function handle(
        MentionlyticsClientInterface $client,
        IngestMentionlyticsMentionAction $ingestAction,
    ): int {
        if (! $this->credentialsConfigured()) {
            $this->components->error('API Connection Status: FAILED');
            $this->line('Configure MENTIONLYTICS_BEARER_TOKEN or MENTIONLYTICS_REFRESH_TOKEN in .env.');

            return self::FAILURE;
        }

        try {
            $verification = $client->verify();
        } catch (MentionlyticsApiException $exception) {
            $this->components->error('API Connection Status: FAILED');
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderVerificationReport($verification);

        if ($this->option('pipeline')) {
            if ($verification->mentionsOnPage === 0) {
                $this->newLine();
                $this->components->warn('Pipeline verification skipped: no mentions returned for the configured date range.');
                $this->line('Authentication, endpoint access, pagination, parsing, and normalization can still be verified once mentions exist in Mentionlytics.');

                return self::SUCCESS;
            }

            return $this->runPipelineVerification($client, $ingestAction);
        }

        return self::SUCCESS;
    }

    private function credentialsConfigured(): bool
    {
        $bearer = config('mentionlytics.bearer_token');
        $refresh = config('mentionlytics.refresh_token');

        return (is_string($bearer) && $bearer !== '')
            || (is_string($refresh) && $refresh !== '');
    }

    private function renderVerificationReport(\App\DTO\MentionlyticsVerificationResultDTO $verification): void
    {
        $this->components->info('✓ Mentionlytics Connected');
        $this->newLine();

        $this->components->info('API Connection Status: OK');
        $this->components->twoColumnDetail('Authentication method', $verification->authenticationMethod);
        $this->components->twoColumnDetail('Token refresh used', $verification->tokenRefreshUsed ? 'yes' : 'no');

        $this->newLine();
        $this->components->info('Account Information');
        $this->components->twoColumnDetail('Query start date', $verification->queryStartDate);
        $this->components->twoColumnDetail('Query end date', $verification->queryEndDate);
        $this->components->twoColumnDetail('API base URL', (string) config('mentionlytics.base_url'));

        $this->newLine();
        $this->components->info('Mentions');
        $this->components->twoColumnDetail('Mentions on page', (string) $verification->mentionsOnPage);
        $this->components->twoColumnDetail(
            'Total mentions in period',
            $verification->totalMentionsInPeriod !== null
                ? (string) $verification->totalMentionsInPeriod
                : 'n/a',
        );
        $this->components->twoColumnDetail(
            'Last mention timestamp',
            $verification->lastMentionTimestamp ?? 'n/a',
        );
        $this->components->twoColumnDetail(
            'Last mention ID',
            $verification->lastMentionId ?? 'n/a',
        );

        $this->newLine();
        $this->components->info('Pagination');
        $this->components->twoColumnDetail('More pages available', $verification->hasMorePages ? 'yes' : 'no');
        $this->components->twoColumnDetail(
            'Pagination cursor',
            $verification->paginationCursor ?? 'n/a',
        );
        $this->components->twoColumnDetail(
            'Pagination verified',
            $verification->paginationVerified ? 'yes' : ($verification->hasMorePages ? 'no' : 'not required'),
        );

        $this->newLine();
        $this->components->warn('Mentionlytics does not provide webhooks. Use PollMentionlyticsMentionsAction for ingestion.');
    }

    private function runPipelineVerification(
        MentionlyticsClientInterface $client,
        IngestMentionlyticsMentionAction $ingestAction,
    ): int {
        $lookbackDays = (int) config('mentionlytics.polling.default_lookback_days');
        $perPage = (int) config('mentionlytics.polling.default_per_page');

        $page = $client->getMentions(new MentionlyticsMentionsQueryDTO(
            startDate: now()->subDays($lookbackDays)->format('Ymd'),
            endDate: now()->format('Ymd'),
            perPage: $perPage,
        ));

        if ($page->mentions === []) {
            $this->components->error('No mentions available for pipeline verification.');

            return self::FAILURE;
        }

        $mention = $this->selectMentionForPipeline($page->mentions);
        $source = $this->resolveMentionlyticsSource();
        $externalId = 'ml-e2e-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $payload = MentionlyticsApiMentionMapper::toIngestPayload($mention, $source->uuid);
        $payload['mention_id'] = $externalId;
        $payload['content'] = $mention->text;

        $this->registerPipelineEventListeners();

        $ingestAction->execute(new MentionlyticsIngestData(
            sourceUuid: $source->uuid,
            externalId: $externalId,
            idempotencyKey: 'mentionlytics-test-'.$externalId,
            payload: $payload,
        ));

        $storedMention = Mention::query()->where('external_id', $externalId)->first();

        if ($storedMention === null) {
            $this->components->error('Mention was not created during ingest.');

            return self::FAILURE;
        }

        $this->stageResults['received'] = true;

        ProcessMentionJob::dispatchSync($storedMention->id);
        $storedMention->refresh();

        return $this->renderPipelineReport($storedMention, $externalId);
    }

    /**
     * @param  list<MentionlyticsMentionDTO>  $mentions
     */
    private function selectMentionForPipeline(array $mentions): MentionlyticsMentionDTO
    {
        $negativeMention = collect($mentions)
            ->first(fn (MentionlyticsMentionDTO $mention): bool => $mention->sentiment === 'negative');

        if ($negativeMention instanceof MentionlyticsMentionDTO) {
            return $negativeMention;
        }

        $substantiveMention = collect($mentions)
            ->first(fn (MentionlyticsMentionDTO $mention): bool => strlen($mention->text) >= 40);

        if ($substantiveMention instanceof MentionlyticsMentionDTO) {
            return $substantiveMention;
        }

        return $mentions[0];
    }

    private function resolveMentionlyticsSource(): Source
    {
        $project = Project::query()->firstOrCreate(
            ['slug' => 'mentionlytics-local'],
            [
                'name' => 'Mentionlytics Local',
                'is_active' => true,
            ],
        );

        return Source::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'type' => SourceType::Mentionlytics,
                'external_id' => 'mentionlytics-default',
            ],
            [
                'name' => 'Mentionlytics Default Source',
                'is_active' => true,
            ],
        );
    }

    private function registerPipelineEventListeners(): void
    {
        Event::listen(MentionNormalized::class, function (): void {
            $this->stageResults['normalized'] = true;
        });

        Event::listen(MentionDeduplicated::class, function (): void {
            $this->stageResults['deduplicated'] = true;
        });

        Event::listen(MentionClassified::class, function (): void {
            $this->stageResults['classified'] = true;
        });

        Event::listen(MentionRouted::class, function (): void {
            $this->stageResults['routed'] = true;
        });
    }

    private function renderPipelineReport(Mention $mention, string $externalId): int
    {
        $aiResult = AiResult::query()->where('mention_id', $mention->id)->latest('processed_at')->first();
        $route = MentionRoute::query()->where('mention_id', $mention->id)->first();
        $notifications = TelegramNotification::query()->where('mention_id', $mention->id)->get();

        $this->stageResults['ai_stored'] = $aiResult !== null;
        $this->stageResults['routing_stored'] = $route !== null;
        $this->stageResults['telegram_sent'] = $notifications->contains(
            fn (TelegramNotification $notification): bool => $notification->status === TelegramNotificationStatus::Sent,
        );

        $this->newLine();
        $this->components->info('Pipeline Verification Report');
        $this->renderStage('Mention ingested', $this->stageResults['received'] ?? false);
        $this->renderStage('Mention normalized', $this->stageResults['normalized'] ?? false);
        $this->renderStage('Mention deduplicated', $this->stageResults['deduplicated'] ?? false);
        $this->renderStage('Claude classification completed', $this->stageResults['classified'] ?? false);
        $this->renderStage('AI result stored', $this->stageResults['ai_stored'] ?? false);
        $this->renderStage('Routing stored', $this->stageResults['routing_stored'] ?? false);
        $this->renderStage('Telegram notification sent', $this->stageResults['telegram_sent'] ?? false);

        $this->newLine();
        $this->components->info('Pipeline Details');
        $this->components->twoColumnDetail('External mention ID', $externalId);
        $this->components->twoColumnDetail('Mention UUID', $mention->uuid);
        $this->components->twoColumnDetail('Mention status', $mention->status->value);

        if ($aiResult !== null) {
            $this->components->twoColumnDetail('AI sentiment', (string) $aiResult->sentiment);
        }

        $failedStages = collect([
            'received' => 'Ingest',
            'normalized' => 'MentionlyticsNormalizer',
            'deduplicated' => 'ExactDeduplicationEngine',
            'classified' => 'AnthropicClaudeClient',
            'ai_stored' => 'AiResultStorage',
            'routing_stored' => 'RoutingEngine',
            'telegram_sent' => 'TelegramBotNotifier',
        ])->filter(fn (string $component, string $stage): bool => ! ($this->stageResults[$stage] ?? false));

        if ($failedStages->isNotEmpty()) {
            $this->newLine();
            $this->components->error('Failed pipeline stages');

            foreach ($failedStages as $stage => $component) {
                $this->line("- {$component} ({$stage})");
            }

            if ($mention->status === MentionStatus::Failed) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->components->info('Mentionlytics pipeline verification completed.');

        return self::SUCCESS;
    }

    private function renderStage(string $label, bool $passed): void
    {
        $this->line(($passed ? '✓' : '✗').' '.$label);
    }
}
