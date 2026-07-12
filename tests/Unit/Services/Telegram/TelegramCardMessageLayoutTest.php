<?php

namespace Tests\Unit\Services\Telegram;

use App\Services\Telegram\TelegramCardMessageLayout;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramCardMessageLayoutTest extends TestCase
{
    private TelegramCardMessageLayout $layout;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'UTC']);
        $this->layout = new TelegramCardMessageLayout;
    }

    #[Test]
    public function it_renders_a_full_moderation_style_card_snapshot(): void
    {
        $message = $this->layout->format(
            sourceLabel: 'YouTube',
            sentiment: 'negative',
            threatLevel: 'P1',
            severity: 5,
            person: 'Путин',
            category: 'Политика',
            language: 'ru',
            confidence: 92,
            summary: 'Короткий негативный обзор с упоминанием ключевых рисков для репутации.',
            url: 'https://youtube.com/watch?v=example',
            occurredAt: Carbon::parse('2026-07-08 18:42:00', 'UTC'),
            mentionId: 1042,
            projectName: 'Путин',
        );

        $this->assertSame(<<<'TEXT'
🌐 YouTube
━━━━━━━━━━━━━━━━━━━━
☹️ Негатив
🔴 P1
Критическая угроза
●●●●●
━━━━━━━━━━━━━━━━━━━━
👤 Путин
📂 Политика
🌍 ru
🎯 92%
━━━━━━━━━━━━━━━━━━━━
📝 Summary

Короткий негативный обзор с
упоминанием ключевых рисков для
репутации.
━━━━━━━━━━━━━━━━━━━━
🔗 URL

https://youtube.com/watch?v=example
━━━━━━━━━━━━━━━━━━━━
⏱️ 08.07 18:42
#M-1042
Проект: Путин
TEXT, $message);
    }

    #[Test]
    public function it_hides_missing_optional_fields(): void
    {
        $message = $this->layout->format(
            sourceLabel: 'Telegram',
            sentiment: 'neutral',
            threatLevel: 'P4',
            severity: 2,
            person: null,
            category: 'другое',
            language: null,
            confidence: 0,
            summary: 'Краткое сообщение.',
            url: null,
            occurredAt: Carbon::parse('2026-07-08 10:15:00', 'UTC'),
            mentionId: 55,
            projectName: 'Card Project',
        );

        $this->assertStringNotContainsString('👤', $message);
        $this->assertStringNotContainsString('📂', $message);
        $this->assertStringNotContainsString('🌍', $message);
        $this->assertStringNotContainsString('🎯', $message);
        $this->assertStringNotContainsString('🔗 URL', $message);
        $this->assertStringContainsString('😐 Нейтрал', $message);
        $this->assertStringContainsString('🟢 P4', $message);
        $this->assertStringContainsString('●●○○○', $message);
        $this->assertStringContainsString('#M-55', $message);
    }

    #[Test]
    public function it_wraps_long_summary_to_five_lines_max(): void
    {
        $summary = implode(' ', array_fill(0, 40, 'слово'));

        $message = $this->layout->format(
            sourceLabel: 'News',
            sentiment: 'positive',
            threatLevel: 'P3',
            severity: 3,
            person: 'Tokayev',
            category: 'News',
            language: 'en',
            confidence: 80,
            summary: $summary,
            url: 'https://news.example.com/article',
            occurredAt: Carbon::parse('2026-07-08 12:00:00', 'UTC'),
            mentionId: 900,
            projectName: 'KZ Project',
        );

        $summarySection = explode('📝 Summary', $message, 2)[1] ?? '';
        $summaryLines = array_values(array_filter(
            explode("\n", trim(explode(TelegramCardMessageLayout::SEPARATOR, $summarySection)[0])),
            fn (string $line): bool => trim($line) !== '',
        ));

        $this->assertLessThanOrEqual(5, count($summaryLines));
    }

    #[Test]
    public function it_resolves_source_labels_from_url_and_metadata(): void
    {
        $mention = new \App\Models\Mention([
            'url' => 'https://www.youtube.com/watch?v=abc',
            'metadata' => null,
        ]);

        $this->assertSame('YouTube', $this->layout->resolveSourceLabel($mention, null));

        $telegramMention = new \App\Models\Mention([
            'url' => 'https://t.me/channel/1',
            'metadata' => ['mchannel' => 'telegram'],
        ]);

        $this->assertSame('Telegram', $this->layout->resolveSourceLabel($telegramMention, null));
    }

    #[Test]
    public function it_appends_delivery_confirmation_footer_with_approval_time(): void
    {
        $message = $this->layout->format(
            sourceLabel: 'YouTube',
            sentiment: 'negative',
            threatLevel: 'P2',
            severity: 4,
            person: 'Preview Person',
            category: 'Politics',
            language: 'ru',
            confidence: 91,
            summary: 'Delivery card summary.',
            url: 'https://youtube.com/watch?v=delivery-preview',
            occurredAt: Carbon::parse('2026-07-08 18:42:00', 'UTC'),
            mentionId: 1042,
            projectName: 'Layout Preview',
            approvedAt: Carbon::parse('2026-07-08 18:44:00', 'UTC'),
        );

        $this->assertStringContainsString(TelegramCardMessageLayout::CONFIRMATION_SEPARATOR, $message);
        $this->assertStringEndsWith("✓ Подтверждено · 08.07 18:44", $message);
        $this->assertStringContainsString('⏱️ 08.07 18:42', $message);
    }

    #[Test]
    public function it_omits_confirmation_footer_for_moderation_cards(): void
    {
        $message = $this->layout->format(
            sourceLabel: 'Telegram',
            sentiment: 'neutral',
            threatLevel: 'P4',
            severity: 2,
            person: null,
            category: null,
            language: null,
            confidence: null,
            summary: 'Moderation only.',
            url: null,
            occurredAt: Carbon::parse('2026-07-08 10:15:00', 'UTC'),
            mentionId: 55,
            projectName: 'Card Project',
        );

        $this->assertStringNotContainsString(TelegramCardMessageLayout::CONFIRMATION_SEPARATOR, $message);
        $this->assertStringNotContainsString('✓ Подтверждено', $message);
    }
}
