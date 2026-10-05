<?php

namespace App\Domain\PAL\CoherenceRisk;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\K12\AcademicRisk\StudentScope;

/**
 * PAL's first governed agent: flags a student whose weak concepts trace back
 * to root blockers scattered across multiple chapters — a systemic gap, not
 * a local one — for a teacher to review.
 *
 * Deliberately the smaller sibling of AcademicRiskAgent, not a copy of its
 * full shape: one detector (no cross-signal hypothesis matrix needed for a
 * single signal type), and no bound workflow — this agent's job ends at a
 * reviewable, evidence-backed recommendation a teacher can read and approve.
 * There is no existing "build a remediation plan" action to hand it off to
 * yet, and inventing one speculatively is exactly what Phase 9 of the PAL
 * Neo4j roadmap was deferred to avoid.
 *
 * Detection itself does not use the generic Ontology/GraphQueryService layer
 * (App\Domain\KnowledgeGraph) — it calls CoherenceMapRepository::rootBlockers()
 * directly, the same proven, already-verified traversal Phases 3-5 use. The
 * generic layer's role here is governance (AgentContext, AgentRunner, the
 * ai_agents manifest, the human-approval gate) — never re-implementing this
 * agent's actual domain math.
 */
class CoherenceRiskAgent implements Agent
{
    public const CASE_TYPE = 'pal_compounding_gaps';

    public function __construct(
        private readonly CompoundingRootBlockerDetector $detector,
        private readonly StudentScope $scope,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $studentIds = $this->resolveStudentIds($context);
        $limit = (int) ($context->input['limit'] ?? 50);

        $cohort = $this->cohort($context, $studentIds);

        if (! $context->manifest->permitsSignal($this->detector->key())) {
            return [
                'students_flagged' => 0,
                'cases' => [],
                'cohort' => $cohort,
                'confidence' => 1.0,
                'message' => 'This agent is not licensed to raise this signal.',
            ];
        }

        $detected = $this->detector->detect($context->scope, $studentIds, $limit);

        if ($detected === []) {
            return [
                'students_flagged' => 0,
                'cases' => [],
                'cohort' => $cohort,
                'detector_coverage' => $this->detector->coverage()?->toArray(),
                'confidence' => 1.0,
                'message' => $cohort['complete']
                    ? 'No compounding root-blocker pattern was found in the current scope.'
                    : sprintf(
                        'No compounding root-blocker pattern was found among the %d of %d students this sweep read.',
                        $cohort['scanned'],
                        $cohort['in_scope']
                    ),
            ];
        }

        $cases = [];

        foreach ($detected as $signal) {
            $case = $this->buildCase($context, $signal);

            if ($case !== null) {
                $cases[] = $case;
            }
        }

        usort($cases, fn ($a, $b) => ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0));

        return [
            'students_flagged' => count($cases),
            'signals_detected' => count($detected),
            'cases' => $cases,
            'cohort' => $cohort,
            'detector_coverage' => $this->detector->coverage()?->toArray(),
            'confidence' => $this->overallConfidence($detected),
        ];
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_flagged'] ?? 0);

        if ($count === 0) {
            return $result['message'] ?? 'No students currently show a compounding root-blocker pattern.';
        }

        $needingApproval = count(array_filter(
            $result['cases'] ?? [],
            fn ($case) => ! empty($case['recommendation']['id'])
        ));

