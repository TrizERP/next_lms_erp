<?php

namespace App\Domain\Governance;

/**
 * ESO binding — Expected Service Outcome.
 *
 * A recommendation that cannot say what it is *for* cannot be measured, and a
 * recommendation that cannot be measured cannot close the learning loop. This rule
 * forces every drafted action to declare three things before a human is asked to
 * approve it:
 *
 *   objective — what should change, in the language of the business
 *   strategy  — the mechanism by which it changes
 *   outcome   — a named metric, a direction, and a horizon
 *
 * The outcome half is what `ai_outcomes` is later populated from, which is why the
 * metric key and the measurement window are mandatory rather than descriptive. A
 * recommendation whose success could never be checked is rejected here, before it
 * reaches a teacher's approval queue.
 *
 * Authored for K-12 from the platform's stated governance semantics; the G2G
 * people-competency project holds the original of this rule and the two should be
 * reconciled when that repository is available.
 */
class EsoBindingRule
{
    public const RULE_BINDING_PRESENT = 'eso.binding_present';

    public const RULE_OBJECTIVE_PRESENT = 'eso.objective_present';

    public const RULE_STRATEGY_PRESENT = 'eso.strategy_present';

    public const RULE_OUTCOME_MEASURABLE = 'eso.outcome_measurable';

    public const RULE_HORIZON_VALID = 'eso.horizon_valid';

    /** A measurement window outside this range is not a horizon, it is a wish. */
    private const MIN_HORIZON_DAYS = 1;

    private const MAX_HORIZON_DAYS = 365;

    private const VALID_DIRECTIONS = ['increase', 'decrease', 'maintain'];

    /**
     * @param  array{objective?:string, strategy?:string, outcome?:array}|null  $binding
     */
    public function validate(?array $binding): GovernanceReport
    {
        if (! is_array($binding) || $binding === []) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_BINDING_PRESENT,
                'message' => 'A recommendation must bind to an expected service outcome before it can be approved.',
            ]], [], [], Verb::Recommend);
        }

        $violations = [];
        $warnings = [];
        $passed = [self::RULE_BINDING_PRESENT];

        $objective = trim((string) ($binding['objective'] ?? ''));
        if ($objective === '') {
            $violations[] = [
                'rule' => self::RULE_OBJECTIVE_PRESENT,
                'message' => 'The ESO binding has no objective: state what should change.',
            ];
        } else {
            $passed[] = self::RULE_OBJECTIVE_PRESENT;
        }

        $strategy = trim((string) ($binding['strategy'] ?? ''));
        if ($strategy === '') {
            $violations[] = [
                'rule' => self::RULE_STRATEGY_PRESENT,
                'message' => 'The ESO binding has no strategy: state how the objective will be met.',
            ];
        } else {
            $passed[] = self::RULE_STRATEGY_PRESENT;
        }

        $outcome = $binding['outcome'] ?? null;

        if (! is_array($outcome)) {
            $violations[] = [
                'rule' => self::RULE_OUTCOME_MEASURABLE,
                'message' => 'The ESO binding has no measurable outcome.',
            ];

            return GovernanceReport::fail($violations, $passed, $warnings, Verb::Recommend);
        }

        $metricKey = trim((string) ($outcome['metric_key'] ?? ''));
        $direction = strtolower(trim((string) ($outcome['direction'] ?? '')));
        $horizonDays = $outcome['horizon_days'] ?? null;

        if ($metricKey === '') {
            $violations[] = [
                'rule' => self::RULE_OUTCOME_MEASURABLE,
                'message' => 'The expected outcome names no metric, so success could never be checked.',
            ];
        }

        if (! in_array($direction, self::VALID_DIRECTIONS, true)) {
            $violations[] = [
                'rule' => self::RULE_OUTCOME_MEASURABLE,
                'message' => sprintf(
                    'The expected outcome direction must be one of: %s.',
                    implode(', ', self::VALID_DIRECTIONS)
                ),
                'context' => ['direction' => $direction],
            ];
        }

        if (! is_numeric($horizonDays)) {
            $violations[] = [
                'rule' => self::RULE_HORIZON_VALID,
                'message' => 'The expected outcome needs a measurement horizon in days.',
            ];
        } elseif ((int) $horizonDays < self::MIN_HORIZON_DAYS || (int) $horizonDays > self::MAX_HORIZON_DAYS) {
            $violations[] = [
                'rule' => self::RULE_HORIZON_VALID,
                'message' => sprintf(
                    'The measurement horizon must be between %d and %d days.',
                    self::MIN_HORIZON_DAYS,
                    self::MAX_HORIZON_DAYS
                ),
                'context' => ['horizon_days' => $horizonDays],
            ];
        }

        // A target value is not mandatory — "increase" is a legitimate goal on its
        // own — but without one the outcome can only be scored as a direction.
        if (! isset($outcome['target_value']) && $direction !== 'maintain') {
            $warnings[] = [
                'rule' => self::RULE_OUTCOME_MEASURABLE,
                'message' => 'No target value was set, so the outcome can only be scored by direction of travel.',
            ];
        }

        if ($violations === []) {
            $passed[] = self::RULE_OUTCOME_MEASURABLE;
            $passed[] = self::RULE_HORIZON_VALID;

            return GovernanceReport::pass($passed, $warnings, Verb::Recommend);
        }

        return GovernanceReport::fail($violations, $passed, $warnings, Verb::Recommend);
    }

    /**
     * Turn a validated binding into the row `ai_outcomes` is seeded with when the
     * recommendation is approved. Keeping this here means the measurement plan comes
     * from the same place that validated it.
     */
    public function toOutcomePlan(array $binding, string $subjectEntityKey, int|string $subjectId): array
    {
        $outcome = $binding['outcome'] ?? [];
        $horizonDays = (int) ($outcome['horizon_days'] ?? 30);

        return [
            'subject_entity_key' => $subjectEntityKey,
            'subject_id' => $subjectId,
            'metric_key' => (string) ($outcome['metric_key'] ?? ''),
            'metric_label' => $outcome['metric_label'] ?? ($binding['objective'] ?? null),
            'target_value' => isset($outcome['target_value']) ? (float) $outcome['target_value'] : null,
            'measure_after' => now()->addDays($horizonDays),
            'status' => 'pending',
            'detail' => [
                'objective' => $binding['objective'] ?? null,
                'strategy' => $binding['strategy'] ?? null,
                'direction' => $outcome['direction'] ?? null,
                'horizon_days' => $horizonDays,
            ],
        ];
    }
}
