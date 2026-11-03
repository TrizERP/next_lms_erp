<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the Fees module's own relationship views — the data the decentralized
 * Knowledge Graph tab shows for Fees. Rooted at `fee_student`, the entity
 * `2026_09_16_000001_seed_fees_ontology.php` already registered, walking the
 * `has_fees`/`has_payments`/`governed_by` relations that same migration declared.
 *
 * Shaped exactly like the three `student` views
 * `2026_08_20_000012_seed_ai_workspace_config.php` seeds: same table, upsert on
 * view_key, baseline-only (sub_institute_id IS NULL) so a tenant override is
 * never touched.
 */
return new class extends Migration
{
    private function views(): array
    {
        return [
            [
                'view_key' => 'fee-ledger',
                'label' => 'Fee ledger',
                'module_key' => 'fees',
                'description' => 'The fee records on file for this student.',
                'root_entity_key' => 'fee_student',
                'path' => [
                    ['relation' => 'has_fees', 'entity' => 'fee_record', 'label' => 'Fee records'],
                ],
                'max_per_hop' => 10,
                'sort_order' => 10,
            ],
            [
                'view_key' => 'fee-payments',
                'label' => 'Payment history',
                'module_key' => 'fees',
                'description' => 'Payments this student has made.',
                'root_entity_key' => 'fee_student',
                'path' => [
                    ['relation' => 'has_payments', 'entity' => 'fee_payment', 'label' => 'Payments'],
                ],
                'max_per_hop' => 15,
                'sort_order' => 20,
            ],
            [
                'view_key' => 'fee-policy-trail',
                'label' => 'Fee policy trail',
                'module_key' => 'fees',
                'description' => 'Which policy governs each fee record on file.',
                'root_entity_key' => 'fee_student',
                'path' => [
                    ['relation' => 'has_fees', 'entity' => 'fee_record', 'label' => 'Fee records'],
                    ['relation' => 'governed_by', 'entity' => 'fee_policy', 'label' => 'Governing policy'],
                ],
                'max_per_hop' => 10,
                'sort_order' => 30,
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return;
        }

        $now = now();

        foreach ($this->views() as $view) {
            $this->upsertView([
                'view_key' => $view['view_key'],
                'label' => $view['label'],
                'module_key' => $view['module_key'],
                'description' => $view['description'],
                'root_entity_key' => $view['root_entity_key'],
                'path' => json_encode($view['path']),
                'max_per_hop' => $view['max_per_hop'],
                'allowed_roles' => null,
                'sort_order' => $view['sort_order'],
                'status' => 1,
                'updated_at' => $now,
            ], $now);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return;
        }

        DB::table('ai_ontology_views')
            ->whereIn('view_key', array_column($this->views(), 'view_key'))
            ->whereNull('sub_institute_id')
            ->delete();
    }

    private function upsertView(array $payload, $now): void
    {
        $existing = DB::table('ai_ontology_views')
            ->where('view_key', $payload['view_key'])
            ->whereNull('sub_institute_id')
            ->first();

        if ($existing) {
            DB::table('ai_ontology_views')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('ai_ontology_views')->insert($payload + [
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => $now,
        ]);
    }
};
