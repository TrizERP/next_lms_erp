<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_30_120000_create_talent_onboarding_tables` +
 * `2026_07_31_100000_create_onboarding_module_tables`, collapsed into one
 * clean create per table for this project's final column set.
 *
 * The source repo carries these two migrations because the first restored a
 * lost, already-live schema for `talent_onboarding_journeys` /
 * `talent_onboarding_tasks` / `talent_onboarding_documents`, and the second
 * (written earlier in wall-clock history but designed to be idempotent
 * on top of the first) topped up the same three tables via guarded
 * `hasColumn()` checks and created the three newer tables
 * (`talent_onboarding_journey_stages`, `talent_onboarding_notes`,
 * `talent_onboarding_activity_log`). Every column either migration adds ends
 * up on the tables below; nothing from the guarded top-up mechanism itself
 * is reproduced.
 *
 * Only the ACTIVE `App\Http\Controllers\Api\Onboarding\*` ("v2 Center",
 * routes under `/onboarding/*`) implementation is ported. The source repo
 * also has a dead `/talent/onboarding/*` implementation
 * (`App\Http\Controllers\Api\Talent\Onboarding*Controller`) reusing the same
 * `talent_onboarding_journeys` / `_tasks` / `_documents` tables with a
 * smaller column set — that implementation is not ported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_onboarding_journeys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();

            /** Human reference ("OHB-2026-0004"), issued by nextJourneyCode(). */
            $table->string('journey_code', 40)->nullable()->index();

            /**
             * tbluser.id of the hire. Nullable because a journey can start
             * during preboarding, before the user record is created - which
             * is why candidate_name / _email / _phone exist alongside it.
             */
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('offer_id')->nullable()->index();
            $table->unsignedBigInteger('application_id')->nullable()->index();
            $table->string('candidate_name', 191)->nullable();
            $table->string('candidate_email', 191)->nullable();
            $table->string('candidate_phone', 50)->nullable();

            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('location', 191)->nullable();
            $table->string('position', 191)->nullable();
            $table->date('joining_date')->nullable()->index();

            // preboarding | first_day | orientation | team_integration |
            // probation | confirmed | exited
            $table->string('stage', 30)->default('preboarding')->index();
            // not-started | in-progress | completed | on-hold | cancelled
            $table->string('status', 20)->default('not-started')->index();

            $table->unsignedBigInteger('buddy_id')->nullable()->index();
            $table->unsignedBigInteger('manager_id')->nullable()->index();
            /** Stamped when the journey closes. */
            $table->date('completed_at')->nullable();

            $table->date('probation_start')->nullable();
            $table->date('probation_end')->nullable()->index();
            $table->date('extension_end')->nullable();
            // pending | confirmed | extended | terminated
            $table->string('confirmation_status', 20)->default('pending')->index();
            $table->date('confirmed_on')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable()->index();
            $table->text('confirmation_notes')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'status'], 'onb_journey_tenant_status_idx');
        });

        Schema::create('talent_onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id')->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            // documents | compliance | it | learning | payroll | benefits | personal
            $table->string('category', 50)->nullable()->index();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            /**
             * Free-text owner for a task nobody holds an account for yet
             * ("IT Helpdesk"); owner_id wins when both are set.
             */
            $table->string('owner_label', 100)->nullable();
            $table->date('due_date')->nullable()->index();
            // pending | in_progress | sent | completed | blocked
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'status'], 'onb_task_tenant_status_idx');
        });

        Schema::create('talent_onboarding_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id')->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('document_type_id')->nullable()->index();
            $table->string('title', 191);
            $table->string('file_name', 191)->nullable();
            /** Path on the `public` disk; 500 chars to fit nested tenant folders. */
            $table->string('file_path', 500)->nullable();
            // pending | sent | received | verified | rejected
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_mandatory')->default(true);
            $table->date('due_date')->nullable();

            // The request -> submit -> verify lifecycle, each step stamped
            // separately so an audit can show when it happened.
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable()->index();

            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('talent_onboarding_journey_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id')->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            // offer_accepted | preboarding | first_day | orientation |
            // team_integration | probation | confirmation
            $table->string('stage_key', 50)->index();
            $table->string('title', 191);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // pending | in_progress | completed | skipped
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('talent_onboarding_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id')->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->text('note');
            // internal | shared - shared notes are visible to the new hire.
            $table->string('visibility', 20)->default('internal')->index();
            $table->string('author_name', 191)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('talent_onboarding_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // actor (tbluser.id)
            $table->string('actor_name', 191)->nullable();
            $table->string('action', 191)->index();
            $table->text('description')->nullable();
            // journey | task | stage | document | note | probation
            $table->string('subject_type', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('subject_name', 191)->nullable();
            // Field-level diff: [{field,label,old,new}].
            $table->longText('changes')->nullable();
            // Scopes the feed to one hire's lifecycle timeline.
            $table->unsignedBigInteger('journey_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_onboarding_activity_log');
        Schema::dropIfExists('talent_onboarding_notes');
        Schema::dropIfExists('talent_onboarding_journey_stages');
        Schema::dropIfExists('talent_onboarding_documents');
        Schema::dropIfExists('talent_onboarding_tasks');
        Schema::dropIfExists('talent_onboarding_journeys');
    }
};
