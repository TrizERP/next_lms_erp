<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Curriculum Planning agent, signal, and review workflow.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_curriculum_planning';
    private const SIGNAL_KEY = 'curriculum_pacing_gap';
    private const WORKFLOW_KEY = 'curriculum_planning_review';
    private const MODULE_KEY = 'curriculum_planning';

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
    }

    private function offerInTheAssistant(): void
    {
        if (!Schema::hasTable('ai_suggestions')) {
            return;
        }

        $rows = [
            [
                'capability' => 'agent',
                'label' => 'Audit curriculum and lesson pacing',
                'description' => 'Continuously audit syllabus structure, lesson plan delivery pace, and competency coverage against learning outcomes.',
                'action_type' => 'run_agent',
                'action_ref' => self::AGENT_KEY,
            ],
            [
                'capability' => 'workflow',
                'label' => 'Curriculum planning review',
                'description' => 'Review syllabus coverage gaps and lesson pacing before proposing academic schedule adjustments.',
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
        if (!Schema::hasTable('ai_signal_definitions')) {
            return;
        }

        $row = [
            'signal_key' => self::SIGNAL_KEY,
            'label' => 'Curriculum unit behind scheduled delivery pace',
            'domain' => 'k12',
            'subject_entity_key' => 'curriculum',
            'description' => 'Detects chapters or units where completed teaching periods fall behind the planned academic calendar schedule, or where learning outcomes lack chapter coverage.',
            'detector_class' => \App\Agents\CurriculumPlanning\CurriculumPlanningAgent::class,
            'severity_scale' => 'risk_score',
            'thresholds' => json_encode([
                'bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25],
                'trigger' => 0.4,
            ]),
            'inputs' => json_encode([
                'service' => 'CurriculumPlanningReportService',
                'basis' => 'lms_curriculum, lms_intelligence_lesson_plans, lms_lesson_plan_periods',
            ]),
            'status' => 1,
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $existing = DB::table('ai_signal_definitions')->where('signal_key', self::SIGNAL_KEY)->first();
        if ($existing) {
            DB::table('ai_signal_definitions')->where('signal_key', self::SIGNAL_KEY)->update($row);
        } else {
            DB::table('ai_signal_definitions')->insert($row);
        }
    }

    private function upsertWorkflow(): void
    {
        if (!Schema::hasTable('workflow_definitions') || !Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = [
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'Curriculum Planning Review',
            'domain' => 'k12',
            'description' => 'Review syllabus coverage gaps and lesson pacing before proposing academic schedule adjustments.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'curriculum_planning.status']),
            'conditions' => json_encode([]),
            'subject_entity_key' => 'curriculum',
            'required_permissions' => json_encode([]),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'requires_approval' => 1,
            'is_consequential' => 0,
            'timeout_minutes' => 1440,
            'max_retries' => 1,
            'status' => 1,
            'sub_institute_id' => null,
            'client_id' => null,
            'updated_at' => now(),
        ];

        $existingId = DB::table('workflow_definitions')->where('workflow_key', self::WORKFLOW_KEY)->value('id');
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
                'key' => 'review_curriculum_pacing',
                'type' => 'approval',
                'label' => 'Review curriculum coverage and agree pacing adjustment',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 48],
                'next' => null,
            ]]),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_curriculum_pacing',
            'change_note' => 'Initial version for curriculum planning review workflow.',
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
        if (!Schema::hasTable('ai_agents')) {
            return;
        }

        $row = [
            'agent_key' => self::AGENT_KEY,
            'name' => 'Curriculum Planning Agent',
            'domain' => 'k12',
            'purpose' => 'Continuously audit syllabus structure, lesson plan delivery pace, and competency coverage against learning outcomes.',
            'description' => 'Reads curriculum authoring structures, audits lesson period schedules against actual progress, identifies coverage gaps, and drafts curriculum alignment notes for coordinator review.',
            'runner_class' => \App\Agents\CurriculumPlanning\CurriculumPlanningAgent::class,
            'agent_type' => 'domain',
            'allowed_tools' => json_encode([
                'curriculum_planning.status',
                'curriculum_planning.lesson_pace',
                'curriculum_planning.outcomes',
                'curriculum_planning.draft_alignment_note',
            ]),
            'allowed_entities' => json_encode([
                'curriculum', 'unit', 'chapter', 'lesson_plan', 'period', 'learning_outcome', 'standard', 'subject', 'case', 'recommendation',
            ]),
            'allowed_signal_keys' => json_encode([self::SIGNAL_KEY]),
            'max_verb' => 'recommend',
            'may_execute_actions' => 0,
            'authorized_workflow_keys' => self::WORKFLOW_KEY,
            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'standard_id' => ['type' => 'integer'],
                    'subject_id' => ['type' => 'integer'],
                    'syear' => ['type' => 'integer'],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['curricula_scanned', 'cases'],
                'properties' => [
                    'curricula_scanned' => ['type' => 'integer'],
                    'gaps_detected' => ['type' => 'integer'],
                    'cases' => ['type' => 'array'],
                    'confidence' => ['type' => 'number'],
                ],
            ]),
            'required_permissions' => json_encode(['lms:read']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 120,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => 'curriculum_planning_review']),
            'status' => 1,
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $existing = DB::table('ai_agents')->where('agent_key', self::AGENT_KEY)->first();
        if ($existing) {
            DB::table('ai_agents')->where('agent_key', self::AGENT_KEY)->update($row);
        } else {
            DB::table('ai_agents')->insert($row);
        }
    }

    private function setModuleCapabilities(array $capabilities): void
    {
        if (!Schema::hasTable('ai_modules')) {
            return;
        }

        $row = DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->first();
        if (!$row) {
            return;
        }

        $current = $row->capabilities ? json_decode($row->capabilities, true) : [];
        $merged = array_merge($current, $capabilities);

        DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->update([
            'capabilities' => json_encode($merged),
            'updated_at' => now(),
        ]);
    }
};
