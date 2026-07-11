<?php

namespace App\Providers;

use App\Contracts\AiResultStorageInterface;
use App\Contracts\Brand24ClientInterface;
use App\Contracts\ClaudeClientInterface;
use App\Contracts\ClaudeStructuredOutputInterface;
use App\Contracts\PromptInjectionGuardInterface;
use App\Contracts\ToolExecutorInterface;
use App\Contracts\LLMCascadeInterface;
use App\Contracts\LLMDecisionStrategyInterface;
use App\Contracts\DeduplicationEngineInterface;
use App\Contracts\FuzzyMatchingStrategyInterface;
use App\Contracts\MentionClusterBuilderInterface;
use App\Contracts\MentionClusterRepositoryInterface;
use App\Contracts\SimilarityCalculatorInterface;
use App\Contracts\MentionlyticsAuthServiceInterface;
use App\Contracts\MentionlyticsClientInterface;
use App\Contracts\MentionlyticsRateLimiterInterface;
use App\Contracts\MentionlyticsRefreshServiceInterface;
use App\Contracts\MentionlyticsResponseCacheInterface;
use App\Contracts\MentionlyticsTokenStorageInterface;
use App\Contracts\SerpApiClientInterface;
use App\Contracts\SerpScreenshotCaptureInterface;
use App\Contracts\SerpScreenshotStorageInterface;
use App\Contracts\SerpSnapshotRepositoryInterface;
use App\Contracts\MentionRouteStorageInterface;
use App\Contracts\MentionRouterInterface;
use App\Contracts\PromptBuilderInterface;
use App\Contracts\ProviderFactoryInterface;
use App\Contracts\IngestIdempotencyServiceInterface;
use App\Contracts\PersonRepositoryInterface;
use App\Contracts\PersonResolverInterface;
use App\Contracts\ModerationLogStorageInterface;
use App\Contracts\TelegramNotificationStorageInterface;
use App\Contracts\TelegramNotifierInterface;
use App\Contracts\ThreatContextBuilderInterface;
use App\Contracts\ThreatEngineInterface;
use App\Contracts\ThreatFactorScorerInterface;
use App\Contracts\ThreatFactorWeightRepositoryInterface;
use App\Contracts\ThreatResultStorageInterface;
use App\Contracts\ThreatRuleRepositoryInterface;
use App\Contracts\RoutingConditionMatcherInterface;
use App\Contracts\RoutingContextBuilderInterface;
use App\Contracts\RoutingEngineInterface;
use App\Contracts\RoutingRuleRepositoryInterface;
use App\Contracts\DeliveryCardBuilderInterface;
use App\Contracts\DeliveryContextBuilderInterface;
use App\Contracts\DeliveryDigestStorageInterface;
use App\Contracts\DeliveryEngineInterface;
use App\Contracts\DeliveryMessageStorageInterface;
use App\Contracts\DigestEngineInterface;
use App\Contracts\TelegramDestinationNotifierInterface;
use App\Factories\ProviderFactory;
use App\Interfaces\MentionIngestStorageInterface;
use App\Interfaces\SourceResolverInterface;
use App\Services\AiResultStorage;
use App\Repositories\MentionClusterRepository;
use App\Repositories\PersonRepository;
use App\Repositories\SerpSnapshotRepository;
use App\Repositories\ThreatFactorWeightRepository;
use App\Repositories\RoutingRuleRepository;
use App\Repositories\ThreatRuleRepository;
use App\Services\Brand24ApiClient;
use App\Services\LocalSerpScreenshotStorage;
use App\Services\S3SerpScreenshotStorage;
use App\Services\SerpApiScreenshotCapture;
use App\Services\MentionlyticsApiClient;
use App\Services\Mentionlytics\MentionlyticsAuthService;
use App\Services\Mentionlytics\DatabaseMentionlyticsTokenStorage;
use App\Services\Mentionlytics\MentionlyticsHttpTransport;
use App\Services\Mentionlytics\MentionlyticsRateLimiter;
use App\Services\Mentionlytics\MentionlyticsRefreshService;
use App\Services\Mentionlytics\MentionlyticsResponseCache;
use App\Services\MentionlyticsTokenManager;
use App\Services\SerpApiClient;
use App\Services\AnthropicClaudeClient;
use App\Services\Classification\CascadeTierEscalator;
use App\Services\Classification\ClaudeStructuredOutputService;
use App\Services\Classification\NullToolExecutor;
use App\Services\Classification\PromptInjectionGuard;
use App\Services\Cascade\ClaudeHaikuAdapter;
use App\Services\Cascade\ClaudeOpusAdapter;
use App\Services\Cascade\ClaudeSonnetAdapter;
use App\Services\Cascade\ConfigurableLlmDecisionStrategy;
use App\Services\Cascade\LlmCascadeEngine;
use App\Services\Cascade\LlmCostCalculator;
use App\Services\Deduplication\MentionClusterBuilder;
use App\Services\Deduplication\MentionSimilarityCalculator;
use App\Services\Deduplication\MinHashMatchingStrategy;
use App\Services\Deduplication\SimHashMatchingStrategy;
use App\Services\ExactDeduplicationEngine;
use App\Services\FuzzyDeduplicationEngine;
use App\Services\IngestIdempotencyService;
use App\Services\MentionIngestStorage;
use App\Services\MentionPromptBuilder;
use App\Services\MentionRouteStorage;
use App\Services\ModerationLogStorage;
use App\Services\PersonResolver;
use App\Services\Delivery\DeliveryCardBuilder;
use App\Services\Delivery\DeliveryContextBuilder;
use App\Services\Delivery\DeliveryEngine;
use App\Services\Delivery\DigestEngine;
use App\Services\DeliveryDigestStorage;
use App\Services\DeliveryMessageStorage;
use App\Services\TelegramDestinationNotifier;
use App\Services\Routing\RoutingConditionMatcher;
use App\Services\Routing\RoutingContextBuilder;
use App\Services\Routing\RoutingEngine;
use App\Services\SourceResolver;
use App\Services\TelegramBotNotifier;
use App\Services\TelegramNotificationStorage;
use App\Services\Threat\ConfigurableThreatFactorScorer;
use App\Services\Threat\ThreatContextBuilder;
use App\Services\Threat\ThreatEngine;
use App\Services\ThreatResultStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MentionIngestStorageInterface::class, MentionIngestStorage::class);
        $this->app->bind(IngestIdempotencyServiceInterface::class, IngestIdempotencyService::class);
        $this->app->bind(SourceResolverInterface::class, SourceResolver::class);
        $this->app->bind(DeduplicationEngineInterface::class, function ($app): DeduplicationEngineInterface {
            return $app->make(config('deduplication.engine'));
        });
        $this->app->bind(FuzzyMatchingStrategyInterface::class, function ($app): FuzzyMatchingStrategyInterface {
            return $app->make(config('deduplication.fuzzy.strategy'));
        });
        $this->app->bind(SimilarityCalculatorInterface::class, MentionSimilarityCalculator::class);
        $this->app->bind(MentionClusterRepositoryInterface::class, MentionClusterRepository::class);
        $this->app->bind(MentionClusterBuilderInterface::class, MentionClusterBuilder::class);
        $this->app->bind(SimHashMatchingStrategy::class, SimHashMatchingStrategy::class);
        $this->app->bind(MinHashMatchingStrategy::class, MinHashMatchingStrategy::class);
        $this->app->bind(FuzzyDeduplicationEngine::class, FuzzyDeduplicationEngine::class);
        $this->app->bind(ExactDeduplicationEngine::class, ExactDeduplicationEngine::class);
        $this->app->bind(ProviderFactoryInterface::class, ProviderFactory::class);
        $this->app->bind(Brand24ClientInterface::class, Brand24ApiClient::class);
        $this->app->bind(MentionlyticsTokenStorageInterface::class, DatabaseMentionlyticsTokenStorage::class);
        $this->app->bind(MentionlyticsRefreshServiceInterface::class, MentionlyticsRefreshService::class);
        $this->app->singleton(MentionlyticsAuthServiceInterface::class, MentionlyticsAuthService::class);
        $this->app->bind(MentionlyticsRateLimiterInterface::class, MentionlyticsRateLimiter::class);
        $this->app->bind(MentionlyticsResponseCacheInterface::class, MentionlyticsResponseCache::class);
        $this->app->singleton(MentionlyticsHttpTransport::class);
        $this->app->bind(MentionlyticsTokenManager::class, MentionlyticsTokenManager::class);
        $this->app->bind(MentionlyticsClientInterface::class, MentionlyticsApiClient::class);
        $this->app->bind(SerpApiClientInterface::class, SerpApiClient::class);
        $this->app->bind(PersonRepositoryInterface::class, PersonRepository::class);
        $this->app->bind(PersonResolverInterface::class, PersonResolver::class);
        $this->app->bind(SerpSnapshotRepositoryInterface::class, SerpSnapshotRepository::class);
        $this->app->bind(SerpScreenshotCaptureInterface::class, SerpApiScreenshotCapture::class);
        $this->app->bind(SerpScreenshotStorageInterface::class, function ($app): SerpScreenshotStorageInterface {
            $disk = (string) config('serpapi.screenshots.disk', 'local');

            return match ($disk) {
                's3' => $app->make(S3SerpScreenshotStorage::class),
                default => $app->make(LocalSerpScreenshotStorage::class),
            };
        });
        $this->app->bind(ClaudeClientInterface::class, AnthropicClaudeClient::class);
        $this->app->bind(ClaudeStructuredOutputInterface::class, function ($app): ClaudeStructuredOutputInterface {
            return $app->make(config('classification.structured_output.service'));
        });
        $this->app->bind(PromptInjectionGuardInterface::class, PromptInjectionGuard::class);
        $this->app->bind(ToolExecutorInterface::class, function ($app): ToolExecutorInterface {
            return $app->make(config('classification.tool_use.executor'));
        });
        $this->app->bind(CascadeTierEscalator::class, CascadeTierEscalator::class);
        $this->app->bind(ClaudeStructuredOutputService::class, ClaudeStructuredOutputService::class);
        $this->app->bind(PromptInjectionGuard::class, PromptInjectionGuard::class);
        $this->app->bind(NullToolExecutor::class, NullToolExecutor::class);
        $this->app->bind(LLMDecisionStrategyInterface::class, function ($app): LLMDecisionStrategyInterface {
            return $app->make(config('cascade.decision_strategy'));
        });
        $this->app->bind(LLMCascadeInterface::class, function ($app): LLMCascadeInterface {
            return $app->make(config('cascade.engine'));
        });
        $this->app->bind(LlmCostCalculator::class, LlmCostCalculator::class);
        $this->app->bind(ClaudeHaikuAdapter::class, ClaudeHaikuAdapter::class);
        $this->app->bind(ClaudeSonnetAdapter::class, ClaudeSonnetAdapter::class);
        $this->app->bind(ClaudeOpusAdapter::class, ClaudeOpusAdapter::class);
        $this->app->bind(LlmCascadeEngine::class, LlmCascadeEngine::class);
        $this->app->bind(ConfigurableLlmDecisionStrategy::class, ConfigurableLlmDecisionStrategy::class);
        $this->app->bind(PromptBuilderInterface::class, MentionPromptBuilder::class);
        $this->app->bind(AiResultStorageInterface::class, AiResultStorage::class);
        $this->app->bind(MentionRouterInterface::class, function ($app): MentionRouterInterface {
            return $app->make(config('routing.engine'));
        });
        $this->app->bind(MentionRouteStorageInterface::class, MentionRouteStorage::class);
        $this->app->bind(RoutingRuleRepositoryInterface::class, RoutingRuleRepository::class);
        $this->app->bind(RoutingConditionMatcherInterface::class, function ($app): RoutingConditionMatcherInterface {
            return $app->make(config('routing.condition_matcher'));
        });
        $this->app->bind(RoutingEngineInterface::class, function ($app): RoutingEngineInterface {
            return $app->make(config('routing.engine'));
        });
        $this->app->bind(RoutingContextBuilderInterface::class, function ($app): RoutingContextBuilderInterface {
            return $app->make(config('routing.context_builder'));
        });
        $this->app->bind(RoutingEngine::class, RoutingEngine::class);
        $this->app->bind(RoutingContextBuilder::class, RoutingContextBuilder::class);
        $this->app->bind(RoutingConditionMatcher::class, RoutingConditionMatcher::class);
        $this->app->bind(TelegramNotifierInterface::class, TelegramBotNotifier::class);
        $this->app->bind(TelegramNotificationStorageInterface::class, TelegramNotificationStorage::class);
        $this->app->bind(ModerationLogStorageInterface::class, ModerationLogStorage::class);
        $this->app->bind(ThreatFactorWeightRepositoryInterface::class, ThreatFactorWeightRepository::class);
        $this->app->bind(ThreatRuleRepositoryInterface::class, ThreatRuleRepository::class);
        $this->app->bind(ThreatFactorScorerInterface::class, function ($app): ThreatFactorScorerInterface {
            return $app->make(config('threat.factor_scorer'));
        });
        $this->app->bind(ThreatEngineInterface::class, function ($app): ThreatEngineInterface {
            return $app->make(config('threat.engine'));
        });
        $this->app->bind(ThreatContextBuilderInterface::class, function ($app): ThreatContextBuilderInterface {
            return $app->make(config('threat.context_builder'));
        });
        $this->app->bind(ThreatResultStorageInterface::class, ThreatResultStorage::class);
        $this->app->bind(ConfigurableThreatFactorScorer::class, ConfigurableThreatFactorScorer::class);
        $this->app->bind(ThreatEngine::class, ThreatEngine::class);
        $this->app->bind(ThreatContextBuilder::class, ThreatContextBuilder::class);
        $this->app->bind(ThreatResultStorage::class, ThreatResultStorage::class);
        $this->app->bind(DeliveryEngineInterface::class, function ($app): DeliveryEngineInterface {
            return $app->make(config('delivery.engine'));
        });
        $this->app->bind(DigestEngineInterface::class, function ($app): DigestEngineInterface {
            return $app->make(config('delivery.digest_engine'));
        });
        $this->app->bind(DeliveryContextBuilderInterface::class, function ($app): DeliveryContextBuilderInterface {
            return $app->make(config('delivery.context_builder'));
        });
        $this->app->bind(DeliveryCardBuilderInterface::class, function ($app): DeliveryCardBuilderInterface {
            return $app->make(config('delivery.card_builder'));
        });
        $this->app->bind(DeliveryMessageStorageInterface::class, DeliveryMessageStorage::class);
        $this->app->bind(DeliveryDigestStorageInterface::class, DeliveryDigestStorage::class);
        $this->app->bind(TelegramDestinationNotifierInterface::class, TelegramDestinationNotifier::class);
        $this->app->bind(DeliveryEngine::class, DeliveryEngine::class);
        $this->app->bind(DigestEngine::class, DigestEngine::class);
        $this->app->bind(DeliveryContextBuilder::class, DeliveryContextBuilder::class);
        $this->app->bind(DeliveryCardBuilder::class, DeliveryCardBuilder::class);
        $this->app->bind(DeliveryMessageStorage::class, DeliveryMessageStorage::class);
        $this->app->bind(DeliveryDigestStorage::class, DeliveryDigestStorage::class);
        $this->app->bind(TelegramDestinationNotifier::class, TelegramDestinationNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
