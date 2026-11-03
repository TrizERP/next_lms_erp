<?php

use App\Domain\Ontology\OntologyRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_09_16_000001_seed_fees_ontology.php` seeded the four `ontology_entities`
 * rows (`fee_student`/`fee_record`/`fee_payment`/`fee_policy`) correctly, but
 * never actually inserted a single one of its four `ontology_relationships` rows
 * — confirmed live: 0 rows with a `fee_*` entity key exist, despite that
 * migration being recorded as run. Its own comments document two bugs in the
 * insert code that have SINCE been fixed in that file (wrong column names —
 * `is_sql_traversable`/`is_graph_traversable` don't exist; then a missing
 * `relationship_key`, NOT NULL with no default) — but a fix to an
 * already-run migration's code does not get re-applied, so the corrected
 * logic never actually reached the database. `migrate:rollback` on that
 * migration would be the direct fix, but is a shared-resource action outside
 * this session's scope; this inserts the same four rows forward instead,
 * with the now-correct shape, so Fees' audit starts from what the ontology
 * was always meant to contain.
 *
 * Values copied verbatim from that migration's `upsertRelationship()` calls —
 * no new relationships invented, nothing changed about what Fees' ontology
 * was designed to say. All four stay `in_graph=false`; whether any of them
 * should eventually point at Neo4j is this stage's own audit to do, not this
 * migration's.
 */
return new class extends Migration
{
    private const KEYS = ['fee_student', 'fee_record', 'fee_payment', 'fee_policy'];

    public function up(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        $now = now();

        foreach ($this->relationships() as $data) {
            $existing = DB::table('ontology_relationships')
                ->where('from_entity_key', $data['from_entity_key'])
                ->where('to_entity_key', $data['to_entity_key'])
                ->where('relation', $data['relation'])
                ->whereNull('sub_institute_id')
                ->first();

            $data += ['sub_institute_id' => null, 'client_id' => null, 'created_at' => $now, 'updated_at' => $now];

            if ($existing) {
                DB::table('ontology_relationships')->where('id', $existing->id)->update($data);

                continue;
            }

            DB::table('ontology_relationships')->insert($data + [
                'relationship_key' => sprintf('%s_%s_%s', $data['from_entity_key'], $data['relation'], $data['to_entity_key']),
            ]);
        }

        app(OntologyRegistry::class)->flushAll();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->whereIn('from_entity_key', self::KEYS)
            ->whereNull('sub_institute_id')
            ->delete();

        app(OntologyRegistry::class)->flushAll();
    }

    /** Verbatim from 2026_09_16_000001_seed_fees_ontology.php's upsertRelationship() calls. */
    private function relationships(): array
    {
        return [
            [
                'from_entity_key' => 'fee_student',
                'to_entity_key' => 'fee_record',
                'relation' => 'has_fees',
                'description' => 'A student has fee records.',
                'from_column' => 'id',
                'to_column' => 'student_id',
                'traversal_cost' => 1,
                'traversable' => true,
                'in_graph' => false,
                'status' => 1,
            ],
            [
                'from_entity_key' => 'fee_student',
                'to_entity_key' => 'fee_payment',
                'relation' => 'has_payments',
                'description' => 'A student has made fee payments.',
                'from_column' => 'id',
                'to_column' => 'student_id',
                'traversal_cost' => 1,
                'traversable' => true,
                'in_graph' => false,
                'status' => 1,
            ],
            [
                'from_entity_key' => 'fee_record',
                'to_entity_key' => 'fee_policy',
                'relation' => 'governed_by',
                'description' => 'A fee record is governed by institute fee policies.',
                'from_column' => null,
                'to_column' => null,
                'traversal_cost' => 2,
                'traversable' => false,
                'in_graph' => false,
                'status' => 1,
            ],
            [
                'from_entity_key' => 'fee_student',
                'to_entity_key' => 'fee_student',
                'relation' => 'same_class',
                'description' => 'Students in the same standard/section.',
                'from_column' => 'standard_id',
                'to_column' => 'id',
                'traversal_cost' => 3,
                'traversable' => false,
                'in_graph' => false,
                'status' => 1,
            ],
        ];
    }
};
