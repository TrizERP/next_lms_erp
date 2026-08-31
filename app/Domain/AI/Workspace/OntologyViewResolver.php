<?php

namespace App\Domain\AI\Workspace;

use App\Domain\KnowledgeGraph\GraphNode;
use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Domain\Ontology\OntologyRegistry;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renders a named relationship view as a chain a person can read.
 *
 * "Ontology/KG" as a chatbot tab would be jargon. What a teacher actually wants is
 * "show me how this student connects to their assessments, the evidence, and what was
 * recommended" — which is a walk along edges that already exist in
 * `ontology_relationships`, hop by hop, labelled in plain language.
 *
 * Every hop goes through GraphQueryService, so the tenant filter is re-applied each
 * time and the data is real. A hop with no configured edge is reported as unavailable
 * rather than skipped silently: a gap in the chain is information, and pretending the
 * chain is shorter than configured would misrepresent the model.
 */
class OntologyViewResolver
{
    public function __construct(
        private readonly GraphQueryService $graph,
        private readonly OntologyRegistry $ontology,
    ) {
    }

    /**
     * Walk one view from one record.
     *
     * @return array{
     *   found:bool, view:array|null, root:array|null,
     *   hops:array<int, array>, sources:array<string,int>
     * }
     */
    public function resolve(string $viewKey, AiContext $context): array
    {
        $view = $this->findView($viewKey, $context);

        if (! $view || ! $context->hasEntity()) {
            return $this->empty();
        }

        $path = $this->decode($view->path);

        if ($path === []) {
            return $this->empty();
        }

        if ($context->entityKey !== $view->root_entity_key) {
            // The view is anchored to a different kind of record than the one on
            // screen; offering it here would produce an empty walk.
            return $this->empty();
        }

        $maxPerHop = max(1, min((int) ($view->max_per_hop ?? 10), 50));

        // The frontier: the records the next hop expands from.
        $frontier = [[
            'entity' => $context->entityKey,
            'id' => $context->entityId,
            'label' => $context->entityLabel ?? (string) $context->entityId,
        ]];

        $hops = [];
        $sources = ['sql' => 0, 'graph' => 0];

        foreach ($path as $index => $step) {
            if (! is_array($step) || empty($step['relation'])) {
                continue;
            }

            $relation = (string) $step['relation'];
            $targetEntity = isset($step['entity']) ? (string) $step['entity'] : null;
            $label = $step['label'] ?? $this->humanize($targetEntity ?? $relation);

            $edge = $targetEntity
                ? $this->ontology->findEdge(
                    $frontier[0]['entity'] ?? '',
                    $targetEntity,
                    $relation,
                    $context->scope->selectedInstituteId
                )
                : null;

            // Configured but unmapped, or declared non-traversable. Say so.
            if ($edge === null || ! ($edge->isSqlTraversable() || $edge->isGraphTraversable())) {
                $hops[] = [
                    'label' => $label,
                    'entity' => $targetEntity,
                    'relation' => $relation,
                    'available' => false,
                    'reason' => 'This relationship is not mapped in the current data model.',
                    'total' => 0,
                    'items' => [],
                ];

                break;
            }

            $collected = [];

            foreach (array_slice($frontier, 0, 5) as $node) {
                if ($node['id'] === null) {
                    continue;
                }

                $neighbours = $this->graph->neighbours(
                    (string) $node['entity'],
                    $node['id'],
                    $relation,
                    $context->scope,
                    $maxPerHop
                );

                foreach ($neighbours as $neighbour) {
                    $collected[$neighbour->fingerprint()] = $neighbour;
                }
            }

            $items = array_values($collected);

            foreach ($items as $item) {
                $sources[$item->source] = ($sources[$item->source] ?? 0) + 1;
            }

            $hops[] = [
                'label' => $label,
                'entity' => $targetEntity,
                'relation' => $relation,
                'available' => true,
                'total' => count($items),
                'source' => $items === [] ? null : $items[0]->source,
                'items' => array_map(
                    fn (GraphNode $node) => [
                        'entity' => $node->entityKey,
                        'id' => $node->id,
                        'label' => $node->label,
                        'attributes' => $node->attributes,
                    ],
                    array_slice($items, 0, $maxPerHop)
                ),
            ];

            if ($items === []) {
                // Nothing to expand from; later hops would be empty by construction.
                break;
            }

            $frontier = array_map(
                fn (GraphNode $node) => [
                    'entity' => $node->entityKey,
                    'id' => $node->id,
                    'label' => $node->label,
                ],
                array_slice($items, 0, 5)
            );
        }

        return [
            'found' => true,
            'view' => [
                'key' => $view->view_key,
                'label' => $view->label,
                'description' => $view->description,
                'root_entity_key' => $view->root_entity_key,
            ],
            'root' => [
                'entity' => $context->entityKey,
                'id' => $context->entityId,
                'label' => $context->entityLabel,
            ],
            'hops' => $hops,
            'sources' => $sources,
        ];
    }

    /**
     * The views available for a context, without walking them — used to render the
     * tab before the user picks one.
     */
    public function available(AiContext $context): array
    {
        if (! Schema::hasTable('ai_ontology_views') || ! $context->hasEntity()) {
            return [];
        }

        return DB::table('ai_ontology_views')
            ->where('status', 1)
            ->where('root_entity_key', $context->entityKey)
            ->where(function ($inner) use ($context) {
                $inner->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $context->scope->selectedInstituteId);
            })
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->view_key,
                'label' => $row->label,
                'description' => $row->description,
                'steps' => array_map(
                    fn ($step) => $step['label'] ?? $this->humanize($step['entity'] ?? ''),
                    $this->decode($row->path)
                ),
            ])
            ->values()
            ->all();
    }

    private function findView(string $viewKey, AiContext $context): ?object
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return null;
        }

        return DB::table('ai_ontology_views')
            ->where('view_key', $viewKey)
            ->where('status', 1)
            ->where(function ($inner) use ($context) {
                $inner->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $context->scope->selectedInstituteId);
            })
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->first();
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    private function empty(): array
    {
        return [
            'found' => false,
            'view' => null,
            'root' => null,
            'hops' => [],
            'sources' => ['sql' => 0, 'graph' => 0],
        ];
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
