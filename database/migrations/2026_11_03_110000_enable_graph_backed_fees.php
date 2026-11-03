<?php

use App\Domain\Ontology\OntologyRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fees Knowledge Graph pass (2026-11-03) — wires the two newly-backfilled,
 * newly-synced labels (`:FeeRecord`, `:FeePayment`) into the ontology so the
 * decentralized Fees tab reads real Neo4j data instead of an honest empty
 * state or a SQL-rendered stand-in.
 *
 * `has_payments` (fee_student -> fee_payment) is a straight flip: real FK
 * (`fees_collect.student_id`), real projection (see config/neo4j.php),
 * real backfilled data.
 *
 * `has_fees` (fee_student -> fee_record) cannot become a direct edge at all:
 * `fees_breackoff` has no `student_id` column — it is a per-standard/grade/
 * section/month fee SCHEDULE, not a per-student record (confirmed live via
 * DESCRIBE). The original migration's SQL definition for this edge
 * (`to_column='student_id'`) was wrong from the day it was seeded and was
 * never actually traversable. The real fact is two hops: a student's
 * standard, then that standard's fee schedule — reusing the exact Standard
 * node and ENROLLED_IN edge Student's own `enrolled_in_standard` already
 * uses (same bridge, now shared via `GraphQueryService::FROM_LABEL_OVERRIDE`
 * + the widened `resolveGraphStartIds()` check). `has_fees` itself is
 * retired (`traversable=false`) rather than deleted, so the mistake stays
 * visible in the ontology rather than quietly disappearing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        $now = now();

        // Retire the broken direct edge.
        DB::table('ontology_relationships')
            ->where('relationship_key', 'fee_student_has_fees_fee_record')
            ->whereNull('sub_institute_id')
            ->update([
                'description' => 'Retired 2026-11-03: fees_breackoff has no student_id column — this edge was '
                    . 'never actually traversable. Superseded by fee_student -> standard (enrolled_in_standard, '
                    . 'reused from Student) -> fee_record (has_fee_schedule).',
                'traversable' => false,
                'updated_at' => $now,
            ]);

        // New hop 1: fee_student -> standard, mirroring Student's own real edge exactly.
        $this->upsertRelationship([
            'from_entity_key' => 'fee_student',
            'to_entity_key' => 'standard',
            'relation' => 'enrolled_in_standard',
            'description' => 'The class a student is enrolled in, read from Neo4j where synced (ENROLLED_IN), '
                . 'from tblstudent_enrollment otherwise. Mirrors the Student module\'s own identical edge.',
            'cardinality' => 'many_to_many',
            'from_column' => 'id',
            'join_table' => 'tblstudent_enrollment',
            'join_from_column' => 'student_id',
            'join_to_column' => 'standard_id',
            'to_column' => 'id',
            'graph_relationship_type' => 'ENROLLED_IN',
            'in_graph' => true,
            'traversable' => true,
            'traversal_cost' => 2,
        ], $now);

        // New hop 2: standard -> fee_record, a real direct FK (fees_breackoff.standard_id).
        $this->upsertRelationship([
            'from_entity_key' => 'standard',
            'to_entity_key' => 'fee_record',
            'relation' => 'has_fee_schedule',
            'description' => 'The fee demand schedule(s) that apply to this standard, for this syear.',
            'cardinality' => 'one_to_many',
            'from_column' => 'id',
            'to_column' => 'standard_id',
            'graph_relationship_type' => 'HAS_FEE_SCHEDULE',
            'in_graph' => true,
            'traversable' => true,
            'traversal_cost' => 1,
        ], $now);

        // Flip has_payments: real FK, real projection, real backfilled data.
        DB::table('ontology_relationships')
            ->where('relationship_key', 'fee_student_has_payments_fee_payment')
            ->whereNull('sub_institute_id')
            ->update([
                'graph_relationship_type' => 'MADE_PAYMENT',
                'in_graph' => true,
                'updated_at' => $now,
            ]);

        // Repoint the two views that walk fee_record through the old broken hop.
        $this->repointView('fee-ledger', [
            ['relation' => 'enrolled_in_standard', 'entity' => 'standard', 'label' => 'Class'],
            ['relation' => 'has_fee_schedule', 'entity' => 'fee_record', 'label' => 'Fee records'],
        ]);

        $this->repointView('fee-policy-trail', [
            ['relation' => 'enrolled_in_standard', 'entity' => 'standard', 'label' => 'Class'],
            ['relation' => 'has_fee_schedule', 'entity' => 'fee_record', 'label' => 'Fee records'],
            ['relation' => 'governed_by', 'entity' => 'fee_policy', 'label' => 'Governing policy'],
        ]);

        app(OntologyRegistry::class)->flushAll();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        $now = now();

        DB::table('ontology_relationships')
            ->where('relationship_key', 'fee_student_has_fees_fee_record')
            ->whereNull('sub_institute_id')
            ->update(['description' => 'A student has fee records.', 'traversable' => true, 'updated_at' => $now]);

        DB::table('ontology_relationships')
            ->whereIn('relationship_key', ['fee_student_enrolled_in_standard_standard', 'standard_has_fee_schedule_fee_record'])
            ->whereNull('sub_institute_id')
            ->delete();

        DB::table('ontology_relationships')
            ->where('relationship_key', 'fee_student_has_payments_fee_payment')
            ->whereNull('sub_institute_id')
            ->update(['graph_relationship_type' => null, 'in_graph' => false, 'updated_at' => $now]);

        $this->repointView('fee-ledger', [
            ['relation' => 'has_fees', 'entity' => 'fee_record', 'label' => 'Fee records'],
        ]);

        $this->repointView('fee-policy-trail', [
            ['relation' => 'has_fees', 'entity' => 'fee_record', 'label' => 'Fee records'],
            ['relation' => 'governed_by', 'entity' => 'fee_policy', 'label' => 'Governing policy'],
        ]);

        app(OntologyRegistry::class)->flushAll();
    }

    private function upsertRelationship(array $data, $now): void
    {
        $existing = DB::table('ontology_relationships')
            ->where('from_entity_key', $data['from_entity_key'])
            ->where('to_entity_key', $data['to_entity_key'])
            ->where('relation', $data['relation'])
            ->whereNull('sub_institute_id')
            ->first();

        $data += ['status' => 1, 'sub_institute_id' => null, 'client_id' => null, 'updated_at' => $now];

        if ($existing) {
            DB::table('ontology_relationships')->where('id', $existing->id)->update($data);

            return;
        }

        DB::table('ontology_relationships')->insert($data + [
            'relationship_key' => sprintf('%s_%s_%s', $data['from_entity_key'], $data['relation'], $data['to_entity_key']),
            'created_at' => $now,
        ]);
    }

    private function repointView(string $viewKey, array $path): void
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return;
        }

        DB::table('ai_ontology_views')
            ->where('view_key', $viewKey)
            ->whereNull('sub_institute_id')
            ->update(['path' => json_encode($path), 'updated_at' => now()]);
    }
};
