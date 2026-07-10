<?php

namespace Tests\Feature\Person;

use App\Actions\CreatePersonAction;
use App\Actions\ResolveMentionPersonAction;
use App\DTO\CreatePersonData;
use App\DTO\NormalizedMentionDTO;
use App\Enums\PersonLanguage;
use App\DTO\PersonMatchResultDTO;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\Project;
use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResolveMentionPersonActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_person_id_when_person_is_resolved(): void
    {
        $project = Project::query()->create([
            'name' => 'Resolve Action Project',
            'slug' => 'resolve-action-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'resolve-source',
            'name' => 'Resolve Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'resolve-mention',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => ['id' => 'resolve-mention'],
        ]);

        $person = $this->app->make(CreatePersonAction::class)->execute(new CreatePersonData(
            projectId: $project->id,
            fullName: 'John Smith',
            primaryLanguage: PersonLanguage::English,
        ));

        $this->mock(\App\Contracts\PersonResolverInterface::class, function ($mock) use ($person): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andReturn(new PersonMatchResultDTO(
                    resolvedPerson: new \App\DTO\ResolvedPersonDTO(
                        personId: $person->id,
                        personUuid: $person->uuid,
                        fullName: 'John Smith',
                        matchedAlias: 'John Smith',
                        matchType: \App\Enums\PersonAliasType::FullName,
                        confidence: 1.0,
                        matchedIn: 'content',
                    ),
                    isAmbiguous: false,
                    candidates: [],
                ));
        });

        $result = $this->app->make(ResolveMentionPersonAction::class)->execute(
            $mention->id,
            new NormalizedMentionDTO(
                projectId: $project->id,
                sourceId: $source->id,
                externalId: 'resolve-mention',
                author: null,
                authorId: null,
                language: 'en',
                text: 'John Smith',
                title: null,
                url: null,
                publishedAt: Carbon::now(),
                receivedAt: Carbon::now(),
            ),
        );

        $mention->refresh();

        $this->assertSame($person->id, $mention->person_id);
        $this->assertNotNull($result->resolvedPerson);
    }
}
