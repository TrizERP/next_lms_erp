<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a third Engagement view, `engagement-attendance`, now that
 * `student -[has_attendance]-> attendance_record` is genuinely live
 * (migration `2026_11_28_200200_enable_graph_backed_student_attendance.php`
 * flipped the existing core-ontology relationship to `in_graph=true`, backed
 * by the new `attendance_student` -> :AttendanceRecord sync added alongside
 * it). A separate, later migration from the original engagement-ontology
 * seed (`2026_11_28_100300_...`) because the live edge did not exist yet
 * when that one ran — this is additive, not a correction of it.
 *
 * Reuses the EXISTING `student`/`attendance_record` entities and the
 * EXISTING `has_attendance` relationship (now graph-backed) — no new
 * ontology_entities/ontology_relationships rows needed here, only a new
 * view pointing at what already exists.
 *
 * Idempotent, baseline-only: safe to re-run.
 */
return new class extends Migration
{
    private function views(): array
    {
        return [
            [
                'view_key' => 'engagement-attendance',
                'label' => 'Attendance',
                'module_key' => 'engagement',
                'description' => 'This student\'s daily attendance record.',
                'root_entity_key' => 'student',
                'path' => [
                    ['relation' => 'has_attendance', 'entity' => 'attendance_record', 'label' => 'Attendance records'],
                ],
                'max_per_hop' => 30,
                'sort_order' => 30,
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return;
        }

        $now = now();

        foreach ($this->views() as $view) {
            $payload = [
                'label' => $view['label'],
                'module_key' => $view['module_key'],
                'description' => $view['description'],
                'root_entity_key' => $view['root_entity_key'],
                'path' => json_encode($view['path']),
                'max_per_hop' => $view['max_per_hop'],
                'allowed_roles' => null,
                'sort_order' => $view['sort_order'],
                'status' => 1,
                'updated_at' => $now,
            ];

            $existing = DB::table('ai_ontology_views')
                ->where('view_key', $view['view_key'])
                ->whereNull('sub_institute_id')
                ->first();

            if ($existing) {
                DB::table('ai_ontology_views')->where('id', $existing->id)->update($payload);

                continue;
            }

            DB::table('ai_ontology_views')->insert($payload + [
                'view_key' => $view['view_key'],
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return;
        }

        DB::table('ai_ontology_views')
            ->whereIn('view_key', array_column($this->views(), 'view_key'))
            ->whereNull('sub_institute_id')
            ->delete();
    }
};
