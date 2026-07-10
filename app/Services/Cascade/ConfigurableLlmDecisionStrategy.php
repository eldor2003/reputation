<?php

namespace App\Services\Cascade;

use App\Contracts\LLMDecisionStrategyInterface;
use App\DTO\ClassificationResultDTO;
use App\DTO\NormalizedMentionDTO;

class ConfigurableLlmDecisionStrategy implements LLMDecisionStrategyInterface
{
    public function selectInitialModel(NormalizedMentionDTO $mention): string
    {
        $textLength = mb_strlen(trim($mention->text));
        $rules = config('cascade.initial_selection.rules', []);

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $maxTextLength = $rule['max_text_length'] ?? null;

            if ($maxTextLength === null || $textLength <= (int) $maxTextLength) {
                return (string) $rule['tier'];
            }
        }

        $order = config('cascade.order', []);

        return (string) ($order[array_key_last($order)] ?? config('cascade.fallback.tier'));
    }

    public function shouldEscalate(ClassificationResultDTO $result, string $currentTier): ?string
    {
        if (! (bool) config('cascade.escalation.enabled', true)) {
            return null;
        }

        $rules = config("cascade.escalation.rules.{$currentTier}");

        if (! is_array($rules)) {
            return null;
        }

        $maxConfidence = (int) ($rules['max_confidence'] ?? 100);
        $minSeverity = (int) ($rules['escalate_on_severity_min'] ?? PHP_INT_MAX);
        $shouldEscalate = $result->confidence <= $maxConfidence
            || $result->severity >= $minSeverity;

        if (! $shouldEscalate) {
            return null;
        }

        return isset($rules['to']) ? (string) $rules['to'] : null;
    }

    public function buildEscalationReason(ClassificationResultDTO $result, string $fromTier, string $toTier): string
    {
        $rules = config("cascade.escalation.rules.{$fromTier}", []);
        $reasons = [];

        if ($result->confidence <= (int) ($rules['max_confidence'] ?? 100)) {
            $reasons[] = "confidence {$result->confidence} below threshold";
        }

        if ($result->severity >= (int) ($rules['escalate_on_severity_min'] ?? PHP_INT_MAX)) {
            $reasons[] = "severity {$result->severity} above threshold";
        }

        if ($reasons === []) {
            return "Escalated from {$fromTier} to {$toTier}.";
        }

        return 'Escalated from '.$fromTier.' to '.$toTier.': '.implode('; ', $reasons).'.';
    }
}
