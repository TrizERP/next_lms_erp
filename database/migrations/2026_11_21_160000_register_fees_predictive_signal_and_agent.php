<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Fees Predictive agent and the signal it raises — the
 * Predictive Intelligence capability of the Fees Recommendation panel's
 * five intelligence capabilities (2026-11-21 plan). Follows the exact
 * template 2026_09_17_100000_register_fees_agent_and_arrears_signal.php
 * already proved for `k12_fees`.
 *
 * `max_verb = recommend` and `may_execute_actions = 0`, matching `k12_fees`:
 * this agent may detect, analyse, explain and recommend a worsening
 * trend. It may not act — proactive outreach stays a human decision,
 * reached through the `fees_proactive_outreach` workflow's approval step,
 * which is why that is the only workflow key it is authorised to bind a
 * recommendation to.
 *
 * SEVERITY BANDS ARE DATA, same as the existing fees_outstanding_balance
 * row, but on a different scale: the score handed to the classifier here
 * is a projected 30-DAY CHANGE (a delta), not an absolute balance, so the
 * bands below are deliberately lower. A starting point, not a finding
 * about any school.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_fees_predictive';

    private const SIGNAL_KEY = 'fees_projected_default_risk';

    public function up(): void
    {
        $this->upsertSignalDefinition();
        $this->upsertAgent();
        $this->setModuleAgentCapability(true);
    }

    public function down(): void
    {
        // Retired rather than deleted, so a re-enable is one column and the history of
        // what ran under this manifest keeps its referent.
        if (Schema::hasTable('ai_agents')) {
            DB::table('ai_agents')->where('agent_key', self::AGENT_KEY)
                ->update(['status' => 0, 'updated_at' => now()]);
        }

        if (Schema::hasTable('ai_signal_definitions')) {
            DB::table('ai_signal_definitions')->where('signal_key', self::SIGNAL_KEY)
                ->update(['status' => 0, 'updated_at' => now()]);
        }
    }

    private function upsertSignalDefinition(): void
    {
        if (! Schema::hasTable('ai_signal_definitions')) {
            return;
        }

        $row = [
            'signal_key' => self::SIGNAL_KEY,
            'label' => 'Projected fee default risk',
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'description' => 'A student whose outstanding fee balance is trending upward across recorded '
                . 'fees_outstanding_balance sweeps, projected forward 30 days at the current pace. The score '
                . 'is the projected 30-day change in rupees, not the balance itself.',
            'detector_class' => \App\Domain\Fees\Collection\FeesRiskTrendDetector::class,
            'severity_scale' => json_encode(['low', 'moderate', 'high', 'critical']),
            'thresholds' => json_encode(['low' => 1, 'moderate' => 2000, 'high' => 8000, 'critical' => 20000]),
            'inputs' => json_encode(['service' => 'FeesCollectionForecastService', 'basis' => 'ai_signals history for fees_outstanding_balance']),
            'requires_evidence' => 1,
            'status' => 1,
            'updated_at' => now(),
        ];

        $existing = DB::table('ai_signal_definitions')
            ->where('signal_key', self::SIGNAL_KEY)
            ->whereNull('sub_institute_id')
            ->first();

        if ($existing !== null) {
            DB::table('ai_signal_definitions')->where('id', $existing->id)->update($row);

            return;
        }

        DB::table('ai_signal_definitions')->insert($row + ['created_at' => now()]);
    }

    private function upsertAgent(): void
    {
        if (! Schema::hasTable('ai_agents')) {
            return;
        }

        $row = [
            'agent_key' => self::AGENT_KEY,
            'name' => 'Fees Predictive Agent',
            'domain' => 'k12',
            'purpose' => 'Find students whose fee balance is trending worse across recorded sweeps, project '
                . 'it forward, and draft a proactive-outreach recommendation for a person to approve.',
            'description' => 'Reads each current defaulter\'s own fees_outstanding_balance history '
                . '(via FeesCollectionForecastService) and raises a signal only when the trend is increasing '
                . 'and enough history exists to say so. It never acts — proactive outreach stays a human act, '
                . 'reached through the fees_proactive_outreach workflow.',
            'runner_class' => \App\Agents\Fees\FeesPredictiveAgent::class,
            'agent_type' => 'domain',
            'allowed_tools' => json_encode(['fees.arrears']),
            'allowed_entities' => json_encode(['student', 'enrollment', 'standard', 'division', 'signal', 'evidence', 'case', 'recommendation']),
            'allowed_signal_keys' => json_encode([self::SIGNAL_KEY]),
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            // Comma-separated, not JSON — see k12_fees's own row for why a JSON
            // array here silently refuses every recommendation as unauthorised.
            'authorized_workflow_keys' => 'fees_proactive_outreach',
            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'student_id' => ['type' => 'integer'],
                    'standard_id' => ['type' => 'integer'],
                    'section_id' => ['type' => 'integer'],
                    'min_amount' => ['type' => 'number'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['students_with_worsening_trend', 'cases'],
                'properties' => [
                    'students_with_worsening_trend' => ['type' => 'integer'],
                    'cases' => ['type' => 'array'],
                    'coverage' => ['type' => 'object'],
                ],
            ]),
            'required_permissions' => json_encode(['fees.collect']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.4,
            'min_evidence_count' => 1,
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'fee_payment_forecast']),
            'status' => 1,
            'updated_at' => now(),
        ];

        $existing = DB::table('ai_agents')
            ->where('agent_key', self::AGENT_KEY)
            ->whereNull('sub_institute_id')
            ->first();

        if ($existing !== null) {
            DB::table('ai_agents')->where('id', $existing->id)->update($row);

            return;
        }

        DB::table('ai_agents')->insert($row + ['created_at' => now()]);
    }

    /**
     * Flip only the `agent` flag, merging into whatever the row already holds —
     * same precaution as the k12_fees registration (it is almost certainly
     * already true, since k12_fees set it, but re-asserting it as a merge
     * rather than an overwrite costs nothing and stays safe either way).
     */
    private function setModuleAgentCapability(bool $enabled): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', 'fees')->get(['id', 'capabilities']);

        foreach ($rows as $row) {
            $existing = json_decode((string) $row->capabilities, true);

            if (! is_array($existing)) {
                $existing = ['conversational' => true, 'generative' => true, 'workflow' => true];
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'capabilities' => json_encode(array_merge($existing, ['agent' => $enabled])),
                'updated_at' => now(),
            ]);
        }
    }
};
