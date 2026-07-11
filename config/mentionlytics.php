<?php

return [

    'bearer_token' => env('MENTIONLYTICS_BEARER_TOKEN'),

    'refresh_token' => env('MENTIONLYTICS_REFRESH_TOKEN'),

    'base_url' => env('MENTIONLYTICS_BASE_URL', 'https://api.mentionlytics.com/v2'),

    'timeout' => (int) env('MENTIONLYTICS_TIMEOUT', 30),

    'access_token_ttl_seconds' => (int) env('MENTIONLYTICS_ACCESS_TOKEN_TTL_SECONDS', 3600),

    'refresh_token_ttl_seconds' => (int) env('MENTIONLYTICS_REFRESH_TOKEN_TTL_SECONDS', 2592000),

    'proactive_refresh_buffer_seconds' => (int) env('MENTIONLYTICS_PROACTIVE_REFRESH_BUFFER_SECONDS', 300),

    'response_cache_seconds' => (int) env('MENTIONLYTICS_RESPONSE_CACHE_SECONDS', 15),

    'refresh_lock_seconds' => (int) env('MENTIONLYTICS_REFRESH_LOCK_SECONDS', 30),

    'refresh_lock_wait_seconds' => (int) env('MENTIONLYTICS_REFRESH_LOCK_WAIT_SECONDS', 10),

    // Deprecated: use access_token_ttl_seconds.
    'bearer_ttl_seconds' => (int) env('MENTIONLYTICS_BEARER_TTL_SECONDS', 3300),

    'rate_limit' => [
        'per_second' => (int) env('MENTIONLYTICS_RATE_LIMIT_PER_SECOND', 20),
        'per_minute' => (int) env('MENTIONLYTICS_RATE_LIMIT_PER_MINUTE', 100),
    ],

    'retry' => [
        'max_attempts' => (int) env('MENTIONLYTICS_RETRY_MAX_ATTEMPTS', 5),
        'base_delay_ms' => (int) env('MENTIONLYTICS_RETRY_BASE_DELAY_MS', 500),
        'max_delay_ms' => (int) env('MENTIONLYTICS_RETRY_MAX_DELAY_MS', 30000),
        // Deprecated keys kept for backward compatibility.
        'times' => (int) env('MENTIONLYTICS_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('MENTIONLYTICS_RETRY_SLEEP_MS', 500),
    ],

    'polling' => [
        'default_per_page' => (int) env('MENTIONLYTICS_POLL_PER_PAGE', 20),
        'default_lookback_days' => (int) env('MENTIONLYTICS_POLL_LOOKBACK_DAYS', 7),
        'interval_minutes' => (int) env('MENTIONLYTICS_POLL_INTERVAL_MINUTES', 15),
    ],

];
