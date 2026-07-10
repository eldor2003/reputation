<?php

namespace App\Console\Commands;

use App\Actions\CreatePersonAction;
use App\Actions\FindPersonByUuidAction;
use App\Actions\SyncPersonAliasesAction;
use App\DTO\CreatePersonData;
use App\Enums\PersonAliasType;
use App\Enums\PersonLanguage;
use App\Exceptions\PersonException;
use App\Models\Person;
use App\Models\Project;
use App\Services\PersonService;
use Illuminate\Console\Command;

class PersonTestCommand extends Command
{
    protected $signature = 'person:test {--cleanup : Remove test persons created during verification}';

    protected $description = 'Verify Person Engine architecture with Russian, English, and custom alias generation';

    /** @var list<string> */
    private array $createdPersonUuids = [];

    public function handle(
        CreatePersonAction $createPersonAction,
        SyncPersonAliasesAction $syncPersonAliasesAction,
        FindPersonByUuidAction $findPersonByUuidAction,
        PersonService $personService,
    ): int {
        if ($this->option('cleanup')) {
            return $this->cleanupTestPersons();
        }

        try {
            $project = $this->resolveTestProject();

            $russianPerson = $createPersonAction->execute(new CreatePersonData(
                projectId: $project->id,
                fullName: (string) config('person.test.russian_name'),
                primaryLanguage: PersonLanguage::Russian,
                customAliases: array_filter([(string) config('person.test.russian_custom_alias')]),
            ));

            $englishPerson = $createPersonAction->execute(new CreatePersonData(
                projectId: $project->id,
                fullName: (string) config('person.test.english_name'),
                primaryLanguage: PersonLanguage::English,
                customAliases: array_filter([(string) config('person.test.english_custom_alias')]),
            ));

            $this->createdPersonUuids = [$russianPerson->uuid, $englishPerson->uuid];

            $syncedRussianPerson = $syncPersonAliasesAction->execute($russianPerson->uuid);
            $loadedEnglishPerson = $findPersonByUuidAction->execute($englishPerson->uuid);

            if ($loadedEnglishPerson === null) {
                throw new PersonException('English test person could not be loaded by UUID.');
            }
        } catch (PersonException $exception) {
            $this->components->error('Person Engine verification failed.');
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('✓ Person Engine Ready');
        $this->newLine();
        $this->components->info('Verification Status: OK');
        $this->components->twoColumnDetail('Project', $project->name.' ('.$project->uuid.')');
        $this->components->twoColumnDetail('Persons created', (string) count($this->createdPersonUuids));

        $this->renderPersonReport('Russian Person', $syncedRussianPerson, $personService);
        $this->renderPersonReport('English Person', $loadedEnglishPerson, $personService);

        $this->newLine();
        $this->components->warn('Mention matching is not implemented yet. Person Engine architecture is ready for downstream integration.');
        $this->line('Run with --cleanup to remove test persons created by this command.');

        return self::SUCCESS;
    }

    private function renderPersonReport(string $label, Person $person, PersonService $personService): void
    {
        $dto = $personService->toDto($person);

        $this->newLine();
        $this->components->info($label);
        $this->components->twoColumnDetail('UUID', $dto->uuid);
        $this->components->twoColumnDetail('Full name', $dto->fullName);
        $this->components->twoColumnDetail('Primary language', $dto->primaryLanguage->label());
        $this->components->twoColumnDetail('Total aliases', (string) count($dto->aliases));

        $counts = collect($dto->aliases)
            ->groupBy(fn ($alias) => $alias->type->value)
            ->map(fn ($group) => $group->count())
            ->all();

        foreach (PersonAliasType::cases() as $type) {
            $this->components->twoColumnDetail(
                $type->label(),
                (string) ($counts[$type->value] ?? 0),
            );
        }

        $sampleRows = collect($dto->aliases)
            ->take(5)
            ->map(fn ($alias): array => [
                $alias->type->label(),
                $alias->language->label(),
                mb_substr($alias->alias, 0, 40),
            ])
            ->all();

        if ($sampleRows !== []) {
            $this->newLine();
            $this->table(['Type', 'Language', 'Alias'], $sampleRows);
        }
    }

    private function resolveTestProject(): Project
    {
        return Project::query()->firstOrCreate(
            ['slug' => 'person-engine-test'],
            [
                'name' => 'Person Engine Test Project',
                'is_active' => true,
            ],
        );
    }

    private function cleanupTestPersons(): int
    {
        $project = Project::query()->where('slug', 'person-engine-test')->first();

        if ($project === null) {
            $this->components->info('No person-engine-test project found. Nothing to clean up.');

            return self::SUCCESS;
        }

        $deleted = Person::query()->where('project_id', $project->id)->delete();

        $this->components->info("Removed {$deleted} test person(s) from project [person-engine-test].");

        return self::SUCCESS;
    }
}
