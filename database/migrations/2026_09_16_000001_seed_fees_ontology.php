<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ontology_entities')) {
            return;
        }

        $now = now();
        $instituteId = null;

        $this->upsertEntity([
            'entity_key' => 'fee_student',
            'label' => 'Student with Fees',
            'domain' => 'k12',
            'category' => 'fees',
            'description' => 'A student who has fee records (demands, payments, pending balances) in the fees module.',
            'source_table' => 'tblstudent',
            'primary_key_column' => 'id',
            'label_column' => "CONCAT_WS(' ', first_name, last_name)",
            'tenant_column' => 'sub_institute_id',
            'attributes' => [
                ['key' => 'enrollment_no', 'column' => 'enrollment_no'],
                ['key' => 'status', 'column' => 'status'],
            ],
            'sort_order' => 100,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertEntity([
            'entity_key' => 'fee_record',
            'label' => 'Fee Record',
            'domain' => 'k12',
            'category' => 'fees',
            'description' => 'An individual fee demand/breakoff record for a student — amount, paid, balance, month.',
            'source_table' => 'fees_breackoff',
            'primary_key_column' => 'id',
            'label_column' => 'fee_type_id',
            'tenant_column' => 'sub_institute_id',
            'attributes' => [
                ['key' => 'amount', 'column' => 'amount'],
                ['key' => 'paid_amount', 'column' => 'paid_amount'],
                ['key' => 'balance', 'column' => 'balance'],
                ['key' => 'month_id', 'column' => 'month_id'],
                ['key' => 'syear', 'column' => 'syear'],
            ],
            'sort_order' => 101,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertEntity([
            'entity_key' => 'fee_payment',
            'label' => 'Fee Payment',
            'domain' => 'k12',
            'category' => 'fees',
            'description' => 'A fee payment receipt — amount paid, payment mode, date.',
            'source_table' => 'fees_collect',
            'primary_key_column' => 'id',
            'label_column' => 'receipt_no',
            'tenant_column' => 'sub_institute_id',
            'attributes' => [
                ['key' => 'amount', 'column' => 'amount'],
                ['key' => 'payment_mode', 'column' => 'payment_mode'],
                ['key' => 'receiptdate', 'column' => 'receiptdate'],
                ['key' => 'fine', 'column' => 'fine'],
                ['key' => 'discount', 'column' => 'fees_discount'],
            ],
            'sort_order' => 102,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertEntity([
            'entity_key' => 'fee_policy',
            'label' => 'Fee Policy',
            'domain' => 'k12',
            'category' => 'fees',
            'description' => 'A fee-related policy or rule from the knowledge base — payment rules, due dates, late fees, etc.',
            'source_table' => 'knowledge_base_detail',
            'primary_key_column' => 'id',
            'label_column' => 'title',
            'tenant_column' => 'sub_institute_id',
            'attributes' => [
                ['key' => 'category', 'column' => 'category'],
                ['key' => 'tags', 'column' => 'tags'],
                ['key' => 'content', 'column' => 'content'],
            ],
            'is_virtual' => false,
            'is_tenant_scoped' => true,
            'sort_order' => 103,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'fee_student',
            'to_entity_key' => 'fee_record',
            'relation' => 'has_fees',
            'description' => 'A student has fee records.',
            // Explicit 'id' rather than null: resolveFromValue() treats a null
            // fromColumn as shorthand for "the node's own primary key" and would
            // have worked either way, but RelationshipDefinition::isSqlTraversable()
            // requires fromColumn to be non-null to count the edge as fully
            // configured — with null here this relationship silently traversed as
            // neither SQL- nor graph-traversable. The columns this block used to set
            // here — is_sql_traversable/is_graph_traversable — do not exist on
            // ontology_relationships at all (confirmed: inserting them throws
            // "Unknown column"); the real columns RelationshipDefinition::fromRow()
            // reads are `traversable` and `in_graph`, used below.
            'from_column' => 'id',
            'to_column' => 'student_id',
            'traversal_cost' => 1,
            'traversable' => true,
            'in_graph' => false,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'fee_student',
            'to_entity_key' => 'fee_payment',
            'relation' => 'has_payments',
            'description' => 'A student has made fee payments.',
            // See has_fees above — explicit primary key, not the null shorthand.
            'from_column' => 'id',
            'to_column' => 'student_id',
            'traversal_cost' => 1,
            'traversable' => true,
            'in_graph' => false,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'fee_record',
            'to_entity_key' => 'fee_policy',
            'relation' => 'governed_by',
            // Declared but not yet mapped: fee_record carries no column linking a
            // specific record to a specific fee_policy row, so there is no real join
            // to walk (same pattern as 'department has employee' in the core seed).
            // traversable => false makes that explicit rather than leaving it to be
            // inferred from both columns being null.
            'description' => 'A fee record is governed by institute fee policies.',
            'from_column' => null,
            'to_column' => null,
            'traversal_cost' => 2,
            'traversable' => false,
            'in_graph' => false,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'fee_student',
            'to_entity_key' => 'fee_student',
            'relation' => 'same_class',
            // Declared but not yet mapped: fee_student (tblstudent) has no
            // standard_id column of its own — that lives on tblstudent_enrollment —
            // so from_column names a column the source table doesn't have and the
            // join would silently resolve nothing even if left "traversable".
            // Marked false to say so rather than leave a dead-but-technically-on edge.
            'description' => 'Students in the same standard/section.',
            'from_column' => 'standard_id',
            'to_column' => 'id',
            'traversal_cost' => 3,
            'traversable' => false,
            'in_graph' => false,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ontology_entities') || ! Schema::hasTable('ontology_relationships')) {
            return;
        }

        DB::table('ontology_relationships')
            ->whereIn('from_entity_key', ['fee_student', 'fee_record', 'fee_payment', 'fee_policy'])
            ->delete();

        DB::table('ontology_entities')
            ->whereIn('entity_key', ['fee_student', 'fee_record', 'fee_payment', 'fee_policy'])
            ->delete();
    }

    private function upsertEntity(array $data): void
    {
        // Each entity above declares 'attributes' as a plain PHP array; the query
        // builder can only bind scalars, so it must be encoded before it reaches
        // insert()/update() — the core ontology seed (2026_08_20_000007) does this
        // at its own call site, this file never did.
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $data['attributes'] = json_encode($data['attributes']);
        }

        $key = $data['entity_key'];
        $sub = $data['sub_institute_id'];

        $existing = DB::table('ontology_entities')
            ->where('entity_key', $key)
            ->where('sub_institute_id', $sub ?? null)
            ->first();

        if ($existing) {
            DB::table('ontology_entities')
                ->where('id', $existing->id)
                ->update($data);
        } else {
            DB::table('ontology_entities')->insert($data);
        }
    }

    private function upsertRelationship(array $data): void
    {
        $existing = DB::table('ontology_relationships')
            ->where('from_entity_key', $data['from_entity_key'])
            ->where('to_entity_key', $data['to_entity_key'])
            ->where('relation', $data['relation'])
            ->where('sub_institute_id', $data['sub_institute_id'] ?? null)
            ->first();

        if ($existing) {
            DB::table('ontology_relationships')
                ->where('id', $existing->id)
                ->update($data);
        } else {
            // relationship_key is NOT NULL with no default; this function never set
            // it (the lookup above matches on from/to/relation instead), so every
            // insert here failed before reaching the column-name issues already
            // fixed above. Same derivation as the core ontology seed's own key.
            DB::table('ontology_relationships')->insert($data + [
                'relationship_key' => sprintf('%s_%s_%s', $data['from_entity_key'], $data['relation'], $data['to_entity_key']),
            ]);
        }
    }
};