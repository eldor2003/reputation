<?php

namespace Tests\Feature\Mentionlytics;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsSchedulerTest extends TestCase
{
    #[Test]
    public function it_registers_mentionlytics_poll_in_the_scheduler(): void
    {
        $this->artisan('help');

        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $matchingEvents = collect($schedule->events())->filter(
            fn (Event $event): bool => str_contains((string) $event->command, 'mentionlytics:poll'),
        );

        $this->assertCount(1, $matchingEvents);
        $this->assertSame(
            sprintf('*/%d * * * *', max(1, (int) config('mentionlytics.polling.interval_minutes'))),
            $matchingEvents->first()->expression,
        );
    }
}
