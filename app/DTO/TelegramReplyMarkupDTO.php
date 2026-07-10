<?php

namespace App\DTO;

readonly class TelegramReplyMarkupDTO
{
    /**
     * @param  array<int, array<int, array<string, string>>>  $inlineKeyboard
     */
    public function __construct(
        public array $inlineKeyboard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'inline_keyboard' => $this->inlineKeyboard,
        ];
    }
}
