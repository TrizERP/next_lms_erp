<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a queued relationship the properties that identify it.
 *
 * -----------------------------------------------------------------------------
 * THE BUG THIS FIXES, AND HOW IT WAS FOUND
 * -----------------------------------------------------------------------------
 * `neo4j_sync_queue` described an edge as (source, type, target) and nothing
 * else, so the drain could only ever write
 *
 *     MERGE (s)-[r:TOOK_LEAVE]->(t)
 *
 * one edge per (person, leave type) pair. The module scripts write
 *
 *     MERGE (person)-[r:TOOK_LEAVE {leaveId: 104882}]->(lt)
 *
 * one edge per leave. The two disagree, and the disagreement is not harmless:
 * on 2026-09-04 a single test leave was inserted and then deleted, and the
 * property-less MERGE matched an EXISTING edge from the bulk load instead of
 * creating its own — so the DELETE removed a real leave that had nothing to do
 * with the test. One row of genuine history, lost to a shape mismatch.
 *
 * With `edge_key` the live sync writes exactly the edge the module script would
 * have written, which is the whole point: the graph should not be able to tell
 * whether a row arrived through the nightly load or through a user clicking
 * save.
 *
 * -----------------------------------------------------------------------------
 * WHY A JSON COLUMN AND NOT MORE COLUMNS
 * -----------------------------------------------------------------------------
 * The identifying set differs per relationship — `{leaveId}` for a leave,
 * `{syear}` for a class-teacher posting, `{syear, division_id}` for a timetable
 * line. Columns would mean a migration per relationship type; a JSON map means
 * the shape lives with the relationship spec in `config/neo4j.php`, next to the
 * endpoints it belongs to.
 *
 * NULL keeps the old behaviour exactly. Every relationship queued before this
 * migration, and every one whose spec declares no key, still MERGEs on
 * (source, type, target) alone — which is correct for an edge that genuinely
 * can only exist once between two nodes, such as (:Question)-[:TAGGED_AS]->().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('neo4j_sync_queue') || Schema::hasColumn('neo4j_sync_queue', 'edge_key')) {
            return;
        }

        Schema::table('neo4j_sync_queue', function (Blueprint $table) {
            // JSON rather than text: the drain decodes it on every row, and a
            // malformed value should fail at write time, not at drain time.
            $table->json('edge_key')->nullable()->after('new_target_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('neo4j_sync_queue') && Schema::hasColumn('neo4j_sync_queue', 'edge_key')) {
            Schema::table('neo4j_sync_queue', function (Blueprint $table) {
                $table->dropColumn('edge_key');
            });
        }
    }
};
