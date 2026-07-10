<?php

namespace Tests\Unit\Services\Cascade;

use App\Enums\LlmCascadeTier;
use App\Services\Cascade\LlmCostCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LlmCostCalculatorTest extends TestCase
{
    #[Test]
    public function it_estimates_cost_from_configured_token_prices(): void
    {
        config([
            'cascade.costs.haiku' => [
                'input_per_token' => 0.000001,
                'output_per_token' => 0.000002,
            ],
        ]);

        $calculator = new LlmCostCalculator;

        $this->assertSame(0.000007, $calculator->estimate(LlmCascadeTier::Haiku->value, 3, 2));
    }
}
