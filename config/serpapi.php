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
        'timeout' => (int) env('SERP_SCREENSHOT_TIMEOUT', 30),
    ],

    'snapshots' => [
        'enabled' => (bool) env('SERP_SNAPSHOTS_ENABLED', true),
        'interval_minutes' => (int) env('SERP_SNAPSHOT_INTERVAL_MINUTES', 360),
        'results_per_page' => (int) env('SERP_SNAPSHOT_RESULTS_PER_PAGE', 10),
        'include_persons' => (bool) env('SERP_SNAPSHOT_INCLUDE_PERSONS', true),
        'engines' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SERP_SNAPSHOT_ENGINES', 'google,yandex,bing,baidu')),
        ))),
        'queries' => array_values(array_filter(array_map(
            'trim',
            explode('|', (string) env('SERP_SNAPSHOT_QUERIES', '')),
        ))),
    ],

];
