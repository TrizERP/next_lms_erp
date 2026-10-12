<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni Management - surveys (Phase D).
 *
 *   alumni_surveys              the survey, its audience and open window
 *   alumni_survey_questions     text | single_choice | multiple_choice | rating | yes_no | scale
 *   alumni_survey_participants  WHO has responded - unique(survey_id, alumni_id) is what stops a second response
 *   alumni_survey_submissions   one per response; alumni_id is NULL when the survey is anonymous
 *   alumni_survey_answers       one per question answered
 *
 * ANONYMITY. For an anonymous survey the answers are stored without the alumnus: participants says "this person has
 * responded" (so they cannot respond twice and a reminder can skip them) and submissions says "a response was made", with
 * nothing joining the two. Reports cannot tell who said what.
 *
 * Idempotent and forward-only (guarded by Schema::hasTable; empty down()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni_surveys')) {
            Schema::create('alumni_surveys', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('title', 190);
                $t->string('survey_type', 30);              // alumni_satisfaction|career_progress|institution_feedback|curriculum_feedback|industry_feedback|event_feedback|mentorship_feedback|placement_feedback
                $t->text('description')->nullable();
                $t->string('status', 20)->default('draft'); // draft|published|closed
                $t->text('audience')->nullable();           // JSON filters; null = every eligible alumnus
                $t->string('audience_label', 500)->nullable();
                $t->unsignedTinyInteger('anonymous')->default(0);
                $t->dateTime('opens_at')->nullable();
                $t->dateTime('closes_at')->nullable();
                $t->timestamp('published_at')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'status'], 'alumni_survey_status_ix');
            });
        }

        if (! Schema::hasTable('alumni_survey_questions')) {
            Schema::create('alumni_survey_questions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('survey_id');
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->string('question_type', 20);
                $t->string('prompt', 500);
                $t->text('options')->nullable();            // JSON list, for the two choice types
                $t->unsignedTinyInteger('required')->default(1);
                $t->smallInteger('scale_min')->nullable();
                $t->smallInteger('scale_max')->nullable();
                $t->timestamps();
                $t->index(['survey_id', 'sort_order'], 'alumni_sq_order_ix');
            });
        }

        if (! Schema::hasTable('alumni_survey_participants')) {
            Schema::create('alumni_survey_participants', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('survey_id');
                $t->unsignedBigInteger('alumni_id');
                $t->timestamp('submitted_at')->nullable();
                $t->timestamps();
                $t->unique(['survey_id', 'alumni_id'], 'alumni_sp_uq');
            });
        }

        if (! Schema::hasTable('alumni_survey_submissions')) {
            Schema::create('alumni_survey_submissions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('survey_id');
                $t->unsignedBigInteger('alumni_id')->nullable();    // NULL for an anonymous survey
                $t->unsignedBigInteger('entered_by')->nullable();   // the staff user, when recorded on someone's behalf
                $t->timestamp('submitted_at')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'survey_id'], 'alumni_ss_survey_ix');
            });
        }

        if (! Schema::hasTable('alumni_survey_answers')) {
            Schema::create('alumni_survey_answers', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('submission_id');
                $t->unsignedBigInteger('question_id');
                $t->text('answer_text')->nullable();                // text, or the chosen option, or yes/no
                $t->decimal('answer_value', 10, 2)->nullable();     // rating / scale, for averages
                $t->text('answer_options')->nullable();             // JSON list, for multiple choice
                $t->timestamps();
                $t->index(['submission_id'], 'alumni_sa_sub_ix');
                $t->index(['question_id'], 'alumni_sa_q_ix');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty - dropping would destroy survey responses.
    }
};
