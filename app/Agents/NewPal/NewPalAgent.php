<?php

namespace App\Agents\NewPal;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\NewPal\Risk\PalInterventionDetector;

/**
 * The New PAL agent: which learners' practised concepts remain stuck at the lowest
 * mastery tier, why that is the figure, and what to do about it.
 *
 * THE SHAPE IS THE ATTENDANCE AND EXAM AGENTS', DELIBERATELY
 *
 * detect → record signals → open a case → explain with cited evidence → draft a
 * recommendation bound to a workflow. That sequence is not reinvented here: it is the
 * one `FeesAgent`, `AttendanceAgent` and `ExamAgent` already prove, and following it
 * means a New PAL case is read, approved and audited by exactly the machinery that
 * already reads, approves and audits a fee, attendance or exam one.
 *
 * NOTHING HERE RE-GRADES A CONCEPT
 *
 * Every figure comes from `PalInterventionDetector`, which in turn asks
 * `LearnerActivitySource` — the same building block `GamificationService::overview()`
 * and the `new_pal.gamification_summary` tool use. This class chooses what to say about
 * a learner's recorded mastery tiers and never what they are, and it never reaches the
 * older `pal` module's tables.
 *
 * WHAT IT MAY DO, AND WHAT IT MAY NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0` on the manifest. The agent may
 * detect, analyse, explain and recommend. It may not contact a family or change a
 * mastery record. Deciding what intervention a learner needs is a decision that belongs
 * to a named human being in an approval queue, which is why the only thing it can bind
 * a recommendation to is the `pal_intervention_followup` workflow and its approval step.
 *
 * "NOTHING FOUND" IS NOT THE SAME AS "NOTHING KNOWN"
 *
 * A learner nobody has enrolled, or one who has never opened a concept, produces
 * exactly the same silence as a learner who has mastered everything they have tried.
 * The judged coverage is carried through every result and into the summary for that
 * reason.
 */
class NewPalAgent implements Agent
{
    private const CASE_TYPE = 'pal_intervention_review';

    private const WORKFLOW_KEY = 'pal_intervention_followup';

    public function __construct(
        private readonly PalInterventionDetector $detector,
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
            'limit' => $input['limit'] ?? null,
            'min_stream_share' => $input['min_stream_share'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $detected = $this->detector->detect($scope, $arguments);
        $coverage = $this->detector->coverage($scope, $arguments);
        $minShare = $this->detector->minStreamShare($arguments);

        if ($detected === []) {
            return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $minShare, [
                'learners_flagged' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => $coverage,
                'confidence' => 1.0,
                'message' => $this->nothingFoundMessage($coverage, $studentId, $minShare),
            ]);
        }

        // One case per learner: a case is about a person, not a proportion. A cohort
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

        // Highest stuck share first — the order a coordinator should work the list in.
        usort($cases, static fn ($a, $b) => ($b['stream_share'] ?? 0) <=> ($a['stream_share'] ?? 0));

        return $this->result($studentId, $scope->selectedInstituteId, $scope->academicYear, $minShare, [
            'learners_flagged' => count($cases),
            'signals_detected' => count($detected),
            'highest_stream_share' => $cases === [] ? null : $cases[0]['stream_share'],
            'cases' => $cases,
            'coverage' => $coverage,
            'confidence' => 1.0,
            'mode' => 'deep_analysis',
        ]);
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['learners_flagged'] ?? 0);
        $coverage = is_array($result['coverage'] ?? null) ? $result['coverage'] : [];

        if ($count === 0) {
            return (string) ($result['message'] ?? 'No learner in this scope is stuck at the Stream tier.');
        }

        return sprintf(
            'Found %d learner%s with most of their practised concepts still at Stream, among the %d of %d learners with enough recorded practice to judge, and opened a case for each.',
            $count,
            $count === 1 ? '' : 's',
            (int) ($coverage['judged'] ?? 0),
            (int) ($coverage['cohort'] ?? 0)
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
            // Recorded, but below the bar for a case. A legitimate outcome — a handful of
            // concepts still at Stream is a signal worth trending and not worth a
            // coordinator's attention on its own.
            return null;
        }

        $signal = $signals[0];
        $name = $signal->subjectLabel ?? ('Student #' . $studentId);
        $streamCount = (int) ($signal->components['stream_count'] ?? 0);
        $attempted = (int) ($signal->components['attempted_concepts'] ?? 0);
        $share = (float) ($signal->components['stream_share'] ?? 0);

