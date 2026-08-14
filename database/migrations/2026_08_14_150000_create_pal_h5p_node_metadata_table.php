<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAL V4 — H5P node metadata.
 *
 * The PAL tag set for one H5P node: which pedagogy it teaches through, which
 * Bloom level it serves, and which CASEL / NGSS / NCDG / Music / Sports /
 * Finance evidence it generates.
 *
 * Why this is not `pal_content_metadata`: that table is uniquely keyed on
 * `(content_master_id, sub_institute_id)` with a NOT NULL `content_master_id`.
 * It describes rows of the `content_master` estate. An H5P node is a row of
 * `h5p_scenarios` / `h5p_interactive_video` / `h5p_flashcard` / the MCQ slice
 * of `lms_question_master` — it has no content_master row and inventing one to
 * satisfy the key would be exactly the duplication this module must avoid.
 *
 * What this table does NOT store is as important as what it does: no title, no
 * body, no media path, no options, no answers. Every one of those already
 * exists in the source H5P table and is read live through
 * H5PContentRepository. This is an association record and nothing more — one
 * row per (h5p_type, node, tenant), holding tags that exist nowhere else.
 *
 * `quality_status` / `tagged_by` follow CONTENT LAW C5: a machine may write
 * `tagged_by = 'ai'` at `quality_status = 'draft'` and never an approved
 * status. Human review is what promotes a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_h5p_node_metadata')) {
            return;
        }

        Schema::create('pal_h5p_node_metadata', function (Blueprint $table) {
            $table->id();

            // ── Identity: which node, in which tenant ──────────────────────
            $table->string('h5p_type', 48);
            $table->unsignedBigInteger('node_id');
            $table->unsignedBigInteger('sub_institute_id')->default(0);

            // Curriculum keys, carried for scoping and indexing only. Chapter
            // is the reliable join in this estate; concept is recorded when
            // the source row happens to carry one.
            $table->unsignedBigInteger('chapter_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('concept_ref_id')->nullable();

            // ── Pedagogy (§1.2) ───────────────────────────────────────────
            $table->string('pedagogy_tag', 48)->nullable();
            $table->json('pedagogy_secondary')->nullable();

            // ── Bloom ladder / difficulty ─────────────────────────────────
            $table->string('bloom_level', 24)->nullable();
            $table->unsignedTinyInteger('practice_level')->nullable();
            $table->unsignedTinyInteger('difficulty_1_to_5')->nullable();

            // ── Framework evidence (§2–§7) ────────────────────────────────
            $table->string('casel_domain', 48)->nullable();
            $table->string('ngss_practice', 48)->nullable();
            $table->string('ncdg_goal', 16)->nullable();
            $table->string('music_domain', 48)->nullable();
            $table->string('sports_domain', 48)->nullable();
            $table->string('finance_level', 48)->nullable();
            $table->json('gardner_intelligence')->nullable();
            $table->string('riasec_signal', 4)->nullable();
            $table->string('hpc_lens_primary', 24)->nullable();

            // ── Delivery ──────────────────────────────────────────────────
            $table->string('cultural_context', 48)->nullable();
            $table->string('language', 8)->nullable();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            // Overrides the registry default for this node only; null = inherit.
            $table->float('engagement_weight')->nullable();

            // ── Provenance (CONTENT LAW C5) ───────────────────────────────
            $table->string('quality_status', 24)->default('draft');
            $table->string('tagged_by', 16)->default('derived');
            $table->float('confidence')->nullable();
            $table->json('ai_rationale')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['h5p_type', 'node_id', 'sub_institute_id'], 'phnm_node_tenant_unique');
            $table->index(['sub_institute_id', 'chapter_id'], 'phnm_chapter_idx');
            $table->index(['sub_institute_id', 'pedagogy_tag', 'quality_status'], 'phnm_pedagogy_idx');
            $table->index(['sub_institute_id', 'h5p_type', 'quality_status'], 'phnm_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_h5p_node_metadata');
    }
};
