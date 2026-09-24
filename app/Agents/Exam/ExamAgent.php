<?php

namespace App\Agents\Exam;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\Exam\Risk\ExamLowScoreDetector;

/**
 * The Exam agent: who scored below the passing mark, why that is the figure, and what
 * to do about it.
 *
 * THE SHAPE IS THE ATTENDANCE AGENT'S, DELIBERATELY
 *
 * detect → record signals → open a case → explain with cited evidence → draft a
 * recommendation bound to a workflow. That sequence is not reinvented here: it is the
 * one `FeesAgent` and `AttendanceAgent` already prove, and following it means an exam
 * case is read, approved and audited by exactly the machinery that already reads,
 * approves and audits a fee or attendance one. A school learns one set of screens, not
 * four.
 *
 * NOTHING HERE MARKS A PAPER
 *
 * Every figure comes from `ExamLowScoreDetector`, which in turn asks
 * `ResultReportService` — the same service behind the `exams.results` tool and the Exam
 * AI Stack's own report tab. This class chooses what to say about those figures and
 * never what they are. If the register says a child scored 28% in a subject, that is
 * the only number this agent can utter, and an absence is never treated as a score.
 *
 * WHAT IT MAY DO, AND WHAT IT MAY NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0` on the manifest. The agent may
 * detect, analyse, explain and recommend. It may not contact a family or change a mark.
 * A parent being told their child is below the passing mark is a decision that belongs
 * to a named human being in an approval queue, which is why the only thing it can bind
 * a recommendation to is the `exam_result_followup` workflow and its approval step.
 *
 * "NOTHING FOUND" IS NOT THE SAME AS "NOTHING KNOWN"
 *
 * A class nobody has entered marks for produces exactly the same empty list as a class
 * that all passed. The read coverage is carried through every result and into the
 * summary for that reason, and the agent says which of the two it is looking at rather
 * than letting a reader assume the flattering one.
 */
class ExamAgent implements Agent
{
    private const CASE_TYPE = 'exam_result_follow_up';

    private const WORKFLOW_KEY = 'exam_result_followup';

    public function __construct(
        private readonly ExamLowScoreDetector $detector,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $studentId = isset($input['student_id']) ? (int) $input['student_id'] : null;

        // `subject_id` is what AgentController's own validation accepts, and it is how a
        // run launched from the AI Stack or a student page names the child. Treating the
        // two as one means a run is scoped the same however it arrived.
        if ($studentId === null && isset($input['subject_id'])) {
            $studentId = (int) $input['subject_id'];
        }

        $arguments = array_filter([
            'student_id' => $studentId,
            'exam_id' => $input['exam_id'] ?? null,
            'subject_name' => $input['subject_name'] ?? null,
            'standard_name' => $input['standard_name'] ?? null,
            'exam_title' => $input['exam_title'] ?? null,
            'limit' => $input['limit'] ?? null,
            'passing_percentage' => $input['passing_percentage'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $detected = $this->detector->detect($scope, $arguments);
        $coverage = $this->detector->coverage($scope, $arguments);
        $floor = $this->detector->passingPercentage($arguments);

        if ($detected === []) {
            return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
                'students_below_passing' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => $coverage,
                'confidence' => 1.0,
                'message' => $this->nothingFoundMessage($coverage, $studentId, $floor),
            ]);
        }

        // One case per student: a case is about a person, not a percentage. A cohort
        // sweep that stopped at a ranked list would leave the rest of the journey with
        // nothing to stand on — no case means no evidence to cite, no recommendation to
        // draft and no approval to ask for.
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

        // Worst score first — the order a subject teacher should work the list in.
        usort($cases, static fn ($a, $b) => ($a['lowest_percentage'] ?? 100) <=> ($b['lowest_percentage'] ?? 100));

        return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
            'students_below_passing' => count($cases),
            'signals_detected' => count($detected),
            'lowest_percentage' => $cases === [] ? null : $cases[0]['lowest_percentage'],
            'cases' => $cases,
            'coverage' => $coverage,
            'confidence' => 1.0,
            'mode' => 'deep_analysis',
        ]);
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_below_passing'] ?? 0);
        $coverage = is_array($result['coverage'] ?? null) ? $result['coverage'] : [];
        $floor = (float) ($result['passing_percentage'] ?? 35.0);

        if ($count === 0) {
            return (string) ($result['message'] ?? 'No student in this scope scored below the passing mark.');
        }

        return sprintf(
            'Found %d student%s scoring below %s in at least one subject, among %d recorded result%s read, and opened a case for each.',
            $count,
            $count === 1 ? '' : 's',
            $this->percent($floor),
            (int) ($coverage['scored_rows'] ?? 0),
            (int) ($coverage['scored_rows'] ?? 0) === 1 ? '' : 's'
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
            // Recorded, but below the bar for a case. A legitimate outcome — one subject
            // a few points under the bar is a signal worth trending and not worth a
            // teacher's attention on its own.
            return null;
        }

