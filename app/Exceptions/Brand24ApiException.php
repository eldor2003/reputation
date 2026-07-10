<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class Brand24ApiException extends Exception
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
