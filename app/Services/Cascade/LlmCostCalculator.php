<?php

namespace App\Services\Cascade;

class LlmCostCalculator
{
    public function estimate(string $tier, int $inputTokens, int $outputTokens): float
    {
        $costs = config("cascade.costs.{$tier}");

        if (! is_array($costs)) {
            return 0.0;
        }

        $inputCost = $inputTokens * (float) ($costs['input_per_token'] ?? 0);
        $outputCost = $outputTokens * (float) ($costs['output_per_token'] ?? 0);

        return round($inputCost + $outputCost, 8);
    }
}
