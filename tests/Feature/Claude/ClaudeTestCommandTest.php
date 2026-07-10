<?php

namespace Tests\Feature\Claude;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClaudeTestCommandTest extends TestCase
{
    #[Test]
    public function claude_test_command_verifies_successful_api_connection(): void
    {
        config([
            'claude.api_key' => 'test-anthropic-key',
            'claude.model' => 'claude-test-model',
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'claude.timeout' => 5,
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_test_1',
                'model' => 'claude-test-model',
                'content' => [[
                    'type' => 'text',
                    'text' => 'OK',
                ]],
                'usage' => [
                    'input_tokens' => 12,
                    'output_tokens' => 2,
                ],
            ], 200),
        ]);

        $this->artisan('claude:test')
            ->expectsOutputToContain('✓ Claude Connected')
            ->expectsOutputToContain('API Connection Status: OK')
            ->expectsOutputToContain('claude-test-model')
            ->expectsOutputToContain('input: 12, output: 2')
            ->expectsOutputToContain('OK')
            ->assertSuccessful();

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->hasHeader('x-api-key', 'test-anthropic-key')
                && ($data['model'] ?? null) === 'claude-test-model'
                && ($data['messages'][0]['content'] ?? null) === "Reply with exactly:\n\nOK";
        });
    }

    #[Test]
    public function claude_test_command_explains_unauthorized_responses(): void
    {
        $this->fakeClaudeFailure(401, [
            'type' => 'error',
            'error' => [
                'type' => 'authentication_error',
                'message' => 'invalid x-api-key',
            ],
        ]);

        $this->artisan('claude:test')
            ->expectsOutputToContain('API Connection Status: FAILED')
            ->expectsOutputToContain('invalid or has been revoked')
            ->assertFailed();
    }

    #[Test]
    public function claude_test_command_explains_payment_required_responses(): void
    {
        $this->fakeClaudeFailure(402, [
            'type' => 'error',
            'error' => [
                'type' => 'insufficient_credit',
                'message' => 'Your credit balance is too low',
            ],
        ]);

        $this->artisan('claude:test')
            ->expectsOutputToContain('no available API credits')
            ->assertFailed();
    }

    #[Test]
    public function claude_test_command_explains_forbidden_responses(): void
    {
        $this->fakeClaudeFailure(403, [
            'type' => 'error',
            'error' => [
                'type' => 'permission_error',
                'message' => 'Request not allowed',
            ],
        ]);

        $this->artisan('claude:test')
            ->expectsOutputToContain('Access forbidden')
            ->assertFailed();
    }

    #[Test]
    public function claude_test_command_explains_rate_limit_responses(): void
    {
        $this->fakeClaudeFailure(429, [
            'type' => 'error',
            'error' => [
                'type' => 'rate_limit_error',
                'message' => 'Rate limited',
            ],
        ]);

        $this->artisan('claude:test')
            ->expectsOutputToContain('Rate limit exceeded')
            ->assertFailed();
    }

    #[Test]
    public function claude_test_command_explains_server_error_responses(): void
    {
        $this->fakeClaudeFailure(503, [
            'type' => 'error',
            'error' => [
                'type' => 'overloaded_error',
                'message' => 'Overloaded',
            ],
        ]);

        $this->artisan('claude:test')
            ->expectsOutputToContain('server error')
            ->assertFailed();
    }

    #[Test]
    public function claude_test_command_fails_when_api_key_is_not_configured(): void
    {
        config([
            'claude.api_key' => '',
            'claude.model' => 'claude-test-model',
        ]);

        $this->artisan('claude:test')
            ->expectsOutputToContain('Anthropic API key is not configured')
            ->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function fakeClaudeFailure(int $status, array $body): void
    {
        config([
            'claude.api_key' => 'test-anthropic-key',
            'claude.model' => 'claude-test-model',
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'claude.timeout' => 5,
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($body, $status),
        ]);
    }
}