        $signal = $signals[0];
        $name = $signal->subjectLabel ?? ('Student #' . $studentId);
        $worst = (float) ($signal->components['lowest_percentage'] ?? 0);
        $subjectCount = (int) ($signal->components['subjects_below_passing'] ?? 0);
        $floor = (float) ($signal->components['passing_percentage'] ?? 35.0);

        $context->addHypothesis(
            $caseId,
            sprintf(
                '%s scored below %s in %d subject%s, lowest %s.',
                $name,
                $this->percent($floor),
                $subjectCount,
                $subjectCount === 1 ? '' : 's',
                $this->percent($worst)
            ),
            'Raised from the recorded result percentages for this student, not from an estimate or a projection.',
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
            'standard_name' => $signal->components['standard_name'] ?? null,
            'lowest_percentage' => round($worst, 2),
            'subjects_below_passing' => $subjectCount,
            'passing_percentage' => $floor,
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
     * Each claim cites the evidence that supports it — a claim with nothing to cite is
     * not made at all.
     *
     * @param  array<int, DetectedSignal>  $signals
     * @param  array<int, int>  $evidenceIds
     * @return array<int, array<string, mixed>>
     */
    private function buildClaims(AgentContext $context, array $signals, array $evidenceIds, int $studentId): array
    {
        // Only verified, in-scope evidence may be cited, so read back what was stored
        // rather than assuming every id is citable.
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
            $worst = (float) ($components['lowest_percentage'] ?? 0);
            $subjectCount = (int) ($components['subjects_below_passing'] ?? 0);
            $floor = (float) ($components['passing_percentage'] ?? 35.0);
            $label = $signal->subjectLabel ?? ('Student #' . $signal->subjectId);

            $claims[] = [
                'claim' => sprintf(
                    '%s scored below %s in %d recorded subject%s, which the exam records class as %s.',
                    $label,
                    $this->percent($floor),
                    $subjectCount,
                    $subjectCount === 1 ? '' : 's',
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
        $worst = (float) ($signal->components['lowest_percentage'] ?? 0);
        $subjectCount = (int) ($signal->components['subjects_below_passing'] ?? 0);
        $floor = (float) ($signal->components['passing_percentage'] ?? 35.0);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'start_exam_result_review',
            'title' => sprintf('Review %s\'s exam results (lowest %s across %d subject%s)', $name, $this->percent($worst), $subjectCount, $subjectCount === 1 ? '' : 's'),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from the recorded result percentages for this student and the subjects '
                . 'behind them.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $signal->confidence ?? 1.0,
            // Capped by what can actually be cited, not by the score alone — see
            // AttendanceAgent/FeesAgent for why: RecommendVerb requires 3 distinct pieces
            // of verified evidence for a high-risk recommendation, 2 for medium, 1 for
            // low, and a single below-passing subject yields exactly one citable row.
            'risk_level' => $this->riskLevel($signal->severity, count($evidenceIds)),
            // Consequential, and that is the point of it. What makes this consequential
            // is what a person does after approving it: a family is told their child is
            // below the passing mark, and that conversation belongs to a named human
            // being, never to a sweep that ran overnight.
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            'eso_binding' => [
                'objective' => sprintf('Bring %s\'s result back above the %s pass bar.', $name, $this->percent($floor)),
                'strategy' => 'Review the below-passing subjects with the subject teacher, establish what support '
                    . 'is needed, and agree the follow-up through the exam result-review workflow.',
                'outcome' => [
                    'metric_key' => 'exam_percentage',
                    'metric_label' => 'Exam result percentage',
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
                'lowest_percentage' => round($worst, 2),
                'subjects_below_passing' => $subjectCount,
                'passing_percentage' => $floor,
                'severity' => $signal->severity,
                'standard_name' => $signal->components['standard_name'] ?? null,
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
        $rowsRead = (int) ($coverage['rows_read'] ?? 0);

        if ($rowsRead === 0) {
            return 'No exam results are recorded in this scope, so nothing is known either way about who is '
                . 'below the passing mark.';
        }

        if ($studentId !== null) {
            return sprintf('Student #%d has no recorded result below %s in this scope.', $studentId, $this->percent($floor));
        }

        return sprintf(
            'No student scored below %s among the %d recorded result%s read.',
            $this->percent($floor),
            (int) ($coverage['scored_rows'] ?? 0),
            (int) ($coverage['scored_rows'] ?? 0) === 1 ? '' : 's'
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
            'passing_percentage' => $floor,
        ], static fn ($value) => $value !== null));
    }

    /**
     * The risk level a recommendation may claim, given how much it can cite.
     *
     * Severity is about how low the score is; risk level is about how defensible acting
     * on it is. They are allowed to differ, and when the record holds one itemised row
     * they must.
     */
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
