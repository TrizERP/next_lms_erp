<?php

namespace App\Services\PAL\Pedagogy;

use App\Services\PAL\Framework\FrameworkCatalogService;
use Illuminate\Support\Facades\DB;

/**
 * Executes the Pedagogy Engine rules against a real concept.
 *
 * The rule rows (thresholds, routing, scaffolding) come from
 * pal_pedagogy_engine_rules; the *content* each rule resolves to comes from the
 * selected concept in `semantic_intelligence`. Nothing here is invented: when a
 * concept carries no evidence for a rule, the rule resolves to an empty match
 * and says so.
 *
 * This is the "rule execution" the PAL_V4 comparison sheet lists as missing for
 * Tiers 1-4, the engagement composition and the pedagogy x trigger map.
 */
class PedagogyEngineResolver
{
    public function __construct(
        private readonly FrameworkCatalogService $catalog
    ) {
    }

    /**
     * @param  array<string, mixed>  $concept   normalised concept from SemanticIntelligenceSource
     * @param  array<int, array<string, mixed>>  $ladder  practice-ladder rules
     * @param  array<string, mixed>  $rule      one engine rule row (already shaped by the service)
     * @return array<string, mixed>|null
     */
    public function resolveRule(string $sectionKey, array $rule, array $concept, array $ladder): ?array
    {
        return match ($sectionKey) {
            'tier-1' => $this->resolveTier1($rule, $concept, $ladder),
            'tier-2' => $this->resolveTier2($rule, $concept),
            'tier-3' => $this->resolveTier3($rule, $concept),
            'tier-4' => $this->resolveTier4($rule, $concept),
            'tier-5' => $this->resolveTier5($rule, $concept),
            default => null,
        };
    }

    // ─── Tier 1: mastery band -> real content for this concept ───────────────

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $concept
     * @param  array<int, array<string, mixed>>  $ladder
     * @return array<string, mixed>
     */
    private function resolveTier1(array $rule, array $concept, array $ladder): array
    {
        $meta = $rule['meta'] ?? [];
        $contentType = $meta['content_type'] ?? 'practice';
        $levels = array_map('intval', (array) ($meta['practice_levels'] ?? []));

        $items = [];
        $sources = [];

        if ($contentType === 'concept') {
            // Below the foundation gate the engine serves concept learning, so the
            // served items are the concept definition and its Knowledge-type
            // objectives, plus any mandatory prerequisites that gate entry.
            $definition = (string) ($concept['identity']['definition'] ?? '');
            if ($definition !== '') {
                $items[] = [
                    'label' => (string) ($concept['identity']['concept_name'] ?? 'Concept'),
                    'detail' => $definition,
                    'tags' => array_values(array_filter([
                        $concept['identity']['concept_type'] ?? null,
                        $concept['identity']['difficulty'] ?? null,
                    ])),
                    'from' => 'concepts[].concept',
                ];
                $sources[] = 'concepts[].concept.definition';
            }

            foreach ($this->pick($concept['learning_objectives'], fn ($o) => strcasecmp((string) ($o['objective_type'] ?? ''), 'Knowledge') === 0) as $objective) {
                $items[] = [
                    'label' => (string) ($objective['objective'] ?? ''),
                    'detail' => null,
                    'tags' => array_values(array_filter([$objective['objective_type'] ?? null, $objective['priority'] ?? null])),
                    'from' => 'learning_objectives[]',
                ];
                $sources[] = 'learning_objectives[]';
            }

            foreach ($concept['prerequisites'] as $prerequisite) {
                $items[] = [
                    'label' => (string) ($prerequisite['concept_name'] ?? $prerequisite['value'] ?? ''),
                    'detail' => 'Prerequisite gate',
                    'tags' => array_values(array_filter([$prerequisite['necessity'] ?? null, $prerequisite['prerequisite_type'] ?? null])),
                    'from' => 'prerequisites[]',
                ];
                $sources[] = 'prerequisites[]';
            }
        } elseif ($contentType === 'expanded_task') {
            // At mastery the engine stops serving practice and moves to transfer:
            // real-world applications and cross-concept relationships.
            foreach ($concept['real_world_applications'] as $application) {
                $items[] = [
                    'label' => (string) ($application['example'] ?? $application['application'] ?? ''),
                    'detail' => $application['application_type'] ?? null,
                    'tags' => array_values(array_filter([$application['relevance'] ?? null])),
                    'from' => 'real_world_applications[]',
                ];
                $sources[] = 'real_world_applications[]';
            }
            foreach ($concept['concept_relationships'] as $relationship) {
                $items[] = [
                    'label' => trim(($relationship['source_concept'] ?? '') . ' → ' . ($relationship['target_concept'] ?? '')),
                    'detail' => $relationship['relation_type'] ?? null,
                    'tags' => ['cross-concept'],
                    'from' => 'concept_relationships[]',
                ];
                $sources[] = 'concept_relationships[]';
            }
            foreach ($this->blueprintForLevels($concept, $levels, $ladder) as $row) {
                $items[] = $row;
                $sources[] = 'assessment_blueprint[]';
            }
        } else {
            foreach ($this->blueprintForLevels($concept, $levels, $ladder) as $row) {
                $items[] = $row;
                $sources[] = 'assessment_blueprint[]';
            }
        }

        return $this->resolution(
            $items,
            $sources,
            $this->pedagogyFromConcept($concept, $rule),
            $this->describeTier1($contentType, $levels, $ladder)
        );
    }

