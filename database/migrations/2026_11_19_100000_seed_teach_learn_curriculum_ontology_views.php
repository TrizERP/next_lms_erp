<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds Teach/Learn's own curriculum-structure view — a curated
 * subject -> chapter -> concept chain for the decentralized Knowledge Graph
 * tab, distinct from that tab's existing generic bubble-canvas sample
 * (ModuleKnowledgeGraph.tsx, which already renders real Standard/Subject/
 * Chapter/Concept nodes for this module via GraphModuleBrowser — this view
 * does not replace that, it is read through a separate page the same way
 * the three `student` views and the three `fee-*` views already are).
 *
 * Walks the `contains`/`contains` relations `2026_08_20_000007_seed_core_ontology.php`
 * already registered (subject->chapter, chapter->learning_concept — both
 * `in_graph: true`, HAS_CHAPTER/HAS_CONCEPT), so this adds no new ontology
 * entity or relationship, only the named view over them. Shaped exactly like
 * `2026_11_02_310000_seed_fees_ontology_views.php`: same table, upsert on
 * view_key, baseline-only (sub_institute_id IS NULL) so a tenant override is
 * never touched.
 */
return new class extends Migration
{
    private function views(): array
    {
        return [
            [
                'view_key' => 'curriculum-structure',
                'label' => 'Curriculum structure',
                'module_key' => 'teach_learn',
                'description' => 'The chapters and concepts that make up this subject.',
                'root_entity_key' => 'subject',
                'path' => [
                    ['relation' => 'contains', 'entity' => 'chapter', 'label' => 'Chapters'],
                    ['relation' => 'contains', 'entity' => 'learning_concept', 'label' => 'Concepts'],
                ],
                'max_per_hop' => 15,
                'sort_order' => 10,
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
