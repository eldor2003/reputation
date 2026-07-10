<?php

namespace App\DTO;

use App\Enums\ModerationAction;

readonly class TelegramModerationCallbackDTO
{
    public function __construct(
        public ModerationAction $action,
        public int $mentionId,
    ) {}
}
