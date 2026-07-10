<?php

return [

    'api_key' => env('SERPAPI_API_KEY'),

    'base_url' => env('SERPAPI_BASE_URL', 'https://serpapi.com'),

    'timeout' => (int) env('SERPAPI_TIMEOUT', 60),

    'retry' => [
        'times' => (int) env('SERPAPI_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('SERPAPI_RETRY_SLEEP_MS', 500),
    ],

    'test' => [
        'query' => env('SERPAPI_TEST_QUERY', 'reputation monitoring'),
        'engine' => env('SERPAPI_TEST_ENGINE', 'google'),
    ],

    'screenshots' => [
        'disk' => env('SERP_SCREENSHOT_DISK', 'local'),
        'path' => env('SERP_SCREENSHOT_PATH', 'serp-screenshots'),
    ],

];
