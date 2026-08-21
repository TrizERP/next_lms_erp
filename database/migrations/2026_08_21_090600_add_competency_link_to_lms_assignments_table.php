<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_29_100300_add_competency_link_to_lms_assignments_table.php`.
 *
 * Links LMS learning assignments to the competency module. The Development &
 * Career Path Workspace's "Learning Assigned" metric
 * (DevelopmentPlanController@metrics) counts `lms_assignments` rows tagged
 * `source = 'competency'` - the only place this port touches the table.
 *
 * `lms_assignments` does not exist in this schema (confirmed by grep across
 * `database/migrations` before writing this file), so it is created here with
 * the source's own column shape, then the competency-link columns are added.
 * If a later LMS migration creates this table independently, the guards below
 * make this migration a no-op against it rather than a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lms_assignments')) {
            Schema::create('lms_assignments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('course_id');
                $table->string('assignment_type', 191)->default('Mandatory');
                $table->date('due_date')->nullable();
                $table->string('status', 191)->default('Not Started');
                $table->enum('approval_status', ['approved', 'pending', 'rejected'])->default('approved')->index();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_note', 500)->nullable();
                $table->integer('progress')->default(0);
                $table->string('assigned_by', 191)->nullable();
                $table->timestamp('assigned_on')->nullable();
                $table->unsignedBigInteger('sub_institute_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('lms_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_assignments', 'development_plan_id')) {
                $table->unsignedBigInteger('development_plan_id')->nullable()->index();
            }
            if (!Schema::hasColumn('lms_assignments', 'competency_id')) {
                $table->unsignedBigInteger('competency_id')->nullable()->index();
            }
            if (!Schema::hasColumn('lms_assignments', 'assigned_by_id')) {
                // `assigned_by` is a display name (varchar); this is the tbluser id.
                $table->unsignedBigInteger('assigned_by_id')->nullable();
            }
            if (!Schema::hasColumn('lms_assignments', 'source')) {
                // 'competency' = created by the Development & Career workspace.
                $table->string('source', 30)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lms_assignments')) {
            return;
        }

        Schema::table('lms_assignments', function (Blueprint $table) {
            foreach (['development_plan_id', 'competency_id', 'assigned_by_id', 'source'] as $column) {
                if (Schema::hasColumn('lms_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
