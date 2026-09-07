<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course-completion certificates â€” G2G LMS migration (Package 3).
 *
 * A DISTINCT concept from `s_competency_certifications` (externally-issued /
 * self-declared credentials, already ported and owned by the Competency
 * Management module): this table is a certificate the LMS itself mints when a
 * learner finishes a course. Combines hp_erp's
 * `2026_07_28_120001_create_lms_certificates_table.php`,
 * `2026_07_28_160000_add_details_and_renewal_to_lms_certificates_table.php`
 * and `2026_07_28_160001_relax_certificate_unique_for_reissue.php` into one
 * clean create â€” the live column shape is identical to the fully-migrated
 * hp_erp table (create + both follow-up ALTERs), just as a single file.
 *
 * `course_id` references `sub_std_map` (existing, shared table â€” read-only
 * here). `enrollment_id` references `lms_course_enroll`, owned by Package 1;
 * that table's migration may land before or after this one depending on
 * merge order, so this stays an unsigned bigint with NO hard foreign key â€”
 * same caveat the task brief calls out explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_certificates', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('course_id')->index();
            // lms_course_enroll.id â€” Package 1's table. No FK constraint: see note above.
            $table->unsignedBigInteger('enrollment_id')->nullable()->index();
            // s_users_skills.id when the course maps to a competency skill.
            $table->unsignedBigInteger('skill_id')->nullable()->index();

            $table->string('certificate_number', 100)->unique();
            $table->string('course_title', 191)->nullable();

            // Presentation fields. Falls back to course_title when not set.
            $table->string('name', 191)->nullable();
            $table->text('description')->nullable();
            // JSON array of free-text tags, e.g. ["Compliance","Mandatory"].
            $table->text('tags')->nullable();

            // Public, non-guessable code backing GET /verify/{code}.
            $table->string('verification_code', 64)->nullable()->unique();

            $table->timestamp('issued_at')->nullable();
            // Null means the certificate does not expire.
            $table->timestamp('expires_at')->nullable()->index();

            $table->string('status', 20)->default('active')->index();

            // Renewal lineage â€” a re-issue supersedes the original rather than
            // overwriting it, so both rows are kept for the audit trail.
            $table->unsignedBigInteger('supersedes')->nullable()->index();
            $table->unsignedBigInteger('superseded_by')->nullable()->index();
            $table->timestamp('reissued_at')->nullable();
            $table->unsignedBigInteger('reissued_by')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexed, not unique â€” a renewal chain needs a second row for the
            // same (user_id, course_id) pair (the superseded original plus its
            // replacement). issueCertificate()'s own existence check is what
            // keeps first-issue idempotent, not a DB constraint.
            $table->index(['user_id', 'course_id'], 'lms_certificates_user_course_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_certificates');
    }
};
