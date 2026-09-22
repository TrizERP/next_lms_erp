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
            'from_column' => null,
            'to_column' => 'student_id',
            'traversal_cost' => 1,
            'is_sql_traversable' => true,
            'is_graph_traversable' => false,
            'sort_order' => 1,
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
            'from_column' => null,
            'to_column' => 'student_id',
            'traversal_cost' => 1,
            'is_sql_traversable' => true,
            'is_graph_traversable' => false,
            'sort_order' => 2,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'fee_record',
            'to_entity_key' => 'fee_policy',
            'relation' => 'governed_by',
            'description' => 'A fee record is governed by institute fee policies.',
            'from_column' => null,
            'to_column' => null,
            'traversal_cost' => 2,
            'is_sql_traversable' => false,
            'is_graph_traversable' => false,
            'sort_order' => 3,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'fee_student',
            'to_entity_key' => 'fee_student',
            'relation' => 'same_class',
            'description' => 'Students in the same standard/section.',
            'from_column' => 'standard_id',
            'to_column' => 'id',
            'traversal_cost' => 3,
            'is_sql_traversable' => false,
            'is_graph_traversable' => false,
            'sort_order' => 4,
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
            DB::table('ontology_relationships')->insert($data);
        }
    }
};