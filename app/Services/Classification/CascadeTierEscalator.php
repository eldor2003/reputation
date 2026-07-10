<?php

namespace App\Services\Classification;

use App\Enums\LlmCascadeTier;

class CascadeTierEscalator
{
    public function next(string $currentTier): ?string
    {
        $order = config('cascade.order', []);
        $index = array_search($currentTier, $order, true);

        if ($index === false) {
            return null;
        }

        return isset($order[$index + 1]) ? (string) $order[$index + 1] : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forcedSelectionRules(string $tier): array
    {
        return [
            ['tier' => $tier],
        ];
    }

    public function defaultEscalationTier(string $currentTier): string
    {
        return $this->next($currentTier)
            ?? LlmCascadeTier::Opus->value;
    }
}
