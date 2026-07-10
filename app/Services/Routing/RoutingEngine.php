<?php

namespace App\Services\Routing;

use App\Contracts\MentionRouterInterface;
use App\Contracts\RoutingConditionMatcherInterface;
use App\Contracts\RoutingEngineInterface;
use App\Contracts\RoutingRuleRepositoryInterface;
use App\DTO\RoutingAssessmentContextDTO;
use App\DTO\RoutingDecisionDTO;
use App\DTO\RoutingTargetDecisionDTO;
use App\Enums\RoutingChannel;
use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingTargetType;
use App\Exceptions\RoutingConfigurationException;
use App\Models\RoutingRule;
use App\Models\RoutingTarget;

class RoutingEngine implements RoutingEngineInterface, MentionRouterInterface
{
    public function __construct(
        private readonly RoutingRuleRepositoryInterface $ruleRepository,
        private readonly RoutingConditionMatcherInterface $conditionMatcher,
    ) {}

    public function route(RoutingAssessmentContextDTO $context): RoutingDecisionDTO
    {
        $matchedRule = $this->resolveMatchedRule($context);

        if ($matchedRule === null) {
            throw new RoutingConfigurationException('No routing rule matched and no fallback rule is configured.');
        }

        return $this->buildDecision($matchedRule, $context);
    }

    private function resolveMatchedRule(RoutingAssessmentContextDTO $context): ?RoutingRule
    {
        $rules = $this->ruleRepository->activeRules(
            $context->mention->project_id,
            $context->mention->person_id,
        );

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $context)) {
                return $rule;
            }
        }

        return $this->ruleRepository->fallbackRule($context->mention->project_id);
    }

    private function ruleMatches(RoutingRule $rule, RoutingAssessmentContextDTO $context): bool
    {
        if ($rule->project_id !== null && $rule->project_id !== $context->mention->project_id) {
            return false;
        }

        if ($rule->person_id !== null && $rule->person_id !== $context->mention->person_id) {
            return false;
        }

        if ($rule->conditions->isEmpty()) {
            return ! $rule->is_fallback;
        }

        foreach ($rule->conditions as $condition) {
            if (! $this->conditionMatcher->matches($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    private function buildDecision(RoutingRule $rule, RoutingAssessmentContextDTO $context): RoutingDecisionDTO
    {
        $targets = $this->resolveTargets($rule);
        $reason = $this->resolveReason($rule, $context);
        $shouldNotify = $this->shouldNotify($rule, $targets);

        return new RoutingDecisionDTO(
            shouldNotify: $shouldNotify,
            priority: $rule->routing_priority,
            channel: $this->resolveLegacyChannel($targets, $shouldNotify),
            reason: $reason,
            routingRuleId: $rule->id,
            deliveryMode: $rule->delivery_mode,
            skipModeration: $rule->skip_moderation,
            targets: $targets,
        );
    }

    /**
     * @return list<RoutingTargetDecisionDTO>
     */
    private function resolveTargets(RoutingRule $rule): array
    {
        return $rule->targets
            ->map(fn (RoutingTarget $target): RoutingTargetDecisionDTO => new RoutingTargetDecisionDTO(
                targetType: $target->target_type,
                targetConfig: $target->target_config,
                sortOrder: $target->sort_order,
            ))
            ->values()
            ->all();
    }

    private function resolveReason(RoutingRule $rule, RoutingAssessmentContextDTO $context): string
    {
        if (is_string($rule->reason_template) && $rule->reason_template !== '') {
            return strtr($rule->reason_template, [
                '{threat_level}' => $context->threatResult->threat_level->value,
                '{threat_score}' => (string) $context->threatResult->threat_score,
                '{rule_name}' => $rule->name,
            ]);
        }

        return sprintf(
            'Matched routing rule "%s" for threat level %s.',
            $rule->name,
            $context->threatResult->threat_level->value,
        );
    }

    /**
     * @param  list<RoutingTargetDecisionDTO>  $targets
     */
    private function shouldNotify(RoutingRule $rule, array $targets): bool
    {
        if ($rule->auto_skip || $rule->delivery_mode === RoutingDeliveryMode::Skip) {
            return false;
        }

        if ($rule->delivery_mode !== RoutingDeliveryMode::Immediate) {
            return false;
        }

        foreach ($targets as $target) {
            if ($target->targetType->isTelegram()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<RoutingTargetDecisionDTO>  $targets
     */
    private function resolveLegacyChannel(array $targets, bool $shouldNotify): RoutingChannel
    {
        if (! $shouldNotify) {
            return RoutingChannel::None;
        }

        foreach ($targets as $target) {
            if ($target->targetType === RoutingTargetType::TelegramModeration) {
                return RoutingChannel::Notification;
            }
        }

        foreach ($targets as $target) {
            if ($target->targetType === RoutingTargetType::TelegramDelivery) {
                return RoutingChannel::Notification;
            }
        }

        return RoutingChannel::None;
    }
}
