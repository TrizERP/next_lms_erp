<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_08_18_112000_create_talent_performance_tables.php` defines eleven
 * tables but on this environment only `s_performance_cycles` and
 * `s_performance_reviews` actually exist - the other nine
 * (goals, appraisals, compensation_revisions, bonus_awards,
 * calibration_sessions, activity_log, notes, attachments, saved_views) were
 * found missing despite that migration being recorded as run. Recreates each
 * missing one with the exact same column set as the original migration
 * (guarded, so already-present tables are left untouched) rather than
 * editing the original.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('s_performance_goals')) {
            Schema::create('s_performance_goals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('competency_id')->nullable();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->unsignedBigInteger('review_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('category', 30)->default('kra')->index();
                $table->decimal('weightage', 5, 2)->default(0);
                $table->string('metric', 191)->nullable();
                $table->string('target_value', 100)->nullable();
                $table->string('achieved_value', 100)->nullable();
                $table->string('unit', 30)->nullable();
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable()->index();
                $table->unsignedTinyInteger('progress')->default(0);
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
        }

        if (!Schema::hasTable('s_performance_appraisals')) {
            Schema::create('s_performance_appraisals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->unsignedBigInteger('review_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('jobrole', 191)->nullable();
                $table->decimal('final_rating', 4, 2)->nullable();
                $table->string('final_rating_label', 60)->nullable();
                $table->string('recommendation', 30)->nullable()->index();
                $table->string('current_designation', 191)->nullable();
                $table->string('proposed_designation', 191)->nullable();
                $table->string('current_grade', 60)->nullable();
                $table->string('proposed_grade', 60)->nullable();
                $table->date('effective_date')->nullable()->index();
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
        }

        if (!Schema::hasTable('s_performance_compensation_revisions')) {
            Schema::create('s_performance_compensation_revisions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->unsignedBigInteger('review_id')->nullable()->index();
                $table->unsignedBigInteger('appraisal_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('currency', 10)->default('INR');
                $table->decimal('current_ctc', 14, 2)->nullable();
                $table->decimal('proposed_ctc', 14, 2)->nullable();
                $table->decimal('increment_amount', 14, 2)->nullable();
                $table->decimal('increment_pct', 6, 2)->nullable();
                $table->string('revision_type', 40)->default('merit')->index();
                $table->date('effective_date')->nullable()->index();
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
        }

        if (!Schema::hasTable('s_performance_bonus_awards')) {
            Schema::create('s_performance_bonus_awards', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->unsignedBigInteger('review_id')->nullable()->index();
                $table->unsignedBigInteger('appraisal_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('bonus_type', 40)->default('performance')->index();
                $table->string('currency', 10)->default('INR');
                $table->decimal('amount', 14, 2)->nullable();
                $table->decimal('pct_of_ctc', 6, 2)->nullable();
                $table->string('payout_month', 7)->nullable()->index();
                $table->date('payout_date')->nullable();
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
        }

        if (!Schema::hasTable('s_performance_calibration_sessions')) {
            Schema::create('s_performance_calibration_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('cycle_id')->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('name', 191);
                $table->unsignedBigInteger('facilitator_id')->nullable()->index();
                $table->dateTime('scheduled_at')->nullable();
                $table->string('status', 30)->default('scheduled')->index();
                $table->unsignedInteger('participant_count')->default(0);
                $table->longText('distribution_target')->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('locked_at')->nullable();
                $table->unsignedBigInteger('created_by')->index()->nullable();
                $table->unsignedBigInteger('updated_by')->index()->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('s_performance_activity_log')) {
            Schema::create('s_performance_activity_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('actor_name', 191)->nullable();
                $table->string('action', 191)->index();
                $table->text('description')->nullable();
                $table->string('subject_type', 100)->nullable()->index();
                $table->unsignedBigInteger('subject_id')->nullable()->index();
                $table->string('subject_name', 191)->nullable();
                $table->longText('changes')->nullable();
                $table->unsignedBigInteger('review_id')->nullable()->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('s_performance_notes')) {
            Schema::create('s_performance_notes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('review_id')->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('note_type', 20)->default('comment')->index();
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
        }

        if (!Schema::hasTable('s_performance_attachments')) {
            Schema::create('s_performance_attachments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('review_id')->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('title', 191)->nullable();
                $table->string('file_name', 191);
                $table->string('file_path', 500)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('document_type', 40)->default('other')->index();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->string('uploaded_by_name', 191)->nullable();
                $table->unsignedBigInteger('created_by')->index()->nullable();
                $table->unsignedBigInteger('updated_by')->index()->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('s_performance_saved_views')) {
            Schema::create('s_performance_saved_views', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name', 191);
                $table->string('tab', 30)->default('reviews')->index();
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
    }

    /**
     * Deliberately does not drop these - see the sibling
     * `2026_08_21_140000_create_missing_talent_onboarding_activity_log`
     * migration for why (they may hold real data by the time this rolls
     * back).
     */
    public function down(): void
    {
        // no-op
    }
};
