<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni Management - mentorship and opportunities (Phase C).
 *
 * Every table carries sub_institute_id (the ERP's tenant column); the service layer scopes every query by it.
 * Students are referenced by tblstudent.id (student_id) - no student data is copied here.
 *
 * Engagement points need no table of their own: they are alumni_activity rows (created in the core migration)
 * whose activity_type is an engagement type and whose points come from the tenant's configurable weights.
 *
 * Idempotent and forward-only (guarded by Schema::hasTable; empty down()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni_mentor_profiles')) {
            Schema::create('alumni_mentor_profiles', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('expertise', 500)->nullable();
                $t->string('industry', 120)->nullable();
                $t->string('job_role', 190)->nullable();
                $t->text('preferred_topics')->nullable();
                $t->string('available_days', 120)->nullable();       // CSV: mon,tue,...
                $t->string('mode', 20)->default('both');              // online|offline|both
                $t->unsignedSmallInteger('experience_years')->nullable();
                $t->unsignedSmallInteger('capacity')->default(3);     // students mentored at once
                $t->string('status', 20)->default('active');          // active|paused
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['sub_institute_id', 'alumni_id'], 'alumni_mentor_uq');
                $t->index(['sub_institute_id', 'status'], 'alumni_mentor_status_ix');
            });
        }

        if (! Schema::hasTable('alumni_mentorship_requests')) {
            Schema::create('alumni_mentorship_requests', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('student_id');
                $t->string('career_area', 190);
                $t->text('guidance_needed')->nullable();
                $t->unsignedBigInteger('preferred_mentor_alumni_id')->nullable();
                $t->date('preferred_date')->nullable();
                // open -> matched -> accepted -> completed ; cancelled at any point before completion
                $t->string('status', 20)->default('open');
                $t->unsignedBigInteger('mentor_alumni_id')->nullable();
                $t->string('declined_mentor_ids', 500)->nullable();   // CSV of alumni who declined this request
                $t->unsignedBigInteger('matched_by')->nullable();
                $t->timestamp('matched_at')->nullable();
                $t->timestamp('responded_at')->nullable();
                $t->string('response_note', 500)->nullable();
                $t->string('created_by_role', 10)->default('staff');  // staff|student
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'status'], 'alumni_mreq_status_ix');
                $t->index(['sub_institute_id', 'student_id'], 'alumni_mreq_student_ix');
                $t->index(['sub_institute_id', 'mentor_alumni_id'], 'alumni_mreq_mentor_ix');
            });
        }

        if (! Schema::hasTable('alumni_mentorship_sessions')) {
            Schema::create('alumni_mentorship_sessions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('request_id');
                $t->unsignedBigInteger('mentor_alumni_id');
                $t->unsignedBigInteger('student_id');
                $t->dateTime('scheduled_at');
                $t->unsignedSmallInteger('duration_minutes')->default(45);
                $t->string('mode', 20)->default('online');
                $t->string('location', 255)->nullable();
                $t->string('status', 20)->default('scheduled');       // scheduled|completed|cancelled|no_show
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'request_id'], 'alumni_msess_req_ix');
                $t->index(['sub_institute_id', 'mentor_alumni_id', 'scheduled_at'], 'alumni_msess_mentor_ix');
            });
        }

        if (! Schema::hasTable('alumni_mentorship_feedback')) {
            Schema::create('alumni_mentorship_feedback', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('session_id');
                $t->string('from_role', 10);                          // student|mentor
                $t->unsignedTinyInteger('rating');                    // 1-5
                $t->text('comments')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['session_id', 'from_role'], 'alumni_mfb_uq');
            });
        }

        if (! Schema::hasTable('alumni_opportunities')) {
            Schema::create('alumni_opportunities', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id')->nullable();      // the alumnus offering it, when there is one
                $t->unsignedBigInteger('posted_by')->nullable();       // the user who entered it
                $t->string('type', 20);                                // job|internship|apprenticeship|project|freelance
                $t->string('company', 190);
                $t->string('title', 190);
                $t->string('location', 160)->nullable();
                $t->string('experience', 80)->nullable();
                $t->string('qualification', 190)->nullable();
                $t->string('skills', 500)->nullable();
                $t->string('salary', 80)->nullable();                  // optional salary/CTC as text
                $t->date('last_date')->nullable();
                $t->string('application_url', 500)->nullable();
                $t->string('contact_person', 190)->nullable();
                $t->text('description')->nullable();
                // pending_approval|published|rejected|closed ; "expired" is derived from last_date, never stored by hand
                $t->string('status', 20)->default('pending_approval');
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->timestamp('reviewed_at')->nullable();
                $t->string('review_remarks', 500)->nullable();
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'status', 'last_date'], 'alumni_opp_status_ix');
                $t->index(['sub_institute_id', 'type'], 'alumni_opp_type_ix');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty - dropping would destroy mentorship and opportunity records.
    }
};
