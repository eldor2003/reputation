<?php

namespace Tests\Unit\Schemas;

use App\Exceptions\SchemaValidationException;
use App\Schemas\ClassificationSchema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassificationSchemaTest extends TestCase
{
    #[Test]
    public function it_accepts_a_valid_classification_payload(): void
    {
        ClassificationSchema::validate([
            'summary' => 'Summary.',
            'sentiment' => 'negative',
            'severity' => 4,
            'language' => 'en',
            'category' => 'customer_service',
            'person' => 'unknown',
            'confidence' => 88,
            'reasoning' => 'Reasoning.',
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_unexpected_fields(): void
    {
        $this->expectException(SchemaValidationException::class);
        $this->expectExceptionMessage('unexpected field');

        ClassificationSchema::validate([
            'summary' => 'Summary.',
            'sentiment' => 'negative',
            'severity' => 4,
            'language' => 'en',
            'category' => 'customer_service',
            'person' => 'unknown',
            'confidence' => 88,
            'reasoning' => 'Reasoning.',
            'extra' => 'not allowed',
        ]);
    }
}
