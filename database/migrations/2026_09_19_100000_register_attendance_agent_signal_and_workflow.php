<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Attendance agent, the signal it raises and the workflow it can put to a
 * person — so the lifecycle can reach stages 8 and 10 to 12 for an attendance question.
 *
 * WHY ALL THREE AT ONCE
 *
 * The Fees equivalent (2026_09_17_100000_register_fees_agent_and_arrears_signal.php) only
 * had to add a manifest, because `workflow_definitions` already carried `fees_collection`
 * from an earlier migration. Attendance has no workflow of its own: `ai_modules` lists it,
 * `attendance.overview` and `attendance.student` answer questions about it, and
 * `AttendanceRiskDetector` feeds the academic-risk agent from it — but there has never been
 * an attendance case type, an attendance recommendation or anything for a person to
 * approve. Adding a manifest without a workflow would have produced an agent that could
 * detect and explain and then had nowhere to send what it found, which is the
 * `recommendation · not_reached` state the Fees migration was written to end.
 *
 * WHAT THE MANIFEST LICENSES, AND WHAT IT DOES NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0`, matching both the academic-risk
 * and fees agents. The Attendance agent may detect, analyse, explain and recommend. It may
 * not act. Contacting a family about their child's absence, marking a register, or opening
 * an intervention stays a human act — reached through the `attendance_followup` workflow's
 * approval step, which is why that is the only workflow key it is authorised to bind a
 * recommendation to.
 *
 * SEVERITY BANDS ARE DATA
 *
 * `ai_signal_definitions.thresholds` holds the bands `ThresholdRegistry` classifies
 * against. The score is `LowAttendanceDetector`'s scaled absence rate on 0..1 — the same
 * scale `AttendanceRiskDetector` already produces — so the registry's own default bands
 * apply unchanged and the row below records them for a reader rather than overriding them.
 * A school that considers a different level of absence serious writes its own row against
 * its `sub_institute_id`; that is the only sanctioned way to diverge, and it needs no code.
 *
 * THIS MIGRATION TOUCHES NOTHING BELONGING TO FEES. Every write is keyed on an attendance
 * key that does not exist yet, and the `ai_modules` update merges into the attendance rows
 * only.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_attendance';

    private const SIGNAL_KEY = 'attendance_low_rate';

    private const WORKFLOW_KEY = 'attendance_followup';

    private const MODULE_KEY = 'attendance';

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

        // `generative` is left on: templates published for attendance stay usable without
        // an agent, exactly as they are for a module that never had one.
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
     * `CapabilityResolver` builds the panel's Agent and Workflow tabs from
     * `ai_suggestions` rows whose `action_ref` names a registered agent or workflow — it
     * does NOT enumerate `ai_agents` directly, and it silently drops a row whose binding
     * has gone. So a registered manifest with no suggestion row is an agent that exists,
     * runs, and is offered to nobody: the Agent tab simply opens empty.
     *
     * These two rows are what put the SAME manifest this file registers in front of a
     * person in the chat, so the agent the AI Stack lists and the agent the chatbot offers
     * are one row in one table rather than two things that have to be kept in step.
     *
     * `requires_entity` is false on both: an attendance sweep is a cohort question asked
     * from a list page, not an action gated behind selecting one child. The academic-risk
     * rows use true for the student module and false for the students module, for exactly
     * that distinction.
     */
    private function offerInTheAssistant(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $rows = [
            [
                'capability' => 'agent',
                'label' => 'Analyse attendance risk',
                'description' => 'Open a case for each student attending below the bar, citing the days they '
                    . 'were recorded absent, and draft a follow-up for approval.',
                'action_type' => 'run_agent',
                'action_ref' => self::AGENT_KEY,
            ],
            [
                'capability' => 'workflow',
                'label' => 'Attendance follow-up',
                'description' => 'Review a student whose attendance has fallen below the bar before anybody '
                    . 'is contacted.',
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
                // workflow's own, both enforced server-side. A second list here would be
                // a second answer to the same question.
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
            'label' => 'Attendance below the school bar',
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'description' => 'A student attending below the configured rate, as reported by the same '
                . 'service the attendance screens and the attendance.overview tool use. The score is the '
                . 'absence rate scaled onto 0..1 between 10% and 40% absence.',
            'detector_class' => \App\Domain\Attendance\Risk\LowAttendanceDetector::class,
            // 'risk_score', as the three academic rows carry — NOT a JSON list of band
            // names. The column is varchar(24) and the fees row shows what happens
            // otherwise: its severity_scale is stored as the truncated fragment
            // `["low","moderate","high"`. A 0..1 score is what this scale is.
            'severity_scale' => 'risk_score',
            // The registry's own defaults plus the trigger, in the shape
            // `ThresholdRegistry::bands()` actually reads — a `bands` key, matching the
            // academic rows. Written on the platform row so a reader can see what the
            // score means; a school overrides by writing its own row against its
            // sub_institute_id, which is the only form the registry treats as an override.
            'thresholds' => json_encode([
                'bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25],
                'trigger' => 0.5,
            ]),
            'inputs' => json_encode([
                'service' => 'AttendanceInsightService',
                'basis' => 'attendance_student.attendance_code',
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
     * The workflow an attendance recommendation is put to a person through.
     *
     * One approval step and nothing else, mirroring `fees_collection`. The workflow itself
     * contacts nobody: what it does is stop the agent's proposal in a queue until a named
     * person reads the evidence and says yes. `requires_approval = 1` is the whole
     * mechanism, and `is_consequential = 0` on the definition matches the fees row — the
     * consequence is carried on the recommendation, which is what governance reads.
     */
    private function upsertWorkflow(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = [
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'Attendance follow-up',
            'domain' => 'k12',
            'module' => self::MODULE_KEY,
            'description' => 'Review a student whose attendance has fallen below the school bar and agree '
                . 'the follow-up with a person before anybody is contacted.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'attendance.overview']),
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
                'key' => 'review_attendance',
                'type' => 'approval',
                'label' => 'Review the attendance record and agree the follow-up',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 24],
                'next' => null,
            ]]),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_attendance',
            'change_note' => 'Initial published version for the attendance follow-up workflow.',
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
            'name' => 'Attendance Agent',
            'domain' => 'k12',
            'purpose' => 'Find students attending below the school bar, evidence it from the marked '
                . 'register, and draft a follow-up for a person to approve.',
            'description' => 'Reads attendance through the same service the attendance screens use, opens '
                . 'a case per student below the bar with the recorded absences as evidence, and drafts an '
                . 'attendance_followup review. It contacts nobody and marks no register — that stays a '
                . 'human act.',
            'runner_class' => \App\Agents\Attendance\AttendanceAgent::class,
            'agent_type' => 'domain',
            'allowed_tools' => json_encode(['attendance.overview', 'attendance.student']),
            'allowed_entities' => json_encode([
                'student', 'enrollment', 'standard', 'division', 'signal', 'evidence', 'case', 'recommendation',
            ]),
            'allowed_signal_keys' => json_encode([self::SIGNAL_KEY]),
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            // Comma-separated, not JSON: `AgentManifest` reads this column with splitCsv(),
            // so a JSON array arrives as one key literally named '["attendance_followup"]'
            // and every recommendation is refused as unauthorised. Both existing manifests
            // store a bare string here; this is not a place to be tidier than they are.
            'authorized_workflow_keys' => self::WORKFLOW_KEY,
            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'student_id' => ['type' => 'integer'],
                    'subject_id' => ['type' => 'integer'],
                    'standard_id' => ['type' => 'integer'],
                    'division_id' => ['type' => 'integer'],
                    'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                    'min_attendance_rate' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['students_below_threshold', 'cases'],
                'properties' => [
                    'students_below_threshold' => ['type' => 'integer'],
                    'signals_detected' => ['type' => 'integer'],
                    'cohort_attendance_rate' => ['type' => 'number'],
                    'lowest_attendance_rate' => ['type' => 'number'],
                    'minimum_attendance_rate' => ['type' => 'number'],
                    'cases' => ['type' => 'array'],
                    'coverage' => ['type' => 'object'],
                    'confidence' => ['type' => 'number'],
                    'mode' => ['type' => 'string'],
                ],
            ]),
            // The permission the attendance MCP tools already annotate themselves with, so
            // the agent cannot read anything a person could not read by asking the tool.
            'required_permissions' => json_encode(['attendance.read']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'attendance_follow_up']),
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
     * deleting any other flag the estate carries.
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
