<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS migration Ã¢â‚¬â€ Package 2 (Assignments, Sessions & Calendar).
 *
 * `lms_assignments` has no equivalent in this project (confirmed absent
 * before writing this migration), so it is created fresh here in its FINAL
 * shape rather than replaying hp_erp's four-migration history
 * (2026_07_28_094337_create_lms_assignments_table +
 * 2026_07_28_140000_add_approval_to_lms_assignments_table +
 * 2026_07_29_100300_add_competency_link_to_lms_assignments_table +
 * 2026_08_11_000200_add_learning_assignment_provenance). Column types,
 * defaults and nullability below are taken from those four files verbatim.
 *
 * `course_id` references `sub_std_map` (this project's existing course
 * table). `user_id` references `tbluser`. Neither FK is declared here Ã¢â‚¬â€
 * matching hp_erp's own create-table migration, which also left them as
 * plain `unsignedBigInteger` Ã¢â‚¬â€ so this stays consistent with the source of
 * truth for this table's shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_assignments')) {
            if (! Schema::hasColumn('lms_assignments', 'origin_event_id')) {
                Schema::table('lms_assignments', function (Blueprint $table) {
                    $table->unsignedBigInteger('origin_event_id')->nullable()
                        ->comment('originating event id that caused this assignment; NULL = created directly');
                    $table->unique(['user_id', 'course_id', 'origin_event_id'], 'lms_assignments_origin_unique');
                });
            }
            return;
        }

        Schema::create('lms_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('course_id');
            $table->string('assignment_type', 191)->default('Mandatory');
            $table->date('due_date')->nullable();
            $table->string('status', 191)->default('Not Started');

            // Approval workflow (hp_erp: add_approval_to_lms_assignments_table).
            $table->enum('approval_status', ['approved', 'pending', 'rejected'])
                ->default('approved')
                ->index();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();

            $table->integer('progress')->default(0);
            $table->string('assigned_by', 191)->nullable();
            $table->timestamp('assigned_on')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->nullable();

            // Competency-module linkage (hp_erp: add_competency_link_to_lms_assignments_table).
            $table->unsignedBigInteger('development_plan_id')->nullable()->index();
            $table->unsignedBigInteger('competency_id')->nullable()->index();
            // `assigned_by` above is a display name (varchar); this is the tbluser id.
            $table->unsignedBigInteger('assigned_by_id')->nullable();
            // 'competency' = created by the Development & Career workspace; null = this module.
            $table->string('source', 30)->nullable()->index();

            // Reactor/event provenance (hp_erp: add_learning_assignment_provenance).
            $table->unsignedBigInteger('origin_event_id')->nullable()
                ->comment('originating event id that caused this assignment; NULL = created directly');

            $table->softDeletes();
            $table->timestamps();

            // One assignment per (person, course, originating event). NULLs are
            // distinct in a MySQL unique index, so rows with no origin_event_id
            // (the normal, non-reactor-created case) never collide with each
            // other under this constraint Ã¢â‚¬â€ matching hp_erp's same index.
            $table->unique(['user_id', 'course_id', 'origin_event_id'], 'lms_assignments_origin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_assignments');
    }
};
