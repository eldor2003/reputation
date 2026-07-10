<?php

namespace App\Services;

use App\DTO\TelegramReplyMarkupDTO;

class TelegramModerationKeyboardBuilder
{
    public function build(int $mentionId): TelegramReplyMarkupDTO
    {
        return new TelegramReplyMarkupDTO([
            [
                [
                    'text' => '✅ Одобрить',
                    'callback_data' => $this->callbackData('approve', $mentionId),
                ],
                [
                    'text' => '❌ Отклонить',
                    'callback_data' => $this->callbackData('reject', $mentionId),
                ],
                [
                    'text' => '📌 Пропустить',
                    'callback_data' => $this->callbackData('skip', $mentionId),
                ],
            ],
        ]);
    }

    private function callbackData(string $action, int $mentionId): string
    {
        return "moderation:{$action}:{$mentionId}";
    }
}
