<?php

namespace App\Actions;

use App\Contracts\LLMCascadeInterface;
use App\Contracts\PromptBuilderInterface;
use App\Contracts\PromptInjectionGuardInterface;
use App\DTO\LlmCascadeExecutionResultDTO;
use App\DTO\LlmCascadeResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\DTO\PersonMatchResultDTO;
use App\Services\Classification\CascadeTierEscalator;
use Illuminate\Support\Facades\Config;

class ExecuteLlmCascadeAction
{
    public function __construct(
        private readonly PromptInjectionGuardInterface $promptInjectionGuard,
        private readonly PromptBuilderInterface $promptBuilder,
        private readonly LLMCascadeInterface $llmCascade,
        private readonly CascadeTierEscalator $tierEscalator,
    ) {}

    public function execute(
        int $mentionId,
        NormalizedMentionDTO $mention,
        ?string $forcedMinimumTier = null,
        ?PersonMatchResultDTO $personMatch = null,
    ): LlmCascadeExecutionResultDTO {
        $guardResult = $this->promptInjectionGuard->scan($mention);
        $prompt = $this->promptBuilder->build($mention, $guardResult, $personMatch);

        $cascadeResult = $forcedMinimumTier !== null
            ? $this->classifyWithForcedTier($prompt, $mention, $mentionId, $forcedMinimumTier)
            : $this->llmCascade->classify($prompt, $mention, $mentionId);

        return new LlmCascadeExecutionResultDTO(
            cascadeResult: $cascadeResult,
            guardResult: $guardResult,
        );
    }

    private function classifyWithForcedTier(
        string $prompt,
        NormalizedMentionDTO $mention,
        int $mentionId,
        string $forcedTier,
    ): LlmCascadeResultDTO {
        $originalRules = config('cascade.initial_selection.rules');

        Config::set(
            'cascade.initial_selection.rules',
            $this->tierEscalator->forcedSelectionRules($forcedTier),
        );

        try {
            return $this->llmCascade->classify($prompt, $mention, $mentionId);
        } finally {
            Config::set('cascade.initial_selection.rules', $originalRules);
        }
    }
}
