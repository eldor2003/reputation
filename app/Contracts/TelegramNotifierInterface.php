<?php

namespace App\Contracts;

use App\DTO\TelegramReplyMarkupDTO;
use App\DTO\TelegramSendResultDTO;

interface TelegramNotifierInterface
{
    public function send(
        string $chatId,
        string $message,
        ?TelegramReplyMarkupDTO $replyMarkup = null,
    ): TelegramSendResultDTO;

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void;
}