        $context->addHypothesis(
            $caseId,
            sprintf(
                '%s has %d of %d practised concepts still at the Stream tier (%s).',
                $name,
                $streamCount,
                $attempted,
                $this->percent($share)
            ),
            'Raised from the recorded concept mastery tiers for this learner, not from an estimate or a projection.',
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
            'division_name' => $signal->components['division_name'] ?? null,
            'stream_share' => round($share, 4),
            'stream_count' => $streamCount,
            'attempted_concepts' => $attempted,
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
            $streamCount = (int) ($components['stream_count'] ?? 0);
            $attempted = (int) ($components['attempted_concepts'] ?? 0);
            $label = $signal->subjectLabel ?? ('Student #' . $signal->subjectId);

            $claims[] = [
                'claim' => sprintf(
                    '%s has %d of %d practised concepts still at the Stream tier, which the recorded mastery map classes as %s.',
                    $label,
                    $streamCount,
                    $attempted,
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
        $share = (float) ($signal->components['stream_share'] ?? 0);
        $streamCount = (int) ($signal->components['stream_count'] ?? 0);
        $attempted = (int) ($signal->components['attempted_concepts'] ?? 0);
        $minShare = (float) ($signal->components['min_stream_share'] ?? 0.5);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'start_pal_intervention_review',
            'title' => sprintf('Review %s\'s learning progress (%d of %d practised concepts at Stream)', $name, $streamCount, $attempted),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from the recorded concept mastery tiers for this learner and the concepts '
                . 'behind them.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $signal->confidence ?? 1.0,
            // Capped by what can actually be cited, not by the share alone — see
            // AttendanceAgent/FeesAgent/ExamAgent for why: RecommendVerb requires 3
            // distinct pieces of verified evidence for a high-risk recommendation, 2 for
            // medium, 1 for low.
            'risk_level' => $this->riskLevel($signal->severity, count($evidenceIds)),
            // Consequential, and that is the point of it. What makes this consequential
            // is what a person does after approving it: a family or a learner is told
            // where they need support, and deciding what that support looks like
            // belongs to a named human being, never to a sweep that ran overnight.
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            'eso_binding' => [
                'objective' => sprintf('Move %s\'s practised concepts off the Stream tier.', $name),
                'strategy' => 'Review the concepts still at Stream with the subject teacher, agree what support '
                    . 'is needed, and record the plan through the PAL intervention-review workflow.',
                'outcome' => [
                    'metric_key' => 'pal_stream_share',
                    'metric_label' => 'Share of practised concepts still at the Stream tier',
                    'direction' => 'decrease',
                    'baseline' => round($share * 100, 2),
                    'target' => round($minShare * 100, 2),
                    'horizon_days' => $signal->severity === 'critical' ? 21 : 45,
                ],
            ],
            'workflow_key' => self::WORKFLOW_KEY,
            'workflow_payload' => [
                'student_id' => $studentId,
                'student_name' => $name,
                'case_id' => $caseId,
                'stream_share' => round($share, 4),
                'stream_count' => $streamCount,
                'attempted_concepts' => $attempted,
                'severity' => $signal->severity,
                'standard_name' => $signal->components['standard_name'] ?? null,
                'division_name' => $signal->components['division_name'] ?? null,
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
    private function nothingFoundMessage(array $coverage, ?int $studentId, float $minShare): string
    {
        $cohort = (int) ($coverage['cohort'] ?? 0);
        $judged = (int) ($coverage['judged'] ?? 0);

        if ($cohort === 0) {
            return 'No learner could be resolved in this scope, so nothing is known either way about their PAL progress.';
        }

        if ($judged === 0) {
            return sprintf(
                'None of the %d learner%s in scope has practised enough concepts to judge, so nothing is known '
                . 'either way about their mastery progress.',
                $cohort,
                $cohort === 1 ? '' : 's'
            );
        }

        if ($studentId !== null) {
            return sprintf(
                'Student #%d has fewer than %s of their practised concepts at the Stream tier.',
                $studentId,
                $this->percent($minShare)
            );
        }

        return sprintf(
            'No learner was found with %s or more of their practised concepts still at the Stream tier, among the %d of %d learners with enough recorded practice to judge.',
            $this->percent($minShare),
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
        float $minShare,
        array $findings
    ): array {
        return array_merge($findings, array_filter([
            'student_id' => $studentId,
            'sub_institute_id' => $instituteId,
            'syear' => $academicYear,
            'min_stream_share' => $minShare,
        ], static fn ($value) => $value !== null));
    }

    /**
     * The risk level a recommendation may claim, given how much it can cite.
     *
     * Severity is about how much of the learner's practice is stuck; risk level is
     * about how defensible acting on it is. They are allowed to differ, and when the
     * record holds one itemised concept they must.
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
