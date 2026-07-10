<?php

namespace App\Contracts;

use App\DTO\SerpSnapshotDTO;
use App\Models\SerpSnapshot;

interface SerpSnapshotRepositoryInterface
{
    public function store(SerpSnapshotDTO $snapshot): SerpSnapshot;
}
