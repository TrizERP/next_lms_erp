<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Exam agent, the signal it raises and the workflow it can put to a
 * person — so the lifecycle can reach stages 8 and 10 to 12 for an exam question, the
 * same way 2026_09_19_100000_register_attendance_agent_signal_and_workflow.php did for
 * Attendance and 2026_09_17_100000_register_fees_agent_and_arrears_signal.php did for
 * Fees.
 *
 * WHY ALL THREE AT ONCE
 *
 * `ai_modules` has listed `exam` since the AI workspace was seeded, `exams.list` and
 * `exams.results` already answer questions about it, and its Templates and Policies
 * tabs are already published — but there has never been an exam case type, an exam
 * recommendation or anything for a person to approve. `config/ai.php`'s own comment on
 * the `exam` block says exactly this: "the exams module itself has no agent of its own
 * yet." Adding a manifest without a workflow would produce an agent that could detect
 * and explain and then have nowhere to send what it found.
 *
 * WHAT THE MANIFEST LICENSES, AND WHAT IT DOES NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0`, matching Fees, Attendance and
 * Admissions. The Exam agent may detect, analyse, explain and recommend. It may not
 * act. Contacting a family about a below-passing result, or changing a mark, stays a
 * human act — reached through the `exam_result_followup` workflow's approval step,
 * which is why that is the only workflow key it is authorised to bind a recommendation
 * to.
 *
 * SEVERITY BANDS ARE DATA
 *
 * `ai_signal_definitions.thresholds` holds the bands `ThresholdRegistry` classifies
 * against. The score is `ExamLowScoreDetector`'s own 0..1 shortfall-below-passing
 * ratio — there is no existing "exam result risk" calibration on this estate to align
 * with, unlike attendance's reuse of `AttendanceRiskDetector`'s scale, so the registry's
 * own default bands are written here explicitly for a reader rather than left implicit.
 * A school that considers a different shortfall serious writes its own row against its
 * `sub_institute_id`; that is the only sanctioned way to diverge, and it needs no code.
 *
 * THIS MIGRATION TOUCHES NOTHING BELONGING TO FEES, ATTENDANCE OR NEW PAL. Every write
 * is keyed on an exam key that does not exist yet, and the `ai_modules` update merges
 * into the exam row only.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_exam';

    private const SIGNAL_KEY = 'exam_low_score';

    private const WORKFLOW_KEY = 'exam_result_followup';

    private const MODULE_KEY = 'exam';

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
        // Retired rather than deleted, so a re-enable is one column and the history of
        // what ran under this manifest keeps its referent.
        foreach ([
            ['ai_agents', 'agent_key', self::AGENT_KEY],
            ['ai_signal_definitions', 'signal_key', self::SIGNAL_KEY],
            ['workflow_definitions', 'workflow_key', self::WORKFLOW_KEY],
        ] as [$table, $column, $value]) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where($column, $value)->update(['status' => 0, 'updated_at' => now()]);
            }
        }

        // `generative` is left on: templates published for exam stay usable without an
        // agent, exactly as they are for a module that never had one.
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
     * runs, and is offered to nobody.
     *
     * These two rows are what put the SAME manifest this file registers in front of a
     * person in the chat, so the agent the AI Stack lists and the agent the chatbot
     * offers are one row in one table.
     *
     * `requires_entity` is false on both: an exam sweep is a cohort question asked from
     * a results page, not an action gated behind selecting one child first.
     */
    private function offerInTheAssistant(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $rows = [
            [
                'capability' => 'agent',
                'label' => 'Analyse below-passing results',
                'description' => 'Open a case for each student scoring below the passing mark, citing the '
                    . 'subjects behind it, and draft a follow-up for approval.',
                'action_type' => 'run_agent',
                'action_ref' => self::AGENT_KEY,
            ],
            [
                'capability' => 'workflow',
                'label' => 'Exam result follow-up',
                'description' => 'Review a student whose exam result has fallen below the passing mark before '
                    . 'anybody is contacted.',
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
            'label' => 'Exam result below the passing mark',
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'description' => 'A student scoring below the configured passing mark on a recorded exam result, '
                . 'as reported by the same service the exam.results tool and the Exam AI Stack report tab use. '
                . 'The score is the shortfall below the passing percentage, scaled onto 0..1.',
            'detector_class' => \App\Domain\Exam\Risk\ExamLowScoreDetector::class,
            'severity_scale' => 'risk_score',
            'thresholds' => json_encode([
                'bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25],
                'trigger' => 0.5,
            ]),
            'inputs' => json_encode([
                'service' => 'ResultReportService',
                'basis' => 'result_marks.per',
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
     * The workflow an exam recommendation is put to a person through.
     *
     * One approval step and nothing else, mirroring `attendance_followup` and
     * `fees_collection`. The workflow itself contacts nobody and changes no mark: what
     * it does is stop the agent's proposal in a queue until a named person reads the
     * evidence and says yes.
     */
    private function upsertWorkflow(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = [
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'Exam result follow-up',
            'domain' => 'k12',
            'module' => self::MODULE_KEY,
            'description' => 'Review a student whose exam result has fallen below the passing mark and agree '
                . 'the follow-up with a person before anybody is contacted.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'exams.results']),
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
                'key' => 'review_exam_result',
                'type' => 'approval',
                'label' => 'Review the exam result and agree the follow-up',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 24],
                'next' => null,
            ]]),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_exam_result',
            'change_note' => 'Initial published version for the exam result follow-up workflow.',
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
            'name' => 'Exam Agent',
            'domain' => 'k12',
            'purpose' => 'Find students scoring below the passing mark, evidence it from the recorded '
                . 'result, and draft a follow-up for a person to approve.',
            'description' => 'Reads exam results through the same service the exam.results tool and the Exam '
                . 'AI Stack report tab use, opens a case per student below the passing mark with the recorded '
                . 'subject percentages as evidence, and drafts an exam_result_followup review. It contacts '
                . 'nobody and changes no mark — that stays a human act.',
            'runner_class' => \App\Agents\Exam\ExamAgent::class,
            'agent_type' => 'domain',
            'allowed_tools' => json_encode(['exams.list', 'exams.results']),
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
                    'exam_id' => ['type' => 'integer'],
                    'subject_name' => ['type' => 'string'],
                    'standard_name' => ['type' => 'string'],
                    'exam_title' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 300],
                    'passing_percentage' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['students_below_passing', 'cases'],
                'properties' => [
                    'students_below_passing' => ['type' => 'integer'],
                    'signals_detected' => ['type' => 'integer'],
                    'lowest_percentage' => ['type' => 'number'],
                    'passing_percentage' => ['type' => 'number'],
                    'cases' => ['type' => 'array'],
                    'coverage' => ['type' => 'object'],
                    'confidence' => ['type' => 'number'],
                    'mode' => ['type' => 'string'],
                ],
            ]),
            // The permission the exam MCP tools already annotate themselves with, so the
            // agent cannot read anything a person could not read by asking the tool.
            'required_permissions' => json_encode(['exam.read']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'exam_result_follow_up']),
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
