<?php

namespace App\Exceptions;

use App\Enums\SourceType;
use Exception;

class SourceNotAvailableException extends Exception
{
    public function __construct(string $sourceUuid, SourceType $sourceType)
    {
        parent::__construct("Источник {$sourceType->value} недоступен: {$sourceUuid}");
    }
}
