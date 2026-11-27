<?php

namespace App\Domain\KnowledgeGraph\Pal;

use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\Coherence\CoherenceRecommender;
use App\Services\PAL\Coherence\GraphRagRetriever;

/**
 * Turns the Set Coherence Map (Neo4j, PAL's own prerequisite graph) into
 * the Recommendation panel's "Graph Reasoning Intelligence" section —
 * relationships discovered by walking the graph (multi-hop prerequisite
 * closures), never a single direct edge presented as reasoning.
 *
 * Both cards require a standard+subject scope — CoherenceRecommender and
 * CoherenceMapRepository have no tenant-wide "all scopes" method, and
 * inventing a cross-scope aggregate here would mean silently picking one
 * scope on the caller's behalf. Without one, both cards report why rather
 * than guessing.
 *
 * Every card's `evidence.edge_provenance` carries CoherenceRecommender's own
 * disclosure that pal_concept_relations is 100% AI-tagged, quality_status
 * 'draft' — never presented as a verified fact.
 */
class PalGraphReasoningEngine
{
    public function __construct(
        private readonly CoherenceMapRepository $map,
        private readonly CoherenceRecommender $recommender,
        private readonly GraphRagRetriever $ragRetriever,
    ) {
    }

    public function reasoningFor(int $tenant, ?int $learnerId, ?int $standardId, ?int $subjectId): array
    {
        if ($standardId === null || $subjectId === null) {
            return [
                $this->unavailable(
                    'pal.graph_reasoning.next_best_action',
                    'Next best action (graph reasoning)',
                    'A standard and subject must be selected to walk the coherence map — none was given.'
                ),
                $this->unavailable(
                    'pal.graph_reasoning.health',
                    'Coherence map health (root blockers, cycles)',
                    'A standard and subject must be selected to inspect the coherence map — none was given.'
                ),
            ];
        }

        $cards = [$this->healthCard($standardId, $subjectId, $tenant)];

        if ($learnerId !== null) {
            $cards[] = $this->nextBestActionCard($learnerId, $standardId, $subjectId);
        }

        return $cards;
    }

    private function nextBestActionCard(int $learnerId, int $standardId, int $subjectId): array
    {
        $result = $this->recommender->nextBestAction($learnerId, $standardId, $subjectId);
        $why = $result['why'] ?? [];
        $rule = $why['rule'] ?? $result['action'];

        if (in_array($result['action'], ['no_map', 'no_entry_point', 'complete'], true)) {
            return [
                'id' => 'pal.graph_reasoning.next_best_action',
                'title' => 'Next best action (graph reasoning)',
                'category' => 'graph_reasoning',
                'severity' => $result['action'] === 'complete' ? 'positive' : 'info',
                'available' => $result['action'] !== 'no_map' && $result['action'] !== 'no_entry_point',
                'reason' => $why['because'] ?? null,
                'priority' => 'Minor',
                'priority_reason' => 'Informational — nothing actionable to flag for this learner.',
                'evidence' => ['why' => $why],
            ];
        }

        $context = $result['concept'] !== null
            ? $this->ragRetriever->contextFor($learnerId, (int) $result['concept']['id'], $this->resolveTenantForLearner($learnerId))
            : null;

        return [
            'id' => 'pal.graph_reasoning.next_best_action',
            'title' => 'Next best action (graph reasoning)',
            'category' => 'graph_reasoning',
            'severity' => $rule === 'unblock' ? 'high' : 'low',
            'available' => true,
            'reason' => null,
            ...$this->priorityForAction($rule, (int) ($why['depth'] ?? 0), (float) ($why['mastery'] ?? 0), (float) ($why['gate'] ?? 0)),
            'evidence' => [
                'starting_entity' => ['type' => 'learner', 'id' => $learnerId],
                'connected_entities' => $result['path'],
                'relationship_path' => $result['path'],
                'hops' => count($result['path']),
                'concept' => $result['concept'],
                'why' => $why,
                'actual_vs_inferred' => 'The prerequisite edges walked are AI-suggested drafts (tagged_by=\'ai\', quality_status=\'draft\'), not human-approved facts — see edge_provenance.',
                'edge_provenance' => $why['edge_provenance'] ?? null,
                'rag_context' => $context,
            ],
        ];
    }

