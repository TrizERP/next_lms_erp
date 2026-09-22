<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The concept -> video registry that fills the content model's variant-2 slot.
 *
 * Why this is its own table rather than a row in `pal_cm_node_overrides`,
 * which already has a `media_url` column:
 *
 *  1. That table is unique on (node_key, sub_institute_id) — exactly ONE row
 *     per node. A search returns several candidates for a teacher to choose
 *     between, and a chapter can hold a dozen videos worth ranking. A
 *     candidate list cannot be represented there at all.
 *  2. Its `node_key` is {prefix}.{semantic_intelligence.id}.{slug}.V2, so it
 *     can only address concepts whose chapter has been through the extractor.
 *     Measured on the live estate: 129 chapters carry concepts, 14 of those
 *     carry video, and only 4 carry both an extraction and video. Addressing
 *     video by node_key would discard 71% of the coverage this exists for.
 *  3. An override is a PATCH OVER A PROJECTION. A video is an asset with its
 *     own provenance (content_master_id, external_id, match_score,
 *     attribution). Storing it there would make merge() misreport what the
 *     projection contains.
 *
 * So the key is `concept_id` — which, unlike content_master.concept_id, is
 * populated here because this table is the thing that populates it.
 *
 * Additive only: no existing table is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_concept_video')) {
            return;
        }

        Schema::create('pal_concept_video', function (Blueprint $table) {
            $table->id();

            // lms_concept, NOT pal_concepts — the two namespaces are
            // deliberately unreconciled and only lms_concept carries the live
            // adaptive pipeline. See App\Models\lms\LmsConceptModel.
            $table->unsignedBigInteger('concept_id');

            // Denormalised so the review queue can filter a chapter without
            // joining every concept first. Always re-derivable from concept_id.
            $table->unsignedBigInteger('chapter_id')->nullable();

            // 0 = shared across tenants; a tenant's own row outranks it.
            $table->unsignedBigInteger('sub_institute_id')->default(0);

            // Where this came from, and who serves it.
            $table->string('source', 24)->default('institute');   // institute|youtube|manual
            $table->string('provider', 24)->nullable();           // upload|youtube|vimeo
            $table->string('external_id', 64)->nullable();        // YouTube videoId; secondary dedupe key
            $table->unsignedBigInteger('content_master_id')->nullable();

            $table->text('media_url');
            $table->text('thumbnail_url')->nullable();
            $table->string('title', 512)->nullable();
            $table->text('description')->nullable();

            // Channel / uploader, shown to the student under the player. A
            // learner handed a third-party video mid-remediation is entitled
            // to know where it came from.
            $table->string('attribution', 191)->nullable();

            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('language', 8)->nullable();

            // The scorer's output at harvest time, plus the human-readable
            // reason the review queue shows so approving is a glance.
            $table->double('match_score')->nullable();
            $table->string('match_reason', 255)->nullable();
            $table->unsignedInteger('rank')->default(0);

            // CONTENT LAW C4 — this column gates delivery. Nothing outside
            // config('pal_content.servable_statuses') reaches a student.
            $table->string('quality_status', 24)->default('draft');
            $table->string('tagged_by', 16)->default('ai');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            // media_url is TEXT, so the unique key needs a prefix length.
            // Raw statement below — Blueprint cannot express one.
            $table->index(['concept_id', 'sub_institute_id', 'quality_status'], 'pcv_serve_idx');
            $table->index(['chapter_id', 'sub_institute_id', 'quality_status'], 'pcv_queue_idx');
            $table->index(['external_id'], 'pcv_external_idx');
        });

        // One row per (concept, tenant, url) — re-harvesting is idempotent,
        // while still allowing the several candidates a reviewer picks from.
        try {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE `pal_concept_video`
                 ADD UNIQUE `pcv_concept_url_unique` (`concept_id`, `sub_institute_id`, `media_url`(191))'
            );
        } catch (\Throwable $e) {
            // A driver that cannot express a prefixed unique key (sqlite, used
            // by the test suite) still gets a correct table — uniqueness is
            // additionally enforced in ConceptVideoLibraryService.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_concept_video');
    }
};
