<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $workflowKey = 'fees_collection';
        $now = now();

        $definition = DB::table('workflow_definitions')
            ->where('workflow_key', $workflowKey)
            ->whereNull('sub_institute_id')
            ->first();

        $definitionPayload = [
            'name' => 'Fees collection review',
            'domain' => 'k12',
            'module' => 'fees',
            'description' => 'Review pending fees for a student and capture the review state in the centralized AI workflow lifecycle.',
            'trigger_type' => 'conversation',
            'trigger_config' => json_encode(['source' => 'fees.getPending']),
            'conditions' => json_encode([]),
            'subject_entity_key' => 'student',
            'required_permissions' => json_encode([]),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'requires_approval' => true,
            'is_consequential' => false,
            'timeout_minutes' => 60 * 24,
            'max_retries' => 1,
            'status' => 1,
            'updated_at' => $now,
        ];

        if ($definition) {
            DB::table('workflow_definitions')
                ->where('id', $definition->id)
                ->update($definitionPayload);

            $definitionId = (int) $definition->id;
        } else {
            $definitionId = (int) DB::table('workflow_definitions')->insertGetId($definitionPayload + [
                'workflow_key' => $workflowKey,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => $now,
            ]);
        }

        $steps = [[
            'key' => 'review_pending_fees',
            'type' => 'approval',
            'label' => 'Review pending fees',
            'sequence' => 0,
            'config' => [
                'approver_role' => 'staff',
                'expires_in_hours' => 24,
            ],
            'next' => null,
        ]];

        $versionPayload = [
            'status' => 'published',
            'steps' => json_encode($steps),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_pending_fees',
            'change_note' => 'Initial published version for the centralized fees review workflow.',
            'published_at' => $now,
            'updated_at' => $now,
        ];

        $existingVersion = DB::table('workflow_versions')
            ->where('definition_id', $definitionId)
            ->where('version', 1)
            ->first();

        if ($existingVersion) {
            DB::table('workflow_versions')->where('id', $existingVersion->id)->update($versionPayload);
            $versionId = (int) $existingVersion->id;
        } else {
            $versionId = (int) DB::table('workflow_versions')->insertGetId($versionPayload + [
                'definition_id' => $definitionId,
                'version' => 1,
                'created_at' => $now,
            ]);
        }

        DB::table('workflow_definitions')
            ->where('id', $definitionId)
            ->update(['active_version_id' => $versionId, 'updated_at' => $now]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('workflow_versions') || ! Schema::hasTable('workflow_definitions')) {
            return;
        }

        $definition = DB::table('workflow_definitions')
            ->where('workflow_key', 'fees_collection')
            ->whereNull('sub_institute_id')
            ->first();

        if ($definition && Schema::hasTable('workflow_versions')) {
            DB::table('workflow_versions')->where('definition_id', $definition->id)->delete();
        }

        DB::table('workflow_definitions')
            ->where('workflow_key', 'fees_collection')
            ->whereNull('sub_institute_id')
            ->delete();
    }
};