    /**
     * Select the concept's real blueprint rows whose Bloom / DOK / difficulty
     * fall inside the ladder levels this mastery band serves.
     *
     * @param  array<string, mixed>  $concept
     * @param  array<int, int>  $levels
     * @param  array<int, array<string, mixed>>  $ladder
     * @return array<int, array<string, mixed>>
     */
    private function blueprintForLevels(array $concept, array $levels, array $ladder): array
    {
        if ($levels === []) {
            return [];
        }

        $blooms = [];
        $dok = [];
        foreach ($ladder as $step) {
            if (in_array((int) ($step['meta']['level'] ?? 0), $levels, true)) {
                $blooms = array_merge($blooms, array_map('strtolower', (array) ($step['meta']['bloom_levels'] ?? [])));
                $dok = array_merge($dok, array_map('strval', (array) ($step['meta']['dok_levels'] ?? [])));
            }
        }

        $rows = [];
        foreach ($concept['assessment_blueprint'] as $item) {
            $bloomMatch = in_array(strtolower((string) ($item['bloom_level'] ?? '')), $blooms, true);
            $dokMatch = in_array((string) ($item['dok_level'] ?? ''), $dok, true);
            if (! $bloomMatch && ! $dokMatch) {
                continue;
            }

            $rows[] = [
                'label' => (string) ($item['recommended_question'] ?? $item['assessment_type'] ?? ''),
                'detail' => $item['assessment_type'] ?? null,
                'tags' => array_values(array_filter([
                    isset($item['bloom_level']) ? 'Bloom: ' . $item['bloom_level'] : null,
                    isset($item['dok_level']) ? 'DOK: ' . $item['dok_level'] : null,
                    $item['difficulty'] ?? null,
                    isset($item['marks']) ? $item['marks'] . ' marks' : null,
                ])),
                'from' => 'assessment_blueprint[]',
                'matched_on' => $bloomMatch ? 'bloom_level' : 'dok_level',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ladder
     */
    private function describeTier1(string $contentType, array $levels, array $ladder): string
    {
        if ($contentType === 'concept') {
            return 'Serves the concept definition, its Knowledge-type objectives and prerequisite gates from the extracted record.';
        }

        $names = [];
        foreach ($ladder as $step) {
            if (in_array((int) ($step['meta']['level'] ?? 0), $levels, true)) {
                $names[] = 'L' . $step['meta']['level'] . ' ' . $step['action'];
            }
        }

        $ladderText = $names === [] ? '' : ' (' . implode(', ', $names) . ')';

        return $contentType === 'expanded_task'
            ? 'Serves real-world applications, cross-concept links and the highest-Bloom blueprint rows' . $ladderText . '.'
            : 'Selects the extracted assessment_blueprint rows matching' . ($ladderText ?: ' this band') . '.';
    }

    // ─── Tier 2: engagement overrides, resolved to real content ──────────────

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private function resolveTier2(array $rule, array $concept): array
    {
        $items = [];
        $sources = [];
        $note = '';

        switch ($rule['id']) {
            case 'engagement-below-50':
                // The injected game node is built from the concept's own knowledge
                // items -- a memory/crossword deck of the facts it must retain.
                $note = 'A game node is built from this concept\'s knowledge items rather than generic filler.';
                foreach ($concept['knowledge_items'] as $item) {
                    $items[] = ['label' => (string) ($item['knowledge'] ?? $item['value'] ?? ''), 'detail' => null, 'tags' => ['game deck'], 'from' => 'knowledge_items[]'];
                    $sources[] = 'knowledge_items[]';
                }
                break;

            case 'engagement-7d-decline':
                $note = 'The pedagogy to fall back to is the concept\'s highest-ranked extracted recommendation.';
                foreach ($concept['pedagogy_recommendations'] as $recommendation) {
                    $items[] = [
                        'label' => (string) ($recommendation['strategy'] ?? ''),
                        'detail' => $recommendation['why_effective'] ?? null,
                        'tags' => array_values((array) ($recommendation['concept_characteristics'] ?? [])),
                        'from' => 'pedagogy_recommendations[]',
                    ];
                    $sources[] = 'pedagogy_recommendations[]';
                }
                break;

            case 'session-over-30-min':
            case 'return-streak-zero':
                $note = 'The low-stakes re-entry deck is drawn from this concept\'s knowledge items and Knowledge-type objectives.';
                foreach ($concept['knowledge_items'] as $item) {
                    $items[] = ['label' => (string) ($item['knowledge'] ?? $item['value'] ?? ''), 'detail' => null, 'tags' => ['spaced review'], 'from' => 'knowledge_items[]'];
                    $sources[] = 'knowledge_items[]';
                }
                break;

            case 'flow-state':
                // "Most intellectually demanding available content" is a real
                // lookup: the top-Bloom blueprint rows this concept actually has.
                $note = 'The most demanding content available for this concept, by Bloom level.';
                foreach ($this->pick($concept['assessment_blueprint'], fn ($b) => in_array(strtolower((string) ($b['bloom_level'] ?? '')), ['analyze', 'evaluate', 'create'], true)) as $item) {
                    $items[] = [
                        'label' => (string) ($item['recommended_question'] ?? $item['assessment_type'] ?? ''),
                        'detail' => $item['assessment_type'] ?? null,
                        'tags' => array_values(array_filter([$item['bloom_level'] ?? null, $item['difficulty'] ?? null])),
                        'from' => 'assessment_blueprint[]',
                    ];
                    $sources[] = 'assessment_blueprint[]';
                }
                break;
        }

        return $this->resolution($items, $sources, $this->pedagogyFromConcept($concept, $rule), $note);
    }

    // ─── Tier 3: the concept's actual misconceptions ─────────────────────────

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private function resolveTier3(array $rule, array $concept): array
    {
        $items = [];
        $sources = [];
        $note = '';

        switch ($rule['id']) {
            case 'misconception-confirmed':
            case 'class-wide-misconception':
                $note = 'Each row is a misconception the extraction actually found for this concept, with its stored root cause and correction.';
                foreach ($concept['misconceptions'] as $misconception) {
                    $items[] = [
                        'label' => (string) ($misconception['misconception'] ?? ''),
                        'detail' => (string) ($misconception['correction'] ?? $misconception['statement'] ?? ''),
                        'tags' => array_values(array_filter([
                            isset($misconception['root_cause']) ? 'Root cause: ' . $misconception['root_cause'] : null,
                        ])),
                        'from' => 'misconceptions[]',
                    ];
                    $sources[] = 'misconceptions[]';
                }
                break;

            case 'no-misconception-match':
            case 'fast-and-wrong':
                $note = 'With no pattern match the engine re-serves the concept through a different extracted pedagogy variant.';
                foreach ($concept['pedagogy_recommendations'] as $recommendation) {
                    $items[] = [
                        'label' => (string) ($recommendation['strategy'] ?? ''),
                        'detail' => $recommendation['why_effective'] ?? null,
                        'tags' => ['concept variant'],
                        'from' => 'pedagogy_recommendations[]',
                    ];
                    $sources[] = 'pedagogy_recommendations[]';
                }
                break;

            case 'correct-high-latency':
                $note = 'Fluency drilling targets the concept\'s extracted knowledge items.';
                foreach ($concept['knowledge_items'] as $item) {
                    $items[] = ['label' => (string) ($item['knowledge'] ?? $item['value'] ?? ''), 'detail' => null, 'tags' => ['fluency'], 'from' => 'knowledge_items[]'];
                    $sources[] = 'knowledge_items[]';
                }
                break;

            case 'fast-and-right':
                $note = 'Escalates to the concept\'s highest-Bloom blueprint rows.';
                foreach ($this->pick($concept['assessment_blueprint'], fn ($b) => in_array(strtolower((string) ($b['bloom_level'] ?? '')), ['analyze', 'evaluate', 'create'], true)) as $item) {
                    $items[] = [
                        'label' => (string) ($item['recommended_question'] ?? $item['assessment_type'] ?? ''),
                        'detail' => $item['assessment_type'] ?? null,
                        'tags' => array_values(array_filter([$item['bloom_level'] ?? null, $item['difficulty'] ?? null])),
                        'from' => 'assessment_blueprint[]',
                    ];
                    $sources[] = 'assessment_blueprint[]';
                }
                break;
        }

        return $this->resolution($items, $sources, $this->pedagogyFromConcept($concept, $rule), $note);
    }

    // ─── Tier 4: modality fit against what the concept actually offers ───────

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private function resolveTier4(array $rule, array $concept): array
    {
        $priority = array_map('strval', (array) ($rule['h5p_priority'] ?? []));
        $items = [];
        $sources = [];

        // Which of the concept's own extracted pedagogies can deliver in this
        // modality, judged by the H5P types the framework catalog allows them.
        foreach ($concept['pedagogy_recommendations'] as $recommendation) {
            $tag = $this->toPedagogyTag((string) ($recommendation['strategy'] ?? ''));
            if ($tag === null) {
                continue;
            }
            $allowed = array_map('strval', $this->catalog->allowedH5pForPedagogy($tag));
            $overlap = array_values(array_intersect($allowed, $priority));

            if ($overlap === [] && $priority !== []) {
                continue;
            }

            $items[] = [
                'label' => (string) ($recommendation['strategy'] ?? ''),
                'detail' => $recommendation['why_effective'] ?? null,
                'tags' => array_merge([$tag], $overlap),
                'from' => 'pedagogy_recommendations[]',
                'matched_on' => $overlap === [] ? null : 'h5p_priority',
            ];
            $sources[] = 'pedagogy_recommendations[]';
        }

        $note = $priority === []
            ? 'Context rule: applies to delivery rather than to a modality preference.'
            : 'Extracted pedagogies for this concept whose allowed H5P types intersect this modality\'s priority list.';

        return $this->resolution($items, $sources, $this->pedagogyFromConcept($concept, $rule), $note);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private function resolveTier5(array $rule, array $concept): array
    {
        $items = [];
        $sources = [];

        if (in_array($rule['id'], ['spaced-review-due', 'low-self-confidence'], true)) {
            foreach ($concept['knowledge_items'] as $item) {
                $items[] = ['label' => (string) ($item['knowledge'] ?? $item['value'] ?? ''), 'detail' => null, 'tags' => ['review'], 'from' => 'knowledge_items[]'];
                $sources[] = 'knowledge_items[]';
            }
        }

        if ($rule['id'] === 'first-session-ever') {
            foreach (array_slice($concept['assessment_blueprint'], 0, 5) as $item) {
                $items[] = [
                    'label' => (string) ($item['recommended_question'] ?? $item['assessment_type'] ?? ''),
                    'detail' => 'Diagnostic item',
                    'tags' => array_values(array_filter([$item['bloom_level'] ?? null, $item['difficulty'] ?? null])),
                    'from' => 'assessment_blueprint[]',
                ];
                $sources[] = 'assessment_blueprint[]';
            }
        }

        return $this->resolution($items, $sources, $this->pedagogyFromConcept($concept, $rule), '');
    }

    // ─── Pedagogy x Trigger map: which triggers this concept actually fires ──

    /**
     * @param  array<int, array<string, mixed>>  $triggers
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    public function resolveTriggers(array $triggers, array $concept): array
    {
        $evidence = [
            'trigger-misconception-confirmed' => ['misconceptions', 'misconceptions[]'],
            'trigger-mastery-above-85' => ['real_world_applications', 'real_world_applications[]'],
            'trigger-spaced-review-due' => ['knowledge_items', 'knowledge_items[]'],
            'trigger-cross-curricular-link' => ['concept_relationships', 'concept_relationships[]'],
            'trigger-riasec-career-signal' => ['real_world_applications', 'real_world_applications[]'],
            'trigger-art-integration' => ['skills', 'skills[]'],
            'trigger-engagement-below-40' => ['knowledge_items', 'knowledge_items[]'],
        ];

        return array_map(function (array $trigger) use ($concept, $evidence) {
            [$key, $path] = $evidence[$trigger['id']] ?? [null, null];
            $rows = $key === null ? [] : ($concept[$key] ?? []);

            $trigger['fires_for_concept'] = $rows !== [];
            $trigger['evidence_count'] = count($rows);
            $trigger['evidence_source'] = $path;
            $trigger['evidence'] = array_map(fn ($row) => [
                'label' => (string) (
                    $row['misconception']
                    ?? $row['example']
                    ?? $row['application']
                    ?? $row['knowledge']
                    ?? $row['skill']
                    ?? $row['relation_type']
                    ?? $row['value']
                    ?? ''
                ),
                'from' => $path,
            ], array_slice($rows, 0, 6));

            return $trigger;
        }, $triggers);
    }

    // ─── Engagement score: real observed session telemetry ───────────────────

    /**
     * The composition weights are engine configuration; the observed values are
     * aggregated from pal_learning_sessions / pal_session_events. Returns nulls
     * (never fabricated numbers) when no sessions exist yet.
     *
     * @return array<string, mixed>
     */
    public function engagementObservations(): array
    {
        $sessions = DB::table('pal_learning_sessions')
            ->selectRaw('COUNT(*) as total, AVG(engagement_score) as avg_score, AVG(duration_minutes) as avg_minutes, AVG(interaction_count) as avg_interactions')
            ->first();

        $total = (int) ($sessions->total ?? 0);

        if ($total === 0) {
            return [
                'has_data' => false,
                'sessions' => 0,
                'note' => 'No PAL learning sessions have been recorded yet, so no engagement score can be computed. The composition below is the formula the engine will apply once telemetry arrives.',
            ];
        }

        $average = $sessions->avg_score !== null ? round((float) $sessions->avg_score, 1) : null;

        return [
            'has_data' => true,
            'sessions' => $total,
            'average_engagement_score' => $average,
            'average_session_minutes' => $sessions->avg_minutes !== null ? round((float) $sessions->avg_minutes, 1) : null,
            'average_interactions' => $sessions->avg_interactions !== null ? round((float) $sessions->avg_interactions, 1) : null,
            'events_recorded' => (int) DB::table('pal_session_events')->count(),
            'source' => 'pal_learning_sessions + pal_session_events',
            'note' => null,
        ];
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    /**
     * The pedagogy the engine would actually pick for this concept: the rule's
     * routing intersected with the concept's extracted recommendations, resolved
     * through the framework catalog so the H5P types come from configuration.
     *
     * @param  array<string, mixed>  $concept
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function pedagogyFromConcept(array $concept, array $rule): array
    {
        $conceptTags = [];
        foreach ($concept['pedagogy_recommendations'] as $recommendation) {
            $strategy = (string) ($recommendation['strategy'] ?? '');
            if ($strategy === '') {
                continue;
            }
            $tag = $this->toPedagogyTag($strategy);
            if ($tag === null) {
                continue;
            }
            $conceptTags[$tag] = $strategy;
        }

        $ruleTags = array_map('strval', (array) ($rule['pedagogy_tags'] ?? []));
        $shared = array_values(array_intersect(array_keys($conceptTags), $ruleTags));
        $selected = $shared[0] ?? ($ruleTags[0] ?? null);

        return [
            'selected' => $selected,
            'selected_h5p' => $selected === null ? [] : array_values($this->catalog->allowedH5pForPedagogy($selected)),
            'rule_routes_to' => $ruleTags,
            'concept_offers' => array_keys($conceptTags),
            'concept_strategies' => array_values($conceptTags),
            'agrees_with_concept' => $shared !== [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $sources
     * @param  array<string, mixed>  $pedagogy
     * @return array<string, mixed>
     */
    private function resolution(array $items, array $sources, array $pedagogy, string $note): array
    {
        $items = array_values(array_filter($items, fn ($item) => trim((string) ($item['label'] ?? '')) !== ''));

        return [
            'matched' => $items !== [],
            'count' => count($items),
            'items' => $items,
            'sources' => array_values(array_unique($sources)),
            'pedagogy' => $pedagogy,
            'note' => $note ?: null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function pick(array $rows, callable $predicate): array
    {
        return array_values(array_filter($rows, $predicate));
    }

    /**
     * Extraction stores prose strategy labels ("Activity Based Teaching"),
     * while the framework catalog keys on canonical tags ("activity_based").
     * Try the label, then progressively drop the trailing descriptor words, and
     * only accept a candidate the catalog actually recognises -- otherwise every
     * unmatched label would silently collapse onto the inquiry_based fallback.
     */
    private function toPedagogyTag(string $strategy): ?string
    {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(trim($strategy)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_diff($tokens, ['teaching', 'learning', 'method', 'methods', 'approach', 'pedagogy']));

        for ($length = count($tokens); $length > 0; $length--) {
            $candidate = implode('_', array_slice($tokens, 0, $length));
            if ($this->catalog->isKnownPedagogy($candidate)) {
                return $this->catalog->normalizePedagogy($candidate);
            }
        }

        return null;
    }
}