    private function healthCard(int $standardId, int $subjectId, int $tenant): array
    {
        $health = $this->map->health($standardId, $subjectId, $tenant);

        if ($health['concepts'] === 0) {
            return $this->unavailable(
                'pal.graph_reasoning.health',
                'Coherence map health (root blockers, cycles)',
                'No coherence map is projected for this class and subject yet.'
            );
        }

        $rootRatio = $health['concepts'] > 0 ? $health['roots'] / $health['concepts'] : 0;

        return [
            'id' => 'pal.graph_reasoning.health',
            'title' => 'Coherence map health (root blockers, cycles)',
            'category' => 'graph_reasoning',
            'severity' => ! $health['acyclic'] ? 'high' : ($rootRatio >= 0.3 ? 'medium' : 'low'),
            'available' => true,
            'reason' => null,
            'priority' => ! $health['acyclic']
                ? 'Major'
                : ($rootRatio >= 0.3 ? 'Medium' : 'Minor'),
            'priority_reason' => ! $health['acyclic']
                ? sprintf('%d cycle-involved concept(s) found — a cyclic prerequisite graph breaks the "deepest unmet ancestor" reasoning this section relies on.', count($health['cycles']))
                : sprintf('%.0f%% of concepts are root nodes with no prerequisite — %s.', $rootRatio * 100, $rootRatio >= 0.3 ? 'a high root ratio, worth a curriculum review' : 'within a healthy range'),
            'evidence' => [
                'concepts' => $health['concepts'],
                'roots' => $health['roots'],
                'without_content' => $health['without_content'],
                'without_questions' => $health['without_questions'],
                'acyclic' => $health['acyclic'],
                'cycles' => $health['cycles'],
                'edges' => $health['edges'],
                'isolated' => $health['isolated'],
                'max_depth' => $health['max_depth'],
                'draft_edges' => $health['draft_edges'],
                'actual_vs_inferred' => 'draft_edges counts edges not yet reviewed by curriculum staff — treat this map as AI-suggested, not verified.',
            ],
        ];
    }

    private function priorityForAction(string $rule, int $depth, float $mastery, float $gate): array
    {
        if ($rule === 'unblock' && $depth >= 2) {
            return ['priority' => 'Major', 'priority_reason' => sprintf('The prerequisite chain breaks %d step(s) upstream of what the learner actually attempted.', $depth)];
        }

        if ($rule === 'unblock') {
            return ['priority' => 'Medium', 'priority_reason' => 'The learner is blocked one prerequisite step back.'];
        }

        if ($rule === 'consolidate') {
            $gap = $gate - $mastery;

            return $gap > 0 && $gap <= 0.08
                ? ['priority' => 'Minor', 'priority_reason' => 'Within one or two questions of the gate — healthy forward progress.']
                : ['priority' => 'Medium', 'priority_reason' => 'Part-way through a concept — finishing it is the shorter path.'];
        }

        return ['priority' => 'Minor', 'priority_reason' => 'Advancing to a new, fully-unlocked concept — not a problem to flag.'];
    }

    private function resolveTenantForLearner(int $learnerId): int
    {
        return (int) (\Illuminate\Support\Facades\DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id') ?? 0);
    }

    private function unavailable(string $id, string $title, string $reason): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'category' => 'graph_reasoning',
            'severity' => 'info',
            'available' => false,
            'reason' => $reason,
            'priority' => 'Minor',
            'priority_reason' => 'Not available — see reason.',
            'evidence' => [],
        ];
    }
}
