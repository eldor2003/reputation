<?php

namespace App\Actions;

use App\DTO\SerpSearchRequestDTO;
use App\Models\SerpSnapshot;
use App\Services\SerpSnapshotService;

class TakeSerpSnapshotAction
{
    public function __construct(
        private readonly SerpSnapshotService $snapshotService,
    ) {}

    public function execute(SerpSearchRequestDTO $request, ?int $personId = null): SerpSnapshot
    {
        return $this->snapshotService->takeSnapshot($request, $personId);
    }
}
