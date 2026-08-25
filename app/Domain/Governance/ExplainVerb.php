<?php

namespace App\Domain\Governance;

/**
 * The explain verb.
 *
 * Explaining is the step where the system stops reporting numbers and starts making
 * assertions, so it is the step that needs grounding. An explanation is accepted only
 * when its narrative is built from claims, and every claim survives GroundedClaims.
 *
 * The narrative and the claims are kept as separate fields on purpose. The narrative
 * is what a teacher reads; the claims are what the platform is prepared to defend.
 * Keeping them apart is what makes it possible to render "Academic risk detected
 * because …" with each clause linked back to the evidence that produced it.
 */
class ExplainVerb
{
    public const RULE_NARRATIVE_PRESENT = 'explain.narrative_present';

    public const RULE_NARRATIVE_COVERED = 'explain.narrative_covered';

    public const RULE_AUDIENCE_VALID = 'explain.audience_valid';

    private const VALID_AUDIENCES = ['teacher', 'admin', 'parent', 'student'];

    public function __construct(private readonly GroundedClaims $groundedClaims)
    {
    }

    /**
     * @param  array{narrative?:string, claims?:array, audience?:string, is_generated?:bool}  $draft
     */
    public function validate(
        array $draft,
        int $subInstituteId,
        ?string $subjectEntityKey = null,
        int|string|null $subjectId = null
    ): GovernanceReport {
        $violations = [];
        $warnings = [];
        $passed = [];

        $narrative = trim((string) ($draft['narrative'] ?? ''));

        if ($narrative === '') {
            $violations[] = [
                'rule' => self::RULE_NARRATIVE_PRESENT,
                'message' => 'An explanation needs a narrative a person can read.',
            ];
        } else {
            $passed[] = self::RULE_NARRATIVE_PRESENT;
        }

        $audience = strtolower((string) ($draft['audience'] ?? 'teacher'));

        if (! in_array($audience, self::VALID_AUDIENCES, true)) {
            $violations[] = [
                'rule' => self::RULE_AUDIENCE_VALID,
                'message' => sprintf(
                    'Audience must be one of: %s.',
                    implode(', ', self::VALID_AUDIENCES)
                ),
                'context' => ['audience' => $audience],
            ];
        } else {
            $passed[] = self::RULE_AUDIENCE_VALID;
        }

        $claims = is_array($draft['claims'] ?? null) ? $draft['claims'] : [];

        $groundingReport = $this->groundedClaims->validate(
            $claims,
            $subInstituteId,
            $subjectEntityKey,
            $subjectId
        );

        $violations = array_merge($violations, $groundingReport->violations);
        $warnings = array_merge($warnings, $groundingReport->warnings);
        $passed = array_merge($passed, $groundingReport->passedRules);

        // A narrative that asserts more than the claims cover is how ungrounded
        // sentences slip through: the claims validate, but the prose says more.
        if ($narrative !== '' && $claims !== [] && $this->narrativeExceedsClaims($narrative, $claims)) {
            $warnings[] = [
                'rule' => self::RULE_NARRATIVE_COVERED,
                'message' => 'The narrative is substantially longer than its claims; check that it asserts nothing uncited.',
            ];
        }

        return $violations === []
            ? GovernanceReport::pass(array_values(array_unique($passed)), $warnings, Verb::Explain)
            : GovernanceReport::fail($violations, array_values(array_unique($passed)), $warnings, Verb::Explain);
    }

    /**
     * Build a narrative from validated claims.
     *
     * Used when no generation step is warranted — which is most of the time. A
     * deterministic sentence assembled from evidence is preferable to a generated
     * one, because it cannot drift from what the evidence says.
     */
    public function composeNarrative(array $claims, ?string $subjectLabel = null): string
    {
        $statements = [];

        foreach ($claims as $claim) {
            $text = trim((string) ($claim['claim'] ?? $claim['text'] ?? ''));

            if ($text !== '') {
                $statements[] = rtrim($text, '.');
            }
        }

        if ($statements === []) {
            return '';
        }

        $subject = $subjectLabel ? $subjectLabel . ': ' : '';

        if (count($statements) === 1) {
            return $subject . $statements[0] . '.';
        }

        $last = array_pop($statements);

        return $subject . implode(', ', $statements) . ', and ' . $last . '.';
    }

    /**
     * Rough coverage heuristic. Not a semantic check — it only flags the case where
     * the prose is far longer than the claims that justify it, which is the shape an
     * over-generated explanation takes.
     */
    private function narrativeExceedsClaims(string $narrative, array $claims): bool
    {
        $claimLength = 0;

        foreach ($claims as $claim) {
            $claimLength += mb_strlen((string) ($claim['claim'] ?? $claim['text'] ?? ''));
        }

        if ($claimLength === 0) {
            return true;
        }

        return mb_strlen($narrative) > ($claimLength * 2.5);
    }
}
