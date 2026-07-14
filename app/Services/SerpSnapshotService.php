<?php

namespace App\Services;

use App\Contracts\SerpApiClientInterface;
use App\Contracts\SerpScreenshotCaptureInterface;
use App\Contracts\SerpScreenshotStorageInterface;
use App\Contracts\SerpSnapshotRepositoryInterface;
use App\DTO\SerpSearchRequestDTO;
use App\DTO\SerpSearchResultDTO;
use App\DTO\SerpSnapshotDTO;
use App\Models\SerpSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpSnapshotService
{
    public function __construct(
        private readonly SerpApiClientInterface $client,
        private readonly SerpSnapshotRepositoryInterface $repository,
        private readonly SerpScreenshotCaptureInterface $screenshotCapture,
        private readonly SerpScreenshotStorageInterface $screenshotStorage,
    ) {}

    public function takeSnapshot(SerpSearchRequestDTO $request, ?int $personId = null): SerpSnapshot
    {
        $searchResult = $this->client->search($request);
        $snapshot = $this->repository->store($this->toSnapshotDto($request, $searchResult, $personId));

        $screenshotPath = $this->captureAndStoreScreenshot($snapshot, $searchResult);

        if ($screenshotPath !== null) {
            $snapshot->update([
                'screenshot_path' => $screenshotPath,
                'metadata' => array_merge($snapshot->metadata ?? [], [
                    'screenshot_available' => true,
                ]),
            ]);
            $snapshot->refresh();
        }

        return $snapshot->load('results');
    }

    private function captureAndStoreScreenshot(SerpSnapshot $snapshot, SerpSearchResultDTO $searchResult): ?string
    {
        if (is_string($searchResult->screenshotUrl) && $searchResult->screenshotUrl !== '') {
            $contents = $this->screenshotCapture->capture($searchResult->screenshotUrl);

        if ($contents !== null) {
            try {
                return $this->screenshotStorage->store($snapshot->uuid, $contents, 'png');
            } catch (\Throwable $exception) {
                Log::warning('SERP screenshot storage failed.', [
                    'snapshot_uuid' => $snapshot->uuid,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
        }

        $this->storeHtmlArchiveIfAvailable($snapshot, $searchResult);

        return null;
    }

    private function storeHtmlArchiveIfAvailable(SerpSnapshot $snapshot, SerpSearchResultDTO $searchResult): void
    {
        if (! is_string($searchResult->rawHtmlUrl) || $searchResult->rawHtmlUrl === '') {
            return;
        }

        try {
            $response = Http::timeout((int) config('serpapi.screenshots.timeout', 30))
                ->accept('*/*')
                ->get($searchResult->rawHtmlUrl);
        } catch (\Throwable $exception) {
            Log::warning('SERP HTML archive fetch failed.', [
                'snapshot_uuid' => $snapshot->uuid,
                'url' => $searchResult->rawHtmlUrl,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            return;
        }

        try {
            $archivePath = $this->screenshotStorage->store($snapshot->uuid, $response->body(), 'html');

            $snapshot->update([
                'metadata' => array_merge($snapshot->metadata ?? [], [
                    'screenshot_available' => false,
                    'archive_path' => $archivePath,
                ]),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('SERP HTML archive storage failed.', [
                'snapshot_uuid' => $snapshot->uuid,
                'exception' => $exception->getMessage(),
            ]);

            $snapshot->update([
                'metadata' => array_merge($snapshot->metadata ?? [], [
                    'screenshot_available' => false,
                    'archive_storage_failed' => true,
                ]),
            ]);
        }
    }

    private function toSnapshotDto(
        SerpSearchRequestDTO $request,
        SerpSearchResultDTO $searchResult,
        ?int $personId,
    ): SerpSnapshotDTO {
        return new SerpSnapshotDTO(
            searchEngine: $request->engine,
            query: $request->query,
            fetchedAt: Carbon::now(),
            responseTimeMs: $searchResult->responseTimeMs,
            serpApiSearchId: $searchResult->serpApiSearchId,
            positions: $searchResult->positions,
            screenshotPath: null,
            metadata: array_filter([
                'raw_html_url' => $searchResult->rawHtmlUrl,
                'screenshot_url' => $searchResult->screenshotUrl,
                'person_id' => $personId,
                'screenshot_available' => is_string($searchResult->screenshotUrl) && $searchResult->screenshotUrl !== ''
                    ? null
                    : false,
            ], fn (mixed $value): bool => $value !== null),
        );
    }
}
