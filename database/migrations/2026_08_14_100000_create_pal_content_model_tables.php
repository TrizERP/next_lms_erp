<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New PAL → Content Model — overlay schema.
 *
 * The Content Model is PROJECTED from `semantic_intelligence` on every request;
 * it is never copied. These three tables hold only what a projection cannot:
 *
 *   pal_cm_node_overrides   human/AI edits layered on top of a projected node
 *   pal_cm_node_revisions   the version history behind those edits (spec §9.1)
 *   pal_cm_enrichment       cached LLM output for the fields the extraction
 *                           does not carry (spec §5.1 standards block)
 *
 * Consequences of that choice, on purpose:
 *   - re-running the extractor updates the Content Model immediately, with no
 *     re-import step and no stale second copy of the curriculum;
 *   - a row here is meaningless on its own, so nothing can be served from the
 *     overlay without the projection agreeing it still exists.
 *
 * Additive only: no existing table is altered. `semantic_intelligence` is read
 * and never written by this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Human / AI edits over a projected node ───────────────────────────
        if (! Schema::hasTable('pal_cm_node_overrides')) {
            Schema::create('pal_cm_node_overrides', function (Blueprint $table) {
                $table->id();

                // Derived key: {prefix}.{semantic_intelligence.id}.{concept-slug}.{discriminator}
                $table->string('node_key', 191);
                $table->unsignedBigInteger('sub_institute_id')->default(0);

                // Denormalised so the review queue can filter without projecting
                // every chapter first. Always re-derivable from node_key.
                $table->unsignedBigInteger('semantic_id')->nullable();
                $table->unsignedBigInteger('chapter_id')->nullable();
                $table->string('concept_slug', 96)->nullable();
                $table->string('content_type', 24)->nullable();

                // Authored body. NULL means "projection wins for this field" —
                // an override never has to restate what it does not change.
                $table->string('title', 512)->nullable();
                $table->longText('body')->nullable();
                $table->text('media_url')->nullable();

                // Partial metadata patch, merged over the derived metadata.
                $table->json('metadata')->nullable();

                // Language variants authored/translated for this node:
                // { "hi": {"title": "...", "body": "...", "source": "llm"} }
                $table->json('language_variants')->nullable();

                $table->string('quality_status', 24)->default('draft');
                $table->string('tagged_by', 16)->default('human');
                $table->double('confidence')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();

                $table->unique(['node_key', 'sub_institute_id'], 'cmno_node_tenant_unique');
                $table->index(['sub_institute_id', 'quality_status'], 'cmno_tenant_status_idx');
                $table->index(['semantic_id', 'concept_slug'], 'cmno_concept_idx');
                $table->index(['content_type', 'quality_status'], 'cmno_type_status_idx');
            });
        }

        // ── Version history (spec §9.1 "every save creates a revision") ──────
        if (! Schema::hasTable('pal_cm_node_revisions')) {
            Schema::create('pal_cm_node_revisions', function (Blueprint $table) {
                $table->id();
                $table->string('node_key', 191);
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->unsignedInteger('version');

                // Full override snapshot AFTER the change, so a restore is a
                // straight write rather than a replay of diffs.
                $table->json('snapshot');
                $table->json('changed_fields')->nullable();
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24)->nullable();
                $table->string('actor_type', 16)->default('human');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['node_key', 'sub_institute_id', 'version'], 'cmnr_version_unique');
                $table->index(['node_key', 'created_at'], 'cmnr_history_idx');
                $table->index(['sub_institute_id', 'created_at'], 'cmnr_tenant_time_idx');
            });
        }

        // ── LLM enrichment cache ────────────────────────────────────────────
        if (! Schema::hasTable('pal_cm_enrichment')) {
            Schema::create('pal_cm_enrichment', function (Blueprint $table) {
                $table->id();
                $table->string('node_key', 191);
                $table->unsignedBigInteger('sub_institute_id')->default(0);

                // cultural_context | framework_tags | variant_draft | translation | authoring_assist
                $table->string('kind', 32);
                // Discriminator inside a kind (e.g. the target language).
                $table->string('variant', 32)->nullable();

                // Hash of the exact input the model saw. A changed source text
                // yields a new fingerprint, so a stale answer can never be served
                // against content it was not computed from.
                $table->string('fingerprint', 64);

                $table->json('payload');
                $table->double('confidence')->nullable();
                $table->string('model', 96)->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->timestamps();

                $table->unique(['node_key', 'kind', 'variant', 'fingerprint', 'sub_institute_id'], 'cmen_lookup_unique');
                $table->index(['sub_institute_id', 'kind'], 'cmen_tenant_kind_idx');
                $table->index(['created_at'], 'cmen_age_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_cm_enrichment');
        Schema::dropIfExists('pal_cm_node_revisions');
        Schema::dropIfExists('pal_cm_node_overrides');
    }
};
