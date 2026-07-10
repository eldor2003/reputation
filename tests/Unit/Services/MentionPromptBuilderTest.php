<?php

namespace Tests\Unit\Services;

use App\DTO\NormalizedMentionDTO;
use App\Prompt\MentionClassificationPrompt;
use App\Services\MentionPromptBuilder;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionPromptBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_isolated_prompt_from_template_and_mention_data(): void
    {
        $builder = new MentionPromptBuilder;

        $prompt = $builder->build(new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 2,
            externalId: 'mention-123',
            author: 'Jane Doe',
            authorId: '42',
            language: 'en',
            text: 'Sample mention text.',
            title: 'Sample title',
            url: 'https://example.com/post',
            publishedAt: Carbon::parse('2026-06-29T10:00:00Z'),
            receivedAt: Carbon::parse('2026-06-29T11:00:00Z'),
        ));

        $this->assertStringContainsString('<system_instructions>', $prompt);
        $this->assertStringContainsString(MentionClassificationPrompt::systemInstructions(), $prompt);
        $this->assertStringContainsString('<mention_data>', $prompt);
        $this->assertStringContainsString('title: Sample title', $prompt);
        $this->assertStringContainsString('author: Jane Doe', $prompt);
        $this->assertStringContainsString('text: Sample mention text.', $prompt);
        $this->assertStringContainsString(MentionClassificationPrompt::securityNotice(), $prompt);
    }
}
