<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the one `ontology_relationships` row already marked `in_graph = true`
 * (seeded in 2026_08_20_000007_seed_core_ontology.php) — its
 * `graph_relationship_type` was `BELONGS_TO`, but that never matches what the
 * live graph actually carries: `(:Chapter)-[:HAS_CONCEPT]->(:Concept)` is the
 * real edge (config/neo4j.php, CoherenceGraphProjection::projectConcepts()).
 * `BELONGS_TO` in this graph means `(:Question)-[:BELONGS_TO]->(:Chapter)` —
 * a different relationship entirely, between different labels.
 *
 * Left uncorrected, GraphQueryService::expandViaGraph() would run a Cypher
 * MATCH for a relationship type that never appears between Chapter and
 * Concept nodes, silently returning zero neighbours instead of the real
 * concept list — indistinguishable from "this chapter has no concepts."
 */
return new class extends Migration
{
    private const RELATIONSHIP_KEY = 'chapter_contains_learning_concept';

    private const WRONG_TYPE = 'BELONGS_TO';

    private const CORRECT_TYPE = 'HAS_CONCEPT';

    public function up(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('relationship_key', self::RELATIONSHIP_KEY)
            ->whereNull('sub_institute_id')
            ->where('graph_relationship_type', self::WRONG_TYPE)
            ->update([
                'graph_relationship_type' => self::CORRECT_TYPE,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('relationship_key', self::RELATIONSHIP_KEY)
            ->whereNull('sub_institute_id')
            ->where('graph_relationship_type', self::CORRECT_TYPE)
            ->update([
                'graph_relationship_type' => self::WRONG_TYPE,
                'updated_at' => now(),
            ]);
    }
};
