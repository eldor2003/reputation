<?php

namespace Tests\Unit\Services;

use App\Actions\CreatePersonAction;
use App\DTO\CreatePersonData;
use App\DTO\NormalizedMentionDTO;
use App\Enums\PersonAliasType;
use App\Enums\PersonLanguage;
use App\Models\PersonAlias;
use App\Models\Project;
use App\Services\PersonResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_exact_full_name_match(): void
    {
        $project = $this->createProject();
        $person = $this->createPerson($project->id, 'John Smith', PersonLanguage::English);

        $result = $this->resolver()->resolve($this->mentionDto(
            projectId: $project->id,
            text: 'Interview with John Smith about the new policy.',
        ));

        $this->assertFalse($result->isAmbiguous);
        $this->assertNotNull($result->resolvedPerson);
        $this->assertSame($person->id, $result->resolvedPerson?->personId);
        $this->assertSame(PersonAliasType::FullName, $result->resolvedPerson?->matchType);
        $this->assertSame(1.0, $result->resolvedPerson?->confidence);
    }

    #[Test]
    public function it_resolves_custom_alias_match(): void
    {
        $project = $this->createProject();
        $person = $this->createPerson(
            projectId: $project->id,
            fullName: 'Jonathan Smith',
            language: PersonLanguage::English,
            customAliases: ['Johnny'],
        );

        $result = $this->resolver()->resolve($this->mentionDto(
            projectId: $project->id,
            text: 'Johnny posted a complaint online.',
        ));

        $this->assertFalse($result->isAmbiguous);
        $this->assertNotNull($result->resolvedPerson);
        $this->assertSame($person->id, $result->resolvedPerson?->personId);
        $this->assertSame(PersonAliasType::Alias, $result->resolvedPerson?->matchType);
    }

    #[Test]
    public function it_resolves_transliteration_match(): void
    {
        $project = $this->createProject();
        $person = $this->createPerson($project->id, 'Владимир Путин', PersonLanguage::Russian);

        $result = $this->resolver()->resolve($this->mentionDto(
            projectId: $project->id,
            text: 'Coverage of vladimir putin at the summit.',
        ));

        $this->assertFalse($result->isAmbiguous);
        $this->assertNotNull($result->resolvedPerson);
        $this->assertSame($person->id, $result->resolvedPerson?->personId);
        $this->assertSame(PersonAliasType::Transliteration, $result->resolvedPerson?->matchType);
    }

    #[Test]
    public function it_resolves_typo_variant_match(): void
    {
        $project = $this->createProject();
        $person = $this->createPerson($project->id, 'John Smith', PersonLanguage::English);

        $typoAlias = PersonAlias::query()
            ->where('person_id', $person->id)
            ->where('type', PersonAliasType::TypoVariant)
            ->value('alias');

        $this->assertIsString($typoAlias);

        $result = $this->resolver()->resolve($this->mentionDto(
            projectId: $project->id,
            text: 'Users discussed '.$typoAlias.' in the comments.',
        ));

        $this->assertFalse($result->isAmbiguous);
        $this->assertNotNull($result->resolvedPerson);
        $this->assertSame($person->id, $result->resolvedPerson?->personId);
        $this->assertSame(PersonAliasType::TypoVariant, $result->resolvedPerson?->matchType);
    }

    #[Test]
    public function it_returns_ambiguous_result_for_homonyms(): void
    {
        $project = $this->createProject();
        $this->createPerson($project->id, 'John Smith', PersonLanguage::English);
        $this->createPerson($project->id, 'John Smith', PersonLanguage::English);

        $result = $this->resolver()->resolve($this->mentionDto(
            projectId: $project->id,
            text: 'John Smith was mentioned in the article.',
        ));

        $this->assertTrue($result->isAmbiguous);
        $this->assertNull($result->resolvedPerson);
        $this->assertCount(2, $result->candidates);
    }

    #[Test]
    public function it_returns_no_match_when_person_is_not_found(): void
    {
        $project = $this->createProject();

        $result = $this->resolver()->resolve($this->mentionDto(
            projectId: $project->id,
            text: 'Nothing relevant here.',
        ));

        $this->assertFalse($result->isAmbiguous);
        $this->assertNull($result->resolvedPerson);
        $this->assertSame([], $result->candidates);
    }

    private function resolver(): PersonResolver
    {
        return $this->app->make(PersonResolver::class);
    }

    private function createProject(): Project
    {
        return Project::query()->create([
            'name' => 'Resolver Project',
            'slug' => 'resolver-project-'.uniqid(),
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $customAliases
     */
    private function createPerson(int $projectId, string $fullName, PersonLanguage $language, array $customAliases = [])
    {
        return $this->app->make(CreatePersonAction::class)->execute(new CreatePersonData(
            projectId: $projectId,
            fullName: $fullName,
            primaryLanguage: $language,
            customAliases: $customAliases,
        ));
    }

    private function mentionDto(int $projectId, string $text, ?string $author = null): NormalizedMentionDTO
    {
        return new NormalizedMentionDTO(
            projectId: $projectId,
            sourceId: 1,
            externalId: 'mention-'.uniqid(),
            author: $author,
            authorId: null,
            language: 'en',
            text: $text,
            title: null,
            url: null,
            publishedAt: Carbon::parse('2026-07-09 10:00:00'),
            receivedAt: Carbon::parse('2026-07-09 11:00:00'),
        );
    }
}
