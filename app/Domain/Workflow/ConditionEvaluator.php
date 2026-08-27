<?php

namespace App\Domain\Workflow;

/**
 * Evaluates the conditions that gate a workflow or a branch.
 *
 * Conditions are data, not code — they arrive from `workflow_definitions.conditions`
 * and from step `config`, which means an administrator can change a branch without a
 * deploy. That also means they must be evaluated safely: there is no expression
 * language here and nothing is ever eval'd. A condition is a triple of
 * {field, operator, value}, resolved against the run's accumulated state.
 *
 * Unknown operators and unresolvable fields evaluate to false, so a malformed
 * condition blocks a branch rather than accidentally opening one.
 */
class ConditionEvaluator
{
    private const OPERATORS = [
        'eq', 'neq', 'gt', 'gte', 'lt', 'lte',
        'in', 'not_in', 'contains', 'exists', 'not_exists', 'between',
    ];

    /**
     * All conditions must hold (AND). An empty set passes — "no conditions" means
     * "nothing blocking", not "never run".
     *
     * @param  array<int, array{field:string, operator:string, value?:mixed}>  $conditions
     */
    public function passes(array $conditions, array $state): bool
    {
        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                return false;
            }

            // A nested {any: [...]} group is an OR.
            if (isset($condition['any']) && is_array($condition['any'])) {
                if (! $this->anyPasses($condition['any'], $state)) {
                    return false;
                }

                continue;
            }

            if (! $this->evaluate($condition, $state)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, array> $conditions */
    public function anyPasses(array $conditions, array $state): bool
    {
        foreach ($conditions as $condition) {
            if (is_array($condition) && $this->evaluate($condition, $state)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Explain a failing condition set, so a stuck run can say why rather than just
     * sitting there.
     *
     * @return array<int, string>
     */
    public function explainFailures(array $conditions, array $state): array
    {
        $failures = [];

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                $failures[] = 'A malformed condition was skipped.';

                continue;
            }

            if (isset($condition['any'])) {
                if (! $this->anyPasses($condition['any'], $state)) {
                    $failures[] = 'None of the alternative conditions were met.';
                }

                continue;
            }

            if (! $this->evaluate($condition, $state)) {
                $failures[] = sprintf(
                    'Condition failed: %s %s %s.',
                    $condition['field'] ?? '(no field)',
                    $condition['operator'] ?? '(no operator)',
                    $this->stringify($condition['value'] ?? null)
                );
            }
        }

        return $failures;
    }

    private function evaluate(array $condition, array $state): bool
    {
        $operator = strtolower((string) ($condition['operator'] ?? ''));

        if (! in_array($operator, self::OPERATORS, true)) {
            return false;
        }

        $field = (string) ($condition['field'] ?? '');
        $actual = $this->resolve($field, $state);
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'exists' => $actual !== null,
            'not_exists' => $actual === null,
            'eq' => $this->looseEquals($actual, $expected),
            'neq' => ! $this->looseEquals($actual, $expected),
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'in' => is_array($expected) && $this->inArrayLoose($actual, $expected),
            'not_in' => is_array($expected) && ! $this->inArrayLoose($actual, $expected),
            'contains' => $this->contains($actual, $expected),
            'between' => $this->between($actual, $expected),
            default => false,
        };
    }

    /**
     * Dot-path lookup into the run state: "case.severity", "signal.score".
     */
    private function resolve(string $field, array $state): mixed
    {
        if ($field === '') {
            return null;
        }

        $value = $state;

        foreach (explode('.', $field) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            if (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};

                continue;
            }

            return null;
        }

        return $value;
    }

    private function looseEquals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($expected) || is_bool($actual)) {
            return (bool) $actual === (bool) $expected;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        if (is_scalar($actual) && is_scalar($expected)) {
            return (string) $actual === (string) $expected;
        }

        return $actual === $expected;
    }

    private function inArrayLoose(mixed $actual, array $expected): bool
    {
        foreach ($expected as $candidate) {
            if ($this->looseEquals($actual, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return $this->inArrayLoose($expected, $actual);
        }

        if (is_string($actual) && is_scalar($expected)) {
            return str_contains(mb_strtolower($actual), mb_strtolower((string) $expected));
        }

        return false;
    }

    private function between(mixed $actual, mixed $expected): bool
    {
        if (! is_array($expected) || count($expected) !== 2 || ! is_numeric($actual)) {
            return false;
        }

        [$low, $high] = array_values($expected);

        if (! is_numeric($low) || ! is_numeric($high)) {
            return false;
        }

        return (float) $actual >= (float) $low && (float) $actual <= (float) $high;
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', array_map(fn ($item) => $this->stringify($item), $value)) . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value === null ? 'null' : (string) $value;
    }
}