        return sprintf(
            '%d student%s flagged with gaps spanning multiple chapters; %d recommendation%s waiting for teacher review.',
            $count,
            $count === 1 ? '' : 's',
            $needingApproval,
            $needingApproval === 1 ? '' : 's'
        );
    }

    // ------------------------------------------------------------------ steps

    private function buildCase(AgentContext $context, DetectedSignal $signal): ?array
    {
        $stored = $context->recordSignals([$signal]);

        $caseId = $context->openCase(self::CASE_TYPE, [$signal], $stored['signal_ids'], $stored['evidence_ids']);

        if ($caseId === null) {
            return null;
        }

        $studentId = (int) $signal->subjectId;
        $studentName = $signal->subjectLabel ?? ('Student #' . $studentId);
        $placement = $this->scope->placement($studentId, $context->scope);

        $chapterCount = (int) ($signal->components['distinct_chapters'] ?? 0);
        $blockerCount = (int) ($signal->components['root_blocker_count'] ?? 0);

        $context->addHypothesis(
            $caseId,
            sprintf(
                '%s\'s recent weak concepts share %d common root cause(s) spread across %d chapters, rather than '
                . 'being independent gaps.',
                $studentName,
                $blockerCount,
                $chapterCount
            ),
            'Root-blocker concepts computed via the prerequisite graph recur across more than one chapter for '
            . 'this student\'s current weak concepts.',
            $stored['evidence_ids'],
            [],
            0.6
        );

        // Deterministic, evidence-only — every claim below names a row already
        // stored, same discipline AcademicRiskAgent's own explanation uses.
        $claims = $this->buildClaims($signal, $stored['evidence_ids'], $context, $studentId);

        $explanation = $context->explain($caseId, $claims, 'teacher', 'student', $studentId, $studentName);

        $recommendation = null;

        if ($explanation['governance']->passed) {
            $recommendation = $this->draftRecommendation(
                $context,
                $caseId,
                $explanation,
                $studentId,
                $studentName,
                $signal,
                $stored['evidence_ids']
            );
        }

        return [
            'case_id' => $caseId,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'placement' => $placement,
            'severity' => $signal->severity,
            'priority_score' => round($signal->score, 4),
            'signal' => $signal->toArray(),
            'explanation' => [
                'narrative' => $explanation['narrative'],
                'governance_passed' => $explanation['governance']->passed,
                'reason_refused' => $explanation['governance']->passed ? null : $explanation['governance']->reason(),
            ],
            'recommendation' => $recommendation,
        ];
    }

    private function buildClaims(DetectedSignal $signal, array $evidenceIds, AgentContext $context, int $studentId): array
    {
        $stored = collect($context->evidenceFor('student', $studentId, null, 100))
            ->whereIn('id', $evidenceIds)
            ->where('verified', true);

        $summary = $stored->firstWhere('kind', 'compounding_root_blockers');
        $blockers = $stored->where('kind', 'root_blocker');

        $claims = [];

        if ($summary !== null) {
            $claims[] = [
                'claim' => $summary['summary'] ?? 'weak concepts trace back to root blockers spanning multiple chapters',
                'evidence_ids' => [$summary['id']],
                'confidence' => $signal->confidence,
            ];
        }

        if ($blockers->isNotEmpty()) {
            $claims[] = [
                'claim' => sprintf(
                    'the specific root blockers are: %s',
                    $blockers->pluck('summary')->implode(' ')
                ),
                'evidence_ids' => $blockers->pluck('id')->values()->all(),
                'confidence' => $signal->confidence,
            ];
        }

        return $claims;
    }

    /**
     * Drafts a review flag. Note what this deliberately does not do: bind a
     * workflow. Approving this recommendation records a teacher's decision
     * and nothing more — there is no downstream action for it to trigger yet.
     */
    private function draftRecommendation(
        AgentContext $context,
        int $caseId,
        array $explanation,
        int $studentId,
        string $studentName,
        DetectedSignal $signal,
        array $evidenceIds
    ): ?array {
        $chapterCount = (int) ($signal->components['distinct_chapters'] ?? 0);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'pal',
            'action_type' => 'flag_compounding_gap_review',
            'title' => sprintf('Review %s\'s gaps across %d chapters', $studentName, $chapterCount),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from root-blocker concepts that recur across multiple chapters for this student.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $signal->confidence ?? 0.5,
            'risk_level' => $signal->severity === 'critical' ? 'high' : ($signal->severity === 'high' ? 'medium' : 'low'),
            'is_consequential' => false,
            'evidence_ids' => $evidenceIds,
            // No workflow_key: this recommendation's approval is the outcome,
            // not a trigger for a further automated action.
        ];

        $result = $context->recommend($draft);

        return [
            'id' => $result['id'],
            'status' => $result['status'],
            'governance_passed' => $result['governance']->passed,
            'reason_refused' => $result['governance']->passed ? null : $result['governance']->reason(),
            'title' => $draft['title'],
            'requires_approval' => true,
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function resolveStudentIds(AgentContext $context): ?array
    {
        $ids = $context->input['student_ids'] ?? null;

        if (is_array($ids) && $ids !== []) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        $studentId = $context->input['student_id'] ?? null;

        return $studentId !== null && is_numeric($studentId) ? [(int) $studentId] : null;
    }

    /**
     * @return array{scanned:int, in_scope:int, complete:bool, narrowed_to_named_students:bool}
     */
    private function cohort(AgentContext $context, ?array $studentIds): array
    {
        $scanned = count($this->scope->students($context->scope, $studentIds));
        $inScope = $studentIds === null ? $this->scope->total($context->scope) : count($studentIds);

        return [
            'scanned' => $scanned,
            'in_scope' => $inScope,
            'complete' => $scanned >= $inScope,
            'narrowed_to_named_students' => $studentIds !== null,
        ];
    }

    /** @param array<int, DetectedSignal> $signals */
    private function overallConfidence(array $signals): float
    {
        $values = array_values(array_filter(
            array_map(fn (DetectedSignal $signal) => $signal->confidence, $signals),
            fn ($value) => $value !== null
        ));

        return $values === [] ? 0.5 : round(array_sum($values) / count($values), 4);
    }
}
