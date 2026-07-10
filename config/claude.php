<?php

return [

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),

    'temperature' => (float) env('CLAUDE_TEMPERATURE', 0),

    'include_temperature' => (bool) env('CLAUDE_INCLUDE_TEMPERATURE', false),

    'max_tokens' => (int) env('CLAUDE_MAX_TOKENS', 1024),

    'provider' => env('CLAUDE_PROVIDER', 'anthropic'),

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),

    'timeout' => (int) env('CLAUDE_TIMEOUT', 30),

    'retry' => [
        'times' => (int) env('CLAUDE_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('CLAUDE_RETRY_SLEEP_MS', 500),
    ],

];
