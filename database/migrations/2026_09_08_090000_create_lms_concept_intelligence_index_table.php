<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A queryable index over the Concept Intelligence blobs.
 *
 * Delivers the follow-on to tracker "Content & LMS Architecture" row 6 / Decision #38.
 *
 * WHY THIS EXISTS
 * The verification in docs/decisions/2026-09-07-concept-intelligence-evidence-store.md
 * established that `semantic_intelligence` is a chapter-scoped, write-once AI extraction
 * cache: every intelligence field (knowledge, ability, skill, competency, blooms_level,
 * dok, prerequisites, misconceptions, real_world_applications, pedagogy) is an opaque
 * JSON/longtext column. Measured 2026-09-08, those blobs hold 26,893 structured entries
 * across 89 chapters - and not one of them is reachable by a WHERE clause. Asking
 * "which concepts develop competency X?" today means decoding every row in PHP.
 *
 * This table is a DERIVED, FULLY REBUILDABLE projection of those blobs, one row per
 * extracted item. Deleting it loses nothing; `lms:project-concept-intelligence` rebuilds
 * it. `semantic_intelligence` remains the sole source of truth and is never written.
 *
 * WHAT THIS IS DELIBERATELY NOT
 * This is not the Evidence & Context Engine. It has NO learner linkage, NO evidence
 * events, NO write API and NO cross-module ingestion, and it does not attempt to
 * reconcile the seven fragmented evidence tables (`ai_evidence`, `hpbrain_evidence`,
 * `pal_learning_evidence`, ...). That engine is Track D's, is scoped on the Central
 * Engines sheet, and this table is explicitly a read-side convenience beneath it -
 * not a claim on it.
 *
 * ROLLBACK
 * down() is correct but is DOCUMENTATION, not a safety net: AppServiceProvider.php:77-88
 * registers a DB::listen that throws on any SQL containing "DROP TABLE". Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_concept_intelligence_index')) {
            return;
        }

        Schema::create('lms_concept_intelligence_index', function (Blueprint $table) {
            $table->id();

            // ---- provenance of the projection ---------------------------------
            $table->unsignedBigInteger('semantic_id')->comment('semantic_intelligence.id this row was projected from');
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->default(0);

            // ---- what was extracted -------------------------------------------
            $table->string('dimension', 24)
                ->comment('knowledge|ability|skill|competency|bloom|dok|prerequisite|misconception|real_world|pedagogy');

            // The concept this item belongs to. Present on 100% of the 26,893 entries
            // measured, so it is the natural grain - one step finer than chapter.
            $table->string('concept_name', 191)->nullable();

            // Normalised slug, for querying. The human label can be a full sentence
            // (misconception statements run to hundreds of characters), so the two are
            // stored separately: item_key is indexed, item_label is displayed.
            $table->string('item_key', 191);
            $table->text('item_label');

            $table->unsignedInteger('ordinal')->default(0)->comment('position within its blob, so display order survives');

            // Only some dimensions carry one: knowledge has `confidence`, bloom has
            // `coverage_score`. Null means the extractor did not express one - which is
            // different from, and must not be flattened into, a confidence of 0.
            $table->double('confidence')->nullable();

            // Everything else on the entry, preserved verbatim so the projection is
            // lossless and nothing has to be re-read from the blob.
            $table->json('attributes')->nullable();

            // Uniqueness on (dimension, concept_name, item_key) would need a composite
            // index over ~600 bytes of utf8mb4 and still truncate long keys. A hash of
            // the same triple is fixed-width, exact, and index-friendly.
            $table->char('row_hash', 64);

            $table->timestamp('projected_at')->nullable()
                ->comment('stamped per run; rows older than the current run are pruned');

            $table->unique(['semantic_id', 'row_hash'], 'lcii_semantic_row_unique');

            // "which concepts anywhere develop competency X?" - the query that was
            // impossible before this table existed.
            $table->index(['dimension', 'item_key'], 'lcii_dimension_item_idx');

            // The per-chapter read the API serves.
            $table->index(['sub_institute_id', 'chapter_id', 'dimension'], 'lcii_tenant_chapter_idx');

            $table->index(['chapter_id', 'concept_name'], 'lcii_chapter_concept_idx');
        });
    }

    public function down(): void
    {
        // Will throw under the DB::listen guard in AppServiceProvider. See the docblock.
        Schema::dropIfExists('lms_concept_intelligence_index');
    }
};
