<?php

return [

    'enabled' => (bool) env('LLM_CASCADE_ENABLED', true),

    'engine' => App\Services\Cascade\LlmCascadeEngine::class,

    'decision_strategy' => App\Services\Cascade\ConfigurableLlmDecisionStrategy::class,

    'order' => [
        App\Enums\LlmCascadeTier::Haiku->value,
        App\Enums\LlmCascadeTier::Sonnet->value,
        App\Enums\LlmCascadeTier::Opus->value,
    ],

    'models' => [
        'haiku' => [
            'adapter' => App\Services\Cascade\ClaudeHaikuAdapter::class,
            'name' => env('LLM_CASCADE_HAIKU_MODEL', 'claude-haiku-4-5'),
        ],
        'sonnet' => [
            'adapter' => App\Services\Cascade\ClaudeSonnetAdapter::class,
            'name' => env('LLM_CASCADE_SONNET_MODEL', 'claude-sonnet-4-6'),
        ],
        'opus' => [
            'adapter' => App\Services\Cascade\ClaudeOpusAdapter::class,
            'name' => env('LLM_CASCADE_OPUS_MODEL', 'claude-opus-4-8'),
        ],
    ],

    'fallback' => [
        'model' => env('LLM_CASCADE_FALLBACK_MODEL', env('CLAUDE_MODEL', 'claude-sonnet-4-6')),
        'tier' => env('LLM_CASCADE_FALLBACK_TIER', App\Enums\LlmCascadeTier::Sonnet->value),
    ],

    'initial_selection' => [
        'rules' => [
            [
                'tier' => App\Enums\LlmCascadeTier::Haiku->value,
                'max_text_length' => (int) env('LLM_CASCADE_HAIKU_MAX_TEXT_LENGTH', 500),
            ],
            [
                'tier' => App\Enums\LlmCascadeTier::Sonnet->value,
                'max_text_length' => (int) env('LLM_CASCADE_SONNET_MAX_TEXT_LENGTH', 2000),
            ],
            [
                'tier' => App\Enums\LlmCascadeTier::Opus->value,
            ],
        ],
    ],

    'escalation' => [
        'enabled' => (bool) env('LLM_CASCADE_ESCALATION_ENABLED', true),
        'rules' => [
            'haiku' => [
                'to' => App\Enums\LlmCascadeTier::Sonnet->value,
                'max_confidence' => (int) env('LLM_CASCADE_HAIKU_MAX_CONFIDENCE', 70),
                'escalate_on_severity_min' => (int) env('LLM_CASCADE_HAIKU_ESCALATE_SEVERITY_MIN', 4),
            ],
            'sonnet' => [
                'to' => App\Enums\LlmCascadeTier::Opus->value,
                'max_confidence' => (int) env('LLM_CASCADE_SONNET_MAX_CONFIDENCE', 75),
                'escalate_on_severity_min' => (int) env('LLM_CASCADE_SONNET_ESCALATE_SEVERITY_MIN', 5),
            ],
        ],
    ],

    'costs' => [
        'haiku' => [
            'input_per_token' => (float) env('LLM_COST_HAIKU_INPUT', 0.00000025),
            'output_per_token' => (float) env('LLM_COST_HAIKU_OUTPUT', 0.00000125),
        ],
        'sonnet' => [
            'input_per_token' => (float) env('LLM_COST_SONNET_INPUT', 0.000003),
            'output_per_token' => (float) env('LLM_COST_SONNET_OUTPUT', 0.000015),
        ],
        'opus' => [
            'input_per_token' => (float) env('LLM_COST_OPUS_INPUT', 0.000015),
            'output_per_token' => (float) env('LLM_COST_OPUS_OUTPUT', 0.000075),
        ],
    ],

];
