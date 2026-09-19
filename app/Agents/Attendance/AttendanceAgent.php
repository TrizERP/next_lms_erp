<?php

namespace App\Agents\Attendance;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\Attendance\Risk\LowAttendanceDetector;

/**
 * The Attendance agent: who is missing school, how much, and what to do about it.
 *
 * THE SHAPE IS THE FEES AGENT'S, DELIBERATELY
 *
 * detect → record signals → open a case → explain with cited evidence → draft a
 * recommendation bound to a workflow. That sequence is not reinvented here: it is the one
 * `FeesAgent` and `AcademicRiskAgent` already prove, and following it means an attendance
 * case is read, approved and audited by exactly the machinery that already reads,
 * approves and audits a fee one. A school learns one set of screens, not three.
 *
 * NOTHING HERE COUNTS A DAY
 *
 * Every figure comes from `LowAttendanceDetector`, which in turn asks
 * `AttendanceInsightService` — the same service behind the `attendance.overview` and
 * `attendance.student` tools the chat and the Attendance screens use. This class chooses
 * what to say about those figures and never what they are. If the register says a child
 * was present on 41 of 58 coded days, that is the only number this agent can utter.
 *
 * WHAT IT MAY DO, AND WHAT IT MAY NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0` on the manifest. The agent may
 * detect, analyse, explain and recommend. It may not contact a family, mark a register,
 * or start an intervention. A parent being told their child has been missing school is a
 * decision that belongs to a named human being in an approval queue, which is why the
 * only thing it can bind a recommendation to is the `attendance_followup` workflow and
 * its approval step.
 *
 * "NOTHING FOUND" IS NOT THE SAME AS "NOTHING KNOWN"
 *
 * A school where nobody has marked a register produces exactly the same empty list as a
 * school where everybody attends. The coverage figures are carried through every result
 * and into the summary for that reason, and the agent says which of the two it is
 * looking at rather than letting a reader assume the flattering one.
 */
class AttendanceAgent implements Agent
{
    private const CASE_TYPE = 'attendance_follow_up';

    private const WORKFLOW_KEY = 'attendance_followup';

    public function __construct(
        private readonly LowAttendanceDetector $detector,
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
            'standard_id' => $input['standard_id'] ?? null,
            'division_id' => $input['division_id'] ?? ($input['section_id'] ?? null),
            'student_id' => $studentId,
            'days' => $input['days'] ?? null,
            'limit' => $input['limit'] ?? null,
            'min_attendance_rate' => $input['min_attendance_rate'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $detected = $this->detector->detect($scope, $arguments);
        $coverage = $this->detector->coverage($scope, $arguments);
        $floor = $this->detector->minimumRate($arguments);

        if ($studentId !== null) {
            $detected = array_values(array_filter(
                $detected,
                static fn (DetectedSignal $signal) => (int) $signal->subjectId === $studentId
            ));
        }

        if ($detected === []) {
            return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
                'students_below_threshold' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => $coverage,
                'confidence' => 1.0,
                'message' => $this->nothingFoundMessage($coverage, $studentId, $floor),
            ]);
        }

        // One case per student: a case is about a person, not a percentage. A cohort sweep
        // that stopped at a ranked list would leave the rest of the journey with nothing
        // to stand on — no case means no evidence to cite, no recommendation to draft and
        // no approval to ask for.
        $byStudent = [];

        foreach ($detected as $signal) {
            $byStudent[(int) $signal->subjectId][] = $signal;
        }

        $cases = [];

        foreach ($byStudent as $subjectId => $signals) {
            $case = $this->buildCaseForStudent($context, (int) $subjectId, $signals, $coverage);

            if ($case !== null) {
                $cases[] = $case;
            }
        }

        // Worst attendance first — the order a class teacher should work the list in.
        usort($cases, static fn ($a, $b) => ($a['attendance_rate'] ?? 1) <=> ($b['attendance_rate'] ?? 1));

