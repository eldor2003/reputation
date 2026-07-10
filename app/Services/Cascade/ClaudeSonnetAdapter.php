<?php

namespace App\Services\Cascade;

use App\Enums\LlmCascadeTier;

class ClaudeSonnetAdapter extends AbstractClaudeModelAdapter
{
    protected function configKey(): string
    {
        return LlmCascadeTier::Sonnet->value;
    }
}
