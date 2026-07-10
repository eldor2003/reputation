<?php

return [

    'transliteration' => [
        'enabled' => (bool) env('PERSON_TRANSLITERATION_ENABLED', true),
    ],

    'typo_variants' => [
        'enabled' => (bool) env('PERSON_TYPO_VARIANTS_ENABLED', true),
        'max_per_alias' => (int) env('PERSON_TYPO_MAX_PER_ALIAS', 10),
    ],

    'test' => [
        'russian_name' => env('PERSON_TEST_RUSSIAN_NAME', 'Владимир Путин'),
        'english_name' => env('PERSON_TEST_ENGLISH_NAME', 'John Smith'),
        'russian_custom_alias' => env('PERSON_TEST_RUSSIAN_ALIAS', 'Путин В.В.'),
        'english_custom_alias' => env('PERSON_TEST_ENGLISH_ALIAS', 'J. Smith'),
    ],

    'resolver' => [
        'ambiguity_threshold' => (float) env('PERSON_RESOLVER_AMBIGUITY_THRESHOLD', 0.05),
    ],

];
