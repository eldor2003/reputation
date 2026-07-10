<?php

return [

    'engine' => App\Services\FuzzyDeduplicationEngine::class,

    'exact_fallback' => [
        'enabled' => (bool) env('DEDUP_EXACT_FALLBACK_ENABLED', true),
    ],

    'fuzzy' => [
        'enabled' => (bool) env('DEDUP_FUZZY_ENABLED', true),
        'strategy' => env('DEDUP_FUZZY_STRATEGY', App\Services\Deduplication\SimHashMatchingStrategy::class),
    ],

    'similarity' => [
        'threshold' => (float) env('DEDUP_SIMILARITY_THRESHOLD', 0.85),
        'minimum' => (float) env('DEDUP_SIMILARITY_MINIMUM', 0.70),
        'weights' => [
            'content' => (float) env('DEDUP_WEIGHT_CONTENT', 0.50),
            'title' => (float) env('DEDUP_WEIGHT_TITLE', 0.15),
            'url' => (float) env('DEDUP_WEIGHT_URL', 0.20),
            'author' => (float) env('DEDUP_WEIGHT_AUTHOR', 0.10),
            'published_at' => (float) env('DEDUP_WEIGHT_PUBLISHED_AT', 0.05),
        ],
    ],

    'simhash' => [
        'bits' => (int) env('DEDUP_SIMHASH_BITS', 64),
        'max_hamming_distance' => (int) env('DEDUP_SIMHASH_MAX_HAMMING_DISTANCE', 8),
    ],

    'minhash' => [
        'permutations' => (int) env('DEDUP_MINHASH_PERMUTATIONS', 128),
    ],

    'time_window_hours' => (int) env('DEDUP_TIME_WINDOW_HOURS', 72),

];
