<?php

return [

    'bearer_token' => env('MENTIONLYTICS_BEARER_TOKEN'),

    'refresh_token' => env('MENTIONLYTICS_REFRESH_TOKEN'),

    'base_url' => env('MENTIONLYTICS_BASE_URL', 'https://api.mentionlytics.com/v2'),

    'timeout' => (int) env('MENTIONLYTICS_TIMEOUT', 30),

    'bearer_ttl_seconds' => (int) env('MENTIONLYTICS_BEARER_TTL_SECONDS', 3300),

    'retry' => [
        'times' => (int) env('MENTIONLYTICS_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('MENTIONLYTICS_RETRY_SLEEP_MS', 500),
    ],

    'polling' => [
        'default_per_page' => (int) env('MENTIONLYTICS_POLL_PER_PAGE', 20),
        'default_lookback_days' => (int) env('MENTIONLYTICS_POLL_LOOKBACK_DAYS', 7),
    ],

];
