<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_08_02_140000_create_competency_approvals_table.php`
 * for the ported `CompetencyApprovalController` (submit-for-approval workflow
 * behind the Capability Library / Competency Framework screens).
 *
 * A single subject-agnostic approval queue for `competency` (writes
 * `s_users_skills.approve_status`) and `framework` (writes
 * `s_competency_frameworks.status`) — role-mapping change requests keep using
 * the existing `s_competency_mapping_reviews` queue, which the controller
 * reads (never writes) alongside this table so the inbox is complete.
 *
 * Schema copied column-for-column from the source migration. Confirmed
 * missing from this target (grepped `database/migrations` for
 * `s_competency_approvals` — no match before this file).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('s_competency_approvals')) {
            return;
        }

        Schema::create('s_competency_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            // 'competency' -> s_users_skills, 'framework' -> s_competency_frameworks
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            // Denormalised so a rejected row still reads sensibly after the
            // subject is renamed or archived.
            $table->string('subject_name', 191)->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();

            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->string('submitted_by_name', 191)->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('reviewer_name', 191)->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The queue reads by tenant + status, and a subject's history reads
            // by subject - one composite index covers both entry points.
            $table->index(['sub_institute_id', 'status'], 's_competency_approvals_tenant_status_index');
            $table->index(['subject_type', 'subject_id'], 's_competency_approvals_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_approvals');
    }
};
