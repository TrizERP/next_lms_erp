<?php

namespace App\Domain\K12\AcademicRisk;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\ThresholdRegistry;

/**
 * The first end-to-end domain agent: academic risk.
 *
 * It runs the full governed lifecycle — detect, gather evidence, build a case,
 * hypothesise, explain, recommend — and stops. It cannot create the intervention it
 * recommends; that needs a teacher's approval and then a workflow, and this agent's
 * manifest licenses it only up to `recommend`.
 *
 * The explanation is composed deterministically from the evidence rather than
 * generated. "Mathematics assessment performance declined across the last three
 * assessments and two assigned activities were incomplete" is a sentence assembled
 * from rows, so it cannot say anything the rows do not.
 */
class AcademicRiskAgent implements Agent
{
    public const CASE_TYPE = 'academic_risk';

    public const WORKFLOW_KEY = 'k12_academic_intervention';

    public function __construct(
        private readonly AssessmentDeclineDetector $assessmentDecline,
        private readonly AttendanceRiskDetector $attendanceRisk,
        private readonly MissedAssignmentDetector $missedAssignments,
        private readonly ThresholdRegistry $thresholds,
        private readonly StudentScope $scope,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $subjectId = $context->input['subject_id'] ?? null;
        $studentIds = $this->resolveStudentIds($context, $subjectId);
        $limit = (int) ($context->input['limit'] ?? 50);

        // ---- Detect --------------------------------------------------------
        $detected = $this->detectAll($context, $studentIds, $limit);

        if ($detected === []) {
            return [
                'students_at_risk' => 0,
                'cases' => [],
                'confidence' => 1.0,
                'message' => 'No academic risk signals were found in the current scope.',
            ];
        }

        // ---- Group by student: a case is about a person, not a metric -------
        $byStudent = [];

        foreach ($detected as $signal) {
            $byStudent[(int) $signal->subjectId][] = $signal;
        }

        $cases = [];

        foreach ($byStudent as $studentId => $signals) {
            $case = $this->buildCaseForStudent($context, $studentId, $signals);

            if ($case !== null) {
                $cases[] = $case;
            }
        }

        // Highest priority first — this is the order a teacher should read them in.
        usort($cases, fn ($a, $b) => ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0));

        return [
            'students_at_risk' => count($cases),
            'signals_detected' => count($detected),
            'cases' => $cases,
            'confidence' => $this->overallConfidence($detected),
        ];
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_at_risk'] ?? 0);

        if ($count === 0) {
            return $result['message'] ?? 'No students are currently showing academic risk signals.';
        }

        $needingApproval = count(array_filter(
            $result['cases'] ?? [],
            fn ($case) => ! empty($case['recommendation']['id'])
        ));

