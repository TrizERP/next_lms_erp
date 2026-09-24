<?php

namespace App\Agents\ExamAssessment;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\ExamAssessment\Risk\OnlineExamLowScoreDetector;

/**
 * The Exam & Assessment agent: who scored below the passing bar on a recorded online
 * exam, why that is the figure, and what to do about it.
 *
 * THE SHAPE IS THE EXAM AGENT'S, DELIBERATELY
 *
 * detect → record signals → open a case → explain with cited evidence → draft a
 * recommendation bound to a workflow — the same sequence `ExamAgent` and
 * `AttendanceAgent` already prove. This module is the LMS's online-delivery domain
 * (online exams, homework, assignments, worksheets, projects) and is a genuinely
 * different module from `exam` (the Mark Entry / Results module `ExamAgent` reads) even
 * though the two names collide in prose — they read different tables and open different
 * case types.
 *
 * NOTHING HERE MARKS AN ATTEMPT
 *
 * Every figure comes from `OnlineExamLowScoreDetector`, which in turn asks
 * `OnlineExamReportService` — the same figures the LMS Result Dashboard already shows a
 * teacher, scoped through `question_paper` and excluding PAL-generated papers.
 *
 * WHAT IT MAY DO, AND WHAT IT MAY NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0`. The agent may detect, analyse,
 * explain and recommend. It may not contact a family or change a mark. That stays a
 * human act, reached through the `exam_assessment_followup` workflow's approval step.
 *
 * ONLY ONLINE EXAM SCORE IS COVERED SO FAR
 *
 * Homework/Assignment/Worksheet/Project completion (via `exam_assessment.assignment_status`
 * and the existing `homework.list` tool) is read-only and conversational for now — no
 * signal is raised from it yet. Extending detection to non-submission is a natural next
 * case type and would follow this same shape; it is left out here rather than rushed
 * without the same evidentiary care this class takes with a scored attempt.
 */
class ExamAssessmentAgent implements Agent
{
    private const CASE_TYPE = 'exam_assessment_follow_up';

    private const WORKFLOW_KEY = 'exam_assessment_followup';

    public function __construct(
        private readonly OnlineExamLowScoreDetector $detector,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $studentId = isset($input['student_id']) ? (int) $input['student_id'] : null;

        if ($studentId === null && isset($input['subject_id'])) {
            $studentId = (int) $input['subject_id'];
        }

        // `subject_id` in the input schema is the AI Stack panel's alias for `student_id`
        // (handled above, matching AttendanceAgent/ExamAgent), so the online-exam subject
        // filter is spelled `exam_subject_id` here to avoid the two colliding on one run.
        $arguments = array_filter([
            'student_id' => $studentId,
            'standard_id' => $input['standard_id'] ?? null,
            'subject_id' => $input['exam_subject_id'] ?? null,
            'limit' => $input['limit'] ?? null,
            'at_risk_percent' => $input['at_risk_percent'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $detected = $this->detector->detect($scope, $arguments);
        $coverage = $this->detector->coverage($scope, $arguments);
        $floor = $this->detector->atRiskPercent($arguments);

        if ($detected === []) {
            return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
                'students_below_bar' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => $coverage,
                'confidence' => 1.0,
                'message' => $this->nothingFoundMessage($coverage, $studentId, $floor),
            ]);
        }

        $byStudent = [];

        foreach ($detected as $signal) {
            $byStudent[(int) $signal->subjectId][] = $signal;
        }

        $cases = [];

        foreach ($byStudent as $subjectId => $signals) {
            $case = $this->buildCaseForStudent($context, (int) $subjectId, $signals);

            if ($case !== null) {
                $cases[] = $case;
            }
        }

        usort($cases, static fn ($a, $b) => ($a['lowest_percent'] ?? 100) <=> ($b['lowest_percent'] ?? 100));

        return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
            'students_below_bar' => count($cases),
            'signals_detected' => count($detected),
            'lowest_percent' => $cases === [] ? null : $cases[0]['lowest_percent'],
            'cases' => $cases,
            'coverage' => $coverage,
            'confidence' => 1.0,
            'mode' => 'deep_analysis',
        ]);
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_below_bar'] ?? 0);
        $coverage = is_array($result['coverage'] ?? null) ? $result['coverage'] : [];
        $floor = (float) ($result['at_risk_percent'] ?? 40.0);

