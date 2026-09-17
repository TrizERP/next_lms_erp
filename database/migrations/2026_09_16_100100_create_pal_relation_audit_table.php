<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed a prerequisite edge, and how.
 *
 * WHY THIS EXISTS
 * The Coherence Map lets curriculum staff approve, reject, create and delete
 * prerequisite edges directly on the canvas. Those edges are not cosmetic: the
 * `requires` relation is read by EsoPolicyService's D2 prerequisite gate, so
 * approving one can change which content a learner is allowed to reach next.
 * A right that consequential, exercised by a click on a canvas, needs a record of
 * who exercised it.
 *
 * Neither edge table can hold that record. `pal_concept_relations` has only
 * created_at/updated_at and is written by AI tagging passes; `pal_learning_relations`
 * is the same shape by design. Adding actor columns there would answer "who touched
 * it last" and lose everything before that - which is the opposite of an audit. So
 * the history lives here, append-only, one row per action.
 *
 * WHY relation_source IS A STRING AND NOT A FK
 * An edge lives in one of two tables depending on whether its endpoints are concepts
 * (`pal_concept_relations`) or not (`pal_learning_relations`). A single FK cannot
 * point at both, so the pair (relation_source, relation_id) is the address. This is
 * the same sidecar convention `pal_concept_nodes` uses against `lms_concept`.
 *
 * Rows survive the edge they describe: a delete writes an audit row and then removes
 * the edge, so relation_id can dangle by design. Do not add a FK, and do not cascade.
 *
 * WHAT THIS IS DELIBERATELY NOT
 * This is not the platform audit trail and does not attempt to unify with
 * `ai_evidence`, `hpbrain_evidence` or the other evidence tables. It records one
 * narrow thing - edits to curriculum prerequisite edges - and is scoped to the
 * Coherence Map's write API.
 *
 * ROLLBACK
 * down() is correct but is DOCUMENTATION, not a safety net: AppServiceProvider.php:77-90
 * registers a DB::listen that throws on any SQL containing "DROP TABLE". Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_relation_audit')) {
            return;
        }

        Schema::create('pal_relation_audit', function (Blueprint $table) {
            $table->id();

            // ---- which edge ----------------------------------------------------
            $table->string('relation_source', 24)->comment('concept -> pal_concept_relations | learning -> pal_learning_relations');
            $table->unsignedBigInteger('relation_id')->comment('id in that table; may dangle after a delete, by design');

            // Denormalised so a deleted edge is still readable from the audit alone.
            $table->string('from_ref', 64)->nullable()->comment('e.g. concept:6512, as shown on the map');
            $table->string('to_ref', 64)->nullable();
            $table->string('relation_type', 32)->nullable();

            // ---- what happened -------------------------------------------------
            $table->string('action', 16)->comment('created|approved|rejected|deleted');
            $table->string('previous_status', 24)->nullable()->comment('null for created');
            $table->string('new_status', 24)->nullable()->comment('null for deleted');

            // ---- who ------------------------------------------------------------
            // From the verified JWT, never from request input.
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->default(0);

            $table->string('note', 191)->nullable();

            $table->timestamp('created_at')->nullable();

            // The per-edge history read.
            $table->index(['relation_source', 'relation_id'], 'pra_relation_idx');

            // "what did this institute change, most recent first" - the review screen.
            $table->index(['sub_institute_id', 'created_at'], 'pra_tenant_time_idx');
        });
    }

    public function down(): void
    {
        // Will throw under the DB::listen guard in AppServiceProvider. See the docblock.
        Schema::dropIfExists('pal_relation_audit');
    }
};
