<?php

namespace App\Services\Cascade;

use App\Enums\LlmCascadeTier;

class ClaudeHaikuAdapter extends AbstractClaudeModelAdapter
{
    protected function configKey(): string
    {
        return LlmCascadeTier::Haiku->value;
    }
}
