<?php

namespace App\Agents\Fees;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\Fees\Collection\FeesRiskTrendDetector;

/**
 * The Fees Predictive agent: whose outstanding balance is trending worse,
 * not just who currently owes money.
 *
 * Same shape as FeesAgent, deliberately: detect → record signals → open a
 * case → explain with cited evidence → draft a recommendation bound to a
 * workflow. The difference is the workflow it binds to (`fees_proactive_outreach`,
 * not `fees_collection`) and the case type (`fee_payment_forecast`) — a
 * trend is a lower-stakes, earlier-warning finding than an existing debt,
 * and should not compete with FeesAgent's own collection-review queue.
 *
 * Every predictive recommendation carries exactly one piece of evidence
 * (the trend computation itself), so riskLevel() below always resolves to
 * 'low' regardless of how steep the projected growth is — a single derived
 * data point should never be allowed to claim more than that, no matter
 * how alarming the number looks.
 */
class FeesPredictiveAgent implements Agent
{
    private const CASE_TYPE = 'fee_payment_forecast';

    private const WORKFLOW_KEY = 'fees_proactive_outreach';

    public function __construct(private readonly FeesRiskTrendDetector $detector)
    {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $studentId = isset($input['student_id']) ? (int) $input['student_id'] : null;

        $arguments = array_filter([
            'standard_id' => $input['standard_id'] ?? null,
            'section_id' => $input['section_id'] ?? null,
            'min_amount' => $input['min_amount'] ?? null,
            'limit' => $input['limit'] ?? null,
        ], static fn ($value) => $value !== null);

        $startedAt = microtime(true);
        $detected = $this->detector->detect($scope, $arguments);
        $coverage = $this->detector->coverage($scope, $arguments);
        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);

        $context->noteToolCall(
            'fees.arrears',
            'completed',
            $elapsed,
            sprintf('%d of %d current defaulter(s) checked for a worsening trend', (int) ($coverage['checked'] ?? 0), (int) ($coverage['cohort'] ?? 0))
        );

        if ($studentId !== null) {
            $detected = array_values(array_filter(
                $detected,
                static fn (DetectedSignal $signal) => (int) $signal->subjectId === $studentId
            ));
        }

        if ($detected === []) {
            return [
                'student_id' => $studentId,
                'sub_institute_id' => $scope->selectedInstituteId,
                'students_with_worsening_trend' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => $coverage,
                'message' => $studentId !== null
                    ? sprintf('Student #%d has no fees_outstanding_balance trend data showing a worsening balance.', $studentId)
                    : 'No current defaulter in this scope has enough recorded history to show a worsening trend, or none is worsening.',
            ];
        }

        $cases = [];

        foreach ($detected as $signal) {
            $case = $this->buildCaseForStudent($context, (int) $signal->subjectId, $signal);

            if ($case !== null) {
                $cases[] = $case;
            }
        }

        usort($cases, static fn ($a, $b) => ($b['projected_30d_change'] ?? 0) <=> ($a['projected_30d_change'] ?? 0));

        return [
            'student_id' => $studentId,
            'sub_institute_id' => $scope->selectedInstituteId,
            'students_with_worsening_trend' => count($cases),
            'signals_detected' => count($detected),
            'cases' => $cases,
            'coverage' => $coverage,
        ];
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_with_worsening_trend'] ?? 0);

        if ($count === 0) {
            return (string) ($result['message'] ?? 'No worsening fee-payment trend was found in the current scope.');
        }

