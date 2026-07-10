<?php

namespace Tests\Feature\Person;

use App\Actions\AddPersonAliasAction;
use App\Actions\CreatePersonAction;
use App\Actions\FindPersonByUuidAction;
use App\Actions\SyncPersonAliasesAction;
use App\Actions\UpdatePersonAction;
use App\DTO\CreatePersonData;
use App\DTO\PersonAliasInputDTO;
use App\DTO\UpdatePersonData;
use App\Enums\PersonAliasType;
use App\Enums\PersonLanguage;
use App\Exceptions\PersonException;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonActionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_person_with_generated_aliases(): void
    {
        $project = $this->createProject();

        $person = $this->app->make(CreatePersonAction::class)->execute(new CreatePersonData(
            projectId: $project->id,
            fullName: 'Владимир Путин',
            primaryLanguage: PersonLanguage::Russian,
            customAliases: ['Путин В.В.'],
        ));

        $person->load('aliases');

        $this->assertSame('Владимир Путин', $person->full_name);
        $this->assertTrue($person->aliases->contains(
            fn ($alias): bool => $alias->type === PersonAliasType::FullName,
        ));
        $this->assertTrue($person->aliases->contains(
            fn ($alias): bool => $alias->type === PersonAliasType::Alias && $alias->alias === 'Путин В.В.',
        ));
        $this->assertTrue($person->aliases->contains(
            fn ($alias): bool => $alias->type === PersonAliasType::Transliteration,
        ));
        $this->assertTrue($person->aliases->contains(
            fn ($alias): bool => $alias->type === PersonAliasType::TypoVariant,
        ));
    }

    #[Test]
    public function it_updates_person_and_regenerates_aliases(): void
    {
        $project = $this->createProject();

        $createAction = $this->app->make(CreatePersonAction::class);
        $person = $createAction->execute(new CreatePersonData(
            projectId: $project->id,
            fullName: 'John Smith',
            primaryLanguage: PersonLanguage::English,
        ));

        $updated = $this->app->make(UpdatePersonAction::class)->execute(
            $person->uuid,
            new UpdatePersonData(
                fullName: 'Jonathan Smith',
                regenerateAliases: true,
            ),
        );

        $updated->load('aliases');

        $this->assertSame('Jonathan Smith', $updated->full_name);
        $this->assertTrue($updated->aliases->contains(
            fn ($alias): bool => $alias->type === PersonAliasType::FullName && $alias->alias === 'Jonathan Smith',
        ));
    }

    #[Test]
    public function it_adds_custom_alias_and_syncs_auto_aliases(): void
    {
        $project = $this->createProject();

        $person = $this->app->make(CreatePersonAction::class)->execute(new CreatePersonData(
            projectId: $project->id,
            fullName: 'John Smith',
            primaryLanguage: PersonLanguage::English,
        ));

        $this->app->make(AddPersonAliasAction::class)->execute(
            $person->uuid,
            new PersonAliasInputDTO(alias: 'Johnny', language: PersonLanguage::Custom),
        );

        $synced = $this->app->make(SyncPersonAliasesAction::class)->execute($person->uuid);
        $found = $this->app->make(FindPersonByUuidAction::class)->execute($person->uuid);

        $this->assertNotNull($found);
        $this->assertTrue($synced->aliases->contains(
            fn ($alias): bool => $alias->alias === 'Johnny' && $alias->type === PersonAliasType::Alias,
        ));
        $this->assertSame($synced->uuid, $found?->uuid);
    }

    #[Test]
    public function it_rejects_missing_project(): void
    {
        $this->expectException(PersonException::class);

        $this->app->make(CreatePersonAction::class)->execute(new CreatePersonData(
            projectId: 999999,
            fullName: 'Missing Project',
            primaryLanguage: PersonLanguage::English,
        ));
    }

    private function createProject(): Project
    {
        return Project::query()->create([
            'name' => 'Action Project',
            'slug' => 'action-project-'.uniqid(),
            'is_active' => true,
        ]);
    }
}
