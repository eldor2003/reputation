<?php

namespace App\Interfaces;

use App\Enums\SourceType;
use App\Models\Source;

interface SourceResolverInterface
{
    public function resolveActiveSource(string $sourceUuid, SourceType $type): Source;
}
