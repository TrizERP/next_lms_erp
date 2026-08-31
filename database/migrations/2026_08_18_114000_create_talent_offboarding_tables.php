<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_30_120001_create_talent_offboarding_tables` +
 * `2026_08_02_100000_add_columns_to_talent_offboarding_cases`, collapsed into
 * one clean create for this project's final column set.
 *
 * Only `talent_offboarding_cases` is created here. The source repo also has a
 * `talent_offboarding_clearances` table, but it belongs to the dead
 * `App\Http\Controllers\Api\Talent\OffboardingClearanceController` variant —
 * the active `Api\Offboarding\OffboardingController` this feature ports keeps
 * the clearance checklist, document checklist, comment thread and activity
 * log as JSON-in-text columns on the case row instead (`clearance_tasks`,
 * `documents`, `comments`, `activity_log`), so no separate clearance table is
 * needed. Likewise there is no `talent_exit_interviews` table: the exit
 * interview lives on the case row (`exit_interview_done` / `_date` /
 * `_notes`), per the active controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_offboarding_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            /** tbluser.id of the departing employee - always known, so not nullable. */
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('location', 191)->nullable();

            $table->string('exit_type', 30)->default('voluntary');
            $table->text('exit_reason')->nullable();
            /** When notice was given; last_working_day is notice + notice period. */
            $table->date('notice_date')->nullable();
            $table->date('last_working_day')->nullable();

            /**
             * 'Resignation Submitted' | 'Notice Period' | 'Clearance' |
             * 'Exit Interview' | 'Awaiting F&F' | 'Closed'.
             */
            $table->string('status', 30)->default('Resignation Submitted');

            $table->boolean('exit_interview_done')->default(false);
            $table->date('exit_interview_date')->nullable();
            $table->text('exit_interview_notes')->nullable();

            $table->unsignedBigInteger('manager_id')->nullable();

            /** Clearance checklist: [{id, department, item, status}]. */
            $table->longText('clearance_tasks')->nullable();
            /** Document checklist: [{id, title, fileName, status, isMandatory}]. */
            $table->longText('documents')->nullable();
            /** Case comment thread: [{id, comment, timestamp, author, initials}]. */
            $table->longText('comments')->nullable();
            /** Lifecycle timeline: [{id, action, description, timestamp, actor}]. */
            $table->longText('activity_log')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'status'], 'offb_case_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_offboarding_cases');
    }
};
