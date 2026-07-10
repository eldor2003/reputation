<?php

namespace App\Actions;

use App\Contracts\ProviderFactoryInterface;
use App\Enums\SourceType;
use App\DTO\DeduplicationResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\MentionStatus;
use App\Events\MentionDeduplicated;
use App\Events\MentionNormalized;
use App\Events\MentionProcessingCompleted;
use App\Events\MentionProcessingFailed;
use App\Exceptions\ClaudeApiException;
use App\Exceptions\InvalidClassificationResponseException;
use App\Exceptions\MentionNormalizationException;
use App\Exceptions\SchemaValidationException;
use App\Models\Mention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMentionAction
{
    public function __construct(
        private readonly ProviderFactoryInterface $providerFactory,
        private readonly ResolveMentionPersonAction $resolveMentionPersonAction,
        private readonly DeduplicateMentionAction $deduplicateMentionAction,
        private readonly ExecuteLlmCascadeAction $executeLlmCascadeAction,
        private readonly ValidateStructuredClassificationAction $validateStructuredClassificationAction,
        private readonly EvaluateMentionThreatAction $evaluateMentionThreatAction,
        private readonly RouteMentionAction $routeMentionAction,
    ) {}

    public function execute(int $mentionId): void
    {
        $mention = Mention::query()
            ->with('raw')
            ->find($mentionId);

        if ($mention === null || $mention->raw === null) {
            Log::error('Mention processing skipped: mention or raw payload not found.', [
                'mention_id' => $mentionId,
            ]);

            return;
        }

        $mention->update(['status' => MentionStatus::Processing]);

        try {
            $provider = $this->providerFactory->resolve(SourceType::from($mention->raw->provider));
            $normalized = $provider->normalize($mention->raw->payload);

            MentionNormalized::dispatch(
                $mention->id,
                $normalized->projectId,
                $normalized->sourceId,
                now(),
            );

            $this->resolveMentionPersonAction->execute($mention->id, $normalized);

            $dedupResult = $this->deduplicateMentionAction->execute($mention->id, $normalized);

            if ($this->isDuplicateOfAnotherMention($dedupResult, $mention->id)) {
                $this->markAsDuplicate($mention, $normalized, $dedupResult);

                MentionProcessingCompleted::dispatch(
                    $mention->id,
                    $normalized->projectId,
                    $normalized->sourceId,
                    now(),
                );

                return;
            }

            $this->persistOriginal($mention, $normalized, $dedupResult);

            $cascadeExecution = $this->executeLlmCascadeAction->execute($mention->id, $normalized);
            $this->validateStructuredClassificationAction->execute($mention->id, $normalized, $cascadeExecution);

            $this->evaluateMentionThreatAction->execute($mention->id);

            $this->routeMentionAction->execute($mention->id);
            $this->finalize($mention);

            MentionProcessingCompleted::dispatch(
                $mention->id,
                $normalized->projectId,
                $normalized->sourceId,
                now(),
            );
        } catch (MentionNormalizationException|ClaudeApiException|InvalidClassificationResponseException|SchemaValidationException $exception) {
            $mention->update(['status' => MentionStatus::Failed]);

            MentionProcessingFailed::dispatch(
                $mention->id,
                $mention->project_id,
                $mention->source_id,
                now(),
            );

            Log::error('Mention processing failed.', [
                'mention_id' => $mentionId,
                'provider' => $mention->raw->provider,
                'exception' => $exception->getMessage(),
            ]);

            return;
        } catch (Throwable $exception) {
            $mention->update(['status' => MentionStatus::Failed]);

            MentionProcessingFailed::dispatch(
                $mention->id,
                $mention->project_id,
                $mention->source_id,
                now(),
            );

            Log::error('Mention processing failed.', [
                'mention_id' => $mentionId,
                'provider' => $mention->raw->provider,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function isDuplicateOfAnotherMention(DeduplicationResultDTO $result, int $mentionId): bool
    {
        return $result->isDuplicate && $result->originalMentionId !== $mentionId;
    }

    private function markAsDuplicate(
        Mention $mention,
        NormalizedMentionDTO $normalized,
        DeduplicationResultDTO $dedupResult,
    ): void {
        DB::transaction(function () use ($mention, $normalized, $dedupResult): void {
            $mention->update([
                'author' => $normalized->author,
                'author_id' => $normalized->authorId,
                'language' => $normalized->language,
                'content' => $normalized->text,
                'title' => $normalized->title,
                'url' => $normalized->url,
                'published_at' => $normalized->publishedAt,
                'received_at' => $normalized->receivedAt,
                'metadata' => $normalized->metadata,
                'dedup_hash' => $dedupResult->dedupHash,
                'simhash' => $dedupResult->fingerprint?->simhash,
                'content_fingerprint' => $dedupResult->fingerprint?->contentFingerprint,
                'mention_cluster_id' => $dedupResult->clusterId,
                'is_duplicate' => true,
                'original_mention_id' => $dedupResult->originalMentionId,
                'status' => MentionStatus::Completed,
            ]);
        });

        Log::info('Duplicate mention detected.', [
            'mention_id' => $mention->id,
            'original_mention_id' => $dedupResult->originalMentionId,
            'dedup_hash' => $dedupResult->dedupHash,
        ]);
    }

    private function persistOriginal(
        Mention $mention,
        NormalizedMentionDTO $normalized,
        DeduplicationResultDTO $dedupResult,
    ): void {
        DB::transaction(function () use ($mention, $normalized, $dedupResult): void {
            $mention->update([
                'project_id' => $normalized->projectId,
                'source_id' => $normalized->sourceId,
                'external_id' => $normalized->externalId,
                'author' => $normalized->author,
                'author_id' => $normalized->authorId,
                'language' => $normalized->language,
                'content' => $normalized->text,
                'title' => $normalized->title,
                'url' => $normalized->url,
                'published_at' => $normalized->publishedAt,
                'received_at' => $normalized->receivedAt,
                'metadata' => $normalized->metadata,
                'dedup_hash' => $dedupResult->dedupHash,
                'simhash' => $dedupResult->fingerprint?->simhash,
                'content_fingerprint' => $dedupResult->fingerprint?->contentFingerprint,
                'mention_cluster_id' => $dedupResult->clusterId,
                'is_duplicate' => false,
                'original_mention_id' => null,
                'status' => MentionStatus::Processing,
            ]);
        });
    }

    private function finalize(Mention $mention): void
    {
        $mention->update(['status' => MentionStatus::Completed]);
    }
}
