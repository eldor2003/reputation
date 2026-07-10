<?php

namespace App\Exceptions;

use RuntimeException;

class ProviderNotImplementedException extends RuntimeException
{
    public function __construct(string $provider)
    {
        parent::__construct("Provider [{$provider}] is not implemented yet.");
    }
}
