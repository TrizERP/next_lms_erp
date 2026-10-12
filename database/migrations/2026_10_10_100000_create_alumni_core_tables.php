<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni Management - core tables (Phase B).
 *
 * TENANCY: every table carries sub_institute_id (the ERP's tenant column) and every
 * unique key includes it. There is no global scope in this codebase, so the service
 * layer scopes every query explicitly.
 *
 * NO DUPLICATED ACADEMIC DATA: the master row links to tblstudent through student_id
 * (unique per tenant, which is what makes conversion idempotent). Class / result
 * history is read from tblstudent_enrollment and result_marks, not copied here. The
 * last_class / division / passing_year columns are a denormalised snapshot taken at
 * conversion so a directory can filter without joining three legacy tables, and so
 * manually-created or imported alumni (who may have no student row) are searchable too.
 *
 * current_company / current_industry / current_job_title on the master row are a
 * search denormalisation of the alumni_career row flagged is_current; the service keeps
 * them in step.
 *
 * Consent flags default to 0 on purpose: an alumnus converted from a student has not
 * agreed to be contacted until staff record it or they opt in.
 *
 * Idempotent and forward-only: guarded by Schema::hasTable, empty down() for safety
 * (dropping would destroy alumni data; remove by hand if ever required).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni')) {
            Schema::create('alumni', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('alumni_code', 40);
                $t->unsignedBigInteger('student_id')->nullable();
                $t->string('source', 20)->default('manual');      // auto|manual|self|import
                $t->string('gr_no', 60)->nullable();
                $t->string('enrollment_no', 60)->nullable();
                $t->string('first_name', 100);
                $t->string('middle_name', 100)->nullable();
                $t->string('last_name', 100)->nullable();
                $t->string('full_name', 320);
                $t->string('gender', 20)->nullable();
                $t->date('dob')->nullable();
                $t->string('blood_group', 10)->nullable();
                $t->string('photo_path', 500)->nullable();
                $t->string('email', 190)->nullable();
                $t->string('mobile', 30)->nullable();
                $t->string('alt_mobile', 30)->nullable();
                $t->text('current_address')->nullable();
                $t->text('permanent_address')->nullable();
                $t->string('city', 120)->nullable();
                $t->string('state', 120)->nullable();
                $t->string('country', 120)->nullable();
                $t->string('pincode', 20)->nullable();
                $t->string('campus', 120)->nullable();
                $t->string('department', 120)->nullable();
                $t->string('course', 120)->nullable();
                $t->unsignedBigInteger('last_standard_id')->nullable();
                $t->string('last_class', 60)->nullable();
                $t->string('division', 60)->nullable();
                $t->string('academic_year', 20)->nullable();
                $t->unsignedSmallInteger('admission_year')->nullable();
                $t->unsignedSmallInteger('passing_year')->nullable();
                $t->string('batch', 40)->nullable();
                $t->date('leaving_date')->nullable();
                // registered|pending_verification|verified|active|inactive|blocked|deceased|opted_out
                $t->string('status', 30)->default('registered');
                $t->string('employment_status', 40)->nullable();
                $t->string('current_company', 190)->nullable();
                $t->string('current_industry', 120)->nullable();
                $t->string('current_job_title', 190)->nullable();
                $t->string('linkedin_url', 300)->nullable();
                $t->unsignedTinyInteger('profile_completion')->default(0);
                $t->unsignedTinyInteger('consent_email')->default(0);
                $t->unsignedTinyInteger('consent_sms')->default(0);
                $t->unsignedTinyInteger('consent_whatsapp')->default(0);
                $t->unsignedTinyInteger('consent_push')->default(0);
                $t->timestamp('last_activity_at')->nullable();
                $t->unsignedBigInteger('verified_by')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->string('verification_remarks', 500)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();

                $t->unique(['sub_institute_id', 'alumni_code'], 'alumni_tenant_code_uq');
                $t->unique(['sub_institute_id', 'student_id'], 'alumni_tenant_student_uq');
                $t->index(['sub_institute_id', 'status'], 'alumni_tenant_status_ix');
                $t->index(['sub_institute_id', 'batch'], 'alumni_tenant_batch_ix');
                $t->index(['sub_institute_id', 'passing_year'], 'alumni_tenant_pyear_ix');
                $t->index(['sub_institute_id', 'country', 'state', 'city'], 'alumni_tenant_geo_ix');
                $t->index(['sub_institute_id', 'current_industry'], 'alumni_tenant_industry_ix');
                $t->index(['sub_institute_id', 'email'], 'alumni_tenant_email_ix');
                $t->index(['sub_institute_id', 'mobile'], 'alumni_tenant_mobile_ix');
                $t->index(['sub_institute_id', 'gr_no'], 'alumni_tenant_gr_ix');
                $t->index(['sub_institute_id', 'enrollment_no'], 'alumni_tenant_enrol_ix');
            });
        }

        if (! Schema::hasTable('alumni_id_sequence')) {
            // One row per (tenant, year): the counter that makes ALM-2026-00001 unique.
            // Incremented inside a transaction with a row lock (see AlumniService).
            Schema::create('alumni_id_sequence', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedSmallInteger('seq_year');
                $t->unsignedInteger('last_number')->default(0);
                $t->timestamps();
                $t->unique(['sub_institute_id', 'seq_year'], 'alumni_seq_uq');
            });
        }

        if (! Schema::hasTable('alumni_settings')) {
            // key/value per tenant: id_pattern, eligibility rule, profile-completion weights,
            // engagement score weights, require_verification_for_self_registration ...
            Schema::create('alumni_settings', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('setting_key', 80);
                $t->longText('setting_value')->nullable();   // JSON
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['sub_institute_id', 'setting_key'], 'alumni_settings_uq');
            });
        }

        if (! Schema::hasTable('alumni_education')) {
            Schema::create('alumni_education', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('qualification', 120);
                $t->string('course', 160)->nullable();
                $t->string('specialization', 160)->nullable();
                $t->string('institution', 220)->nullable();
                $t->string('country', 120)->nullable();
                $t->string('city', 120)->nullable();
                $t->unsignedSmallInteger('admission_year')->nullable();
                $t->unsignedSmallInteger('passing_year')->nullable();
                $t->string('current_status', 30)->nullable();   // ongoing|completed|discontinued
                $t->string('score', 40)->nullable();             // CGPA or percentage, as entered
                $t->string('scholarship', 200)->nullable();
                $t->string('awards', 300)->nullable();
                $t->string('further_education', 300)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'alumni_id'], 'alumni_edu_alumni_ix');
                $t->index(['sub_institute_id', 'institution'], 'alumni_edu_inst_ix');
            });
        }

        if (! Schema::hasTable('alumni_career')) {
            Schema::create('alumni_career', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('employment_status', 40)->nullable();
                $t->string('company_name', 190);
                $t->string('company_type', 80)->nullable();
                $t->string('industry', 120)->nullable();
                $t->string('job_title', 190)->nullable();
                $t->string('department', 120)->nullable();
                $t->string('employment_type', 60)->nullable();
                $t->date('start_date')->nullable();
                $t->date('end_date')->nullable();
                $t->unsignedTinyInteger('is_current')->default(0);
                $t->string('location', 160)->nullable();
                $t->string('country', 120)->nullable();
                $t->string('salary_range', 80)->nullable();      // optional, tenant-configurable visibility
                $t->string('website', 300)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'alumni_id'], 'alumni_career_alumni_ix');
                $t->index(['sub_institute_id', 'company_name'], 'alumni_career_company_ix');
                $t->index(['sub_institute_id', 'industry'], 'alumni_career_industry_ix');
            });
        }

        if (! Schema::hasTable('alumni_business')) {
            Schema::create('alumni_business', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('business_name', 190);
                $t->string('business_type', 100)->nullable();
                $t->string('industry', 120)->nullable();
                $t->string('role', 40)->nullable();              // founder|co_founder
                $t->unsignedSmallInteger('established_year')->nullable();
                $t->string('website', 300)->nullable();
                $t->string('location', 160)->nullable();
                $t->unsignedInteger('employee_count')->nullable();
                $t->text('description')->nullable();
                $t->text('social_links')->nullable();            // JSON
                $t->string('contact_details', 300)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'alumni_id'], 'alumni_biz_alumni_ix');
            });
        }

        if (! Schema::hasTable('alumni_skills')) {
            Schema::create('alumni_skills', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('skill', 120);                         // free text: custom skills allowed
                $t->string('level', 20)->default('intermediate'); // beginner|intermediate|advanced|expert
                $t->timestamps();
                $t->unique(['alumni_id', 'skill'], 'alumni_skill_uq');
                $t->index(['sub_institute_id', 'skill'], 'alumni_skill_search_ix');
            });
        }

        if (! Schema::hasTable('alumni_achievements')) {
            Schema::create('alumni_achievements', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('category', 40);                       // award|certification|academic|professional|sports|social_service|...
                $t->string('title', 220);
                $t->text('description')->nullable();
                $t->date('achieved_on')->nullable();
                $t->string('organization', 220)->nullable();
                $t->unsignedBigInteger('document_id')->nullable();
                $t->string('verification_status', 20)->default('pending'); // pending|verified|rejected
                $t->unsignedBigInteger('verified_by')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'alumni_id'], 'alumni_ach_alumni_ix');
            });
        }

        if (! Schema::hasTable('alumni_documents')) {
            // Files live on a PRIVATE disk; file_path is never exposed, downloads go through an
            // authorised endpoint that checks tenant and ownership/role.
            Schema::create('alumni_documents', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('doc_type', 40);
                $t->string('original_name', 255);
                $t->string('file_path', 500);
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size_bytes')->default(0);
                $t->string('verification_status', 20)->default('pending');
                $t->unsignedBigInteger('verified_by')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->string('remarks', 500)->nullable();
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'alumni_id'], 'alumni_doc_alumni_ix');
            });
        }

        if (! Schema::hasTable('alumni_privacy')) {
            // Per-field visibility. A missing row means the tenant default (private for
            // contact fields, alumni_only for the rest) - see AlumniPrivacy.
            Schema::create('alumni_privacy', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('field_key', 60);
                $t->string('visibility', 20);                     // public|alumni_only|institution_only|private
                $t->timestamps();
                $t->unique(['alumni_id', 'field_key'], 'alumni_privacy_uq');
            });
        }

        if (! Schema::hasTable('alumni_activity')) {
            // Timeline + engagement ledger. type examples: registered, verified, profile_updated,
            // career_added, event_attended, mentoring, guest_lecture, donation ...
            Schema::create('alumni_activity', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('activity_type', 40);
                $t->string('title', 255);
                $t->text('detail')->nullable();
                $t->unsignedSmallInteger('points')->default(0);
                $t->string('ref_type', 40)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $t->timestamp('occurred_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'alumni_id', 'occurred_at'], 'alumni_act_alumni_ix');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty - see class doc-block.
    }
};
