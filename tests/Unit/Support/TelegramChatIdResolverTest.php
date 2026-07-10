<?php

namespace Tests\Unit\Support;

use App\Support\TelegramChatIdResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramChatIdResolverTest extends TestCase
{
    #[Test]
    public function it_parses_comma_separated_chat_ids(): void
    {
        $chatIds = TelegramChatIdResolver::resolve(
            chatIds: '-100111111, -100222222 ,-100333333',
        );

        $this->assertSame(['-100111111', '-100222222', '-100333333'], $chatIds);
    }

    #[Test]
    public function it_includes_legacy_single_chat_id(): void
    {
        $chatIds = TelegramChatIdResolver::resolve(
            legacyChatId: '-100123456',
        );

        $this->assertSame(['-100123456'], $chatIds);
    }

    #[Test]
    public function it_merges_chat_ids_and_legacy_value_without_duplicates(): void
    {
        $chatIds = TelegramChatIdResolver::resolve(
            chatIds: '-100111111,-100222222',
            legacyChatId: '-100111111',
        );

        $this->assertSame(['-100111111', '-100222222'], $chatIds);
    }

    #[Test]
    public function it_reads_chat_ids_from_runtime_config(): void
    {
        config([
            'telegram.chat_ids' => ['-100111111', '-100222222'],
            'telegram.chat_id' => null,
        ]);

        $this->assertSame(['-100111111', '-100222222'], TelegramChatIdResolver::fromConfig());
    }

    #[Test]
    public function it_falls_back_to_legacy_chat_id_from_runtime_config(): void
    {
        config([
            'telegram.chat_ids' => [],
            'telegram.chat_id' => '-100123456',
        ]);

        $this->assertSame(['-100123456'], TelegramChatIdResolver::fromConfig());
    }
}
