<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flips the existing `student -[has_attendance]-> attendance_record`
 * ontology_relationship (seeded by `2026_08_20_000007_seed_core_ontology.php`,
 * SQL-only until now) to `in_graph=true`, pointed at the new, live
 * `HAS_ATTENDANCE` edge this pass adds — part of the "Context Graph - Whole
 * LMS Integration" task's Engagement follow-up.
 *
 * Deliberately does NOT touch `fee_student -[has_attendance]-> attendance_record`
 * (a separate relationship row, Fees' own cross-module edge, also SQL-only) —
 * out of scope, a different consumer's decision to make.
 *
 * Idempotent: only updates the row if found; safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('from_entity_key', 'student')
            ->where('relation', 'has_attendance')
            ->where('to_entity_key', 'attendance_record')
            ->whereNull('sub_institute_id')
            ->update([
                'in_graph' => true,
                'graph_relationship_type' => 'HAS_ATTENDANCE',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('from_entity_key', 'student')
            ->where('relation', 'has_attendance')
            ->where('to_entity_key', 'attendance_record')
            ->whereNull('sub_institute_id')
            ->update([
                'in_graph' => false,
                'graph_relationship_type' => null,
                'updated_at' => now(),
            ]);
    }
};
