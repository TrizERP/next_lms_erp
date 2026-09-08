<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content ownership/provenance sidecar.
 *
 * Delivers tracker "Content & LMS Architecture" row 4 and Decision #37:
 * every content item tagged Platform-authored / School-authored / Teacher-authored,
 * so a teacher with creation rights sees default content PLUS their own additions
 * layered on top — never a fork that replaces the default.
 *
 * WHY A SIDECAR, NOT A COLUMN
 * The three content estates are live and large — measured on vivek_erp 2026-09-07:
 *   content_master        31,385 rows
 *   lms_teacher_resource   2,605 rows
 *   lms_question_master   62,487 rows
 * across 56 tenants, and all three are still read by the legacy Blade UI. Adding a
 * column to any of them is the additive-only violation that CONTENT LAW C2
 * (docs/lms-pal-content-intelligence-master-prompt.md §3) exists to prevent.
 *
 * WHY ONE TABLE FOR ALL THREE ESTATES
 * The "3-way split" is three unrelated tables with different columns, controllers
 * and upload paths, and no shared field for ownership, quality or format
 * (docs/decisions/2026-09-07-chapter-resource-split.md). Solving ownership per
 * table means solving it three times and then reconciling three answers.
 *
 * WHY NOT REUSE pal_content_metadata
 * That table is PAL's pedagogy overlay and its quality_status gates DELIVERY TO A
 * LEARNER (CONTENT LAW C4). Ownership is an authoring/governance fact that the
 * Blade content library needs and PAL's delivery engine does not. It is also keyed
 * (content_master_id, sub_institute_id), so it structurally cannot describe the 11
 * live H5P items, which have no content_master row at all.
 *
 * TENANCY IS EXPLICIT (CONTENT LAW C3)
 * owner_sub_institute_id is stored, never inferred. Undecidable tenancy is rejected
 * by ContentProvenanceService rather than defaulted.
 *
 * ROLLBACK
 * down() is correct and is DOCUMENTATION, not a safety net. AppServiceProvider.php:77-88
 * registers a DB::listen that throws on any SQL containing "DROP TABLE", so
 * migrate:rollback aborts partway through. Reversing this migration requires
 * temporarily disabling that guard. Treat this migration as forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_content_provenance')) {
            return;
        }

        Schema::create('lms_content_provenance', function (Blueprint $table) {
            $table->id();

            // ---- what this row describes -------------------------------------
            // entity_type is a key into config('lms_content.entity_types'), which
            // maps it to a physical table. Nothing downstream hardcodes a table name.
            $table->string('entity_type', 24)->comment('content | teacher_resource | question | h5p');
            $table->unsignedBigInteger('entity_id')->comment('primary key in the table entity_type resolves to');

            // ---- tenancy (C3: explicit, never inferred) -----------------------
            // The owning tenant. For platform rows this is the platform tenant id
            // (config lms_content.platform_sub_institute_ids, currently [1]) rather
            // than 0 — there is no sub_institute_id = 0 row in content_master.
            $table->unsignedBigInteger('owner_sub_institute_id');

            // ---- provenance --------------------------------------------------
            $table->string('ownership', 24)->comment('platform | school | teacher');
            $table->unsignedBigInteger('authored_by_user_id')->nullable();
            $table->string('authored_by_profile', 64)->nullable()
                ->comment('snapshot of the profile name at authoring time; profiles get renamed');
            $table->string('authoring_mode', 16)->nullable()->comment('generate | upload | manual | imported');
            $table->string('generation_source', 32)->nullable()
                ->comment('mirrors content_master.source — e.g. Gamma AI, Claude AI, Uploaded');

            // ---- the "layer on top, never a fork" mechanism -------------------
            // Set when a school/teacher item EXTENDS a platform item. The parent is
            // never suppressed: ChapterContentAssembler emits both and nests the
            // derived item under its parent. Written forward by the authoring
            // service only; the backfill always leaves it NULL because a historical
            // derivation cannot be reconstructed after the fact.
            $table->unsignedBigInteger('derived_from_entity_id')->nullable();

            // ---- visibility (NOT a permission — Decision #23) -----------------
            // Can only ever narrow what RBAC already allows, never widen it.
            $table->string('visibility', 16)->default('tenant')->comment('global | tenant | self');
            $table->string('status', 16)->default('active')->comment('active | archived');

            $table->timestamps();

            // One provenance fact per item per owning tenant. Platform content read
            // by many tenants still has exactly ONE row, owned by the platform tenant.
            $table->unique(['entity_type', 'entity_id', 'owner_sub_institute_id'], 'lcp_entity_tenant_unique');

            // The read path: "give me the platform layer plus this tenant's layer
            // for these ids". Ordered to match that predicate.
            $table->index(['entity_type', 'owner_sub_institute_id', 'ownership', 'status'], 'lcp_read_idx');

            // The overlay lookup: "does anything derive from this platform item?"
            $table->index(['entity_type', 'derived_from_entity_id'], 'lcp_derived_idx');
        });
    }

    public function down(): void
    {
        // See the ROLLBACK note in the class docblock: this will throw under the
        // DB::listen guard in AppServiceProvider. Kept correct for the record.
        Schema::dropIfExists('lms_content_provenance');
    }
};
