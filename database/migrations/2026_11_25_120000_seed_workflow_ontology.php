<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register the Workflow nav tab's data in the decentralized ontology, so the
 * per-module Knowledge Graph tab's SQL-hybrid traversal (GraphQueryService)
 * knows about it — not just the live Neo4j browser (see the sibling
 * neo4j_sync_trigger_platform_workflows* migrations for that half).
 *
 * Distinct entity_keys from the existing 'workflow' row seeded by
 * 2026_08_20_000007_seed_core_ontology.php — that one maps to
 * `workflow_definitions` (the separate AI/agent execution engine) and is
 * left untouched.
 *
 * Relationship direction matters here: `GraphQueryService::expandViaGraph()`
 * assumes the ontology's from->to direction is the SAME direction the real
 * Neo4j edge was MERGEd in. The live sync (config/neo4j.php) writes
 * (:WorkflowRun)-[:FOR_CHAIN]->(:WorkflowChain) and
 * (:WorkflowRunStep)-[:PART_OF]->(:WorkflowRun), so these relationships are
 * declared run->chain and step->run (not the reverse) and `in_graph` is only
 * set true on rows whose direction actually matches what was synced.
 * `relationshipsTo()` already surfaces the reverse ("a chain's runs", "a
 * run's steps") as incoming edges — no second row needed per edge.
 */
return new class extends Migration
{
    private const ENTITY_KEYS = ['workflow_chain', 'workflow_run', 'workflow_run_step'];

    public function up(): void
    {
        if (! Schema::hasTable('ontology_entities')) {
            return;
        }

        $now = now();
        $instituteId = null;

        $this->upsertEntity([
            'entity_key' => 'workflow_chain',
            'label' => 'Approval Workflow',
            'domain' => 'shared',
            'category' => 'intelligence',
            'description' => 'A configured approval chain for one workflow point (e.g. fees.concession.flow).',
            'source_table' => 'platform_workflows',
            'primary_key_column' => 'id',
            'label_column' => 'name',
            'tenant_column' => 'sub_institute_id',
            'attributes' => [
                ['key' => 'flow_key', 'column' => 'flow_key'],
                ['key' => 'module', 'column' => 'module'],
                ['key' => 'component', 'column' => 'component'],
                ['key' => 'status', 'column' => 'status'],
                ['key' => 'condition', 'column' => 'condition'],
            ],
            'is_virtual' => false,
            'is_tenant_scoped' => true,
            'sort_order' => 281,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertEntity([
            'entity_key' => 'workflow_run',
            'label' => 'Approval Request',
            'domain' => 'shared',
            'category' => 'intelligence',
            'description' => 'One request for sign-off against an approval chain.',
            'source_table' => 'platform_workflow_runs',
            'primary_key_column' => 'id',
            'label_column' => 'title',
            'tenant_column' => 'sub_institute_id',
            'attributes' => [
                ['key' => 'flow_key', 'column' => 'flow_key'],
                ['key' => 'entity_type', 'column' => 'entity_type'],
                ['key' => 'entity_id', 'column' => 'entity_id'],
                ['key' => 'status', 'column' => 'status'],
                ['key' => 'requested_by_name', 'column' => 'requested_by_name'],
            ],
            'is_virtual' => false,
            'is_tenant_scoped' => true,
            'sort_order' => 282,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertEntity([
            'entity_key' => 'workflow_run_step',
            'label' => 'Approval Step',
            'domain' => 'shared',
            'category' => 'intelligence',
            'description' => 'One step of one approval request.',
            'source_table' => 'platform_workflow_run_steps',
            'primary_key_column' => 'id',
            'label_column' => 'step_name',
            // No sub_institute_id column on this table — reached only through
            // its already-scoped parent (workflow_run), same pattern
            // OntologyRegistry documents for lms_online_exam/pal_learning_evidence.
            'tenant_column' => null,
            'attributes' => [
                ['key' => 'approver_type', 'column' => 'approver_type'],
                ['key' => 'approver', 'column' => 'approver'],
                ['key' => 'status', 'column' => 'status'],
                ['key' => 'due_at', 'column' => 'due_at'],
                ['key' => 'on_breach', 'column' => 'on_breach'],
            ],
            'is_virtual' => false,
            'is_tenant_scoped' => false,
            'sort_order' => 283,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'workflow_run',
            'to_entity_key' => 'workflow_chain',
            'relation' => 'for_chain',
            'description' => 'A request for sign-off is against one approval chain.',
            'from_column' => 'workflow_id',
            'to_column' => 'id',
            'cardinality' => 'many_to_one',
            'graph_relationship_type' => 'FOR_CHAIN',
            'traversal_cost' => 1,
            'traversable' => true,
            'in_graph' => true,
            'status' => 1,
            'sub_institute_id' => $instituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->upsertRelationship([
            'from_entity_key' => 'workflow_run_step',
            'to_entity_key' => 'workflow_run',
            'relation' => 'part_of',
            'description' => 'A step belongs to one approval request.',
            'from_column' => 'run_id',
            'to_column' => 'id',
            'cardinality' => 'many_to_one',
            'graph_relationship_type' => 'PART_OF',
            'traversal_cost' => 1,
            'traversable' => true,
            'in_graph' => true,
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
            ->whereIn('from_entity_key', self::ENTITY_KEYS)
            ->delete();

        DB::table('ontology_entities')
            ->whereIn('entity_key', self::ENTITY_KEYS)
            ->delete();
    }

    private function upsertEntity(array $data): void
    {
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
            DB::table('ontology_relationships')->insert($data + [
                'relationship_key' => sprintf('%s_%s_%s', $data['from_entity_key'], $data['relation'], $data['to_entity_key']),
            ]);
        }
    }
};
