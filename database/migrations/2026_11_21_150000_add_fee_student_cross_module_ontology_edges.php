<?php

use App\Domain\Ontology\OntologyRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fee_student`'s only real edges today are `has_fees`->fee_record,
 * `has_payments`->fee_payment, and `enrolled_in_standard`->standard — there
 * is no path from Fees to any other module's entity at all, which blocks
 * any "fee gap correlates with X in another module" query from using
 * GraphQueryService::traverse() (it would have to hand-roll SQL instead).
 *
 * `fee_student` and `student` are two ontology entities over the exact
 * same source table/primary key (`tblstudent`/`id` — confirmed in
 * 2026_08_20_000007_seed_core_ontology.php and
 * 2026_09_16_000001_seed_fees_ontology.php), so `student`'s own working
 * edges can be copied verbatim with `fee_student` as the `from_entity_key`:
 *   - `has_attendance` -> attendance_record (from_column='id', to_column='student_id')
 *   - `produced` -> learning_evidence (from_column='id', to_column='learner_id')
 * Both stay SQL-only (`in_graph` false, no `graph_relationship_type`), same
 * as the originals — no new Neo4j projection is implied by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ontology_relationships') || ! Schema::hasTable('ontology_entities')) {
            return;
        }

        $now = now();

        $this->upsertRelationship([
            'from_entity_key' => 'fee_student',
            'to_entity_key' => 'attendance_record',
            'relation' => 'has_attendance',
            'description' => 'Attendance records for a student with fee records — mirrors the Student module\'s own identical edge.',
            'cardinality' => 'one_to_many',
            'from_column' => 'id',
            'to_column' => 'student_id',
            'traversal_cost' => 1,
            'traversable' => true,
            'in_graph' => false,
            'status' => 1,
            'sub_institute_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $now);

        $this->upsertRelationship([
            'from_entity_key' => 'fee_student',
            'to_entity_key' => 'learning_evidence',
            'relation' => 'produced',
            'description' => 'PAL learning evidence produced by a student with fee records — mirrors the Student module\'s own identical edge.',
            'cardinality' => 'one_to_many',
            'from_column' => 'id',
            'to_column' => 'learner_id',
            'traversal_cost' => 1,
            'traversable' => true,
            'in_graph' => false,
            'status' => 1,
            'sub_institute_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $now);

        app(OntologyRegistry::class)->flushAll();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->where('from_entity_key', 'fee_student')
            ->whereIn('relation', ['has_attendance', 'produced'])
            ->whereNull('sub_institute_id')
            ->delete();

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

        if ($existing) {
            DB::table('ontology_relationships')->where('id', $existing->id)->update($data);

            return;
        }

        DB::table('ontology_relationships')->insert($data + [
            'relationship_key' => sprintf('%s_%s_%s', $data['from_entity_key'], $data['relation'], $data['to_entity_key']),
        ]);
    }
};