        return sprintf(
            '%d student%s showing academic risk; %d recommendation%s drafted and waiting for approval.',
            $count,
            $count === 1 ? '' : 's',
            $needingApproval,
            $needingApproval === 1 ? '' : 's'
        );
    }

    // ------------------------------------------------------------------ steps

    /**
     * @return array<int, DetectedSignal>
     */
    private function detectAll(AgentContext $context, ?array $studentIds, int $limit): array
    {
        $signals = [];

        foreach ([$this->assessmentDecline, $this->attendanceRisk, $this->missedAssignments] as $detector) {
            // An agent may be licensed for only some of these signals.
            if (! $context->manifest->permitsSignal($detector->key())) {
                continue;
            }

            foreach ($detector->detect($context->scope, $studentIds, $limit) as $signal) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    /**
     * @param  array<int, DetectedSignal>  $signals
     */
    private function buildCaseForStudent(AgentContext $context, int $studentId, array $signals): ?array
    {
        $stored = $context->recordSignals($signals);

        $caseId = $context->openCase(
            self::CASE_TYPE,
            $signals,
            $stored['signal_ids'],
            $stored['evidence_ids']
        );

        if ($caseId === null) {
            // Signals were recorded but none was serious enough for a case. That is a
            // legitimate outcome and worth returning, so a trend is still visible.
            return null;
        }

        $studentName = $signals[0]->subjectLabel ?? ('Student #' . $studentId);
        $placement = $this->scope->placement($studentId, $context->scope);

        // ---- Hypotheses ----------------------------------------------------
        //
        // Held in a variable rather than iterated inline, because the finding returned
        // below needs them too. Persisting the reasoning and then dropping it from the
        // payload the assistant reads leaves a caller with what was measured but not
        // what the agent made of it.
        $hypotheses = $this->hypothesise($signals);

        foreach ($hypotheses as $hypothesis) {
            $context->addHypothesis(
                $caseId,
                $hypothesis['statement'],
                $hypothesis['rationale'],
                $stored['evidence_ids'],
                [],
                $hypothesis['confidence']
            );
        }

        // ---- Explain -------------------------------------------------------
        $claims = $this->buildClaims($signals, $stored['evidence_ids'], $context, $studentId);

        $explanation = $context->explain(
            $caseId,
            $claims,
            'teacher',
            'student',
            $studentId,
            $studentName
        );

        // ---- Recommend -----------------------------------------------------
        $recommendation = null;

        if ($explanation['governance']->passed) {
            $recommendation = $this->draftRecommendation(
                $context,
                $caseId,
                $explanation,
                $studentId,
                $studentName,
                $signals,
                $stored['evidence_ids'],
                $placement
            );
        }

        return [
            'case_id' => $caseId,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'placement' => $placement,
            'severity' => $this->highestSeverity($signals),
            'priority_score' => $this->priority($signals),
            'signals' => array_map(fn (DetectedSignal $signal) => $signal->toArray(), $signals),
            'hypotheses' => $hypotheses,
            'explanation' => [
                'narrative' => $explanation['narrative'],
                'governance_passed' => $explanation['governance']->passed,
                'reason_refused' => $explanation['governance']->passed ? null : $explanation['governance']->reason(),
            ],
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Each claim cites the evidence that supports it. This is what GroundedClaims
     * validates, and what the UI renders as a clickable "why".
     */
    private function buildClaims(
        array $signals,
        array $evidenceIds,
        AgentContext $context,
        int $studentId
    ): array {
        // Only verified, in-scope evidence may be cited, so read back what was stored
        // rather than assuming every id is citable.
        $stored = collect($context->evidenceFor('student', $studentId, null, 100))
            ->whereIn('id', $evidenceIds)
            ->where('verified', true)
            ->groupBy('kind');

        $claims = [];

        foreach ($signals as $signal) {
            $claim = $this->claimForSignal($signal, $stored);

            if ($claim !== null) {
                $claims[] = $claim;
            }
        }

        return $claims;
    }

    private function claimForSignal(DetectedSignal $signal, $storedByKind): ?array
    {
        $components = $signal->components;

        return match ($signal->signalKey) {
            AssessmentDeclineDetector::KEY => $this->composeClaim(
                $this->assessmentClaimText($components),
                $storedByKind,
                ['assessment_trend', 'assessment_score'],
                $signal->confidence
            ),
            AttendanceRiskDetector::KEY => $this->composeClaim(
                $this->attendanceClaimText($components),
                $storedByKind,
                ['attendance_rate', 'attendance_streak', 'attendance_absence'],
                $signal->confidence
            ),
            MissedAssignmentDetector::KEY => $this->composeClaim(
                $this->assignmentClaimText($components),
                $storedByKind,
                ['assignment_completion', 'assignment_missed'],
                $signal->confidence
            ),
            default => null,
        };
    }

    private function composeClaim(string $text, $storedByKind, array $kinds, ?float $confidence): ?array
    {
        $evidenceIds = [];

        foreach ($kinds as $kind) {
            foreach ($storedByKind->get($kind, collect()) as $evidence) {
                $evidenceIds[] = $evidence['id'];
            }
        }

        // A claim with nothing to cite is not made at all — better silence than an
        // assertion the platform cannot defend.
        if ($evidenceIds === []) {
            return null;
        }

        return [
            'claim' => $text,
            'evidence_ids' => array_values(array_unique($evidenceIds)),
            'confidence' => $confidence,
        ];
    }

    private function assessmentClaimText(array $components): string
    {
        $recent = $components['recent_average_percent'] ?? null;
        $previous = $components['previous_average_percent'] ?? null;
        $attempts = $components['attempts_considered'] ?? 0;

        if ($previous !== null && $recent !== null && $previous > $recent) {
            return sprintf(
                'assessment performance declined from %s%% to %s%% across the last %d assessments',
                round($previous, 1),
                round($recent, 1),
                $attempts
            );
        }

        return sprintf(
            'assessment performance is averaging %s%% across the last %d assessments',
            round((float) ($recent ?? 0), 1),
            $attempts
        );
    }

    private function attendanceClaimText(array $components): string
    {
        $rate = round(((float) ($components['absence_rate'] ?? 0)) * 100, 1);
        $streak = (int) ($components['consecutive_absences'] ?? 0);
        $days = (int) ($components['recorded_days'] ?? 0);

        if ($streak >= 2) {
            return sprintf(
                'attendance is %s%% absent over %d recorded days, including %d consecutive absences',
                $rate,
                $days,
                $streak
            );
        }

        return sprintf('attendance is %s%% absent over %d recorded days', $rate, $days);
    }

    private function assignmentClaimText(array $components): string
    {
        $missed = (int) ($components['missed'] ?? 0);
        $assigned = (int) ($components['assigned'] ?? 0);
        $subject = $components['dominant_subject'] ?? null;
        $subjectMissed = (int) ($components['dominant_subject_missed'] ?? 0);

        if ($subject && $subjectMissed >= 2) {
            return sprintf(
                '%d of %d assigned activities were incomplete, %d of them in %s',
                $missed,
                $assigned,
                $subjectMissed,
                $subject
            );
        }

        return sprintf('%d of %d assigned activities were incomplete', $missed, $assigned);
    }

    /**
     * @param  array<int, DetectedSignal>  $signals
     */
    private function hypothesise(array $signals): array
    {
        $keys = array_map(fn (DetectedSignal $signal) => $signal->signalKey, $signals);
        $hypotheses = [];

        $hasAttendance = in_array(AttendanceRiskDetector::KEY, $keys, true);
        $hasAssessment = in_array(AssessmentDeclineDetector::KEY, $keys, true);
        $hasAssignments = in_array(MissedAssignmentDetector::KEY, $keys, true);

        if ($hasAttendance && $hasAssessment) {
            $hypotheses[] = [
                'statement' => 'Missed classroom time is contributing to the drop in assessment performance.',
                'rationale' => 'Absence and declining assessment results are co-occurring for this student.',
                'confidence' => 0.6,
            ];
        }

        if ($hasAssignments && $hasAssessment) {
            $hypotheses[] = [
                'statement' => 'Incomplete practice is leaving specific concepts unconsolidated before assessment.',
                'rationale' => 'Assignment completion and assessment performance are both below expectation.',
                'confidence' => 0.55,
            ];
        }

        if ($hasAssignments && ! $hasAttendance) {
            $hypotheses[] = [
                'statement' => 'The student is present but disengaging from independent work.',
                'rationale' => 'Attendance shows no risk while assigned work is going uncompleted.',
                'confidence' => 0.5,
            ];
        }

        // Every rule above needs two signal types at once. In practice one detector
        // fires far more often than the others — the estate's attendance and homework
        // tables are thin — so a case would routinely be built, explained and acted on
        // without a single hypothesis ever being recorded, and the reasoning step
        // existed only on paper.
        //
        // A single signal still supports a reading; it just supports a weaker one, and
        // saying so with lower confidence is more useful than saying nothing. These are
        // deliberately phrased as candidates to check, not conclusions.
        if ($hypotheses === []) {
            $hypotheses = $this->singleSignalHypotheses($hasAttendance, $hasAssessment, $hasAssignments);
        }

        return $hypotheses;
    }

    /**
     * The fallback reading when only one detector fired.
     *
     * Confidence is capped well below the corroborated cases above, because one
     * signal cannot distinguish between the competing explanations — which is
     * precisely what the teacher is being asked to check.
     */
    private function singleSignalHypotheses(
        bool $hasAttendance,
        bool $hasAssessment,
        bool $hasAssignments
    ): array {
        if ($hasAssessment) {
            return [[
                'statement' => 'A specific gap in recent content is holding assessment performance down.',
                'rationale' => 'Assessment results are declining while attendance and assigned work show no '
                    . 'corroborating signal, which points at comprehension rather than participation.',
                'confidence' => 0.35,
            ]];
        }

        if ($hasAttendance) {
            return [[
                'statement' => 'Absence is the leading risk, and its academic effect has not surfaced yet.',
                'rationale' => 'Attendance is below threshold while assessment results are not yet declining. '
                    . 'Acting now is preventive.',
                'confidence' => 0.35,
            ]];
        }

        if ($hasAssignments) {
            return [[
                'statement' => 'Independent work is not being completed, ahead of any measured drop in results.',
                'rationale' => 'Assigned work is incomplete while attendance and assessments show no signal.',
                'confidence' => 0.35,
            ]];
        }

        return [];
    }

    /**
     * Draft the intervention. Note what this does NOT do: it does not create the
     * intervention. It binds a workflow key and an ESO, and leaves the recommendation
     * in `pending_approval` for a teacher.
     */
    private function draftRecommendation(
        AgentContext $context,
        int $caseId,
        array $explanation,
        int $studentId,
        string $studentName,
        array $signals,
        array $evidenceIds,
        ?array $placement
    ): ?array {
        $severity = $this->highestSeverity($signals);
        $focusSubject = $this->focusSubject($signals);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'create_academic_intervention',
            'title' => sprintf(
                'Start an academic intervention for %s%s',
                $studentName,
                $focusSubject['name'] ? ' in ' . $focusSubject['name'] : ''
            ),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from detected academic risk signals and their supporting evidence.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $this->overallConfidence($signals),
            'risk_level' => $severity === 'critical' ? 'high' : ($severity === 'high' ? 'medium' : 'low'),
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            'eso_binding' => [
                'objective' => sprintf(
                    'Return %s to expected academic progress%s.',
                    $studentName,
                    $focusSubject['name'] ? ' in ' . $focusSubject['name'] : ''
                ),
                'strategy' => 'Targeted intervention with assigned practice and teacher follow-up.',
                'outcome' => [
                    'metric_key' => AcademicRiskMetrics::ASSESSMENT_AVERAGE,
                    'metric_label' => 'Assessment average (%)',
                    'direction' => 'increase',
                    'horizon_days' => $severity === 'critical' ? 21 : 30,
                ],
            ],
            'workflow_key' => self::WORKFLOW_KEY,
            'workflow_payload' => [
                'student_id' => $studentId,
                'student_name' => $studentName,
                'case_id' => $caseId,
                'severity' => $severity,
                'focus_subject_id' => $focusSubject['id'],
                'focus_subject_name' => $focusSubject['name'],
                'standard_id' => $placement['standard_id'] ?? null,
                'section_id' => $placement['section_id'] ?? null,
            ],
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

    private function resolveStudentIds(AgentContext $context, mixed $subjectId): ?array
    {
        if ($subjectId !== null && is_numeric($subjectId)) {
            return [(int) $subjectId];
        }

        $ids = $context->input['student_ids'] ?? null;

        if (is_array($ids) && $ids !== []) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        return null;
    }

    /** @param array<int, DetectedSignal> $signals */
    private function highestSeverity(array $signals): string
    {
        $highest = 'low';

        foreach ($signals as $signal) {
            if ($this->thresholds->severityRank($signal->severity) > $this->thresholds->severityRank($highest)) {
                $highest = $signal->severity;
            }
        }

        return $highest;
    }

    /** @param array<int, DetectedSignal> $signals */
    private function priority(array $signals): float
    {
        $max = 0.0;

        foreach ($signals as $signal) {
            $max = max($max, $signal->score);
        }

        // More corroborating signals raise priority, capped so it cannot exceed 1.
        return round(min(1.0, $max + (count($signals) - 1) * 0.05), 4);
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

    /**
     * @param  array<int, DetectedSignal>  $signals
     * @return array{id:int|null, name:string|null}
     */
    private function focusSubject(array $signals): array
    {
        foreach ($signals as $signal) {
            if ($signal->signalKey === MissedAssignmentDetector::KEY) {
                return [
                    'id' => $signal->components['dominant_subject_id'] ?? null,
                    'name' => $signal->components['dominant_subject'] ?? null,
                ];
            }
        }

        return ['id' => null, 'name' => null];
    }
}
