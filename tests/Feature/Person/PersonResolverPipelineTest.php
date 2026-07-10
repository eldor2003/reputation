<?php

namespace Tests\Feature\Person;

use App\Actions\CreatePersonAction;
use App\Actions\ProcessMentionAction;
use App\DTO\CreatePersonData;
use App\Enums\MentionStatus;
use App\Enums\PersonLanguage;
use App\Enums\SourceType;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonResolverPipelineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_person_during_pipeline_processing(): void
    {
        config([
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100123456'],
            'telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_person_pipeline',
                'model' => 'claude-test-model',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'summary' => 'Negative mention about John Smith.',
                        'sentiment' => 'negative',
                        'severity' => 3,
                        'language' => 'en',
                        'category' => 'reputation',
                        'person' => 'John Smith',
                        'confidence' => 90,
                        'reasoning' => 'Direct mention of monitored person.',
                    ], JSON_THROW_ON_ERROR),
                ]],
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 901,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);

        $project = Project::query()->create([
            'name' => 'Pipeline Person Project',
            'slug' => 'pipeline-person-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'person-source',
            'name' => 'Person Source',
            'is_active' => true,
        ]);

        $person = $this->app->make(CreatePersonAction::class)->execute(new CreatePersonData(
            projectId: $project->id,
            fullName: 'John Smith',
            primaryLanguage: PersonLanguage::English,
        ));

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'person-pipeline-mention',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => 'person-pipeline-mention',
                'text' => 'John Smith criticized the company in a public post.',
                'author' => 'Reporter',
                'language' => 'en',
                'published' => '2026-07-09T10:00:00Z',
                'received_at' => '2026-07-09T11:00:00Z',
            ],
        ]);

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $mention->refresh();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertSame($person->id, $mention->person_id);
    }

    #[Test]
    public function it_leaves_person_id_null_when_resolution_is_ambiguous(): void
    {
        config([
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100123456'],
            'telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_ambiguous_pipeline',
                'model' => 'claude-test-model',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'summary' => 'Ambiguous mention.',
                        'sentiment' => 'neutral',
                        'severity' => 1,
                        'language' => 'en',
                        'category' => 'general',
                        'person' => 'John Smith',
                        'confidence' => 80,
                        'reasoning' => 'Ambiguous person reference.',
                    ], JSON_THROW_ON_ERROR),
                ]],
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 902,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);

        $project = Project::query()->create([
            'name' => 'Ambiguous Person Project',
            'slug' => 'ambiguous-person-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'ambiguous-source',
            'name' => 'Ambiguous Source',
            'is_active' => true,
        ]);

        $createPerson = $this->app->make(CreatePersonAction::class);
        $createPerson->execute(new CreatePersonData(
            projectId: $project->id,
            fullName: 'John Smith',
            primaryLanguage: PersonLanguage::English,
        ));
        $createPerson->execute(new CreatePersonData(
            projectId: $project->id,
            fullName: 'John Smith',
            primaryLanguage: PersonLanguage::English,
        ));

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'ambiguous-pipeline-mention',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => 'ambiguous-pipeline-mention',
                'text' => 'John Smith was mentioned again.',
                'author' => 'Reporter',
                'language' => 'en',
                'published' => '2026-07-09T10:00:00Z',
                'received_at' => '2026-07-09T11:00:00Z',
            ],
        ]);

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $mention->refresh();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertNull($mention->person_id);
    }
}
