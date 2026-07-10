<?php

namespace Tests\Feature\Person;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonMigrationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_person_and_alias_tables(): void
    {
        $this->assertTrue(Schema::hasTable('persons'));
        $this->assertTrue(Schema::hasTable('person_aliases'));
        $this->assertTrue(Schema::hasColumns('persons', [
            'uuid',
            'project_id',
            'full_name',
            'primary_language',
            'is_active',
            'notes',
            'metadata',
        ]));
        $this->assertTrue(Schema::hasColumns('person_aliases', [
            'person_id',
            'alias',
            'normalized_alias',
            'type',
            'language',
            'source_alias_id',
            'is_auto_generated',
        ]));
    }
}
