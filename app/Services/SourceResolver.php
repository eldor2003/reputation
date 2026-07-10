<?php

namespace App\Services;

use App\DTO\MentionIngestData;
use App\Enums\SourceType;
use App\Exceptions\SourceNotAvailableException;
use App\Interfaces\SourceResolverInterface;
use App\Models\Source;

class SourceResolver implements SourceResolverInterface
{
    public function resolveActiveSource(string $sourceUuid, SourceType $type): Source
    {
        $source = Source::query()
            ->where('uuid', $sourceUuid)
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if ($source === null) {
            throw new SourceNotAvailableException($sourceUuid, $type);
        }

        return $source;
    }
}
