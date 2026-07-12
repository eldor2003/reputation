<?php

namespace Tests\Unit\Services;

use App\Models\AiResult;
use App\Models\Mention;
use App\Models\Person;
use App\Models\Project;
use App\Models\Source;
use App\Enums\PersonLanguage;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Services\TelegramNotificationMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramNotificationMessageBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prefers_linked_person_over_ai_classification(): void
    {
        $project = Project::query()->create([
            'name' => 'Person Project',
            'slug' => 'person-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Brand24,
            'external_id' => 'source',
            'name' => 'Brand24',
            'is_active' => true,
        ]);

        $person = Person::query()->create([
            'project_id' => $project->id,
            'full_name' => 'Tokayev',
            'primary_language' => PersonLanguage::Russian,
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'person_id' => $person->id,
            'external_id' => 'mention-1',
            'content' => 'Sample mention',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $classification = AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test-model',
            'summary' => 'Summary',
            'sentiment' => 'negative',
            'severity' => 3,
            'language' => 'ru',
            'category' => 'politics',
            'person' => 'Putin',
            'confidence' => 88,
            'reasoning' => 'Reasoning',
            'raw_response' => ['id' => 'msg_1'],
            'processed_at' => now(),
        ]);

        $message = $this->app->make(TelegramNotificationMessageBuilder::class)->build($mention, $classification);

        $this->assertStringContainsString('👤 Tokayev', $message);
        $this->assertStringNotContainsString('👤 Putin', $message);
    }

    #[Test]
    public function it_uses_single_active_project_person_when_mention_is_not_linked(): void
    {
        $project = Project::query()->create([
            'name' => 'Brand24 Production',
            'slug' => 'brand24-production',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Brand24,
            'external_id' => 'source',
            'name' => 'Brand24',
            'is_active' => true,
        ]);

        Person::query()->create([
            'project_id' => $project->id,
            'full_name' => 'Kassym-Jomart Tokayev',
            'primary_language' => PersonLanguage::Russian,
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-2',
            'content' => 'Shnurov mentioned in text',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $classification = AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test-model',
            'summary' => 'Summary',
            'sentiment' => 'negative',
            'severity' => 3,
            'language' => 'ru',
            'category' => 'politics',
            'person' => 'Shnurov',
            'confidence' => 88,
            'reasoning' => 'Reasoning',
            'raw_response' => ['id' => 'msg_2'],
            'processed_at' => now(),
        ]);

        $message = $this->app->make(TelegramNotificationMessageBuilder::class)->build($mention, $classification);

        $this->assertStringContainsString('👤 Kassym-Jomart Tokayev', $message);
        $this->assertStringNotContainsString('👤 Shnurov', $message);
    }
}