        return sprintf(
            'Found %d student%s with a worsening fee-payment trend, projected to grow further over the next 30 days, and opened a case for each.',
            $count,
            $count === 1 ? '' : 's'
        );
    }

    // ---------------------------------------------------------------- internals

    private function buildCaseForStudent(AgentContext $context, int $studentId, DetectedSignal $signal): ?array
    {
        $stored = $context->recordSignals([$signal]);

        $caseId = $context->openCase(
            self::CASE_TYPE,
            [$signal],
            $stored['signal_ids'],
            $stored['evidence_ids']
        );

        if ($caseId === null) {
            // Recorded, but below the bar for a case — a legitimate outcome.
            return null;
        }

        $name = $signal->subjectLabel ?? ('Student #' . $studentId);
        $projected = (float) ($signal->components['projected_30d_change'] ?? 0);

        $context->addHypothesis(
            $caseId,
            sprintf(
                "%s's fee balance is trending upward and is projected to grow by %s over the next 30 days at the current pace.",
                $name,
                $this->money($projected)
            ),
            sprintf('Raised from %d recorded fees_outstanding_balance sweep(s) for this student, not from an estimate outside that history.', (int) ($signal->components['history_points'] ?? 0)),
            $stored['evidence_ids'],
            [],
            $signal->confidence
        );

        $citable = collect($context->evidenceFor('student', $studentId, null, 100))
            ->whereIn('id', $stored['evidence_ids'])
            ->where('verified', true)
            ->pluck('id')
            ->all();

        $explanation = $context->explain(
            $caseId,
            $citable === [] ? [] : [[
                'claim' => sprintf(
                    "%s's outstanding balance moved from %s to %s across %d recorded sweep(s), a pace that projects to %s more over the next 30 days.",
                    $name,
                    $this->money((float) ($signal->components['first_score'] ?? 0)),
                    $this->money((float) ($signal->components['last_score'] ?? 0)),
                    (int) ($signal->components['history_points'] ?? 0),
                    $this->money($projected)
                ),
                'evidence_ids' => $citable,
                'confidence' => $signal->confidence,
            ]],
            'teacher',
            'student',
            $studentId,
            $name
        );

        $recommendation = null;

        if ($explanation['governance']->passed) {
            $recommendation = $this->draftRecommendation($context, $caseId, $explanation, $studentId, $name, $signal, $stored['evidence_ids']);
        }

        return [
            'case_id' => $caseId,
            'student_id' => $studentId,
            'student_name' => $name,
            'projected_30d_change' => round($projected, 2),
            'history_points' => (int) ($signal->components['history_points'] ?? 0),
            'severity' => $signal->severity,
            'signal' => $signal->toArray(),
            'explanation' => [
                'narrative' => $explanation['narrative'],
                'governance_passed' => $explanation['governance']->passed,
                'reason_refused' => $explanation['governance']->passed ? null : $explanation['governance']->reason(),
            ],
            'recommendation' => $recommendation,
        ];
    }

    private function draftRecommendation(
        AgentContext $context,
        int $caseId,
        array $explanation,
        int $studentId,
        string $name,
        DetectedSignal $signal,
        array $evidenceIds
    ): array {
        $projected = (float) ($signal->components['projected_30d_change'] ?? 0);

        $draft = [
            'case_id' => $caseId,
            'explanation_id' => $explanation['id'],
            'domain' => 'k12',
            'action_type' => 'start_fees_proactive_outreach',
            'title' => sprintf('Reach out before %s\'s fee balance grows further', $name),
            'body' => $explanation['narrative'],
            'rationale' => 'Drafted from this student\'s own recorded fee-balance trend, not a one-off snapshot.',
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
            'confidence' => $signal->confidence ?? 0.45,
            // Exactly one piece of evidence (the trend computation) is ever
            // cited here, so this always resolves to 'low' — a single
            // derived data point should never claim more, regardless of
            // how large the projected amount is.
            'risk_level' => $this->riskLevel(count($evidenceIds)),
            // Always consequential: per this module's locked-in design, no
            // Fees action — proactive or not — skips human approval.
            'is_consequential' => true,
            'evidence_ids' => $evidenceIds,
            'eso_binding' => [
                'objective' => sprintf('Keep %s\'s outstanding balance from growing by the projected %s over the next 30 days.', $name, $this->money($projected)),
                'strategy' => 'Reach out proactively, before the balance grows further, through the fees_proactive_outreach workflow.',
                'outcome' => [
                    'metric_key' => 'fees_outstanding_balance',
                    'metric_label' => 'Outstanding fee balance (INR)',
                    'direction' => 'decrease',
                    'baseline' => (float) ($signal->components['last_score'] ?? 0),
                    'target' => (float) ($signal->components['last_score'] ?? 0),
                    'horizon_days' => 30,
                ],
            ],
            'workflow_key' => self::WORKFLOW_KEY,
            'workflow_payload' => [
                'student_id' => $studentId,
                'student_name' => $name,
                'case_id' => $caseId,
                'projected_30d_change' => round($projected, 2),
                'history_points' => (int) ($signal->components['history_points'] ?? 0),
                'severity' => $signal->severity,
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

    private function riskLevel(int $evidenceCount): string
    {
        return match (true) {
            $evidenceCount >= 3 => 'high',
            $evidenceCount === 2 => 'medium',
            default => 'low',
        };
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}
