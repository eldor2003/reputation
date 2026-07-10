<?php

namespace App\DTO;

readonly class TelegramSendResultDTO
{
    public function __construct(
        public string $messageId,
        public string $chatId,
    ) {}
}
