<?php

namespace Tests\Unit\Services;

use App\Enums\SourceType;
use App\Services\IngestIdempotencyKeyBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestIdempotencyKeyBuilderTest extends TestCase
{
    #[Test]
    public function it_uses_provided_idempotency_key_when_present(): void
    {
        $builder = new IngestIdempotencyKeyBuilder;

        $key = $builder->build(
            SourceType::YouScan,
            '550e8400-e29b-41d4-a716-446655440000',
            'mention-123',
            'custom-idempotency-key',
        );

        $this->assertSame('custom-idempotency-key', $key);
    }

    #[Test]
    public function it_derives_idempotency_key_from_provider_source_and_external_id(): void
    {
        $builder = new IngestIdempotencyKeyBuilder;

        $sourceUuid = '550e8400-e29b-41d4-a716-446655440000';

        $first = $builder->build(SourceType::Brand24, $sourceUuid, 'mention-123');
        $second = $builder->build(SourceType::Brand24, $sourceUuid, 'mention-123');
        $different = $builder->build(SourceType::Brand24, $sourceUuid, 'mention-456');

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $different);
    }
}
