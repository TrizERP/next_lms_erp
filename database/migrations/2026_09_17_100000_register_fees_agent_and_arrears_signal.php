<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Fees agent and the signal it raises, so the lifecycle can reach stages
 * 8 and 10 to 12 for a fee question.
 *
 * WHY THIS IS THE WHOLE GAP
 *
 * Everything else was already in place and nothing here invents a new mechanism:
 * `workflow_definitions` already carries `fees_collection`, published, with an approval
 * step; `config/ai.php` already binds the Fees module to `agent_key = k12_fees` and
 * `case_type = fee_collection`; `FeesAgent` exists in code. The one thing missing was
 * the manifest row — so `AgentRegistry` returned null, `ModuleRegistry` reported
 * "configured to use the k12_fees agent, but no active manifest for it exists", and
 * every fee question answered `agent · skipped`, which left evidence, recommendation,
 * approval and action permanently not-reached.
 *
 * WHAT THE MANIFEST LICENSES, AND WHAT IT DOES NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0`, matching the academic-risk agent.
 * The Fees agent may detect, analyse, explain and recommend. It may not act. Collecting,
 * cancelling or refunding money stays a human act on the Fees screen, reached through the
 * `fees_collection` workflow's approval step — which is why that is the only workflow
 * key it is authorised to bind a recommendation to.
 *
 * SEVERITY BANDS ARE DATA
 *
 * `ai_signal_definitions.thresholds` holds the rupee bands `ThresholdRegistry` classifies
 * against, per institute. They are written in rupees because the score handed to the
 * classifier is the outstanding amount itself, so a reader of the row can see what the
 * band means. A school that considers a different figure serious changes the row, not
 * the code. The values below are a starting point, not a finding about any school.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_fees';

    private const SIGNAL_KEY = 'fees_outstanding_balance';

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

        $this->setModuleAgentCapability(false);
    }

    private function upsertSignalDefinition(): void
    {
        if (! Schema::hasTable('ai_signal_definitions')) {
            return;
        }

        $row = [
            'signal_key' => self::SIGNAL_KEY,
            'label' => 'Outstanding fee balance',
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'description' => 'A student carrying unpaid fees, as reported by the same arrears engine the '
                . 'Fees screen uses. The score is the outstanding amount in rupees.',
            'detector_class' => \App\Domain\Fees\Collection\FeeArrearsDetector::class,
            'severity_scale' => json_encode(['low', 'moderate', 'high', 'critical']),
            'thresholds' => json_encode(['low' => 1, 'moderate' => 5000, 'high' => 15000, 'critical' => 40000]),
            'inputs' => json_encode(['service' => 'FeesArrearsService', 'basis' => 'fees_collect_controller::getBk']),
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
            'name' => 'Fees Agent',
            'domain' => 'k12',
            'purpose' => 'Find students carrying unpaid fees, explain the balance with the fee heads behind '
                . 'it, and draft a collection review for a person to approve.',
            'description' => 'Reads arrears through the same engine the Fees screen uses, opens a case per '
                . 'student with the unpaid heads as evidence, and drafts a fees_collection review. It never '
                . 'collects, cancels or refunds — that stays a human act on the Fees screen.',
            'runner_class' => \App\Agents\Fees\FeesAgent::class,
            'agent_type' => 'domain',
            'allowed_tools' => json_encode(['fees.arrears', 'fees.getPending', 'fees.collection_report']),
            'allowed_entities' => json_encode(['student', 'enrollment', 'standard', 'division', 'signal', 'evidence', 'case', 'recommendation']),
            'allowed_signal_keys' => json_encode([self::SIGNAL_KEY]),
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            // Comma-separated, not JSON: AgentManifest reads this column with
            // splitCsv(), so a JSON array arrives as one key literally named
            // '["fees_collection"]' and every recommendation is refused as
            // unauthorised. The k12_academic_risk row stores a bare string too.
            'authorized_workflow_keys' => 'fees_collection',
            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'student_id' => ['type' => 'integer'],
                    'standard_id' => ['type' => 'integer'],
                    'section_id' => ['type' => 'integer'],
                    'min_amount' => ['type' => 'number'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    'intent' => ['type' => 'string'],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['students_with_arrears', 'cases'],
                'properties' => [
                    'students_with_arrears' => ['type' => 'integer'],
                    'total_outstanding' => ['type' => 'number'],
                    'cases' => ['type' => 'array'],
                    'coverage' => ['type' => 'object'],
                ],
            ]),
            'required_permissions' => json_encode(['fees.collect']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'fee_collection']),
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
     * Flip only the `agent` flag, merging into whatever the row already holds.
     *
     * Writing a freshly built object over `capabilities` would enable this one flag by
     * silently deleting any other the estate carries.
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
