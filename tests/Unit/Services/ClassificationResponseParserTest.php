<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidClassificationResponseException;
use App\Services\ClassificationResponseParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassificationResponseParserTest extends TestCase
{
    private ClassificationResponseParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = $this->app->make(ClassificationResponseParser::class);
    }

    #[Test]
    public function it_parses_a_valid_claude_response(): void
    {
        $result = $this->parser->parse($this->claudeResponse([
            'summary' => 'Great product feedback.',
            'sentiment' => 'positive',
            'severity' => 2,
            'language' => 'en',
            'category' => 'product_feedback',
            'person' => 'unknown',
            'confidence' => 92,
            'reasoning' => 'The mention expresses clear satisfaction with the product.',
        ]));

        $this->assertSame('positive', $result->sentiment);
        $this->assertSame('Great product feedback.', $result->summary);
        $this->assertSame(2, $result->severity);
        $this->assertSame('en', $result->language);
        $this->assertSame('product_feedback', $result->category);
        $this->assertSame('unknown', $result->person);
        $this->assertSame(92, $result->confidence);
        $this->assertSame('The mention expresses clear satisfaction with the product.', $result->reasoning);
        $this->assertSame('msg_123', $result->rawResponse['id']);
    }

    #[Test]
    public function it_rejects_invalid_json(): void
    {
        $this->expectException(InvalidClassificationResponseException::class);
        $this->expectExceptionMessage('not valid JSON');

        $this->parser->parse([
            'content' => [
                ['type' => 'text', 'text' => 'not-json'],
            ],
        ]);
    }

    #[Test]
    public function it_rejects_invalid_sentiment(): void
    {
        $this->expectException(InvalidClassificationResponseException::class);
        $this->expectExceptionMessage('invalid sentiment');

        $this->parser->parse($this->claudeResponse([
            'summary' => 'Summary.',
            'sentiment' => 'mixed',
            'severity' => 3,
            'language' => 'en',
            'category' => 'other',
            'person' => 'unknown',
            'confidence' => 80,
            'reasoning' => 'Reasoning.',
        ]));
    }

    #[Test]
    public function it_rejects_severity_outside_allowed_range(): void
    {
        $this->expectException(InvalidClassificationResponseException::class);
        $this->expectExceptionMessage('severity');

        $this->parser->parse($this->claudeResponse([
            'summary' => 'Summary.',
            'sentiment' => 'neutral',
            'severity' => 6,
            'language' => 'en',
            'category' => 'other',
            'person' => 'unknown',
            'confidence' => 80,
            'reasoning' => 'Reasoning.',
        ]));
    }

    #[Test]
    public function it_rejects_confidence_outside_allowed_range(): void
    {
        $this->expectException(InvalidClassificationResponseException::class);
        $this->expectExceptionMessage('confidence');

        $this->parser->parse($this->claudeResponse([
            'summary' => 'Summary.',
            'sentiment' => 'neutral',
            'severity' => 3,
            'language' => 'en',
            'category' => 'other',
            'person' => 'unknown',
            'confidence' => 101,
            'reasoning' => 'Reasoning.',
        ]));
    }

    /**
     * @param  array<string, mixed>  $classification
     * @return array<string, mixed>
     */
    private function claudeResponse(array $classification): array
    {
        return [
            'id' => 'msg_123',
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($classification, JSON_THROW_ON_ERROR),
                ],
            ],
        ];
    }
}
