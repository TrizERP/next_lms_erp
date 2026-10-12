<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `lms_student_engagement` genuinely usable, part 1 of 2 (part 2 is the
 * sibling `..._142000_create_neo4j_sync_trigger_lms_student_engagement`
 * migration).
 *
 * The table was created whole by `2026_06_18_153544_create_lms_student_profile_table`
 * with the right shape for one activity event (student_id, activity_type,
 * activity_id, time_spent_seconds, started_at/completed_at, metadata) but with
 * no tenant column at all — every other K12 entity this app syncs to the graph
 * carries `sub_institute_id` (see config/neo4j.php's entity specs), and
 * without it a cross-tenant read of this table cannot be scoped and the live
 * sync trigger has nothing to hand the projection as a hint on DELETE.
 *
 * Additive only: nullable, backfill-free, no existing reader
 * (`PedagogyEngineController::analyzeEngagement()`) is broken by its presence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_student_engagement')) {
            return;
        }

        Schema::table('lms_student_engagement', function (Blueprint $table) {
            if (! Schema::hasColumn('lms_student_engagement', 'sub_institute_id')) {
                $table->unsignedBigInteger('sub_institute_id')->nullable()->after('student_id');
                $table->index(['student_id', 'sub_institute_id'], 'idx_engagement_student_tenant');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_student_engagement')) {
            return;
        }

        Schema::table('lms_student_engagement', function (Blueprint $table) {
            if (Schema::hasColumn('lms_student_engagement', 'sub_institute_id')) {
                $table->dropIndex('idx_engagement_student_tenant');
                $table->dropColumn('sub_institute_id');
            }
        });
    }
};
