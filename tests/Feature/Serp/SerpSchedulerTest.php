<?php

namespace Tests\Feature\Serp;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpSchedulerTest extends TestCase
{
    #[Test]
    public function it_registers_serp_snapshot_in_the_scheduler(): void
    {
        $this->artisan('help');

        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $matchingEvents = collect($schedule->events())->filter(
            fn (Event $event): bool => str_contains((string) $event->command, 'serp:snapshot'),
        );

        $this->assertCount(1, $matchingEvents);

        $interval = max(1, (int) config('serpapi.snapshots.interval_minutes'));
        $expectedExpression = $interval < 60
            ? sprintf('*/%d * * * *', $interval)
            : sprintf('0 */%d * * *', max(1, (int) round($interval / 60)));

        $this->assertSame($expectedExpression, $matchingEvents->first()->expression);
    }
}
