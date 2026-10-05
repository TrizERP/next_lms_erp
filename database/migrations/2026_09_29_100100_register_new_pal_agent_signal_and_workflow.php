<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the New PAL agent, the signal it raises and the workflow it can put to a
 * person — so the lifecycle can reach stages 8 and 10 to 12 for a New PAL question, the
 * same way 2026_09_19_100000_register_attendance_agent_signal_and_workflow.php did for
 * Attendance and 2026_09_29_100000_register_exam_agent_signal_and_workflow.php did for
 * Exam.
 *
 * WHY ALL THREE AT ONCE
 *
 * `ai_modules` has carried `new_pal` since 2026_09_28_100200 registered it, and
 * `new_pal.gamification_summary` / `.content_model_status` / `.coherence_gaps` already
 * answer questions about it — but there has never been a New PAL case type or anything
 * for a person to approve. `config/ai.php`'s own comment on the `new_pal` block says
 * exactly this: "New PAL itself has no agent of its own yet." Adding a manifest without
 * a workflow would produce an agent that could detect and explain and then have nowhere
 * to send what it found.
 *
 * NEVER THE OLDER `pal` MODULE
 *
 * The agent this migration registers (`App\Agents\NewPal\NewPalAgent`) reads through
 * `PalInterventionDetector`, which itself reads only New PAL's own gamification tables
 * via `LearnerActivitySource` — the same source `GamificationService::overview()` and
 * the `new_pal.gamification_summary` tool already use. The legacy `pal` module's tables
 * are never touched.
 *
 * WHAT THE MANIFEST LICENSES, AND WHAT IT DOES NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0`, matching Fees, Attendance and
 * Exam. The New PAL agent may detect, analyse, explain and recommend. It may not act.
 * Deciding what intervention a learner needs stays a human act — reached through the
 * `pal_intervention_followup` workflow's approval step, which is why that is the only
 * workflow key it is authorised to bind a recommendation to.
 *
 * SEVERITY BANDS ARE DATA
 *
 * `ai_signal_definitions.thresholds` holds the bands `ThresholdRegistry` classifies
 * against. The score is `PalInterventionDetector`'s own 0..1 share of practised
 * concepts still at the Stream tier — already a bounded proportion, so the registry's
 * own default bands apply, written here explicitly for a reader rather than left
 * implicit. A school that considers a different share serious writes its own row
 * against its `sub_institute_id`; that is the only sanctioned way to diverge.
 *
 * THIS MIGRATION TOUCHES NOTHING BELONGING TO FEES, ATTENDANCE OR EXAM. Every write is
 * keyed on a New PAL key that does not exist yet, and the `ai_modules` update merges
 * into the `new_pal` row only.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_new_pal';

    private const SIGNAL_KEY = 'pal_low_mastery_progress';

    private const WORKFLOW_KEY = 'pal_intervention_followup';

    private const MODULE_KEY = 'new_pal';

    public function up(): void
    {
        $this->upsertSignalDefinition();
        $this->upsertWorkflow();
        $this->upsertAgent();
        $this->setModuleCapabilities(['agent' => true, 'workflow' => true, 'generative' => true]);
        $this->offerInTheAssistant();
    }

    public function down(): void
    {
        foreach ([
            ['ai_agents', 'agent_key', self::AGENT_KEY],
            ['ai_signal_definitions', 'signal_key', self::SIGNAL_KEY],
            ['workflow_definitions', 'workflow_key', self::WORKFLOW_KEY],
        ] as [$table, $column, $value]) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where($column, $value)->update(['status' => 0, 'updated_at' => now()]);
            }
        }

        $this->setModuleCapabilities(['agent' => false, 'workflow' => false]);

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->where('module_key', self::MODULE_KEY)
                ->whereIn('capability', ['agent', 'workflow'])
                ->whereIn('action_ref', [self::AGENT_KEY, self::WORKFLOW_KEY])
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }

    /**
     * Make the agent and its workflow offerable from the assistant panel.
     *
     * See the exam and attendance registration migrations for why these two rows — not
     * the `ai_agents` row alone — are what put the agent in front of a person in chat.
     */
    private function offerInTheAssistant(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $rows = [
            [
                'capability' => 'agent',
                'label' => 'Analyse learning progress',
                'description' => 'Open a case for each learner whose practised concepts remain mostly at the '
                    . 'Stream tier, citing the concepts behind it, and draft a follow-up for approval.',
                'action_type' => 'run_agent',
                'action_ref' => self::AGENT_KEY,
            ],
            [
                'capability' => 'workflow',
                'label' => 'PAL intervention follow-up',
                'description' => 'Review a learner whose recorded progress has stalled before anybody agrees '
                    . 'an intervention.',
                'action_type' => 'start_workflow',
                'action_ref' => self::WORKFLOW_KEY,
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('ai_suggestions')
                ->where('module_key', self::MODULE_KEY)
                ->where('capability', $row['capability'])
                ->where('action_ref', $row['action_ref'])
                ->whereNull('sub_institute_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('ai_suggestions')->insert($row + [
                'module_key' => self::MODULE_KEY,
                'icon' => null,
                'prompt' => null,
                'payload' => null,
                'requires_entity' => false,
                'allowed_roles' => null,
                'required_permissions' => null,
                'sort_order' => 10,
                'status' => 1,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertSignalDefinition(): void
    {
        if (! Schema::hasTable('ai_signal_definitions')) {
            return;
        }

        $row = [
            'signal_key' => self::SIGNAL_KEY,
            'label' => 'Practised concepts stuck at the Stream tier',
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'description' => 'A learner whose recorded, practised concepts remain mostly at the Stream tier — '
                . 'the tier every concept starts at — as reported by the same records the '
                . 'new_pal.gamification_summary tool and the Coherence Map use. The score is the share of '
                . 'practised concepts still at Stream, already a 0..1 proportion.',
            'detector_class' => \App\Domain\NewPal\Risk\PalInterventionDetector::class,
            'severity_scale' => 'risk_score',
            'thresholds' => json_encode([
                'bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25],
                'trigger' => 0.5,
            ]),
            'inputs' => json_encode([
                'service' => 'LearnerActivitySource',
                'basis' => 'pal_concept_mastery (tier, sessions)',
            ]),
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

    /**
     * The workflow a New PAL recommendation is put to a person through.
     *
     * One approval step and nothing else, mirroring `attendance_followup` and
     * `exam_result_followup`. The workflow itself contacts nobody and changes no
     * mastery record: what it does is stop the agent's proposal in a queue until a
     * named person reads the evidence and says yes.
     */
    private function upsertWorkflow(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = [
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'PAL intervention follow-up',
            'domain' => 'k12',
            'module' => self::MODULE_KEY,
            'description' => 'Review a learner whose recorded PAL progress has stalled and agree the '
                . 'intervention with a person before anything is actioned.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'new_pal.gamification_summary']),
            'conditions' => json_encode([]),
            'subject_entity_key' => 'student',
            'required_permissions' => json_encode([]),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'requires_approval' => 1,
            'is_consequential' => 0,
            'timeout_minutes' => 1440,
            'max_retries' => 1,
            'status' => 1,
            'updated_at' => now(),
        ];

        $existingId = DB::table('workflow_definitions')
            ->where('workflow_key', self::WORKFLOW_KEY)
            ->whereNull('sub_institute_id')
            ->value('id');

        if ($existingId === null) {
            $existingId = DB::table('workflow_definitions')->insertGetId($definition + ['created_at' => now()]);
        } else {
            DB::table('workflow_definitions')->where('id', $existingId)->update($definition);
        }

        $versionId = DB::table('workflow_versions')
            ->where('definition_id', $existingId)
            ->where('version', 1)
            ->value('id');

        $version = [
            'definition_id' => $existingId,
            'version' => 1,
            'status' => 'published',
            'steps' => json_encode([[
                'key' => 'review_pal_progress',
                'type' => 'approval',
                'label' => 'Review the learner\'s progress and agree the intervention',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 24],
                'next' => null,
            ]]),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_pal_progress',
            'change_note' => 'Initial published version for the PAL intervention follow-up workflow.',
            'published_at' => now(),
            'updated_at' => now(),
        ];

        if ($versionId === null) {
            $versionId = DB::table('workflow_versions')->insertGetId($version + ['created_at' => now()]);
        } else {
            DB::table('workflow_versions')->where('id', $versionId)->update($version);
        }

        DB::table('workflow_definitions')->where('id', $existingId)->update([
            'active_version_id' => $versionId,
            'updated_at' => now(),
        ]);
    }

    private function upsertAgent(): void
    {
        if (! Schema::hasTable('ai_agents')) {
            return;
        }

        $row = [
            'agent_key' => self::AGENT_KEY,
            'name' => 'New PAL Agent',
            'domain' => 'k12',
            'purpose' => 'Find learners whose practised concepts remain mostly at the Stream tier, evidence '
                . 'it from the recorded mastery map, and draft a follow-up for a person to approve.',
            'description' => 'Reads New PAL\'s own gamification records through the same service the '
                . 'new_pal.gamification_summary tool uses, opens a case per learner stuck at Stream with the '
                . 'recorded concepts as evidence, and drafts a pal_intervention_followup review. It contacts '
                . 'nobody and changes no mastery record — that stays a human act. It never reads the older pal '
                . "module's tables.",
            'runner_class' => \App\Agents\NewPal\NewPalAgent::class,
            'agent_type' => 'domain',
            'allowed_tools' => json_encode(['new_pal.gamification_summary', 'new_pal.content_model_status', 'new_pal.coherence_gaps']),
            'allowed_entities' => json_encode([
                'student', 'enrollment', 'standard', 'division', 'signal', 'evidence', 'case', 'recommendation',
            ]),
            'allowed_signal_keys' => json_encode([self::SIGNAL_KEY]),
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            // Comma-separated, not JSON: `AgentManifest` reads this column with
            // splitCsv() — see the attendance registration migration for why a JSON
            // array here silently refuses every recommendation as unauthorised.
            'authorized_workflow_keys' => self::WORKFLOW_KEY,
            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'student_id' => ['type' => 'integer'],
                    'subject_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 150],
                    'min_stream_share' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['learners_flagged', 'cases'],
                'properties' => [
                    'learners_flagged' => ['type' => 'integer'],
                    'signals_detected' => ['type' => 'integer'],
                    'highest_stream_share' => ['type' => 'number'],
                    'min_stream_share' => ['type' => 'number'],
                    'cases' => ['type' => 'array'],
                    'coverage' => ['type' => 'object'],
                    'confidence' => ['type' => 'number'],
                    'mode' => ['type' => 'string'],
                ],
            ]),
            // The permission the New PAL MCP tools already annotate themselves with, so
            // the agent cannot read anything a person could not read by asking the tool.
            'required_permissions' => json_encode(['new_pal.read']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'pal_intervention_review']),
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
     * Flip only the named flags, merging into whatever the row already holds.
     *
     * @param  array<string, bool>  $flags
     */
    private function setModuleCapabilities(array $flags): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->get(['id', 'capabilities']);

        foreach ($rows as $row) {
            $existing = json_decode((string) $row->capabilities, true);

            if (! is_array($existing)) {
                $existing = ['conversational' => true];
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'capabilities' => json_encode(array_merge($existing, $flags)),
                'updated_at' => now(),
            ]);
        }
    }
};
