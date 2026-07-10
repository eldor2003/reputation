<?php

namespace App\Services\Threat;

use App\Contracts\ThreatEngineInterface;
use App\Contracts\ThreatFactorScorerInterface;
use App\Contracts\ThreatFactorWeightRepositoryInterface;
use App\Contracts\ThreatRuleRepositoryInterface;
use App\DTO\ThreatAssessmentContextDTO;
use App\DTO\ThreatFactorDTO;
use App\DTO\ThreatResultDTO;
use App\DTO\ThreatScoreDTO;
use App\Enums\ThreatLevel;
use App\Exceptions\ThreatConfigurationException;
use App\Models\ThreatRule;

class ThreatEngine implements ThreatEngineInterface
{
    public function __construct(
        private readonly ThreatFactorWeightRepositoryInterface $factorWeightRepository,
        private readonly ThreatRuleRepositoryInterface $ruleRepository,
        private readonly ThreatFactorScorerInterface $factorScorer,
    ) {}

    public function evaluate(ThreatAssessmentContextDTO $context): ThreatResultDTO
    {
        $score = $this->calculateScore($context);
        $level = $this->resolveThreatLevel($score->totalScore, $context->mention->project_id);

        return new ThreatResultDTO(
            threatLevel: $level,
            threatScore: round($score->totalScore, 2),
            factors: $score->factors,
        );
    }

    private function calculateScore(ThreatAssessmentContextDTO $context): ThreatScoreDTO
    {
        $weights = $this->factorWeightRepository->activeWeights($context->mention->project_id);

        if ($weights->isEmpty()) {
            throw new ThreatConfigurationException('No active threat factor weights are configured.');
        }

        $factors = [];
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($weights as $weight) {
            $scoringConfig = is_array($weight->scoring_config) ? $weight->scoring_config : [];
            $factorScore = $this->factorScorer->score($weight->factor_key, $scoringConfig, $context);
            $factorWeight = (float) $weight->weight;
            $weightedScore = $factorScore * $factorWeight;

            $factors[] = new ThreatFactorDTO(
                key: $weight->factor_key,
                rawValue: $this->factorScorer->extractRawValue($weight->factor_key, $context),
                score: round($factorScore, 2),
                weight: $factorWeight,
                weightedScore: round($weightedScore, 4),
            );

            $weightedSum += $weightedScore;
            $totalWeight += $factorWeight;
        }

        $totalScore = $totalWeight > 0 ? ($weightedSum / $totalWeight) : 0.0;

        return new ThreatScoreDTO(
            totalScore: round($totalScore, 2),
            factors: $factors,
        );
    }

    private function resolveThreatLevel(float $score, int $projectId): ThreatLevel
    {
        $rules = $this->ruleRepository->activeRules($projectId);

        if ($rules->isEmpty()) {
            throw new ThreatConfigurationException('No active threat rules are configured.');
        }

        /** @var ThreatRule|null $matchedRule */
        $matchedRule = $rules
            ->sortBy('priority')
            ->filter(fn (ThreatRule $rule): bool => $score >= (float) $rule->min_score)
            ->first();

        if ($matchedRule === null) {
            return ThreatLevel::P4;
        }

        return ThreatLevel::from($matchedRule->level);
    }
}
