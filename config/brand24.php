<?php

return [

    'api_key' => env('BRAND24_API_KEY'),

    'base_url' => env('BRAND24_BASE_URL', 'https://api-data.brand24.com'),

    'account_id' => env('BRAND24_ACCOUNT_ID'),

    'timeout' => (int) env('BRAND24_TIMEOUT', 30),

    'retry' => [
        'times' => (int) env('BRAND24_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('BRAND24_RETRY_SLEEP_MS', 500),
    ],

];
