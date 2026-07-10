<?php

return [

    'engine' => App\Services\Threat\ThreatEngine::class,

    'factor_scorer' => App\Services\Threat\ConfigurableThreatFactorScorer::class,

    'context_builder' => App\Services\Threat\ThreatContextBuilder::class,

];
