<?php

namespace Tests\Feature\Telegram;

use App\Enums\MentionStatus;
use App\Enums\ModerationAction;
use App\Enums\SourceType;
use App\Events\MentionApproved;
use App\Events\MentionRejected;
use App\Events\MentionSkipped;
use App\Models\Mention;
use App\Models\ModerationLog;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramModerationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/telegram/webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.webhook_secret' => 'test-webhook-secret']);
    }

    #[Test]
    public function it_rejects_unauthorized_webhook_requests(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->callbackPayload('approve'));

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_records_approve_action_and_dispatches_event(): void
    {
        Event::fake([MentionApproved::class]);

        Http::fake([
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);

        config(['telegram.bot_token' => 'test-bot-token']);

        $mention = $this->createMention();

        $response = $this->postJson(
            self::ENDPOINT,
            $this->callbackPayload('approve', $mention->id),
            $this->authorizedHeaders(),
        );

        $response->assertOk()->assertExactJson(['success' => true]);

        $log = ModerationLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame($mention->id, $log->mention_id);
        $this->assertSame(ModerationAction::Approve, $log->action);
        $this->assertSame('987654321', $log->moderator_id);
        $this->assertSame('moderator_user', $log->moderator_username);
        $this->assertSame('-100123456', $log->telegram_chat_id);
        $this->assertSame('42', $log->telegram_message_id);
        $this->assertSame('callback-query-1', $log->callback_query_id);

        Event::assertDispatched(MentionApproved::class, fn (MentionApproved $event) => $event->mentionId === $mention->id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'answerCallbackQuery'));
    }

    #[Test]
    public function it_records_reject_action_and_dispatches_event(): void
    {
        Event::fake([MentionRejected::class]);

        Http::fake([
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);

        config(['telegram.bot_token' => 'test-bot-token']);

        $mention = $this->createMention();

        $this->postJson(
            self::ENDPOINT,
            $this->callbackPayload('reject', $mention->id),
            $this->authorizedHeaders(),
        )->assertOk();

        $this->assertSame(ModerationAction::Reject, ModerationLog::query()->first()?->action);
        Event::assertDispatched(MentionRejected::class);
    }

    #[Test]
    public function it_records_skip_action_and_dispatches_event(): void
    {
        Event::fake([MentionSkipped::class]);

        Http::fake([
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);

        config(['telegram.bot_token' => 'test-bot-token']);

        $mention = $this->createMention();

        $this->postJson(
            self::ENDPOINT,
            $this->callbackPayload('skip', $mention->id),
            $this->authorizedHeaders(),
        )->assertOk();

        $this->assertSame(ModerationAction::Skip, ModerationLog::query()->first()?->action);
        Event::assertDispatched(MentionSkipped::class);
    }

    #[Test]
    public function it_ignores_duplicate_moderation_for_the_same_mention(): void
    {
        Event::fake([MentionApproved::class, MentionRejected::class, MentionSkipped::class]);

        Http::fake([
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);

        config(['telegram.bot_token' => 'test-bot-token']);

        $mention = $this->createMention();

        ModerationLog::query()->create([
            'mention_id' => $mention->id,
            'action' => ModerationAction::Approve,
            'moderator_id' => '111',
            'telegram_chat_id' => '-100123456',
        ]);

        $this->postJson(
            self::ENDPOINT,
            $this->callbackPayload('reject', $mention->id),
            $this->authorizedHeaders(),
        )->assertOk();

        $this->assertDatabaseCount('moderation_logs', 1);
        Event::assertNothingDispatched();
    }

    private function createMention(): Mention
    {
        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-1',
            'name' => 'YouScan Source',
            'is_active' => true,
        ]);

        return Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-123',
            'content' => 'Sample mention',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackPayload(string $action, int $mentionId = 1): array
    {
        return [
            'callback_query' => [
                'id' => 'callback-query-1',
                'from' => [
                    'id' => 987654321,
                    'username' => 'moderator_user',
                ],
                'message' => [
                    'message_id' => 42,
                    'chat' => ['id' => -100123456],
                ],
                'data' => "moderation:{$action}:{$mentionId}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authorizedHeaders(): array
    {
        return [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret',
        ];
    }
}
