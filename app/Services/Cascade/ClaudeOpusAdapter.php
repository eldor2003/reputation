<?php

namespace App\Services\Cascade;

use App\Enums\LlmCascadeTier;

class ClaudeOpusAdapter extends AbstractClaudeModelAdapter
{
    protected function configKey(): string
    {
        return LlmCascadeTier::Opus->value;
    }
}
