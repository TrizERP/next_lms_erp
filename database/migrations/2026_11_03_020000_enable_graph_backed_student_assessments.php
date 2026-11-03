<?php

use App\Domain\Ontology\OntologyRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hop 1 of the "student-assessments" Knowledge Graph view (`attempts`:
 * Student -> Assessment) was `in_graph=false` because the real
 * (:Student)-[:ATTEMPTED]->(:Assessment) edge existed for only 330 of the
 * ~295,669 real (enrolment, exam paper) pairs — code-complete and
 * trigger-wired (`ResultGraphProjection`), but never backfilled against the
 * historical `lms_online_exam` table.
 *
 * Backfilled live on 2026-11-03 by re-deriving every distinct
 * (tblstudent_enrollment.id, lms_online_exam.question_paper_id) pair and
 * MERGEing the same edge the existing projection already produces for new
 * attempts — same outbox tables, same relationship type, no new pipeline.
 * Verified: 294,849 live edges after (up from 330); `neo4j:reconcile
 * --relationships` reports this edge SYNCED; a fresh enqueue through the
 * normal ResultGraphProjection->GraphDrain path for an untouched row still
 * produces the edge correctly, so incremental sync for new attempts is
 * unaffected. The ~820-pair shortfall against the full SQL-derived set is
 * pre-existing missing Student/Assessment nodes (not an edge-sync defect;
 * tracked separately, same shape as the already-known node-population gaps
 * `neo4j:reconcile --entity=tblstudent_enrollment --fix` addresses).
 *
 * This also depends on the Student->StuDetail bridging fix in
 * GraphQueryService::resolveGraphStartIds() (2026-11-03) — without it, this
 * hop would resolve zero rows for a student reached by their real person id,
 * exactly as `enrolled_in_standard` did before that fix.
 *
 * FLUSHES ONTOLOGY CACHE. `OntologyRegistry::relationships()` caches
 * `ontology_relationships` for an hour (`Cache::remember`, CACHE_TTL=3600);
 * a plain `DB::table()->update()` like this one does not invalidate it, so
 * without the explicit flush below a flipped `in_graph` can take up to an
 * hour to actually take effect — caught live while verifying this hop (it
 * read stale `in_graph=0` immediately after migrating).
 */
return new class extends Migration
{
    private const RELATIONSHIP_KEY = 'student_attempts_assessment';

    public function up(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('relationship_key', self::RELATIONSHIP_KEY)
            ->whereNull('sub_institute_id')
            ->update([
                'graph_relationship_type' => 'ATTEMPTED',
                'in_graph' => true,
                'updated_at' => now(),
            ]);

        app(OntologyRegistry::class)->flushAll();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('relationship_key', self::RELATIONSHIP_KEY)
            ->whereNull('sub_institute_id')
            ->update([
                'graph_relationship_type' => null,
                'in_graph' => false,
                'updated_at' => now(),
            ]);

        app(OntologyRegistry::class)->flushAll();
    }
};
