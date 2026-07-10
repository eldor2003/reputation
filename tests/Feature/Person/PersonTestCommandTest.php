<?php

namespace Tests\Feature\Person;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonTestCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function person_test_command_verifies_person_engine(): void
    {
        $this->artisan('person:test')
            ->expectsOutputToContain('Person Engine Ready')
            ->expectsOutputToContain('Verification Status: OK')
            ->assertSuccessful();
    }

    #[Test]
    public function person_test_command_cleanup_removes_test_persons(): void
    {
        $this->artisan('person:test')->assertSuccessful();

        $project = Project::query()->where('slug', 'person-engine-test')->first();
        $this->assertNotNull($project);
        $this->assertGreaterThan(0, $project?->persons()->count());

        $this->artisan('person:test --cleanup')
            ->expectsOutputToContain('Removed')
            ->assertSuccessful();

        $this->assertSame(0, $project?->fresh()?->persons()->count());
    }
}
