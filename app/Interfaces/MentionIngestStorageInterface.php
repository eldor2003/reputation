<?php

namespace App\Interfaces;

use App\DTO\MentionIngestData;
use App\Models\Mention;

interface MentionIngestStorageInterface
{
    public function store(MentionIngestData $data): Mention;
}
