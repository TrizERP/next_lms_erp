<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prerequisite edges between curriculum nodes that are NOT concepts.
 *
 * WHY THIS EXISTS
 * `pal_concept_relations` (2026_08_13_100000) already carries concept -> concept
 * prerequisites, and six services read it: EsoPolicyService's D2 gate,
 * LearnerStateEngine, CoherenceRecommender, CoherenceMapRepository,
 * CoherenceGraphProjection and CoherenceSyncCommand. Its from_concept_id /
 * to_concept_id columns are typed to `lms_concept.id` and commented as such, so a
 * topic -> topic edge cannot be expressed there without overloading the meaning of
 * two columns that six consumers already interpret one way. Measured 2026-09-14,
 * that table holds 1,601 live rows; widening it is a migration of live data that
 * buys nothing the Coherence Map needs.
 *
 * This table is the other half: every prerequisite edge whose endpoints are NOT
 * concepts. It is typed by node kind rather than hardcoded to topics, because the
 * Coherence Map is specified to accept new hierarchy levels without a rebuild -
 * a unit -> unit or chapter -> chapter edge is the same row shape with a different
 * from_node_type. The read service unions this with `pal_concept_relations` and
 * presents one edge list; the write service routes by node type.
 *
 * DIRECTION, WHICH IS NOT THE OBVIOUS ONE
 * Same convention as `pal_concept_relations`, stated at EsoPolicyService.php:960-963:
 * `from` is the node BEING LEARNED and `to` is ITS PREREQUISITE. Read a row as
 * "from requires to". Inverting this silently reverses the entire map, so the two
 * tables deliberately agree rather than each being locally intuitive.
 *
 * QUALITY, NOT TRUTH
 * quality_status mirrors `pal_concept_relations`: 'draft' for anything a machine
 * proposed, 'approved' once a person confirmed it, 'rejected' to remember a human
 * said no so the next AI pass does not re-propose it. tagged_by records WHO
 * proposed it - 'ai', 'structural' (derived from existing curriculum ordering) or
 * 'human'. The map renders draft edges dashed and approved edges solid, so an
 * unreviewed guess is never presented as curriculum fact.
 *
 * WHAT THIS IS DELIBERATELY NOT
 * This is not a replacement for `pal_concept_relations` and nothing here migrates
 * it. It is not a second source of concept edges - a concept -> concept row in this
 * table would be invisible to the six services above, so the write path rejects
 * that combination rather than silently splitting the concept graph in two.
 * It is also not `lms_course_prerequisites`, which links `sub_std_map.id` pairs for
 * the G2G corporate LMS and has no bearing on curriculum sequencing.
 *
 * ROLLBACK
 * down() is correct but is DOCUMENTATION, not a safety net: AppServiceProvider.php:77-90
 * registers a DB::listen that throws on any SQL containing "DROP TABLE". Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pal_learning_relations')) {
            return;
        }

        Schema::create('pal_learning_relations', function (Blueprint $table) {
            $table->id();

            // ---- the edge ------------------------------------------------------
            // Typed endpoints rather than a topic_id pair, so a new hierarchy level
            // is a new value here and not a new table.
            $table->string('from_node_type', 16)->comment('topic|unit|chapter - the node being learned');
            $table->unsignedBigInteger('from_node_id')->comment('topic_master.id | lms_units.id | chapter_master.id, per from_node_type');
            $table->string('to_node_type', 16)->comment('topic|unit|chapter - the prerequisite');
            $table->unsignedBigInteger('to_node_id');

            // Matches pal_concept_relations.relation_type so the merged edge list
            // needs no translation layer.
            $table->string('relation_type', 32)->default('requires')->comment('requires|cross_curricular');

            // ---- tenancy -------------------------------------------------------
            // Never a FK, per the estate convention; 0 means an edge shared across
            // tenants, which is why reads filter on IN (tenant, 0).
            $table->unsignedBigInteger('sub_institute_id')->default(0);
            $table->string('scope', 16)->default('tenant');

            // ---- review state --------------------------------------------------
            $table->string('quality_status', 24)->default('draft')->comment('draft|approved|rejected');
            $table->string('tagged_by', 16)->default('human')->comment('human|ai|structural');

            // Null where the proposer expressed none. Distinct from 0.0, which would
            // mean "proposed and confident it is wrong".
            $table->double('confidence')->nullable();

            $table->string('note', 191)->nullable()->comment('why this edge exists, shown in the inspector');

            $table->timestamps();

            // One edge per (endpoints, type, tenant). The write path relies on this
            // for idempotency: re-drawing the same link updates rather than duplicates.
            $table->unique(
                ['from_node_type', 'from_node_id', 'to_node_type', 'to_node_id', 'relation_type', 'sub_institute_id'],
                'plr_edge_unique'
            );

            // "what depends on this node?" - the downstream half of the map, which
            // has no other index to ride on.
            $table->index(['to_node_type', 'to_node_id', 'relation_type'], 'plr_reverse_idx');

            // The approval queue read: every draft edge for one tenant.
            $table->index(['sub_institute_id', 'quality_status'], 'plr_tenant_status_idx');
        });
    }

    public function down(): void
    {
        // Will throw under the DB::listen guard in AppServiceProvider. See the docblock.
        Schema::dropIfExists('pal_learning_relations');
    }
};
