<?php

namespace App\Exceptions;

use Exception;

class TelegramApiException extends Exception
{
    public function __construct(string $message, ?Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
