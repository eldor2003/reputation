<?php

return [

    'schema' => [
        'severity' => [
            'min' => (int) env('CLASSIFICATION_SEVERITY_MIN', 1),
            'max' => (int) env('CLASSIFICATION_SEVERITY_MAX', 5),
        ],
        'confidence' => [
            'min' => (int) env('CLASSIFICATION_CONFIDENCE_MIN', 0),
            'max' => (int) env('CLASSIFICATION_CONFIDENCE_MAX', 100),
        ],
        'language' => [
            'pattern' => env('CLASSIFICATION_LANGUAGE_PATTERN', '/^[a-z]{2}$/'),
        ],
    ],

    'validation' => [
        'max_retries' => (int) env('CLASSIFICATION_VALIDATION_MAX_RETRIES', 1),
        'escalate_on_failure' => (bool) env('CLASSIFICATION_VALIDATION_ESCALATE_ON_FAILURE', true),
    ],

    'prompt_isolation' => [
        'system_tag' => env('CLASSIFICATION_PROMPT_SYSTEM_TAG', 'system_instructions'),
        'output_tag' => env('CLASSIFICATION_PROMPT_OUTPUT_TAG', 'output_format'),
        'security_tag' => env('CLASSIFICATION_PROMPT_SECURITY_TAG', 'security_notice'),
        'mention_tag' => env('CLASSIFICATION_PROMPT_MENTION_TAG', 'mention_data'),
        'person_tag' => env('CLASSIFICATION_PROMPT_PERSON_TAG', 'person_candidates'),
    ],

    'injection_guard' => [
        'enabled' => (bool) env('CLASSIFICATION_INJECTION_GUARD_ENABLED', true),
        'patterns' => [
            '/ignore\s+(all\s+)?previous\s+instructions/i',
            '/disregard\s+(all\s+)?prior\s+instructions/i',
            '/you\s+are\s+chatgpt/i',
            '/return\s+positive\s+sentiment/i',
            '/return\s+negative\s+sentiment/i',
            '/system\s+prompt/i',
            '/override\s+classification/i',
        ],
    ],

    'structured_output' => [
        'service' => App\Services\Classification\ClaudeStructuredOutputService::class,
    ],

    'tool_use' => [
        'enabled' => (bool) env('CLASSIFICATION_TOOL_USE_ENABLED', false),
        'executor' => App\Services\Classification\NullToolExecutor::class,
    ],

];
