<?php

namespace App\Services\Routing;

use App\Contracts\RoutingConditionMatcherInterface;
use App\DTO\RoutingAssessmentContextDTO;
use App\Enums\RoutingConditionOperator;
use App\Enums\RoutingConditionType;
use App\Models\RoutingCondition;
use Carbon\CarbonInterface;

class RoutingConditionMatcher implements RoutingConditionMatcherInterface
{
    public function matches(RoutingCondition $condition, RoutingAssessmentContextDTO $context): bool
    {
        return match ($condition->condition_type) {
            RoutingConditionType::Project => $this->matchProject($condition, $context),
            RoutingConditionType::Person => $this->matchPerson($condition, $context),
            RoutingConditionType::ThreatLevel => $this->matchScalar(
                $context->threatResult->threat_level->value,
                $condition->operator,
                $condition->value,
            ),
            RoutingConditionType::SourceType => $this->matchScalar(
                $context->source->type->value,
                $condition->operator,
                $condition->value,
            ),
            RoutingConditionType::SourceId => $this->matchScalar(
                (string) $context->source->id,
                $condition->operator,
                $condition->value,
            ),
            RoutingConditionType::TimeOfDay => $this->matchTimeRange(
                $context->evaluatedAt,
                $condition->operator,
                $condition->value,
            ),
            RoutingConditionType::WorkingHours => $this->matchWorkingHours(
                $context->evaluatedAt,
                $condition->operator,
                $condition->value,
            ),
            RoutingConditionType::NightMode => $this->matchNightMode(
                $context->evaluatedAt,
                $condition->operator,
                $condition->value,
            ),
        };
    }

    private function matchProject(RoutingCondition $condition, RoutingAssessmentContextDTO $context): bool
    {
        return $this->matchScalar(
            (string) $context->mention->project_id,
            $condition->operator,
            $condition->value,
        );
    }

    private function matchPerson(RoutingCondition $condition, RoutingAssessmentContextDTO $context): bool
    {
        $personId = $context->mention->person_id;

        if ($personId === null) {
            return $condition->operator === RoutingConditionOperator::NotIn
                || $condition->operator === RoutingConditionOperator::NotBetween;
        }

        return $this->matchScalar((string) $personId, $condition->operator, $condition->value);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $configuredValue
     */
    private function matchScalar(string $actual, RoutingConditionOperator $operator, array $configuredValue): bool
    {
        $expected = $this->normalizeExpectedValues($configuredValue);

        return match ($operator) {
            RoutingConditionOperator::Equals => count($expected) === 1 && $actual === (string) $expected[0],
            RoutingConditionOperator::In => in_array($actual, array_map('strval', $expected), true),
            RoutingConditionOperator::NotIn => ! in_array($actual, array_map('strval', $expected), true),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $configuredValue
     */
    private function matchTimeRange(
        CarbonInterface $evaluatedAt,
        RoutingConditionOperator $operator,
        array $configuredValue,
    ): bool {
        $start = (string) ($configuredValue['start'] ?? config('routing.default_night_mode.start'));
        $end = (string) ($configuredValue['end'] ?? config('routing.default_night_mode.end'));
        $timezone = (string) ($configuredValue['timezone'] ?? config('routing.timezone'));

        $localTime = $evaluatedAt->copy()->timezone($timezone);
        $isWithinRange = $this->isTimeWithinRange($localTime, $start, $end);

        return match ($operator) {
            RoutingConditionOperator::Between => $isWithinRange,
            RoutingConditionOperator::NotBetween => ! $isWithinRange,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $configuredValue
     */
    private function matchWorkingHours(
        CarbonInterface $evaluatedAt,
        RoutingConditionOperator $operator,
        array $configuredValue,
    ): bool {
        $start = (string) ($configuredValue['start'] ?? config('routing.default_working_hours.start'));
        $end = (string) ($configuredValue['end'] ?? config('routing.default_working_hours.end'));
        $timezone = (string) ($configuredValue['timezone'] ?? config('routing.timezone'));
        /** @var list<int> $weekdays */
        $weekdays = $configuredValue['weekdays'] ?? config('routing.default_working_hours.weekdays');

        $localTime = $evaluatedAt->copy()->timezone($timezone);
        $isWeekday = in_array($localTime->dayOfWeekIso, $weekdays, true);
        $isWithinHours = $this->isTimeWithinRange($localTime, $start, $end);
        $isWorkingHours = $isWeekday && $isWithinHours;

        return match ($operator) {
            RoutingConditionOperator::Between => $isWorkingHours,
            RoutingConditionOperator::NotBetween => ! $isWorkingHours,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $configuredValue
     */
    private function matchNightMode(
        CarbonInterface $evaluatedAt,
        RoutingConditionOperator $operator,
        array $configuredValue,
    ): bool {
        return $this->matchTimeRange($evaluatedAt, $operator, $configuredValue);
    }

    private function isTimeWithinRange(CarbonInterface $localTime, string $start, string $end): bool
    {
        $currentMinutes = ($localTime->hour * 60) + $localTime->minute;
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));

        $startMinutes = ($startHour * 60) + $startMinute;
        $endMinutes = ($endHour * 60) + $endMinute;

        if ($startMinutes <= $endMinutes) {
            return $currentMinutes >= $startMinutes && $currentMinutes < $endMinutes;
        }

        return $currentMinutes >= $startMinutes || $currentMinutes < $endMinutes;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $configuredValue
     * @return list<mixed>
     */
    private function normalizeExpectedValues(array $configuredValue): array
    {
        if (array_key_exists('values', $configuredValue) && is_array($configuredValue['values'])) {
            return array_values($configuredValue['values']);
        }

        if (array_key_exists('value', $configuredValue)) {
            return [$configuredValue['value']];
        }

        return array_is_list($configuredValue) ? $configuredValue : [$configuredValue];
    }
}
