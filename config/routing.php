<?php

return [

    'engine' => App\Services\Routing\RoutingEngine::class,

    'context_builder' => App\Services\Routing\RoutingContextBuilder::class,

    'condition_matcher' => App\Services\Routing\RoutingConditionMatcher::class,

    'timezone' => env('ROUTING_TIMEZONE', config('app.timezone', 'UTC')),

    'default_working_hours' => [
        'start' => '09:00',
        'end' => '18:00',
        'weekdays' => [1, 2, 3, 4, 5],
    ],

    'default_night_mode' => [
        'start' => '22:00',
        'end' => '08:00',
    ],

];
