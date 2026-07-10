<?php

namespace App\Console\Commands;

use App\Actions\IngestBrand24MentionAction;
use App\Contracts\Brand24ClientInterface;
use App\DTO\Brand24IngestData;
use App\DTO\Brand24MentionDTO;
use App\DTO\Brand24MentionsQueryDTO;
use App\Enums\RoutingDeliveryMode;
use App\Enums\SourceType;
use App\Enums\TelegramNotificationStatus;
use App\Events\MentionClassified;
use App\Events\MentionDeduplicated;
use App\Events\MentionNormalized;
use App\Events\MentionRouted;
use App\Events\MentionThreatAssessed;
use App\Exceptions\Brand24ApiException;
use App\Jobs\ProcessMentionJob;
use App\Models\AiResult;
use App\Models\DeliveryDigestItem;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\MentionThreatResult;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use App\Support\Brand24ApiMentionMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class PipelineVerifyE2eCommand extends Command
{
    protected $signature = 'pipeline:verify-e2e {--project-id= : Brand24 project ID to fetch a mention from}';

    protected $description = 'Verify the Phase 2 pipeline end-to-end using real Brand24, Claude, Threat, Routing, and Telegram integrations';

    private float $pipelineStartedAt = 0;

    private ?float $claudeStartedAt = null;

    private ?float $claudeCompletedAt = null;

    /** @var array<string, bool> */
    private array $stageResults = [];

    public function handle(
        Brand24ClientInterface $brand24Client,
        IngestBrand24MentionAction $ingestAction,
    ): int {
        $this->pipelineStartedAt = microtime(true);

        $accountId = config('brand24.account_id');
        $projectId = $this->option('project-id');

        if (! is_numeric($accountId)) {
            $this->components->error('BRAND24_ACCOUNT_ID is not configured.');

            return self::FAILURE;
        }

        try {
            $projectId = $this->resolveProjectId($brand24Client, (int) $accountId);
        } catch (Brand24ApiException $exception) {
            $this->components->error('Failed to resolve Brand24 project: '.$exception->getMessage());

            return self::FAILURE;
        }

        try {
            $brand24Mention = $this->fetchBrand24Mention($brand24Client, $projectId);
        } catch (Brand24ApiException $exception) {
            $this->components->error('Failed to fetch Brand24 mention: '.$exception->getMessage());

            return self::FAILURE;
        }

        $source = $this->resolveBrand24Source($projectId);
        $externalId = 'e2e-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $payload = Brand24ApiMentionMapper::toIngestPayload(
            mention: $brand24Mention,
            externalId: $externalId,
            sourceUuid: $source->uuid,
        );

        $this->registerPipelineEventListeners();

        $ingestAction->execute(new Brand24IngestData(
            sourceUuid: $source->uuid,
            externalId: $externalId,
            idempotencyKey: 'pipeline-verify-'.$externalId,
            payload: $payload,
        ));

        $mention = Mention::query()->where('external_id', $externalId)->first();

        if ($mention === null) {
            $this->components->error('Mention was not created during ingest.');

            return self::FAILURE;
        }

        $this->stageResults['received'] = true;

        $this->claudeStartedAt = microtime(true);
        ProcessMentionJob::dispatchSync($mention->id);

        $mention->refresh();

        return $this->renderReport(
            mention: $mention,
            brand24ExternalId: $externalId,
            brand24ProjectId: $projectId,
        );
    }

    private function resolveProjectId(Brand24ClientInterface $brand24Client, int $accountId): int
    {
        $option = $this->option('project-id');

        if (is_string($option) && $option !== '' && is_numeric($option)) {
            return (int) $option;
        }

        $projects = $brand24Client->getProjects($accountId);

        if ($projects->projects === []) {
            throw new Brand24ApiException('No Brand24 projects available for verification.');
        }

        return (int) $projects->projects[0]->id;
    }

    private function fetchBrand24Mention(
        Brand24ClientInterface $brand24Client,
        int $projectId,
    ): Brand24MentionDTO {
        $page = $brand24Client->getMentions(new Brand24MentionsQueryDTO(
            projectId: $projectId,
            dateFrom: now()->subDays(7)->toDateString(),
            dateTo: now()->toDateString(),
            limit: 25,
        ));

        if ($page->results === []) {
            throw new Brand24ApiException('No Brand24 mentions returned for the selected project and date range.');
        }

        $negativeMention = collect($page->results)
            ->first(fn (Brand24MentionDTO $mention): bool => $mention->sentiment < 0);

        if ($negativeMention instanceof Brand24MentionDTO) {
            return $negativeMention;
        }

        $substantiveMention = collect($page->results)
            ->first(fn (Brand24MentionDTO $mention): bool => is_string($mention->content) && strlen($mention->content) >= 40);

        if ($substantiveMention instanceof Brand24MentionDTO) {
            return $substantiveMention;
        }

        return $page->results[0];
    }

    private function resolveBrand24Source(int $brand24ProjectId): Source
    {
        $project = Project::query()->firstOrCreate(
            ['slug' => 'brand24-production'],
            [
                'name' => 'Brand24 Production',
                'is_active' => true,
            ],
        );

        return Source::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'type' => SourceType::Brand24,
                'external_id' => (string) $brand24ProjectId,
            ],
            [
                'name' => 'Brand24 Project '.$brand24ProjectId,
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
            $this->claudeCompletedAt = microtime(true);
            $this->stageResults['classified'] = true;
        });

        Event::listen(MentionRouted::class, function (): void {
            $this->stageResults['routed'] = true;
        });

        Event::listen(MentionThreatAssessed::class, function (): void {
            $this->stageResults['threat_assessed'] = true;
        });
    }

    private function renderReport(
        Mention $mention,
        string $brand24ExternalId,
        int $brand24ProjectId,
    ): int {
        $aiResult = AiResult::query()->where('mention_id', $mention->id)->latest('processed_at')->first();
        $threatResult = MentionThreatResult::query()->where('mention_id', $mention->id)->latest('assessed_at')->first();
        $route = MentionRoute::query()->where('mention_id', $mention->id)->first();
        $notifications = TelegramNotification::query()->where('mention_id', $mention->id)->get();
        $digestItem = DeliveryDigestItem::query()->where('mention_id', $mention->id)->latest('id')->first();

        $this->stageResults['ai_stored'] = $aiResult !== null;
        $this->stageResults['threat_stored'] = $threatResult !== null;
        $this->stageResults['routing_stored'] = $route !== null;
        $this->stageResults['telegram_sent'] = $notifications->contains(
            fn (TelegramNotification $notification): bool => $notification->status === TelegramNotificationStatus::Sent,
        );
        $this->stageResults['digest_queued'] = $digestItem !== null;

        $requiresTelegram = $route !== null
            && $route->should_notify
            && $route->delivery_mode === RoutingDeliveryMode::Immediate;

        $totalProcessingMs = round((microtime(true) - $this->pipelineStartedAt) * 1000, 2);
        $claudeResponseMs = ($this->claudeStartedAt !== null && $this->claudeCompletedAt !== null)
            ? round(($this->claudeCompletedAt - $this->claudeStartedAt) * 1000, 2)
            : null;

        $this->newLine();
        $this->components->info('Production Pipeline E2E Verification Report');
        $this->newLine();

        $this->renderStage('Mention received', $this->stageResults['received'] ?? false);
        $this->renderStage('Mention normalized', $this->stageResults['normalized'] ?? false);
        $this->renderStage('Mention deduplicated', $this->stageResults['deduplicated'] ?? false);
        $this->renderStage('Claude classification completed', $this->stageResults['classified'] ?? false);
        $this->renderStage('AI result stored', $this->stageResults['ai_stored'] ?? false);
        $this->renderStage('Threat assessment completed', $this->stageResults['threat_assessed'] ?? false);
        $this->renderStage('Threat result stored', $this->stageResults['threat_stored'] ?? false);
        $this->renderStage('Routing stored', $this->stageResults['routing_stored'] ?? false);

        if ($route !== null && $route->delivery_mode === RoutingDeliveryMode::Digest) {
            $this->renderStage('Digest item queued', $this->stageResults['digest_queued'] ?? false);
        }

        if ($requiresTelegram) {
            $this->renderStage('Telegram moderation notification sent', $this->stageResults['telegram_sent'] ?? false);
        } else {
            $this->line('○ Telegram moderation notification skipped (routing policy)');
        }

        $this->newLine();
        $this->components->info('Details');
        $this->components->twoColumnDetail('Brand24 project ID', (string) $brand24ProjectId);
        $this->components->twoColumnDetail('Brand24 mention ID', $brand24ExternalId);
        $this->components->twoColumnDetail('Mention UUID', $mention->uuid);
        $this->components->twoColumnDetail('Mention status', $mention->status->value);
        $this->components->twoColumnDetail('Claude model', $aiResult?->model ?? (string) config('claude.model'));
        $this->components->twoColumnDetail('Claude response time', $claudeResponseMs !== null ? $claudeResponseMs.' ms' : 'n/a');
        $this->components->twoColumnDetail('Total processing time', $totalProcessingMs.' ms');

        if ($aiResult !== null) {
            $this->components->twoColumnDetail('AI sentiment', (string) $aiResult->sentiment);
            $this->components->twoColumnDetail('AI severity', (string) $aiResult->severity);
        }

        if ($route !== null) {
            $this->components->twoColumnDetail('Should notify', $route->should_notify ? 'yes' : 'no');
            $this->components->twoColumnDetail('Routing priority', $route->priority->value);
            $this->components->twoColumnDetail('Delivery mode', $route->delivery_mode?->value ?? 'n/a');
        }

        if ($threatResult !== null) {
            $this->components->twoColumnDetail('Threat level', $threatResult->threat_level->value);
            $this->components->twoColumnDetail('Threat score', (string) $threatResult->threat_score);
        }

        if ($digestItem !== null) {
            $this->components->twoColumnDetail('Digest type queued', $digestItem->digest_type->value);
        }

        if ($notifications->isNotEmpty()) {
            $this->components->twoColumnDetail(
                'Telegram message IDs',
                $notifications
                    ->map(fn (TelegramNotification $notification): string => "{$notification->chat_id}:{$notification->message_id}")
                    ->implode(', '),
            );
        }

        $failedStages = collect([
            'received' => 'Ingest',
            'normalized' => 'Brand24Normalizer',
            'deduplicated' => 'ExactDeduplicationEngine',
            'classified' => 'AnthropicClaudeClient',
            'ai_stored' => 'AiResultStorage',
            'threat_assessed' => 'ThreatEngine',
            'threat_stored' => 'ThreatResultStorage',
            'routing_stored' => 'RoutingEngine',
        ])->filter(fn (string $component, string $stage): bool => ! ($this->stageResults[$stage] ?? false));

        if ($route !== null && $route->delivery_mode === RoutingDeliveryMode::Digest) {
            $failedStages = $failedStages->merge(collect([
                'digest_queued' => 'DigestEngine',
            ])->filter(fn (string $component, string $stage): bool => ! ($this->stageResults[$stage] ?? false)));
        }

        if ($requiresTelegram) {
            $failedStages = $failedStages->merge(collect([
                'telegram_sent' => 'TelegramBotNotifier',
            ])->filter(fn (string $component, string $stage): bool => ! ($this->stageResults[$stage] ?? false)));
        }

        if ($failedStages->isNotEmpty()) {
            $this->newLine();
            $this->components->error('Failed components');
            foreach ($failedStages as $stage => $component) {
                $this->line("- {$component} ({$stage})");
            }

            if ($requiresTelegram && ! ($this->stageResults['telegram_sent'] ?? false)) {
                $this->line('- Telegram moderation notification was expected but not sent.');
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('All pipeline stages verified successfully.');

        return self::SUCCESS;
    }

    private function renderStage(string $label, bool $passed): void
    {
        $this->line(($passed ? '✓' : '✗').' '.$label);
    }
}
