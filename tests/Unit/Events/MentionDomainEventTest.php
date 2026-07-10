<?php

namespace Tests\Unit\Events;

use App\Events\MentionClassified;
use App\Events\MentionDomainEvent;
use App\Events\MentionReceived;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionDomainEventTest extends TestCase
{
    #[Test]
    public function it_exposes_pipeline_context(): void
    {
        $timestamp = Carbon::parse('2026-06-29T12:00:00Z');

        $event = new MentionReceived(
            mentionId: 1,
            projectId: 2,
            sourceId: 3,
            timestamp: $timestamp,
        );

        $this->assertInstanceOf(MentionDomainEvent::class, $event);
        $this->assertSame(1, $event->mentionId);
        $this->assertSame(2, $event->projectId);
        $this->assertSame(3, $event->sourceId);
        $this->assertSame($timestamp, $event->timestamp);
    }

    #[Test]
    public function concrete_events_extend_the_base_domain_event(): void
    {
        $event = new MentionClassified(
            mentionId: 10,
            projectId: 20,
            sourceId: 30,
            timestamp: now(),
        );

        $this->assertInstanceOf(MentionDomainEvent::class, $event);
    }
}
