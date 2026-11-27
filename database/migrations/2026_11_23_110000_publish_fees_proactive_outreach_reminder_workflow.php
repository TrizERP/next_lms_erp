<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1 paused after the approval step and went nowhere. Version 2 adds a
 * `send_reminder` action step after it, so approving a Fees Recommendation now
 * actually sends the fee reminder instead of only recording the decision — the
 * difference Fees Intelligence's own approve/reject does not have.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = DB::table('workflow_definitions')
            ->where('workflow_key', 'fees_proactive_outreach')
            ->whereNull('sub_institute_id')
            ->first();

        if (! $definition) {
            return;
        }

        $now = now();

        $existing = DB::table('workflow_versions')
            ->where('definition_id', $definition->id)
            ->where('version', 2)
            ->first();

        $steps = [
            [
                'key' => 'review_outreach_plan', 'type' => 'approval', 'label' => 'Review proactive outreach plan',
                'sequence' => 0,
                'config' => ['approver_role' => 'staff', 'expires_in_hours' => 24],
                'next' => 'send_reminder',
            ],
            [
                'key' => 'send_reminder', 'type' => 'action', 'label' => 'Send the fee reminder',
                'sequence' => 1,
                'config' => ['action' => 'send_fees_reminder', 'channel' => 'email', 'deadline_in_days' => 7],
                'next' => null,
            ],
        ];

        $payload = [
            'status' => 'published',
            'steps' => json_encode($steps),
            'outcome_metrics' => json_encode([]),
            'entry_step_key' => 'review_outreach_plan',
            'change_note' => 'Send the real fee reminder once staff approve the outreach plan, instead of only recording the decision.',
            'published_at' => $now,
            'updated_at' => $now,
        ];

        $versionId = $existing
            ? (int) $existing->id
            : (int) DB::table('workflow_versions')->insertGetId($payload + [
                'definition_id' => $definition->id, 'version' => 2, 'created_at' => $now,
            ]);

        if ($existing) {
            DB::table('workflow_versions')->where('id', $versionId)->update($payload);
        }

        DB::table('workflow_definitions')->where('id', $definition->id)->update([
            'active_version_id' => $versionId,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Existing runs pin their version. Reverting an active workflow version is unsafe.
    }
};
