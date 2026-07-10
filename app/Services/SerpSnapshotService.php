<?php

namespace App\Services;

use App\Contracts\SerpSnapshotRepositoryInterface;
use App\DTO\SerpSearchRequestDTO;
use App\DTO\SerpSearchResultDTO;
use App\DTO\SerpSnapshotDTO;
use App\Contracts\SerpApiClientInterface;
use App\Models\SerpSnapshot;
use Carbon\Carbon;

class SerpSnapshotService
{
    public function __construct(
        private readonly SerpApiClientInterface $client,
        private readonly SerpSnapshotRepositoryInterface $repository,
    ) {}

    public function takeSnapshot(SerpSearchRequestDTO $request): SerpSnapshot
    {
        $searchResult = $this->client->search($request);

        return $this->repository->store($this->toSnapshotDto($request, $searchResult));
    }

    private function toSnapshotDto(
        SerpSearchRequestDTO $request,
        SerpSearchResultDTO $searchResult,
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
            ]),
        );
    }
}
