<?php

namespace App\Services\Threat;

use App\Contracts\ThreatFactorScorerInterface;
use App\DTO\ThreatAssessmentContextDTO;
use App\Enums\SourceType;
use App\Enums\ThreatFactorKey;
use Carbon\Carbon;

class ConfigurableThreatFactorScorer implements ThreatFactorScorerInterface
{
    public function extractRawValue(string $factorKey, ThreatAssessmentContextDTO $context): mixed
    {
        return match ($factorKey) {
            ThreatFactorKey::Sentiment->value => $context->aiResult->sentiment,
            ThreatFactorKey::Severity->value => $context->aiResult->severity,
            ThreatFactorKey::SourceCredibility->value => $this->resolveSourceCredibilityKey($context),
            ThreatFactorKey::SerpVisibility->value => $context->serpTopPosition,
            ThreatFactorKey::ClusterSize->value => $context->clusterSize,
            ThreatFactorKey::MentionRecency->value => $this->resolveRecencyHours($context),
            ThreatFactorKey::PersonImportance->value => $this->resolvePersonImportance($context),
            default => null,
        };
    }

    public function score(string $factorKey, array $scoringConfig, ThreatAssessmentContextDTO $context): float
    {
        if ($factorKey === ThreatFactorKey::SourceCredibility->value) {
            $configScore = $context->source->config['credibility_score'] ?? null;

            if (is_numeric($configScore)) {
                return max(0, min(100, (float) $configScore));
            }
        }

        $rawValue = $this->extractRawValue($factorKey, $context);
        $type = (string) ($scoringConfig['type'] ?? 'default');

        return match ($type) {
            'map' => $this->scoreMap($rawValue, $scoringConfig),
            'linear' => $this->scoreLinear($rawValue, $scoringConfig),
            'thresholds' => $this->scoreThresholds($rawValue, $scoringConfig),
            'recency_hours' => $this->scoreRecencyHours($rawValue, $scoringConfig),
            'position' => $this->scorePosition($rawValue, $scoringConfig),
            'metadata' => $this->scoreMetadata($rawValue, $scoringConfig),
            default => (float) ($scoringConfig['default'] ?? 0),
        };
    }

    private function resolveSourceCredibilityKey(ThreatAssessmentContextDTO $context): string
    {
        if ($context->source->type instanceof SourceType) {
            return $context->source->type->value;
        }

        return (string) $context->source->type;
    }

    private function resolveRecencyHours(ThreatAssessmentContextDTO $context): ?float
    {
        $reference = $context->mention->published_at ?? $context->mention->received_at;

        if ($reference === null) {
            return null;
        }

        return Carbon::parse($reference)->diffInMinutes(now(), absolute: true) / 60;
    }

    private function resolvePersonImportance(ThreatAssessmentContextDTO $context): ?float
    {
        $metadata = $context->person?->metadata;

        if (! is_array($metadata)) {
            return null;
        }

        $importance = $metadata['importance_score'] ?? null;

        return is_numeric($importance) ? (float) $importance : null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scoreMap(mixed $rawValue, array $config): float
    {
        $values = is_array($config['values'] ?? null) ? $config['values'] : [];
        $key = is_string($rawValue) || is_numeric($rawValue) ? (string) $rawValue : 'unknown';

        if (isset($values[$key]) && is_numeric($values[$key])) {
            return (float) $values[$key];
        }

        return (float) ($config['default'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scoreLinear(mixed $rawValue, array $config): float
    {
        if (! is_numeric($rawValue)) {
            return (float) ($config['default'] ?? 0);
        }

        $min = (float) ($config['min'] ?? 0);
        $max = (float) ($config['max'] ?? 1);
        $multiplier = (float) ($config['multiplier'] ?? 1);
        $value = (float) $rawValue;

        if ($max <= $min) {
            return (float) ($config['default'] ?? 0);
        }

        $normalized = max($min, min($max, $value));

        return $normalized * $multiplier;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scoreThresholds(mixed $rawValue, array $config): float
    {
        if (! is_numeric($rawValue)) {
            return (float) ($config['default'] ?? 0);
        }

        $value = (float) $rawValue;
        $thresholds = is_array($config['thresholds'] ?? null) ? $config['thresholds'] : [];

        usort($thresholds, fn (array $left, array $right): int => ((float) ($right['min'] ?? 0)) <=> ((float) ($left['min'] ?? 0)));

        foreach ($thresholds as $threshold) {
            if (! is_array($threshold)) {
                continue;
            }

            if ($value >= (float) ($threshold['min'] ?? PHP_FLOAT_MAX)) {
                return (float) ($threshold['score'] ?? 0);
            }
        }

        return (float) ($config['default'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scoreRecencyHours(mixed $rawValue, array $config): float
    {
        if (! is_numeric($rawValue)) {
            return (float) ($config['default'] ?? 0);
        }

        $hours = (float) $rawValue;
        $thresholds = is_array($config['thresholds'] ?? null) ? $config['thresholds'] : [];

        usort($thresholds, fn (array $left, array $right): int => ((float) ($left['max_hours'] ?? PHP_FLOAT_MAX)) <=> ((float) ($right['max_hours'] ?? PHP_FLOAT_MAX)));

        foreach ($thresholds as $threshold) {
            if (! is_array($threshold)) {
                continue;
            }

            if ($hours <= (float) ($threshold['max_hours'] ?? PHP_FLOAT_MAX)) {
                return (float) ($threshold['score'] ?? 0);
            }
        }

        return (float) ($config['default'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scorePosition(mixed $rawValue, array $config): float
    {
        if (! is_numeric($rawValue)) {
            return (float) ($config['default'] ?? 0);
        }

        $position = (int) $rawValue;
        $thresholds = is_array($config['thresholds'] ?? null) ? $config['thresholds'] : [];

        usort($thresholds, fn (array $left, array $right): int => ((float) ($left['max_position'] ?? PHP_FLOAT_MAX)) <=> ((float) ($right['max_position'] ?? PHP_FLOAT_MAX)));

        foreach ($thresholds as $threshold) {
            if (! is_array($threshold)) {
                continue;
            }

            if ($position <= (int) ($threshold['max_position'] ?? PHP_INT_MAX)) {
                return (float) ($threshold['score'] ?? 0);
            }
        }

        return (float) ($config['default'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scoreMetadata(mixed $rawValue, array $config): float
    {
        if (! is_numeric($rawValue)) {
            return (float) ($config['default'] ?? 0);
        }

        return max(0, min(100, (float) $rawValue));
    }
}
