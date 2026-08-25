<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment feature area of Talent Management, ported from hp_erp's
 * `App\Http\Controllers\talent\*` set (the controllers G2G's frontend
 * actually calls — not the `talent_management/` namespace duplicates).
 *
 * `talent_job_postings`, `talent_job_applications`, `talent_interview_schedules`
 * and `talent_evaluation_form` have NO create migration in hp_erp — they are
 * pre-existing legacy schema there. Their column sets below are derived
 * exhaustively from the source models' `$fillable`/`$casts`, every
 * `Schema::table` alter migration touching them, and every field read via
 * `$request->input()`/`$request->get()`/direct property assignment across:
 *   talent_jobpostingcontroller, talent_jobapplicationcontroller,
 *   talent_interviewschedulescontroller, feedbackController, InterviewController,
 *   candidateController, TalentOfferController, CandidateDropoffController.
 * No column below is invented/speculative — each one traces to a concrete
 * read/write site named in the porting report.
 *
 * `talent_screening_results`, `talent_offers` and `talent_offer_templates` DO
 * have real create migrations in hp_erp
 * (2025_12_29_095254_create_talent_screening_results_table,
 * 2026_01_12_114331_create_talent_offers_table +
 * 2026_05_06_000000_add_reportmanager_punch_times_to_talent_offers_table,
 * 2026_01_12_120047_create_talent_offer_templates_table) — their columns are
 * ported directly from those, minus FK constraints: hp_erp's create migrations
 * used `->constrained()`/`->foreign()` on a few columns there, but every other
 * `_id` column across this whole feature (and across this project) is a plain
 * convention-based column with no FK constraint, so those three are dropped
 * to match.
 *
 * Every table also gets this project's standard audit columns
 * (sub_institute_id, created_by/updated_by/deleted_by, timestamps,
 * softDeletes) even where hp_erp's table lacked some of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------
        // talent_job_postings
        // Derived from talent_jobpostingcontroller@store/@update/@getHiringStatus/
        // @destroy. talent_jobposting model declares no $fillable.
        // ------------------------------------------------------------
        Schema::create('talent_job_postings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->string('title', 255);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('employment_type', 100)->nullable();
            $table->string('experience', 255)->nullable();
            $table->string('education', 255)->nullable();
            $table->string('priority_level', 100)->nullable();
            $table->integer('positions')->nullable();
            $table->decimal('min_salary', 14, 2)->nullable();
            $table->decimal('max_salary', 14, 2)->nullable();
            $table->date('deadline')->nullable();
            $table->text('skills')->nullable();
            $table->text('certifications')->nullable();
            $table->text('benefits')->nullable();
            $table->longText('description')->nullable();
            /**
             * store() validates lowercase 'active'/'inactive'; getRequisitions()
             * separately queries ucfirst($status) ('Active'/'Closed'). Kept as a
             * plain string (not an enum) so neither observed write/read path can
             * ever be rejected by the schema, exactly as hp_erp allows.
             */
            $table->string('status', 50)->default('active');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'status'], 'tjp_tenant_status_idx');
        });

        // ------------------------------------------------------------
        // talent_job_applications
        // Derived from talent_jobapplication::$fillable + controller usage
        // (store/show/update/updateStatus/getCandidateApplications/
        // getShortlistedCandidates in talent_jobapplicationcontroller).
        // ------------------------------------------------------------
        Schema::create('talent_job_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->unsignedBigInteger('job_id')->nullable()->index();
            $table->string('first_name', 255)->nullable();
            $table->string('middle_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('current_location', 255)->nullable();
            $table->string('employment_type', 100)->nullable();
            $table->string('experience', 255)->nullable();
            $table->string('education', 255)->nullable();
            $table->decimal('expected_salary', 14, 2)->nullable();
            $table->text('skills')->nullable();
            $table->text('certifications')->nullable();
            $table->string('resume_path', 500)->nullable();
            $table->date('applied_date')->nullable();
            /**
             * 'Pending Review'|'Under Review'|'Shortlisted'|'Interview Scheduled'|
             * 'Rejected'|'Hired' (validated set) plus 'Completed' (written by
             * feedbackController@storeFeedback) and lowercase 'hired'/'rejected'/
             * 'offered' (written by TalentOfferController/CandidateDropoffController
             * queries) - kept as a plain string.
             */
            $table->string('status', 50)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'job_id'], 'tja_tenant_job_idx');
            $table->index(['sub_institute_id', 'status'], 'tja_tenant_status_idx');
        });

        // ------------------------------------------------------------
        // talent_interview_schedules
        // Derived from talent_interviewschedules::$fillable +
        // 2025_12_27_120318_add_panel_id_to_talent_interview_schedules_table +
        // 2025_12_31_064328_change_interviewer_id_to_json_in_talent_interview_schedules_table
        // + controller usage (store/update/customUpdate/getInterviewDetails/
        // candidatepipeline in talent_interviewschedulescontroller).
        // ------------------------------------------------------------
        Schema::create('talent_interview_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->unsignedBigInteger('job_id')->nullable()->index();
            $table->unsignedBigInteger('applicant_id')->nullable()->index();
            /** Plain column, no FK (source had ->foreign() onto talent_Interview_Panel). */
            $table->unsignedBigInteger('panel_id')->nullable();
            $table->string('round_no', 255)->nullable();
            $table->date('interview_date')->nullable();
            $table->string('time', 255)->nullable();
            $table->integer('duration')->nullable();
            $table->string('location', 255)->nullable();
            /** JSON array of interviewer user ids; cast to `array` on the model. */
            $table->json('interviewer_id')->nullable();
            /** 'Scheduled'|'Completed'|'Under Review'|'Pending Review'|'Rejected'|'Selected'|'Accepted'|'active'. */
            $table->string('status', 50)->nullable();
            $table->string('rating', 255)->nullable();
            $table->string('feedback', 100)->nullable();
            $table->text('additional_notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'applicant_id'], 'tis_tenant_applicant_idx');
            $table->index(['sub_institute_id', 'status'], 'tis_tenant_status_idx');
        });

        // ------------------------------------------------------------
        // talent_interview_panel
        // Referenced by talent_interviewpanelController and the
        // TalentInterviewPanel/TalentInterviewSchedule models (panel_id), but
        // has NO create migration in hp_erp (pre-existing legacy schema there
        // too). Derived from TalentInterviewPanel::$fillable +
        // talent_interviewpanelController@getInterviewPanel/@storeinterviewer/
        // @update/@destroy usage.
        // ------------------------------------------------------------
        Schema::create('talent_interview_panel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->string('panel_name', 255);
            $table->string('target_positions', 255)->nullable();
            $table->text('description')->nullable();
            /** JSON array of interviewer user ids. */
            $table->json('available_interviewers')->nullable();
            /** 'active'|'inactive'. */
            $table->string('status', 50)->default('active');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ------------------------------------------------------------
        // talent_screening_results (real create migration in hp_erp:
        // 2025_12_29_095254_create_talent_screening_results_table)
        // ------------------------------------------------------------
        Schema::create('talent_screening_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->unsignedBigInteger('candidate_id')->index();
            $table->integer('competency_match')->unsigned();
            $table->enum('cultural_fit', ['High', 'Medium', 'Low']);
            $table->enum('predicted_success', ['Highly Likely', 'Likely', 'Possible', 'Unlikely']);
            $table->integer('overall_fit_score')->unsigned();
            $table->integer('ranking_score')->unsigned();
            $table->longText('skill_gaps')->nullable();
            $table->longText('strengths')->nullable();
            $table->text('recommendation')->nullable();
            $table->longText('deepseek_analysis')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ------------------------------------------------------------
        // talent_evaluation_form
        // Derived from TalentEvaluationForm::$fillable +
        // 2025_12_29_102324 (evaluation_criteria -> json) +
        // 2026_01_01_070946 (+ sub_institute_id) +
        // 2026_01_01_071045 (recommendation/key_strengths/areas_of_concern/
        // additional_comments -> text) +
        // 2026_01_13_073907 (+ notes, + status enum) + controller usage
        // (feedbackController@storeFeedback/@updateFeedback, InterviewController@recordDecision).
        // ------------------------------------------------------------
        Schema::create('talent_evaluation_form', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();

            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedBigInteger('candidate_id')->nullable()->index();
            $table->unsignedBigInteger('panel_id')->nullable();
            /** JSON array of {name, score}; cast to `array` on the model. */
            $table->json('evaluation_criteria')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('key_strengths')->nullable();
            $table->text('areas_of_concern')->nullable();
            $table->text('additional_comments')->nullable();
            $table->text('notes')->nullable();
            /**
             * hp_erp's own alter migration declared this
             * enum('Rejected','Hired','Completed') default 'Completed'
             * (InterviewController@recordDecision's write path), but
             * feedbackController@storeFeedback/@updateFeedback validate and
             * write 'draft'/'submitted'/'approved'/'rejected' onto the SAME
             * column - a genuine conflict between hp_erp's two write paths.
             * Kept as a plain string so neither path can ever be rejected by
             * the schema; default matches feedbackController's own default
             * since it is the form's primary creation path.
             */
            $table->string('status', 50)->default('draft');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'candidate_id'], 'tef_tenant_candidate_idx');
        });

        // ------------------------------------------------------------
        // talent_offers (real create migration in hp_erp:
        // 2026_01_12_114331_create_talent_offers_table, plus
        // 2026_05_06_000000_add_reportmanager_punch_times_to_talent_offers_table folded in)
        // ------------------------------------------------------------
        Schema::create('talent_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();

            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('job_id');
            $table->string('template_id', 50)->nullable();
            $table->string('position', 255);
            $table->string('salary', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('expires_at')->nullable();
            /** 'draft'|'sent'|'rejected'|'expired'. */
            $table->string('status', 30)->default('draft');
            $table->string('offer_letter_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('reportmanager')->nullable();
            $table->time('punchintime')->nullable();
            $table->time('punchouttime')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('rejected_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'status'], 'toff_tenant_status_idx');
        });

        // ------------------------------------------------------------
        // talent_offer_templates (real create migration in hp_erp:
        // 2026_01_12_120047_create_talent_offer_templates_table)
        // ------------------------------------------------------------
        Schema::create('talent_offer_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->string('module_name', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->longText('html_content');
            $table->integer('sort_order')->nullable();
            $table->integer('status')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_offer_templates');
        Schema::dropIfExists('talent_offers');
        Schema::dropIfExists('talent_evaluation_form');
        Schema::dropIfExists('talent_screening_results');
        Schema::dropIfExists('talent_interview_panel');
        Schema::dropIfExists('talent_interview_schedules');
        Schema::dropIfExists('talent_job_applications');
        Schema::dropIfExists('talent_job_postings');
    }
};