        if ($count === 0) {
            return (string) ($result['message'] ?? 'No student in this scope scored below the online exam bar.');
        }

        return sprintf(
            'Found %d student%s scoring below %s on an online exam, among %d attempt%s recorded, and opened a case for each.',
            $count,
            $count === 1 ? '' : 's',
            $this->percent($floor),
            (int) ($coverage['attempts_recorded'] ?? 0),
            (int) ($coverage['attempts_recorded'] ?? 0) === 1 ? '' : 's'
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<int, DetectedSignal>  $signals
     * @return array<string, mixed>|null
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
            return null;
        }

        $signal = $signals[0];
        $name = $signal->subjectLabel ?? ('Student #' . $studentId);
        $worst = (float) ($signal->components['lowest_percent'] ?? 0);
        $attemptsBelow = (int) ($signal->components['attempts_below_bar'] ?? 0);
        $floor = (float) ($signal->components['at_risk_percent'] ?? 40.0);

        $context->addHypothesis(
            $caseId,
            sprintf(
                '%s scored below %s on %d online exam attempt%s, lowest %s.',
                $name,
                $this->percent($floor),
                $attemptsBelow,
                $attemptsBelow === 1 ? '' : 's',
                $this->percent($worst)
            ),
            'Raised from the recorded online exam attempts for this student, not from an estimate.',
            $stored['evidence_ids'],
            [],
            1.0
        );

        $explanation = $context->explain(
            $caseId,
            $this->buildClaims($context, $signals, $stored['evidence_ids'], $studentId),
            'teacher',
            'student',
            $studentId,
            $name
        );

        $recommendation = null;

        if ($explanation['governance']->passed) {
            $recommendation = $this->draftRecommendation(
                $context,
                $caseId,
                $explanation,
                $studentId,
                $name,
                $signal,
                $stored['evidence_ids']
            );
        }

