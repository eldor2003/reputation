<?php

namespace App\Contracts;

use App\DTO\TelegramSendResultDTO;
use App\Enums\TelegramDestination;

interface TelegramDestinationNotifierInterface
{
    public function send(
        TelegramDestination $destination,
        string $chatId,
        string $message,
    ): TelegramSendResultDTO;

    /**
     * @return list<string>
     */
    public function chatIds(TelegramDestination $destination): array;
}
