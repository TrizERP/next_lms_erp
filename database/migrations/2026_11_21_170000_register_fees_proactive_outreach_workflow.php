<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the `fees_proactive_outreach` workflow — a deliberately
 * separate, lower-stakes workflow from `fees_collection`, so a predictive
 * trend finding (FeesPredictiveAgent) does not compete in the same
 * approval queue as an existing-debt collection review (FeesAgent). Mirrors
 * `fees_collection`'s own definition/version row shape exactly (read live
 * from `workflow_definitions`/`workflow_versions` id 2/3 before writing
 * this), including `is_consequential = 0` at the workflow-definition level
 * — that flag describes the workflow's own action (capturing an outreach
 * decision), not the recommendation that triggers it, which is marked
 * `is_consequential = true` by FeesPredictiveAgent regardless.
 */
return new class extends Migration
{
    private const WORKFLOW_KEY = 'fees_proactive_outreach';

    public function up(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $now = now();

        $existing = DB::table('workflow_definitions')
            ->where('workflow_key', self::WORKFLOW_KEY)
            ->whereNull('sub_institute_id')
            ->first();

        if ($existing !== null) {
            return;
        }

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'workflow_key' => self::WORKFLOW_KEY,
            'name' => 'Fees proactive outreach',
            'domain' => 'k12',
            'module' => 'fees',
            'description' => 'Reach out to a family before a trending fee balance grows further, and capture '
                . 'the outreach decision in the centralized AI workflow lifecycle.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'fees_predictive_agent']),
            'conditions' => json_encode([]),
            'subject_entity_key' => 'student',
            'required_permissions' => json_encode([]),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'requires_approval' => 1,
            'is_consequential' => 0,
            'timeout_minutes' => 1440,
            'max_retries' => 1,
            'status' => 1,
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $versionId = DB::table('workflow_versions')->insertGetId([
            'definition_id' => $definitionId,
            'version' => 1,
            'status' => 'published',
            'steps' => json_encode([[
                'key' => 'review_outreach_plan',
                'type' => 'approval',
                'label' => 'Review proactive outreach plan',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 24],
                'next' => null,
            ]]),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_outreach_plan',
            'change_note' => 'Initial published version for the Fees proactive-outreach workflow.',
            'published_by' => null,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('workflow_definitions')->where('id', $definitionId)->update(['active_version_id' => $versionId]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('workflow_definitions')) {
            return;
        }

        // Retired rather than deleted, so any recommendation already bound to
        // this workflow key keeps its referent.
        DB::table('workflow_definitions')
            ->where('workflow_key', self::WORKFLOW_KEY)
            ->whereNull('sub_institute_id')
            ->update(['status' => 0, 'updated_at' => now()]);
    }
};
