<?php

namespace App\Contracts;

use App\DTO\TelegramSendResultDTO;

interface TestTelegramNotifierInterface
{
    public function send(string $chatId, string $message): TelegramSendResultDTO;
}