        return [
            'case_id' => $caseId,
            'student_id' => $studentId,
            'student_name' => $name,
            'standard_id' => $signal->components['standard_id'] ?? null,
            'lowest_percent' => round($worst, 2),
            'attempts_below_bar' => $attemptsBelow,
            'at_risk_percent' => $floor,
            'severity' => $signal->severity,
            'signals' => array_map(static fn (DetectedSignal $item) => $item->toArray(), $signals),
            'explanation' => [
                'narrative' => $explanation['narrative'],
                'governance_passed' => $explanation['governance']->passed,
                'reason_refused' => $explanation['governance']->passed ? null : $explanation['governance']->reason(),
            ],
            'recommendation' => $recommendation,
        ];
    }

    /**
     * @param  array<int, DetectedSignal>  $signals
     * @param  array<int, int>  $evidenceIds
     * @return array<int, array<string, mixed>>
     */
    private function buildClaims(AgentContext $context, array $signals, array $evidenceIds, int $studentId): array
    {
        $citable = collect($context->evidenceFor('student', $studentId, null, 100))
            ->whereIn('id', $evidenceIds)
            ->where('verified', true)
            ->pluck('id')
            ->all();

        if ($citable === []) {
            return [];
        }

        $claims = [];

        foreach ($signals as $signal) {
            $components = $signal->components;
            $attempts = (int) ($components['attempts_below_bar'] ?? 0);
            $floor = (float) ($components['at_risk_percent'] ?? 40.0);
            $label = $signal->subjectLabel ?? ('Student #' . $signal->subjectId);

            $claims[] = [
                'claim' => sprintf(
                    '%s scored below %s on %d recorded online exam attempt%s, which the result classes as %s.',
                    $label,
                    $this->percent($floor),
                    $attempts,
                    $attempts === 1 ? '' : 's',
                    $signal->severity
                ),
                'evidence_ids' => $citable,
                'confidence' => $signal->confidence,
            ];
        }

        return $claims;
    }

    /**
     * @param  array<int, int>  $evidenceIds
     * @return array<string, mixed>
     */
    private function draftRecommendation(
        AgentContext $context,
        int $caseId,
        array $explanation,
        int $studentId,
        string $name,
        DetectedSignal $signal,
        array $evidenceIds
    ): array {
        $worst = (float) ($signal->components['lowest_percent'] ?? 0);
        $attempts = (int) ($signal->components['attempts_below_bar'] ?? 0);
        $floor = (float) ($signal->components['at_risk_percent'] ?? 40.0);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'start_exam_assessment_review',
            'title' => sprintf('Review %s\'s online exam results (lowest %s across %d attempt%s)', $name, $this->percent($worst), $attempts, $attempts === 1 ? '' : 's'),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from the recorded online exam attempts for this student.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $signal->confidence ?? 1.0,
            'risk_level' => $this->riskLevel($signal->severity, count($evidenceIds)),
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            'eso_binding' => [
                'objective' => sprintf('Bring %s\'s online exam results back above %s.', $name, $this->percent($floor)),
                'strategy' => 'Review the below-bar attempts with the subject teacher and agree the follow-up '
                    . 'through the exam-assessment review workflow.',
                'outcome' => [
                    'metric_key' => 'online_exam_percent',
                    'metric_label' => 'Online exam attempt score (percent)',
                    'direction' => 'increase',
                    'baseline' => round($worst, 2),
                    'target' => $floor,
                    'horizon_days' => $signal->severity === 'critical' ? 14 : 30,
                ],
            ],
            'workflow_key' => self::WORKFLOW_KEY,
            'workflow_payload' => [
                'student_id' => $studentId,
                'student_name' => $name,
                'case_id' => $caseId,
                'lowest_percent' => round($worst, 2),
                'attempts_below_bar' => $attempts,
                'at_risk_percent' => $floor,
                'severity' => $signal->severity,
                'standard_id' => $signal->components['standard_id'] ?? null,
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
            'workflow_key' => self::WORKFLOW_KEY,
        ];
    }

    /**
     * @param  array<string, mixed>  $coverage
     */
    private function nothingFoundMessage(array $coverage, ?int $studentId, float $floor): string
    {
        $attempts = (int) ($coverage['attempts_recorded'] ?? 0);

        if ($attempts === 0) {
            return 'No online exam attempts are recorded in this scope, so nothing is known either way about '
                . 'who is below the bar.';
        }

        if ($studentId !== null) {
            return sprintf('Student #%d has no recorded online exam attempt below %s in this scope.', $studentId, $this->percent($floor));
        }

        return sprintf(
            'No student scored below %s among the %d online exam attempt%s recorded.',
            $this->percent($floor),
            $attempts,
            $attempts === 1 ? '' : 's'
        );
    }

    /**
     * @param  array<string, mixed>  $findings
     * @return array<string, mixed>
     */
    private function result(
        ?int $studentId,
        int|string|null $instituteId,
        ?int $academicYear,
        float $floor,
        array $findings
    ): array {
        return array_merge($findings, array_filter([
            'student_id' => $studentId,
            'sub_institute_id' => $instituteId,
            'syear' => $academicYear,
            'at_risk_percent' => $floor,
        ], static fn ($value) => $value !== null));
    }

    private function riskLevel(string $severity, int $evidenceCount): string
    {
        $claimed = match ($severity) {
            'critical' => 'high',
            'high' => 'medium',
            default => 'low',
        };

        $supported = match (true) {
            $evidenceCount >= 3 => 'high',
            $evidenceCount === 2 => 'medium',
            default => 'low',
        };

        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];

        return $rank[$claimed] <= $rank[$supported] ? $claimed : $supported;
    }

    private function percent(float $rate): string
    {
        return number_format($rate, 1) . '%';
    }
}
