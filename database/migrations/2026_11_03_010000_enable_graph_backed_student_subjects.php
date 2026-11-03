<?php

use App\Domain\Ontology\OntologyRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hop 2 of the "student-learning" Knowledge Graph view (`teaches`: Standard ->
 * Subject, labelled "Subjects" in the UI) currently falls back to SQL via a
 * `chapter_master` join — "does this standard have >=1 chapter in this
 * subject", a proxy for "is this subject taught here", not a direct
 * assignment, and not what the relation name implies.
 *
 * Audited live on 2026-11-03: the real, already-synced, already-fully-live
 * Neo4j edge for exactly this fact is `(:Standard)-[:HAS_SUBJECT]->(:Subject)`,
 * sourced from `sub_std_map` (standard_id, subject_id) — already trigger-wired,
 * confirmed 5,815 live edges. `TEACHES` is NOT used here: that type is already
 * taken by the PAL coherence pipeline's unrelated `(:Content)-[:TEACHES]->
 * (:Concept)` (124 live edges) — reusing it for this would collide two
 * different meanings on one relationship type.
 *
 * No new trigger, projection, or backfill needed: `sub_std_map` already feeds
 * HAS_SUBJECT via the existing declarative projection. This migration only
 * repoints the existing `standard_teaches_subject` ontology_relationships row
 * at the real type and flips `in_graph`, the same shape as
 * 2026_11_02_330000_enable_graph_backed_student_enrollment.php did for the
 * hop before it. The ontology's own relation key/label ("teaches"/"Subjects")
 * is left as-is — only the underlying Neo4j wiring changes.
 *
 * FLUSHES ONTOLOGY CACHE. `OntologyRegistry::relationships()` caches
 * `ontology_relationships` for an hour (`Cache::remember`, CACHE_TTL=3600);
 * a plain `DB::table()->update()` like this one does not invalidate it, so
 * without the explicit flush below a flipped `in_graph` can take up to an
 * hour to actually take effect.
 */
return new class extends Migration
{
    private const RELATIONSHIP_KEY = 'standard_teaches_subject';

    public function up(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('relationship_key', self::RELATIONSHIP_KEY)
            ->whereNull('sub_institute_id')
            ->update([
                'graph_relationship_type' => 'HAS_SUBJECT',
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
