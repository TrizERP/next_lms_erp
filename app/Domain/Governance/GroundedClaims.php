<?php

namespace App\Domain\Governance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The grounding rule: nothing the system asserts may float free of evidence.
 *
 * A claim is a sentence the platform is prepared to show a teacher as a reason —
 * "Mathematics assessment performance declined across the last three assessments".
 * This class is what stops that sentence being generated prose. Each claim must cite
 * evidence ids; each cited id must exist, belong to the same subject and tenant, and
 * be `verified`. Generated text can be *stored* as evidence, but it cannot be *cited*
 * until a human or a deterministic check has verified it.
 *
 * That last rule is the one that keeps the loop honest: without it, a model could
 * write a plausible reason, save it as evidence, and cite itself.
 */
class GroundedClaims
{
    public const RULE_CLAIMS_PRESENT = 'grounded.claims_present';

    public const RULE_CLAIM_HAS_CITATION = 'grounded.claim_has_citation';

    public const RULE_EVIDENCE_EXISTS = 'grounded.evidence_exists';

    public const RULE_EVIDENCE_IN_SCOPE = 'grounded.evidence_in_scope';

    public const RULE_EVIDENCE_VERIFIED = 'grounded.evidence_verified';

    public const RULE_EVIDENCE_SUBJECT_MATCH = 'grounded.evidence_subject_match';

    /**
     * Validate a set of claims against the evidence store.
     *
     * @param  array<int, array{claim?:string, text?:string, evidence_ids?:array, confidence?:float}>  $claims
     */
    public function validate(
        array $claims,
        int $subInstituteId,
        ?string $subjectEntityKey = null,
        int|string|null $subjectId = null,
        bool $allowUnverified = false
    ): GovernanceReport {
        $violations = [];
        $warnings = [];
        $passed = [];

        if ($claims === []) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_CLAIMS_PRESENT,
                'message' => 'An explanation must make at least one claim.',
            ]]);
        }

        $passed[] = self::RULE_CLAIMS_PRESENT;

        // Collect every cited id up front so the store is read once.
        $citedIds = [];
        foreach ($claims as $index => $claim) {
            $ids = $this->normalizeIds($claim['evidence_ids'] ?? []);

            if ($ids === []) {
                $violations[] = [
                    'rule' => self::RULE_CLAIM_HAS_CITATION,
                    'message' => sprintf(
                        'Claim %d cites no evidence: "%s".',
                        $index + 1,
                        $this->claimText($claim)
                    ),
                    'context' => ['claim_index' => $index],
                ];

                continue;
            }

            $citedIds = array_merge($citedIds, $ids);
        }

        $citedIds = array_values(array_unique($citedIds));

        if ($citedIds === []) {
            return GovernanceReport::fail($violations, $passed, $warnings);
        }

        if (! Schema::hasTable('ai_evidence')) {
            return GovernanceReport::fail([[
                'rule' => self::RULE_EVIDENCE_EXISTS,
                'message' => 'The evidence store is unavailable, so no claim can be grounded.',
            ]], $passed);
        }

        $records = DB::table('ai_evidence')
            ->whereIn('id', $citedIds)
            ->get()
            ->keyBy('id');

        foreach ($claims as $index => $claim) {
            foreach ($this->normalizeIds($claim['evidence_ids'] ?? []) as $evidenceId) {
                $record = $records->get($evidenceId);

                if (! $record) {
                    $violations[] = [
                        'rule' => self::RULE_EVIDENCE_EXISTS,
                        'message' => sprintf('Claim %d cites evidence #%s, which does not exist.', $index + 1, $evidenceId),
                        'context' => ['claim_index' => $index, 'evidence_id' => $evidenceId],
                    ];

                    continue;
                }

                if ((int) $record->sub_institute_id !== $subInstituteId) {
                    // Never leak that the row exists elsewhere — report it as out of scope.
                    $violations[] = [
                        'rule' => self::RULE_EVIDENCE_IN_SCOPE,
                        'message' => sprintf('Claim %d cites evidence outside this school\'s scope.', $index + 1),
                        'context' => ['claim_index' => $index, 'evidence_id' => $evidenceId],
                    ];

                    continue;
                }

                if ($subjectEntityKey !== null && $record->subject_entity_key !== $subjectEntityKey) {
                    $violations[] = [
                        'rule' => self::RULE_EVIDENCE_SUBJECT_MATCH,
                        'message' => sprintf(
                            'Claim %d cites evidence about a %s, but the case is about a %s.',
                            $index + 1,
                            $record->subject_entity_key,
                            $subjectEntityKey
                        ),
                        'context' => ['claim_index' => $index, 'evidence_id' => $evidenceId],
                    ];

                    continue;
                }

                if ($subjectId !== null && (string) $record->subject_id !== (string) $subjectId) {
                    $violations[] = [
                        'rule' => self::RULE_EVIDENCE_SUBJECT_MATCH,
                        'message' => sprintf(
                            'Claim %d cites evidence about a different record than the case subject.',
                            $index + 1
                        ),
                        'context' => ['claim_index' => $index, 'evidence_id' => $evidenceId],
                    ];

                    continue;
                }

                if (! $record->verified) {
                    $entry = [
                        'rule' => self::RULE_EVIDENCE_VERIFIED,
                        'message' => sprintf(
                            'Claim %d cites unverified%s evidence #%s.',
                            $index + 1,
                            $record->is_generated ? ', generated' : '',
                            $evidenceId
                        ),
                        'context' => [
                            'claim_index' => $index,
                            'evidence_id' => $evidenceId,
                            'is_generated' => (bool) $record->is_generated,
                        ],
                    ];

                    // Generated evidence is never citable, whatever the caller asked for.
                    if ($allowUnverified && ! $record->is_generated) {
                        $warnings[] = $entry;
                    } else {
                        $violations[] = $entry;
                    }
                }
            }
        }

        if ($violations === []) {
            $passed[] = self::RULE_CLAIM_HAS_CITATION;
            $passed[] = self::RULE_EVIDENCE_EXISTS;
            $passed[] = self::RULE_EVIDENCE_IN_SCOPE;
            $passed[] = self::RULE_EVIDENCE_VERIFIED;

            return GovernanceReport::pass($passed, $warnings, Verb::Explain);
        }

        return GovernanceReport::fail($violations, $passed, $warnings, Verb::Explain);
    }

    /**
     * The evidence ids a set of claims relies on. Used to stamp
     * `ai_recommendations.evidence_ids` from the explanation that justified it.
     */
    public function citedEvidenceIds(array $claims): array
    {
        $ids = [];

        foreach ($claims as $claim) {
            $ids = array_merge($ids, $this->normalizeIds($claim['evidence_ids'] ?? []));
        }

        return array_values(array_unique($ids));
    }

    private function normalizeIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($id) => is_numeric($id) ? (int) $id : null,
            $value
        ), static fn ($id) => $id !== null && $id > 0));
    }

    private function claimText(array $claim): string
    {
        $text = $claim['claim'] ?? $claim['text'] ?? '';

        return mb_substr((string) $text, 0, 120);
    }
}
