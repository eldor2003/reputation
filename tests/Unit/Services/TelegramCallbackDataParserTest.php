<?php

namespace Tests\Unit\Services;

use App\Enums\ModerationAction;
use App\Exceptions\InvalidTelegramCallbackException;
use App\Services\TelegramCallbackDataParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramCallbackDataParserTest extends TestCase
{
    private TelegramCallbackDataParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new TelegramCallbackDataParser;
    }

    #[Test]
    public function it_parses_moderation_callback_data(): void
    {
        $parsed = $this->parser->parse('moderation:approve:15');

        $this->assertSame(ModerationAction::Approve, $parsed->action);
        $this->assertSame(15, $parsed->mentionId);
    }

    #[Test]
    public function it_rejects_invalid_callback_data(): void
    {
        $this->expectException(InvalidTelegramCallbackException::class);

        $this->parser->parse('invalid:data');
    }
}
