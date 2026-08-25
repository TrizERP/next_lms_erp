<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_30_100000_create_performance_module_tables.php`
 * and `2026_08_11_000600_add_competency_to_performance_goals.php`.
 *
 * Performance & Rewards Center domain tables.
 *
 * Backs Talent Management -> Performance & Rewards Center. Existing tables
 * were audited for reuse first and deliberately NOT repurposed:
 *   - user_rating_details             per-jobrole KASA competency rating snapshot,
 *                                     no cycle / stage / overall rating / manager
 *   - s_competency_assessment_cycles  competency-FRAMEWORK scoped assessments; shared
 *     + s_competency_assessments      by live /competency/* endpoints, so writing
 *                                     performance rows would corrupt that module's
 *                                     cycle counts, calibration queue and approvals
 *   - talent_evaluation_form          recruitment evaluation of a CANDIDATE
 *   - task.kra                        free-text label on a front-desk task
 *   - task_management_*               project delivery, not performance goals
 *   - staff_document                  HR paperwork keyed to document_type_id
 *   - s_competency_evidence           competency proof; reuse would surface review
 *                                     PDFs on the Competency Evidence tab
 *   - discliplinary_management        misconduct incidents
 * Departments (hrms_departments), employees/managers (tbluser) and designations
 * (org_designation) ARE reused as-is; no mirror tables were created for them.
 *
 * Conventions: bigIncrements PK, indexed unsignedBigInteger sub_institute_id
 * (no default - tenant-scoped data, always created with a real tenant id),
 * nullable indexed audit columns, timestamps + softDeletes, string-based
 * status enums, and loose joins (jobrole string / department_id int) with NO
 * foreign-key constraints.
 *
 * `s_performance_goals.competency_id` (folded in from the source's separate
 * `add_competency_to_performance_goals` migration, since this is a fresh
 * migration rather than an alter): FK -> competency.id (a KASBA BUNDLE).
 * NEVER a skill id: a skill is one of five KASBA dimensions inside a bundle.
 * Nullable, deliberately - a goal that develops no particular competency is a
 * legitimate goal. No FK constraint: the competency module / table isn't
 * present in this target.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Review cycles ----------------------------------------------------
        // Drives the header cycle selector, the "Active Review Cycles" KPI, the
        // Create / Launch dialog and the Cycle Timeline view.
        Schema::create('s_performance_cycles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 191);
            $table->string('code', 60)->nullable()->index();
            // annual | half_yearly | quarterly | probation | project
            $table->string('cycle_type', 30)->default('annual')->index();
            $table->text('description')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            // Per-stage deadlines: the stepper's due dates and the KPI subtitles
            // ("Due in next 7 days", "Overdue") are computed from these.
            $table->date('self_review_due')->nullable();
            $table->date('manager_review_due')->nullable();
            $table->date('calibration_due')->nullable();
            $table->date('final_review_due')->nullable();
            // draft | active | calibration | closed
            $table->string('status', 30)->default('draft')->index();
            // Highest rating on the scale (5 => "5 - Outstanding").
            $table->unsignedTinyInteger('rating_scale_max')->default(5);
            $table->dateTime('launched_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Employee reviews -------------------------------------------------
        // The main table on the screen: one row per employee per cycle. Also
        // backs "Employees in Cycle", "Self Reviews Pending", "Manager Reviews
        // Pending", "Final Ratings Completed", the 5-step stepper, the Review
        // Board kanban and the whole Employee Overview sidebar.
        Schema::create('s_performance_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->unsignedBigInteger('user_id')->index();               // employee under review (tbluser.id)
            $table->unsignedBigInteger('manager_id')->nullable()->index(); // reviewing manager (tbluser.id)
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('jobrole', 191)->nullable();
            // self_review | manager_review | calibration | final_review | completed
            $table->string('stage', 30)->default('self_review')->index();
            // pending | in_progress | completed
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('self_rating', 4, 2)->nullable();
            $table->decimal('manager_rating', 4, 2)->nullable();
            // Set during calibration; overall_rating is the value the row displays.
            $table->decimal('calibrated_rating', 4, 2)->nullable();
            $table->decimal('overall_rating', 4, 2)->nullable()->index();
            // Denormalised label so the table renders "4 - Exceeds" without a join.
            $table->string('overall_rating_label', 60)->nullable();
            $table->decimal('potential_rating', 4, 2)->nullable();
            $table->string('potential_rating_label', 60)->nullable();
            // Ratings show as "(Draft)" in the sidebar until the stage is finalised.
            $table->boolean('is_draft')->default(true);
            $table->text('self_comments')->nullable();
            $table->text('manager_comments')->nullable();
            $table->dateTime('self_submitted_at')->nullable();
            $table->dateTime('manager_submitted_at')->nullable();
            $table->unsignedBigInteger('calibrated_by')->nullable();
            $table->dateTime('calibrated_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->dateTime('last_reminder_at')->nullable();
            $table->unsignedBigInteger('calibration_session_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The screen always lists one cycle at a time, filtered by stage/status.
            $table->index(['sub_institute_id', 'cycle_id', 'stage'], 's_perf_rev_tenant_cycle_stage_idx');
        });

        // 3. Goals (KRA / KPI / OKR) -----------------------------------------
        // Backs the Goals tab and the sidebar's Goals tab. No existing table
        // holds a weighted, measurable goal: task.kra is a 50-char free-text
        // label on a front-desk task with no weight, target or achievement.
        Schema::create('s_performance_goals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('competency_id')->nullable()
                ->comment('FK -> competency.id (a KASBA BUNDLE). NEVER a skill id: a skill is one of five KASBA dimensions inside a bundle (Q-A2, G-DATA-11).');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->unsignedBigInteger('review_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();               // goal owner (tbluser.id)
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            // kra | kpi | okr | competency | project
            $table->string('category', 30)->default('kra')->index();
            // Weightages across an employee's goals are expected to total 100.
            $table->decimal('weightage', 5, 2)->default(0);
            $table->string('metric', 191)->nullable();
            $table->string('target_value', 100)->nullable();
            $table->string('achieved_value', 100)->nullable();
            $table->string('unit', 30)->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->unsignedTinyInteger('progress')->default(0);          // 0..100
            // draft | active | achieved | partially_achieved | missed | cancelled
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('self_rating', 4, 2)->nullable();
            $table->decimal('manager_rating', 4, 2)->nullable();
            $table->text('manager_comments')->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('competency_id', 's_performance_goals_competency_idx');
        });

        // 4. Appraisals -------------------------------------------------------
        // Backs the Appraisals tab: the final-rating decision plus the promotion
        // recommendation and its approval workflow.
        Schema::create('s_performance_appraisals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->unsignedBigInteger('review_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();               // appraisee (tbluser.id)
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('jobrole', 191)->nullable();
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->string('final_rating_label', 60)->nullable();
            // promote | retain | pip | exit | lateral_move
            $table->string('recommendation', 30)->nullable()->index();
            $table->string('current_designation', 191)->nullable();
            $table->string('proposed_designation', 191)->nullable();
            $table->string('current_grade', 60)->nullable();
            $table->string('proposed_grade', 60)->nullable();
            $table->date('effective_date')->nullable()->index();
            // draft | pending_approval | approved | rejected
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Compensation revisions ------------------------------------------
        // Backs the Compensation tab. Kept separate from bonus awards so each
        // tab maps 1:1 to a table with no per-type nullables. employee_salary_
        // structures is payroll's salary-structure JSON with no proposed/
        // approved workflow, so it can only be READ for current CTC.
        Schema::create('s_performance_compensation_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->unsignedBigInteger('review_id')->nullable()->index();
            $table->unsignedBigInteger('appraisal_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();               // employee (tbluser.id)
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('currency', 10)->default('INR');
            $table->decimal('current_ctc', 14, 2)->nullable();
            $table->decimal('proposed_ctc', 14, 2)->nullable();
            $table->decimal('increment_amount', 14, 2)->nullable();
            $table->decimal('increment_pct', 6, 2)->nullable();
            // merit | promotion | market_correction | retention | probation_confirmation
            $table->string('revision_type', 40)->default('merit')->index();
            $table->date('effective_date')->nullable()->index();
            // draft | pending_approval | approved | rejected
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Bonus awards -----------------------------------------------------
        // Backs the Bonus tab.
        Schema::create('s_performance_bonus_awards', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->unsignedBigInteger('review_id')->nullable()->index();
            $table->unsignedBigInteger('appraisal_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();               // employee (tbluser.id)
            $table->unsignedBigInteger('department_id')->nullable()->index();
            // performance | retention | spot | festival | referral | project
            $table->string('bonus_type', 40)->default('performance')->index();
            $table->string('currency', 10)->default('INR');
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('pct_of_ctc', 6, 2)->nullable();
            // Stored as YYYY-MM so the tab can group/filter by payout month.
            $table->string('payout_month', 7)->nullable()->index();
            $table->date('payout_date')->nullable();
            // draft | pending_approval | approved | rejected | paid
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Calibration sessions --------------------------------------------
        // Backs the Calibration tab, the "Calibration Pending" KPI and the
        // sidebar's Calibration Status. The per-employee calibrated rating lives
        // on s_performance_reviews.calibrated_rating, not here.
        Schema::create('s_performance_calibration_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('name', 191);
            $table->unsignedBigInteger('facilitator_id')->nullable()->index();
            $table->dateTime('scheduled_at')->nullable();
            // scheduled | in_progress | completed | locked
            $table->string('status', 30)->default('scheduled')->index();
            $table->unsignedInteger('participant_count')->default(0);
            // Target rating distribution guardrail, e.g. {"5":10,"4":20,"3":50,...}
            $table->longText('distribution_target')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 8. Activity log -----------------------------------------------------
        // Backs the Activity Feed and Audit Trail tabs. A deliberately separate
        // table from s_competency_activity_log: that feed's "Module" label is
        // DERIVED from subject_type by the Competency Audit Center, so
        // performance rows would render there as unknown modules and skew its
        // counts, filters and Version History tab.
        Schema::create('s_performance_activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();   // actor (tbluser.id)
            $table->string('actor_name', 191)->nullable();
            $table->string('action', 191)->index();
            $table->text('description')->nullable();
            // cycle | review | goal | appraisal | compensation | bonus | calibration | note | attachment
            $table->string('subject_type', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('subject_name', 191)->nullable();
            // Field-level diff: [{field,label,old,new}] - the Audit Trail's change summary.
            $table->longText('changes')->nullable();
            // Lets the feed be scoped to one review (the screen's Activity Feed panel).
            $table->unsignedBigInteger('review_id')->nullable()->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->timestamps();
        });

        // 9. Notes and comments ----------------------------------------------
        // Backs "Comments (3)" and "Notes (2)". One table, discriminated by
        // note_type: both are free text on a review, they differ only in who
        // may see them (comment = participants, note = HR/manager private).
        Schema::create('s_performance_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('review_id')->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();   // employee the note is about
            // comment | note
            $table->string('note_type', 20)->default('comment')->index();
            // all | manager | hr | private
            $table->string('visibility', 20)->default('all');
            $table->text('body');
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->string('author_name', 191)->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 10. Attachments -----------------------------------------------------
        // Backs "Attachments (4)" and the sidebar's Docs tab. staff_document is
        // HR paperwork keyed to document_type_id with no review linkage, and
        // s_competency_evidence would leak review PDFs onto the Competency
        // Employee-Profile Evidence tab - so this is its own store.
        Schema::create('s_performance_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('review_id')->index();
            $table->unsignedBigInteger('cycle_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();   // employee the file belongs to
            $table->string('title', 191)->nullable();
            $table->string('file_name', 191);
            $table->string('file_path', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            // goal_evidence | review_document | appraisal_letter | other
            $table->string('document_type', 40)->default('other')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->string('uploaded_by_name', 191)->nullable();
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 11. Saved views -----------------------------------------------------
        // Backs the "Saved Views" dropdown (server-side, so presets follow the
        // user across devices and can be shared with the team).
        Schema::create('s_performance_saved_views', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->index();               // owner (tbluser.id)
            $table->string('name', 191);
            // Which tab the preset belongs to: goals | reviews | appraisals | ...
            $table->string('tab', 30)->default('reviews')->index();
            // The serialised filter set, e.g. {"department_id":"3","stage":"calibration"}
            $table->longText('filters')->nullable();
            $table->boolean('is_shared')->default(false)->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_performance_saved_views');
        Schema::dropIfExists('s_performance_attachments');
        Schema::dropIfExists('s_performance_notes');
        Schema::dropIfExists('s_performance_activity_log');
        Schema::dropIfExists('s_performance_calibration_sessions');
        Schema::dropIfExists('s_performance_bonus_awards');
        Schema::dropIfExists('s_performance_compensation_revisions');
        Schema::dropIfExists('s_performance_appraisals');
        Schema::dropIfExists('s_performance_goals');
        Schema::dropIfExists('s_performance_reviews');
        Schema::dropIfExists('s_performance_cycles');
    }
};
