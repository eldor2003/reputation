<?php

namespace Tests\Feature\Events;

use App\Events\MentionApproved;
use App\Events\MentionProcessingCompleted;
use App\Events\MentionRouted;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventListenerRegistrationTest extends TestCase
{
    #[Test]
    public function it_registers_each_mention_listener_exactly_once(): void
    {
        $this->assertSame(1, count(Event::getListeners(MentionProcessingCompleted::class)));
        $this->assertSame(2, count(Event::getListeners(MentionRouted::class)));
        $this->assertSame(1, count(Event::getListeners(MentionApproved::class)));
    }
}