        return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $floor, [
            'students_below_threshold' => count($cases),
            'signals_detected' => count($detected),
            'lowest_attendance_rate' => $cases === [] ? null : $cases[0]['attendance_rate'],
            'cohort_attendance_rate' => $coverage['cohort_rate'],
            'cases' => $cases,
            'coverage' => $coverage,
            'confidence' => 1.0,
            'mode' => 'deep_analysis',
        ]);
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_below_threshold'] ?? 0);
        $coverage = is_array($result['coverage'] ?? null) ? $result['coverage'] : [];
        $floor = (float) ($result['minimum_attendance_rate'] ?? 0.75);

        if ($count === 0) {
            return (string) ($result['message'] ?? 'No student in this scope is below the attendance bar.');
        }

        return sprintf(
            'Found %d student%s attending below %s, among the %d of %d students with enough marked days to judge, and opened a case for each.',
            $count,
            $count === 1 ? '' : 's',
            $this->percent($floor),
            (int) ($coverage['judged'] ?? 0),
            (int) ($coverage['cohort'] ?? 0)
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<int, DetectedSignal>  $signals
     * @param  array<string, mixed>  $coverage
     * @return array<string, mixed>|null
     */
    private function buildCaseForStudent(
        AgentContext $context,
        int $studentId,
        array $signals,
        array $coverage
    ): ?array {
        $stored = $context->recordSignals($signals);

        $caseId = $context->openCase(
            self::CASE_TYPE,
            $signals,
            $stored['signal_ids'],
            $stored['evidence_ids']
        );

        if ($caseId === null) {
            // Recorded, but below the bar for a case. A legitimate outcome — one missed
            // afternoon is a signal worth trending and not worth a teacher's attention.
            return null;
        }

        $signal = $signals[0];
        $name = $signal->subjectLabel ?? ('Student #' . $studentId);
        $rate = (float) ($signal->components['attendance_rate'] ?? 0);
        $absent = (int) ($signal->components['absent_days'] ?? 0);
        $present = (int) ($signal->components['present_days'] ?? 0);
        $windowDays = (int) ($signal->components['window_days'] ?? 30);

        $context->addHypothesis(
            $caseId,
            sprintf('%s has attended %s of marked days in the last %d days.', $name, $this->percent($rate), $windowDays),
            'Raised from the marked register for this student, not from an estimate or a projection.',
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
                $stored['evidence_ids'],
                $coverage
            );
        }

        return [
            'case_id' => $caseId,
            'student_id' => $studentId,
            'student_name' => $name,
            'standard_name' => $signal->components['standard_name'] ?? null,
            'division_name' => $signal->components['division_name'] ?? null,
            'attendance_rate' => round($rate, 4),
            'present_days' => $present,
            'absent_days' => $absent,
            'window_days' => $windowDays,
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
            $rate = (float) ($components['attendance_rate'] ?? 0);
            $absent = (int) ($components['absent_days'] ?? 0);
            $present = (int) ($components['present_days'] ?? 0);
            $label = $signal->subjectLabel ?? ('Student #' . $signal->subjectId);

            $claims[] = [
                'claim' => sprintf(
                    '%s was absent on %d of %d marked day%s, an attendance rate of %s, which the register classes as %s.',
                    $label,
                    $absent,
                    $present + $absent,
                    ($present + $absent) === 1 ? '' : 's',
                    $this->percent($rate),
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
     * @param  array<string, mixed>  $coverage
     * @return array<string, mixed>
     */
    private function draftRecommendation(
        AgentContext $context,
        int $caseId,
        array $explanation,
        int $studentId,
        string $name,
        DetectedSignal $signal,
        array $evidenceIds,
        array $coverage
    ): array {
        $rate = (float) ($signal->components['attendance_rate'] ?? 0);
        $absent = (int) ($signal->components['absent_days'] ?? 0);
        $windowDays = (int) ($signal->components['window_days'] ?? 30);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'start_attendance_follow_up',
            'title' => sprintf('Follow up %s\'s attendance (%s over %d days)', $name, $this->percent($rate), $windowDays),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from the marked register for this student and the days they were recorded absent.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $signal->confidence ?? 1.0,
            // Capped by what can actually be cited, not by the percentage alone.
            //
            // `RecommendVerb` requires 3 distinct pieces of verified evidence for a
            // high-risk recommendation, 2 for medium, 1 for low. A student with a single
            // itemised absence yields few citable rows, and claiming 'high' on that would
            // get the whole recommendation refused — leaving a real absence problem with
            // no approval request attached to it. Inflating the evidence to clear the bar
            // would be the wrong repair: the bar exists to stop a consequential action
            // resting on one data point. So severity says how bad the attendance is, and
            // risk level says how well it is evidenced.
            'risk_level' => $this->riskLevel($signal->severity, count($evidenceIds)),
            // Consequential, and that is the point of it.
            //
            // `RecommendVerb::normalizeForPersistence()` files a non-consequential
            // recommendation as `draft` and only a consequential one as
            // `pending_approval`. What makes this consequential is what a person does
            // after approving it: a family is contacted about their child's absence, and
            // some of those absences will have reasons the school does not yet know —
            // illness, bereavement, a care arrangement. That conversation belongs to a
            // named human being, never to a sweep that ran overnight.
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            // What success would look like, which governance requires before anything can
            // be approved: a recommendation that cannot say what it is trying to achieve
            // cannot be measured afterwards, so it is refused rather than approved on
            // trust.
            'eso_binding' => [
                'objective' => sprintf('Bring %s\'s attendance back above the school\'s bar.', $name),
                'strategy' => 'Speak to the family about the recorded absences, establish the reason, and agree '
                    . 'what support is needed through the attendance follow-up workflow.',
                'outcome' => [
                    'metric_key' => 'attendance_rate',
                    'metric_label' => 'Attendance rate (percent of marked days present)',
                    'direction' => 'increase',
                    'baseline' => round($rate * 100, 2),
                    'target' => 90,
                    'horizon_days' => $signal->severity === 'critical' ? 14 : 30,
                ],
            ],
            'workflow_key' => self::WORKFLOW_KEY,
            'workflow_payload' => [
                'student_id' => $studentId,
                'student_name' => $name,
                'case_id' => $caseId,
                'attendance_rate' => round($rate, 4),
                'absent_days' => $absent,
                'present_days' => (int) ($signal->components['present_days'] ?? 0),
                'window_days' => $windowDays,
                'severity' => $signal->severity,
                'standard_name' => $signal->components['standard_name'] ?? null,
                'division_name' => $signal->components['division_name'] ?? null,
                'judged_out_of' => (int) ($coverage['cohort'] ?? 0),
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
        $judged = (int) ($coverage['judged'] ?? 0);
        $cohort = (int) ($coverage['cohort'] ?? 0);

        if ($judged === 0) {
            return $cohort === 0
                ? 'No attendance has been marked in this scope, so nothing is known either way about who is missing school.'
                : sprintf(
                    'None of the %d students in scope has enough marked days to judge, so nothing is known '
                    . 'either way about their attendance.',
                    $cohort
                );
        }

        if ($studentId !== null) {
            return sprintf(
                'Student #%d is attending at or above %s over the window read.',
                $studentId,
                $this->percent($floor)
            );
        }

        return $coverage['complete'] === true
            ? sprintf('No student in this scope is attending below %s.', $this->percent($floor))
            : sprintf(
                'No student attending below %s was found among the %d of %d students with enough marked days to judge.',
                $this->percent($floor),
                $judged,
                $cohort
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
            'minimum_attendance_rate' => $floor,
        ], static fn ($value) => $value !== null));
    }

    /**
     * The risk level a recommendation may claim, given how much it can cite.
     *
     * Severity is about how low the attendance is; risk level is about how defensible
     * acting on it is. They are allowed to differ, and when the register holds one
     * itemised row they must.
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
        return number_format($rate * 100, 1) . '%';
    }
}
