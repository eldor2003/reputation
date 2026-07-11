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
            $snapshot->update(['screenshot_path' => $screenshotPath]);
            $snapshot->refresh();
        }

        return $snapshot->load('results');
    }

    private function captureAndStoreScreenshot(SerpSnapshot $snapshot, SerpSearchResultDTO $searchResult): ?string
    {
        $captureUrl = $searchResult->screenshotUrl ?? $searchResult->rawHtmlUrl;

        if (! is_string($captureUrl) || $captureUrl === '') {
            return null;
        }

        $contents = $this->screenshotCapture->capture($captureUrl);

        if ($contents === null) {
            return null;
        }

        return $this->screenshotStorage->store($snapshot->uuid, $contents);
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
                'person_id' => $personId,
            ]),
        );
    }
}
