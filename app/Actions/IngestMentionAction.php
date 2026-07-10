<?php

namespace App\Actions;

use App\Contracts\IngestIdempotencyServiceInterface;
use App\DTO\MentionIngestData;
use App\DTO\ProviderIngestData;
use App\Enums\MentionStatus;
use App\Events\MentionReceived;
use App\Exceptions\IngestMentionException;
use App\Exceptions\SourceNotAvailableException;
use App\Interfaces\MentionIngestStorageInterface;
use App\Interfaces\SourceResolverInterface;
use App\Jobs\ProcessMentionJob;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestMentionAction
{
    public function __construct(
        private readonly SourceResolverInterface $sourceResolver,
        private readonly MentionIngestStorageInterface $mentionIngestStorage,
        private readonly IngestIdempotencyServiceInterface $ingestIdempotencyService,
    ) {}

    public function execute(ProviderIngestData $data): void
    {
        if ($this->ingestIdempotencyService->exists($data->idempotencyKey)) {
            $this->logDuplicateWebhook($data, 'database');

            return;
        }

        $lock = $this->ingestIdempotencyService->acquireLock($data->idempotencyKey);

        if ($lock === null) {
            $this->logDuplicateWebhook($data, 'redis_lock');

            return;
        }

        try {
            if ($this->ingestIdempotencyService->exists($data->idempotencyKey)) {
                $this->logDuplicateWebhook($data, 'database');

                return;
            }

            $this->processIngest($data);
        } finally {
            $lock->release();
        }
    }

    private function processIngest(ProviderIngestData $data): void
    {
        try {
            $source = $this->sourceResolver->resolveActiveSource(
                $data->sourceUuid,
                $data->sourceType,
            );

            $mention = $this->mentionIngestStorage->store(new MentionIngestData(
                projectId: $source->project_id,
                sourceId: $source->id,
                externalId: $data->externalId,
                receivedAt: now(),
                status: MentionStatus::Pending,
                provider: $data->sourceType->value,
                rawPayload: $data->payload,
            ));

            $this->ingestIdempotencyService->record(
                idempotencyKey: $data->idempotencyKey,
                mentionId: $mention->id,
                provider: $data->sourceType->value,
                sourceId: $source->id,
                externalId: $data->externalId,
            );

            MentionReceived::dispatch(
                $mention->id,
                $source->project_id,
                $source->id,
                now(),
            );

            ProcessMentionJob::dispatch($mention->id);
        } catch (SourceNotAvailableException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->logDuplicateWebhook($data, 'unique_constraint');

                return;
            }

            $this->handleUnexpectedFailure($data, $exception);
        } catch (Throwable $exception) {
            $this->handleUnexpectedFailure($data, $exception);
        }
    }

    private function handleUnexpectedFailure(ProviderIngestData $data, Throwable $exception): void
    {
        Log::error('Mention ingest failed.', [
            'provider' => $data->sourceType->value,
            'source_uuid' => $data->sourceUuid,
            'external_id' => $data->externalId,
            'idempotency_key' => $data->idempotencyKey,
            'exception' => $exception->getMessage(),
        ]);

        throw new IngestMentionException(
            'Failed to ingest '.$data->sourceType->value.' mention.',
            $exception,
        );
    }

    private function logDuplicateWebhook(ProviderIngestData $data, string $reason): void
    {
        Log::info('Duplicate ingest webhook ignored.', [
            'provider' => $data->sourceType->value,
            'source_uuid' => $data->sourceUuid,
            'external_id' => $data->externalId,
            'idempotency_key' => $data->idempotencyKey,
            'reason' => $reason,
        ]);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
