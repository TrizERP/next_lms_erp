<?php

use App\Domain\K12\AcademicRisk\AcademicRiskAgent;
use App\Domain\K12\AcademicRisk\AcademicRiskMetrics;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 1 paused after the recommendation was already approved and asked for a
 * second teacher approval. Version 2 uses that recorded recommendation decision as
 * the only human gate; WorkflowEngine still verifies it before the action writes data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definition = DB::table('workflow_definitions')
            ->where('workflow_key', AcademicRiskAgent::WORKFLOW_KEY)
            ->whereNull('sub_institute_id')
            ->first();

        if (! $definition) {
            return;
        }

        $existing = DB::table('workflow_versions')
            ->where('definition_id', $definition->id)
            ->where('version', 2)
            ->first();

        $steps = [
            [
                'key' => 'generate_activity', 'type' => 'generate', 'label' => 'Draft intervention activities',
                'sequence' => 0,
                'config' => [
                    'template' => 'k12.intervention_activity', 'purpose' => 'intervention_activity', 'domain' => 'k12',
                    'required' => false,
                    'variables_from' => [
                        'student_name' => 'input.student_name', 'subject_name' => 'input.focus_subject_name',
                        'severity' => 'input.severity',
                    ],
                ],
                'next' => 'create_intervention',
            ],
            [
                'key' => 'create_intervention', 'type' => 'action', 'label' => 'Create the intervention and assign activities',
                'sequence' => 1,
                'config' => ['action' => 'create_academic_intervention', 'intervention_type' => 'academic_support', 'due_in_days' => 14],
                'next' => 'notify_student',
            ],
            [
                'key' => 'notify_student', 'type' => 'notify', 'label' => 'Tell the student what has been assigned',
                'sequence' => 2,
                'config' => [
                    'channel' => 'in_app', 'audience' => 'student', 'notification_type' => 'ACADEMIC_SUPPORT',
                    'recipient_from' => 'input.student_id',
                    'message' => 'Your teacher has assigned some extra practice to help you catch up. Check your activities.',
                ],
                'next' => 'capture_baseline',
            ],
            [
                'key' => 'capture_baseline', 'type' => 'measure', 'label' => 'Record the starting point',
                'sequence' => 3, 'config' => [], 'next' => null,
            ],
        ];

        $payload = [
            'status' => 'published',
            'steps' => json_encode($steps),
            'outcome_metrics' => json_encode([AcademicRiskMetrics::ASSESSMENT_AVERAGE]),
            'entry_step_key' => 'generate_activity',
            'change_note' => 'Use the recommendation approval as the workflow approval; remove the duplicate teacher gate.',
            'published_at' => now(),
            'updated_at' => now(),
        ];

        $versionId = $existing
            ? (int) $existing->id
            : (int) DB::table('workflow_versions')->insertGetId($payload + [
                'definition_id' => $definition->id, 'version' => 2, 'created_at' => now(),
            ]);

        if ($existing) {
            DB::table('workflow_versions')->where('id', $versionId)->update($payload);
        }

        DB::table('workflow_definitions')->where('id', $definition->id)->update([
            'active_version_id' => $versionId,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Existing runs pin their version. Reverting an active workflow version is unsafe.
    }
};
