<?php

use App\Domain\Ontology\OntologyRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student Evidence (`student-evidence`: Signal -> Case -> Evidence) stays SQL-only,
 * by decision, not by gap.
 *
 * `ai_signals`/`ai_case_signals`/`ai_case_evidence` (via `SignalStore`/`CaseBuilder`/
 * `EvidenceStore`) are the Context Graph half of this platform's own KG/CG split —
 * confirmed in three independent places: `docs/context-graph/KG_CG_USAGE_MAP.md`
 * lists `SignalStore` under CG (MySQL), not KG (Neo4j); Enterprise Brain's own
 * Signal->Evidence->Case->Decision chain is "MySQL projection — not Neo4j, by
 * deliberate standing decision, ADR-008"; `GraphVocabulary.php`'s own docblock says
 * "K-12 has no `intelligence` family... that loop runs over MySQL... not over this
 * Neo4j graph." Adding a Signal/Case/Evidence label to Neo4j here would reintroduce
 * exactly the pattern the same usage map says was "deliberately left disconnected"
 * for G2G's analogous case. So: no new Neo4j label, no new relationship type, no
 * `in_graph` flip — this view is correct as SQL-only and stays that way.
 *
 * Two things this migration DOES fix, within that boundary:
 *
 * 1. A real latent bug in the existing SQL traversal: `student_has_signal_signal`
 *    joins on `ai_signals.subject_id` alone, with no filter on `subject_entity_key`.
 *    `ai_signals` holds both `subject_entity_key='student'` (532 rows) and
 *    `='enquiry'` (41 rows) sharing the same id space — a coincidental id collision
 *    could surface an enquiry's signal as a student's. Fixed generically (not as a
 *    signal-specific hack) via a new `sql_filters` convention on the edge's already-
 *    present, previously-unused `attributes` JSON column, applied by
 *    `GraphQueryService::expandViaSql()`.
 * 2. The decentralized Knowledge Graph tab previously gave no indication that this
 *    view is architecturally SQL-only vs. simply not-yet-synced. Setting the view's
 *    `description` (already plumbed to the frontend, just not yet rendered) gives
 *    `ModuleKnowledgeGraph.tsx` something real to show instead of silence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ontology_relationships')) {
            DB::table('ontology_relationships')
                ->where('relationship_key', 'student_has_signal_signal')
                ->whereNull('sub_institute_id')
                ->update([
                    'attributes' => json_encode(['sql_filters' => ['subject_entity_key' => 'student']]),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('ai_ontology_views')) {
            DB::table('ai_ontology_views')
                ->where('view_key', 'student-evidence')
                ->whereNull('sub_institute_id')
                ->update([
                    'description' => 'This view is maintained in the MySQL domain by architectural decision — Signal, '
                        . 'Case and Evidence data is not part of the Neo4j Knowledge Graph (see '
                        . 'docs/context-graph/KG_CG_USAGE_MAP.md). The data below is real; it is just not graph-sourced.',
                    'updated_at' => now(),
                ]);
        }

        app(OntologyRegistry::class)->flushAll();
    }

    public function down(): void
    {
        if (Schema::hasTable('ontology_relationships')) {
            DB::table('ontology_relationships')
                ->where('relationship_key', 'student_has_signal_signal')
                ->whereNull('sub_institute_id')
                ->update(['attributes' => '[]', 'updated_at' => now()]);
        }

        if (Schema::hasTable('ai_ontology_views')) {
            DB::table('ai_ontology_views')
                ->where('view_key', 'student-evidence')
                ->whereNull('sub_institute_id')
                ->update([
                    'description' => 'The signals raised for this student, the case they opened, and what was recommended.',
                    'updated_at' => now(),
                ]);
        }

        app(OntologyRegistry::class)->flushAll();
    }
};
