<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Teach & Learn agent capabilities, signal definitions, and content generation workflow.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_course_catalogue';
    private const WORKFLOW_KEY = 'teach_learn_content_generation';
    private const MODULE_KEY = 'teach_learn';

    public function up(): void
    {
        $this->upsertSignalDefinitions();
        $this->upsertWorkflow();
        $this->setModuleCapabilities(['agent' => true, 'workflow' => true]);
        $this->offerInTheAssistant();
    }

    public function down(): void
    {
        if (Schema::hasTable('workflow_definitions')) {
            DB::table('workflow_definitions')->where('workflow_key', self::WORKFLOW_KEY)->update(['status' => 0, 'updated_at' => now()]);
        }

        $this->setModuleCapabilities(['agent' => false, 'workflow' => false]);
    }

    private function upsertSignalDefinitions(): void
    {
        if (!Schema::hasTable('ai_signal_definitions')) {
            return;
        }

        $signals = [
            [
                'signal_key' => 'catalogue_hierarchy_gap',
                'label' => 'Catalogue Hierarchy Gap',
                'domain' => 'teach_learn',
                'subject_entity_key' => 'chapter',
                'description' => 'Detected broken or missing 5-tier curriculum mappings (standard, subject, chapter, topic, intelligence).',
                'detector_class' => 'App\\Services\\Lms\\CourseCatalogueHierarchyValidator',
                'severity_scale' => 'count',
                'thresholds' => json_encode(['bands' => ['critical' => 10, 'high' => 5, 'moderate' => 1], 'trigger' => 1]),
                'inputs' => json_encode(['chapter_master', 'topic_master', 'lms_concept']),
                'requires_evidence' => 1,
                'status' => 1,
            ],
            [
                'signal_key' => 'missing_content_mapping',
                'label' => 'Missing Pedagogical Content Mapping',
                'domain' => 'teach_learn',
                'subject_entity_key' => 'chapter',
                'description' => 'Topic intelligence is missing pedagogical content modules or assessment bank items in content_master.',
                'detector_class' => 'App\\Services\\Lms\\ContentAgentService',
                'severity_scale' => 'count',
                'thresholds' => json_encode(['bands' => ['critical' => 5, 'high' => 3, 'moderate' => 1], 'trigger' => 1]),
                'inputs' => json_encode(['content_master', 'topic_master']),
                'requires_evidence' => 1,
                'status' => 1,
            ],
        ];

        foreach ($signals as $sig) {
            $existing = DB::table('ai_signal_definitions')
                ->where('signal_key', $sig['signal_key'])
                ->first();

            if ($existing) {
                DB::table('ai_signal_definitions')->where('id', $existing->id)->update($sig + ['updated_at' => now()]);
            } else {
                DB::table('ai_signal_definitions')->insert($sig + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function upsertWorkflow(): void
    {
        if (!Schema::hasTable('workflow_definitions')) {
            return;
        }

        $row = [
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'Teach & Learn Content Generation',
            'domain' => 'k12',
            'module' => 'teach_learn',
            'description' => 'Automated review and generation of pedagogical content for missing curriculum topics and chapters.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'teach_learn.content_model_coverage']),
            'conditions' => '[]',
            'subject_entity_key' => 'chapter',
            'active_version_id' => 10,
            'required_permissions' => json_encode(['lms.read', 'lms.write']),
            'allowed_roles' => json_encode(['admin', 'staff', 'teacher']),
            'requires_approval' => 1,
            'is_consequential' => 0,
            'timeout_minutes' => 1440,
            'max_retries' => 1,
            'status' => 1,
            'updated_at' => now(),
        ];

        $existing = DB::table('workflow_definitions')
            ->where('workflow_key', self::WORKFLOW_KEY)
            ->first();

        if ($existing) {
            DB::table('workflow_definitions')->where('id', $existing->id)->update($row);
        } else {
            DB::table('workflow_definitions')->insert($row + ['created_at' => now()]);
        }
    }

    private function setModuleCapabilities(array $flags): void
    {
        if (!Schema::hasTable('ai_modules')) {
            return;
        }

        $rows = DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->get(['id', 'capabilities']);

        foreach ($rows as $row) {
            $existing = json_decode((string) $row->capabilities, true);

            if (!is_array($existing)) {
                $existing = ['conversational' => true];
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'capabilities' => json_encode(array_merge($existing, $flags)),
                'updated_at' => now(),
            ]);
        }
    }

    private function offerInTheAssistant(): void
    {
        if (!Schema::hasTable('ai_suggestions')) {
            return;
        }

        $rows = [
            [
                'capability' => 'agent',
                'label' => 'Validate curriculum & catalogue hierarchy',
                'description' => 'Continuously validate the 5-tier Teach/Learn content hierarchy and audit missing pedagogical resources.',
                'action_type' => 'run_agent',
                'action_ref' => self::AGENT_KEY,
            ],
            [
                'capability' => 'workflow',
                'label' => 'Generate missing chapter resources',
                'description' => 'Automatically review and generate missing lesson presentations, revision notes, and assessments.',
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
                'sort_order' => 10,
                'status' => 1,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
