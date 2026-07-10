<?php

namespace Tests\Unit\Services;

use App\Services\TelegramModerationKeyboardBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramModerationKeyboardBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_moderation_inline_keyboard(): void
    {
        $keyboard = (new TelegramModerationKeyboardBuilder)->build(42);

        $this->assertSame([
            [
                ['text' => '✅ Одобрить', 'callback_data' => 'moderation:approve:42'],
                ['text' => '❌ Отклонить', 'callback_data' => 'moderation:reject:42'],
                ['text' => '📌 Пропустить', 'callback_data' => 'moderation:skip:42'],
            ],
        ], $keyboard->inlineKeyboard);
    }
}
