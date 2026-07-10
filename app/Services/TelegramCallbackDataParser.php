<?php

namespace App\Services;

use App\DTO\TelegramModerationCallbackDTO;
use App\Enums\ModerationAction;
use App\Exceptions\InvalidTelegramCallbackException;

class TelegramCallbackDataParser
{
    public function parse(string $callbackData): TelegramModerationCallbackDTO
    {
        if (! preg_match('/^moderation:(approve|reject|skip):(\d+)$/', $callbackData, $matches)) {
            throw new InvalidTelegramCallbackException('Telegram callback data is invalid.');
        }

        return new TelegramModerationCallbackDTO(
            action: ModerationAction::from($matches[1]),
            mentionId: (int) $matches[2],
        );
    }
}
