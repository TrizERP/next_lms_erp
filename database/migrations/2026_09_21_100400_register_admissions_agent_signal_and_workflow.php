<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Admissions agent, the signal it raises and the workflow it can put to a
 * person — so the lifecycle can reach stages 8 and 10 to 12 for an admissions question.
 *
 * WHY ALL THREE AT ONCE
 *
 * Admissions previously carried a `depth_reason` in `config/ai.php` saying the module had
 * read tools and a confirmation flow but no agent that opens cases. That was true, and it
 * was the reason an admissions question could be answered but never acted on: no case
 * type, no recommendation, nothing for a person to approve. Adding a manifest without a
 * workflow would produce an agent that could detect and explain and then had nowhere to
 * send what it found, which is the `recommendation · not_reached` state the Fees migration
 * was written to end.
 *
 * WHAT THE MANIFEST LICENSES, AND WHAT IT DOES NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0`, matching the fees, attendance and
 * academic-risk agents. The Admissions agent may detect, analyse, explain and recommend.
 * It may not act. Contacting a family about a place, editing an enquiry, or confirming an
 * admission stays a human act — reached through the `admissions_followup` workflow's
 * approval step, which is why that is the only workflow key it is authorised to bind a
 * recommendation to.
 *
 * `allowed_tools` names only the two READ tools the detector actually uses.
 * `admissions.confirm` and `admissions.updateEnquiry` are real tools in this registry and
 * they write; they are deliberately absent, so the agent could not reach them even if a
 * plan named them. Confirming an admission keeps its own confirmable-tool gate and is
 * never reachable from a model-written plan.
 *
 * SEVERITY BANDS ARE DATA
 *
 * `ai_signal_definitions.thresholds` holds the bands `ThresholdRegistry` classifies
 * against. The score is `StalledEnquiryDetector`'s overdue interval scaled onto 0..1
 * between one day and three weeks late, so the registry's own default bands apply
 * unchanged and the row below records them for a reader rather than overriding them. A
 * school that considers a different delay serious writes its own row against its
 * `sub_institute_id`; that is the only sanctioned way to diverge, and it needs no code.
 *
 * THIS MIGRATION TOUCHES NOTHING BELONGING TO FEES, ATTENDANCE OR STUDENTS. Every write is
 * keyed on an admissions key that does not exist yet, and the `ai_modules` update merges
 * into the admissions rows only.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_21_100400_register_admissions_agent_signal_and_workflow.php
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_admissions';

    private const SIGNAL_KEY = 'admissions_enquiry_stalled';

    private const WORKFLOW_KEY = 'admissions_followup';

    private const MODULE_KEY = 'admissions';

    public function up(): void
    {
        $this->upsertSignalDefinition();
        $this->upsertWorkflow();
        $this->upsertAgent();
        $this->setModuleCapabilities(['agent' => true, 'workflow' => true]);
        $this->offerInTheAssistant();
    }

    public function down(): void
    {
        // Retired rather than deleted, so a re-enable is one column and the history of what
        // ran under this manifest keeps its referent.
        foreach ([
            ['ai_agents', 'agent_key', self::AGENT_KEY],
            ['ai_signal_definitions', 'signal_key', self::SIGNAL_KEY],
            ['workflow_definitions', 'workflow_key', self::WORKFLOW_KEY],
        ] as [$table, $column, $value]) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where($column, $value)->update(['status' => 0, 'updated_at' => now()]);
            }
        }

        // `generative` is left alone: it is owned by the template migration, and templates
        // published for admissions stay usable without an agent.
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
     * `CapabilityResolver` builds the panel's Agent and Workflow tabs from `ai_suggestions`
     * rows whose `action_ref` names a registered agent or workflow — it does NOT enumerate
     * `ai_agents` directly, and it silently drops a row whose binding has gone. So a
     * registered manifest with no suggestion row is an agent that exists, runs, and is
     * offered to nobody: the Agent tab simply opens empty.
     *
     * `requires_entity` is false on both: an admissions sweep is a pipeline question asked
     * from a list page, not an action gated behind selecting one enquiry.
     */
    private function offerInTheAssistant(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $rows = [
            [
                'capability' => 'agent',
                'label' => 'Find overdue admission follow-ups',
                'description' => 'Open a case for each enquiry past the follow-up date the school recorded '
                    . 'for it, citing that date and what the record is still missing, and draft a follow-up '
                    . 'for approval.',
                'action_type' => 'run_agent',
                'action_ref' => self::AGENT_KEY,
            ],
            [
                'capability' => 'workflow',
                'label' => 'Admission follow-up',
                'description' => 'Review an enquiry the school has not come back to before anybody is '
                    . 'contacted.',
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
                // Left null: who may run this is the manifest's `allowed_roles` and the
                // workflow's own, both enforced server-side. A second list here would be a
                // second answer to the same question.
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
            'label' => 'Admission enquiry past its follow-up date',
            'domain' => 'k12',
            'subject_entity_key' => 'enquiry',
            'description' => 'An open admission enquiry whose own recorded follow-up date has passed, as '
                . 'reported by the same service the admission screens and the admissions.listEnquiries tool '
                . 'use. The score is the overdue interval scaled onto 0..1 between one day and 21 days late. '
                . 'An open enquiry with no follow-up date recorded is not signalled: its age is not knowable '
                . 'from what the tool returns.',
            'detector_class' => \App\Domain\Admissions\Risk\StalledEnquiryDetector::class,
            // 'risk_score', as the academic and attendance rows carry — NOT a JSON list of
            // band names. The column is varchar(24) and the fees row shows what happens
            // otherwise: its severity_scale is stored as a truncated fragment. A 0..1 score
            // is what this scale is.
            'severity_scale' => 'risk_score',
            // The registry's own defaults plus the trigger, in the shape
            // `ThresholdRegistry::bands()` actually reads — a `bands` key. Written on the
            // platform row so a reader can see what the score means; a school overrides by
            // writing its own row against its sub_institute_id, which is the only form the
            // registry treats as an override.
            'thresholds' => json_encode([
                'bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25],
                'trigger' => 0.5,
            ]),
            'inputs' => json_encode([
                'service' => 'AdmissionMcpService',
                'basis' => 'admission_enquiry.followup_date',
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
     * The workflow an admissions recommendation is put to a person through.
     *
     * One approval step and nothing else, mirroring `fees_collection` and
     * `attendance_followup`. The workflow itself contacts nobody: what it does is stop the
     * agent's proposal in a queue until a named person reads the evidence and says yes.
     * `requires_approval = 1` is the whole mechanism, and `is_consequential = 0` on the
     * definition matches the other two rows — the consequence is carried on the
     * recommendation, which is what governance reads.
     */
    private function upsertWorkflow(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = [
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'Admission follow-up',
            'domain' => 'k12',
            'module' => self::MODULE_KEY,
            'description' => 'Review an enquiry the school said it would come back to and has not, and agree '
                . 'the follow-up with a person before the family is contacted.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'admissions.listEnquiries']),
            'conditions' => json_encode([]),
            'subject_entity_key' => 'enquiry',
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
                'key' => 'review_enquiry',
                'type' => 'approval',
                'label' => 'Review the enquiry and agree the follow-up',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 24],
                'next' => null,
            ]]),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_enquiry',
            'change_note' => 'Initial published version for the admission follow-up workflow.',
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
            'name' => 'Admissions Agent',
            'domain' => 'k12',
            'purpose' => 'Find admission enquiries the school said it would come back to and has not, evidence '
                . 'it from the recorded follow-up date, and draft a follow-up for a person to approve.',
            'description' => 'Reads admission enquiries through the same service the admission screens use, '
                . 'opens a case per enquiry past its own recorded follow-up date with that date and the '
                . 'confirmation check as evidence, and drafts an admissions_followup review. It contacts '
                . 'nobody, edits no enquiry and confirms no admission — those stay human acts.',
            'runner_class' => \App\Agents\Admissions\AdmissionsAgent::class,
            'agent_type' => 'domain',
            // Reads only. The two write tools in this module — admissions.confirm and
            // admissions.updateEnquiry — are deliberately absent.
            'allowed_tools' => json_encode(['admissions.listEnquiries', 'admissions.validateConfirmation']),
            'allowed_entities' => json_encode([
                'enquiry', 'student', 'standard', 'division', 'signal', 'evidence', 'case', 'recommendation',
            ]),
            'allowed_signal_keys' => json_encode([self::SIGNAL_KEY]),
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            // Comma-separated, not JSON: `AgentManifest` reads this column with splitCsv(),
            // so a JSON array arrives as one key literally named '["admissions_followup"]'
            // and every recommendation is refused as unauthorised. Every existing manifest
            // stores a bare string here; this is not a place to be tidier than they are.
            'authorized_workflow_keys' => self::WORKFLOW_KEY,
            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'enquiry_id' => ['type' => 'integer'],
                    'subject_id' => ['type' => 'integer'],
                    'search_text' => ['type' => 'string'],
                    'overdue_days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['enquiries_overdue', 'cases'],
                'properties' => [
                    'enquiries_overdue' => ['type' => 'integer'],
                    'signals_detected' => ['type' => 'integer'],
                    'longest_overdue_days' => ['type' => 'integer'],
                    'open_enquiries' => ['type' => 'integer'],
                    'minimum_overdue_days' => ['type' => 'integer'],
                    'cases' => ['type' => 'array'],
                    'coverage' => ['type' => 'object'],
                    'confidence' => ['type' => 'number'],
                    'mode' => ['type' => 'string'],
                ],
            ]),
            // The permission the admissions MCP tools already annotate themselves with, so
            // the agent cannot read anything a person could not read by asking the tool.
            'required_permissions' => json_encode(['admission.confirm']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'admission_follow_up']),
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
     * Writing a freshly built object over `capabilities` would enable these by silently
     * deleting any other flag the estate carries — including the `generative` flag the
     * template migration sets.
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
