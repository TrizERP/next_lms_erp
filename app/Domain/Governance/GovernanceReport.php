<?php

namespace App\Domain\Governance;

/**
 * The verdict of a governance check, and why.
 *
 * A report is always produced — including on success — and is stored on the row it
 * judged (`governance_report` on explanations, recommendations and agent runs). That
 * is what makes a refusal auditable rather than an unexplained absence: when an agent
 * declines to recommend, the reason is on the record.
 */
final class GovernanceReport
{
    /**
     * @param  array<int, array{rule:string,message:string,context?:array}>  $violations
     * @param  array<int, array{rule:string,message:string,context?:array}>  $warnings
     * @param  array<int, string>  $passedRules
     */
    private function __construct(
        public readonly bool $passed,
        public readonly array $violations = [],
        public readonly array $warnings = [],
        public readonly array $passedRules = [],
        public readonly ?Verb $verb = null,
    ) {
    }

    public static function pass(array $passedRules = [], array $warnings = [], ?Verb $verb = null): self
    {
        return new self(true, [], $warnings, $passedRules, $verb);
    }

    public static function fail(array $violations, array $passedRules = [], array $warnings = [], ?Verb $verb = null): self
    {
        return new self(false, $violations, $warnings, $passedRules, $verb);
    }

    /** Merge several rule results into one verdict. Any violation fails the whole. */
    public static function merge(self ...$reports): self
    {
        $violations = [];
        $warnings = [];
        $passedRules = [];
        $verb = null;

        foreach ($reports as $report) {
            $violations = array_merge($violations, $report->violations);
            $warnings = array_merge($warnings, $report->warnings);
            $passedRules = array_merge($passedRules, $report->passedRules);
            $verb ??= $report->verb;
        }

        return new self($violations === [], $violations, $warnings, array_values(array_unique($passedRules)), $verb);
    }

    public function withVerb(Verb $verb): self
    {
        return new self($this->passed, $this->violations, $this->warnings, $this->passedRules, $verb);
    }

    /** First violation message, for a one-line refusal shown to a user. */
    public function reason(): ?string
    {
        return $this->violations[0]['message'] ?? null;
    }

    public function violationRules(): array
    {
        return array_values(array_unique(array_column($this->violations, 'rule')));
    }

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'verb' => $this->verb?->value,
            'violations' => $this->violations,
            'warnings' => $this->warnings,
            'passed_rules' => $this->passedRules,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }
}
