<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from hp_erp's `s_users_skills` table (schema captured via
 * SHOW CREATE TABLE on the hp_erp database). This table was missing from
 * next_lms_erp entirely, which is why InterviewPanelController@getInterviewers'
 * job_role/skills join (ported 1:1 from hp_erp's talent_interviewpanelController)
 * always returned an empty `skills` array here.
 */
class CreateSUsersSkillsTable extends Migration
{
    public function up()
    {
        Schema::create('s_users_skills', function (Blueprint $table) {
            $table->id();
            $table->string('department', 191)->nullable()->index();
            $table->string('sub_department', 191)->nullable()->index();
            $table->integer('department_id')->nullable()->index();
            $table->string('category', 191)->nullable()->index();
            $table->string('competency_type', 50)->nullable()->index();
            $table->string('sub_category', 191)->nullable()->index();
            $table->string('micro_category', 191)->nullable();
            $table->string('title', 191)->index();
            $table->text('description')->nullable();
            $table->string('skill_code', 50)->nullable();
            $table->tinyText('related_skills')->nullable();
            $table->string('bussiness_links', 191)->nullable();
            $table->tinyText('custom_tags')->nullable();
            $table->tinyText('proficiency_level')->nullable();
            $table->tinyText('job_titles')->nullable();
            $table->tinyText('learning_resources')->nullable();
            $table->tinyText('assesment_method')->nullable();
            $table->tinyText('certification_qualifications')->nullable();
            $table->tinyText('experience_project')->nullable();
            $table->tinyText('skill_maps')->nullable();
            $table->string('skill_status', 50)->nullable();
            $table->string('skill_importance', 50)->nullable();
            $table->string('legal_compliance_relevance', 100)->nullable();
            $table->tinyText('sop_practice_link')->nullable();
            $table->string('performance_metrics', 191)->nullable();
            $table->string('common_errors_tips', 191)->nullable();
            $table->string('sme_contacts', 100)->nullable();
            $table->string('sub_skills', 100)->nullable();
            $table->string('skill_flow', 191)->nullable();
            $table->tinyText('tasklist')->nullable();
            // L-05 (carried over from hp_erp): filter with status != 'Inactive', NEVER
            // status = 'Active' - NULL means nobody marked the row, not that it's inactive.
            $table->enum('status', ['Active', 'Inactive'])->nullable()->index();
            $table->enum('approve_status', ['Approved', 'Pending', 'Cancelled'])->nullable()->index();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            // No FK constraints: tbluser.id/school_setup.id are `int unsigned` in this
            // database, not the `bigint unsigned` hp_erp used, so a same-type FK isn't
            // possible without altering those PK columns. Not needed for the join.
        });
    }

    public function down()
    {
        Schema::dropIfExists('s_users_skills');
    }
}
