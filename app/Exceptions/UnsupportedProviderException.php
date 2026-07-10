<?php

namespace App\Exceptions;

use Exception;

class UnsupportedProviderException extends Exception
{
    public function __construct(string $provider)
    {
        parent::__construct("No mention normalizer registered for provider: {$provider}");
    }
}
