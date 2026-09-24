<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Exam & Assessment agent, the signal it raises and the workflow it can
 * put to a person — the fourth of these registrations (Attendance, Exam, New PAL before
 * it), so the lifecycle can reach stages 8 and 10 to 12 for this module too.
 *
 * WHY THIS MODULE, WHICH DID NOT EXIST AN HOUR AGO, ALREADY HAS ONE
 *
 * `2026_09_29_100200_register_exam_assessment_module.php` gave this module its
 * `ai_modules` row and its RBAC menu row; this migration gives it the case-opening half.
 * Both were needed together because a module with no agent cannot reach stage 10, and a
 * module with an agent nobody could enable would reproduce the exact "Your role cannot
 * enable agents…" failure this estate has already fixed four times.
 *
 * ONLY ONLINE EXAM SCORE IS COVERED
 *
 * `OnlineExamLowScoreDetector` reads `lms_online_exam`/`question_paper` through
 * `OnlineExamReportService`. Homework/Assignment/Worksheet/Project completion is
 * read-only for now via `exam_assessment.assignment_status` and the existing
 * `homework.list` tool — see `ExamAssessmentAgent`'s own doc comment for why that second
 * case type is left for a later, equally careful pass rather than rushed here.
 *
 * WHAT THE MANIFEST LICENSES, AND WHAT IT DOES NOT
 *
 * `max_verb = recommend` and `may_execute_actions = 0`, matching every agent before it.
 * The agent may detect, analyse, explain and recommend. It may not act — reached through
 * the `exam_assessment_followup` workflow's approval step, the only workflow key it is
 * authorised to bind a recommendation to.
 *
 * THIS MIGRATION TOUCHES NOTHING BELONGING TO FEES, ATTENDANCE, EXAM OR NEW PAL. Every
 * write is keyed on an `exam_assessment` key that did not exist before
 * 2026_09_29_100200, and the `ai_modules` update merges into that row only.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_exam_assessment';

    private const SIGNAL_KEY = 'exam_assessment_low_score';

    private const WORKFLOW_KEY = 'exam_assessment_followup';

    private const MODULE_KEY = 'exam_assessment';

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

    private function offerInTheAssistant(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $rows = [
            [
                'capability' => 'agent',
                'label' => 'Analyse online exam results',
                'description' => 'Open a case for each student scoring below the online exam bar, citing the '
                    . 'attempts behind it, and draft a follow-up for approval.',
                'action_type' => 'run_agent',
                'action_ref' => self::AGENT_KEY,
            ],
            [
                'capability' => 'workflow',
                'label' => 'Exam & Assessment follow-up',
                'description' => 'Review a student whose online exam results have fallen below the bar before '
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
            'label' => 'Online exam result below the bar',
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'description' => 'A student scoring below the configured bar on a recorded online exam attempt, as '
                . 'reported by the same figures the LMS Result Dashboard shows. The score is the shortfall '
                . 'below the bar, scaled onto 0..1.',
            'detector_class' => \App\Domain\ExamAssessment\Risk\OnlineExamLowScoreDetector::class,
            'severity_scale' => 'risk_score',
            'thresholds' => json_encode([
                'bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25],
                'trigger' => 0.5,
            ]),
            'inputs' => json_encode([
                'service' => 'OnlineExamReportService',
                'basis' => 'lms_online_exam.obtain_marks / question_paper.total_marks',
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

    private function upsertWorkflow(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = [
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'Exam & Assessment follow-up',
            'domain' => 'k12',
            'module' => self::MODULE_KEY,
            'description' => 'Review a student whose online exam results have fallen below the bar and agree '
                . 'the follow-up with a person before anybody is contacted.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'exam_assessment.online_exam_summary']),
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
                'key' => 'review_exam_assessment',
                'type' => 'approval',
                'label' => 'Review the online exam result and agree the follow-up',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 24],
                'next' => null,
            ]]),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_exam_assessment',
            'change_note' => 'Initial published version for the Exam & Assessment follow-up workflow.',
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
            'name' => 'Exam & Assessment Agent',
            'domain' => 'k12',
            'purpose' => 'Find students scoring below the bar on a recorded online exam, evidence it from the '
                . 'attempt, and draft a follow-up for a person to approve.',
            'description' => 'Reads online exam attempts through the same figures the LMS Result Dashboard '
                . 'shows, opens a case per student below the bar with the recorded attempts as evidence, and '
                . 'drafts an exam_assessment_followup review. It contacts nobody and changes no mark — that '
                . 'stays a human act. It is unrelated to the Exam (Mark Entry / Results) module\'s own agent.',
            'runner_class' => \App\Agents\ExamAssessment\ExamAssessmentAgent::class,
            'agent_type' => 'domain',
            'allowed_tools' => json_encode(['exam_assessment.online_exam_summary', 'exam_assessment.assignment_status', 'homework.list']),
            'allowed_entities' => json_encode([
                'student', 'enrollment', 'standard', 'division', 'signal', 'evidence', 'case', 'recommendation',
            ]),
            'allowed_signal_keys' => json_encode([self::SIGNAL_KEY]),
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            'authorized_workflow_keys' => self::WORKFLOW_KEY,
            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'student_id' => ['type' => 'integer'],
                    'subject_id' => ['type' => 'integer'],
                    'standard_id' => ['type' => 'integer'],
                    'exam_subject_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                    'at_risk_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['students_below_bar', 'cases'],
                'properties' => [
                    'students_below_bar' => ['type' => 'integer'],
                    'signals_detected' => ['type' => 'integer'],
                    'lowest_percent' => ['type' => 'number'],
                    'at_risk_percent' => ['type' => 'number'],
                    'cases' => ['type' => 'array'],
                    'coverage' => ['type' => 'object'],
                    'confidence' => ['type' => 'number'],
                    'mode' => ['type' => 'string'],
                ],
            ]),
            'required_permissions' => json_encode(['exam_assessment.read']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'exam_assessment_follow_up']),
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
