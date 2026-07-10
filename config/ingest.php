<?php

return [

    'api_token' => env('INGEST_API_TOKEN'),

    'idempotency' => [
        'lock_ttl_seconds' => (int) env('INGEST_IDEMPOTENCY_LOCK_TTL', 300),
    ],

];
