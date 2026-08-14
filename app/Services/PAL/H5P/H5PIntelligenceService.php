<?php

namespace App\Services\PAL\H5P;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Model intelligence: the reasoning layer on top of the registry, the
 * content repository and the engagement metrics.
 *
 * Three things live here:
 *
 *  1. `chapterModel()` — the complete H5P Model for one chapter. Every node,
 *     with its pedagogy and framework tags, its measured engagement, and where
 *     the chapter's coverage is thin. This is what the H5P Content workspace
 *     renders.
 *
 *  2. `coverage()` — §9 read against real content. The matrix says which
 *     pedagogy generates evidence for which framework tag; this checks which
 *     of those tags the chapter's actual H5P nodes deliver, and names the
 *     specific pedagogy + H5P type that would close each gap.
 *
 *  3. `selectPedagogy()` — §1.3, executed. The rules are rows in the registry,
 *     not an if-ladder in this file, and the learner's engagement ranking comes
 *     from `pal_pedagogy_effectiveness` and `pal_learning_events` — the real
 *     history, or an explicit "no history yet" when there is none.
 */
class H5PIntelligenceService
{
    /** Days of pedagogy history the selector considers (§1.3 uses 30). */
    public const HISTORY_DAYS = 30;

    public function __construct(
        protected H5PModelRegistry $registry,
        protected H5PContentRepository $repository,
        protected H5PTaggingService $tagging,
        protected H5PEngagementService $engagement
    ) {
    }

    /**
     * The whole model for one chapter.
     *
     * @param  array  $context  chapter_id / subject_id / standard_id / sub_institute_id
     * @param  array  $options  ['type' => ?string, 'limit' => int, 'window_days' => ?int]
     */
    public function chapterModel(array $context, array $options = []): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $limit = (int) ($options['limit'] ?? H5PContentRepository::DEFAULT_LIMIT);
        $window = isset($options['window_days']) ? (int) $options['window_days'] : null;

        $inventory = $this->repository->inventory($context);
        $nodes = $this->repository->nodesForContext($context, $options['type'] ?? null, $limit);
        $tags = $this->tagging->tagNodes($nodes, $context);

        $nodeKeys = array_column($nodes, 'node_key');
        $nodeEngagement = $this->engagement->forNodes($nodeKeys, $context, $window);
        $typeEngagement = $this->engagement->forTypes($context, $window);

        $enriched = [];
        foreach ($nodes as $node) {
            $key = $node['node_key'];
            $enriched[] = $node + [
                'model' => $tags[$key] ?? null,
                'engagement' => $nodeEngagement[$key] ?? null,
            ];
        }

        return [
            'context' => $context + $this->repository->resolveContextNames($context),
            'registry_source' => $this->registry->source($tenant),
            'inventory' => $this->shapeInventory($inventory, $typeEngagement, $tenant),
            'nodes' => $enriched,
            'node_count' => count($enriched),
            'truncated' => count($enriched) >= $limit,
            'coverage' => $this->coverage($context, $tags),
            'pedagogy_distribution' => $this->pedagogyDistribution($tags, $tenant),
            'tagging_health' => $this->taggingHealth($tags),
            'telemetry' => $this->engagement->summary($context, $window),
            'ai' => [
                'available' => $this->tagging->aiAvailable(),
                'unavailable_reason' => $this->tagging->aiUnavailableReason(),
            ],
        ];
    }

    /**
     * The hub projection: one card per natively implemented H5P type, with the
     * real node count, the pedagogies that use it and its measured engagement.
     */
    public function hubModules(array $context): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $inventory = $this->repository->inventory($context);
        $engagement = $this->engagement->forTypes($context, null);
        $modules = [];
        $position = 0;

        foreach ($this->registry->nativeTypes($tenant) as $code => $type) {
            $implementation = $type['metadata']['implementation'] ?? [];
            $counts = $inventory[$code] ?? ['nodes' => 0, 'children' => 0, 'child_label' => null, 'available' => false, 'reason' => null];
            $pedagogies = $this->registry->pedagogiesForH5pType($code, $tenant);

            $modules[] = [
                'id' => ++$position,
                'h5p_type' => $code,
                'title' => $implementation['module_title'] ?? $type['label'],
                'description' => $implementation['module_description'] ?? $type['description'],
                'icon' => $implementation['icon'] ?? 'mdi mdi-shape',
                'route' => $implementation['route'] ?? null,
                'node_count' => $counts['nodes'],
                'child_count' => $counts['children'],
                'child_label' => $counts['child_label'],
                'available' => $counts['available'],
                'unavailable_reason' => $counts['reason'],
                'pedagogies' => $pedagogies,
                'bloom_range' => array_values(array_filter([
                    $type['metadata']['bloom_from'] ?? null,
                    $type['metadata']['bloom_to'] ?? null,
                ])),
                'fluency_trackable' => $type['metadata']['fluency_trackable'] ?? 'no',
                'xapi_events' => array_values((array) ($type['metadata']['xapi_events'] ?? [])),
                'engagement' => $engagement[$code] ?? null,
            ];
        }

        return $modules;
    }

    // ── §9 coverage against real content ────────────────────────────────────

    /**
     * Which framework tags this chapter's H5P content actually delivers, and
     * what to author to close each gap.
     *
     * A tag is `covered` when at least one node carries it. A gap names the
     * pedagogy the matrix says would generate that evidence, and the H5P type
     * that pedagogy is authored against — so the recommendation is actionable
     * ("add a Branching Scenario") rather than a scolding ("SEL coverage low").
     *
     * @param  array|null  $tags  pre-computed node tags; re-read when omitted
     */
    public function coverage(array $context, ?array $tags = null): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);

        if ($tags === null) {
            $nodes = $this->repository->nodesForContext($context);
            $tags = $this->tagging->tagNodes($nodes, $context);
        }

        $fields = [
            'casel' => 'casel_domain',
            'ngss' => 'ngss_practice',
            'ncdg' => 'ncdg_goal',
            'music' => 'music_domain',
            'sports' => 'sports_domain',
            'finance' => 'finance_level',
        ];

        $observed = [];
        foreach ($tags as $tag) {
            foreach ($fields as $framework => $field) {
                $value = $tag['values'][$field] ?? null;
                if ($value !== null && $value !== '') {
                    $observed[$framework][$value] = ($observed[$framework][$value] ?? 0) + 1;
                }
            }
        }

        $frameworks = $this->registry->frameworks($tenant);
        $out = [];

        foreach ($frameworks as $framework => $terms) {
            $tagRows = [];
            $coveredCount = 0;

            foreach ($terms as $code => $term) {
                $nodeCount = $observed[$framework][$code] ?? 0;
                $covered = $nodeCount > 0;
                $coveredCount += $covered ? 1 : 0;

                $tagRows[] = [
                    'code' => $code,
                    'label' => $term['label'],
                    'node_count' => $nodeCount,
                    'covered' => $covered,
                    'closes_with' => $covered ? [] : $this->howToCover($framework, $code, $tenant),
                ];
            }

            $total = max(1, count($terms));
            $out[$framework] = [
                'label' => ucfirst($framework),
                'tags' => $tagRows,
                'covered' => $coveredCount,
                'total' => count($terms),
                'ratio' => round($coveredCount / $total, 3),
            ];
        }

        return $out;
    }

    /**
     * The pedagogies §9 says generate evidence for a tag, each paired with the
     * H5P type it is primarily authored against.
     */
    protected function howToCover(string $framework, string $tag, int $tenant): array
    {
        $out = [];
        foreach ($this->registry->pedagogiesForFrameworkTag($framework, $tag, $tenant) as $pedagogy => $strength) {
            $term = $this->registry->pedagogy($pedagogy, $tenant);
            $primary = array_values((array) ($term['metadata']['primary_h5p'] ?? []));

            $out[] = [
                'pedagogy' => $pedagogy,
                'pedagogy_label' => $term['label'] ?? $pedagogy,
                'strength' => $strength,
                'h5p_type' => $primary[0] ?? null,
                'h5p_type_label' => $primary ? ($this->registry->type($primary[0], $tenant)['label'] ?? $primary[0]) : null,
                'implemented' => $primary
                    ? (($this->registry->type($primary[0], $tenant)['metadata']['implementation']['status'] ?? 'planned') === 'native')
                    : false,
            ];
        }

        // A "strong" generator is the recommendation; supporting ones follow.
        usort($out, fn ($a, $b) => [$b['strength'] === 'strong', $b['implemented']] <=> [$a['strength'] === 'strong', $a['implemented']]);

        return $out;
    }

    /** How the chapter's nodes distribute across the 12 pedagogies. */
    protected function pedagogyDistribution(array $tags, int $tenant): array
    {
        $counts = [];
        foreach ($tags as $tag) {
            $pedagogy = $tag['values']['pedagogy_tag'] ?? null;
            if ($pedagogy) {
                $counts[$pedagogy] = ($counts[$pedagogy] ?? 0) + 1;
            }
        }

        $total = array_sum($counts);
        $out = [];
        foreach ($this->registry->pedagogies($tenant) as $code => $term) {
            $count = $counts[$code] ?? 0;
            $out[] = [
                'pedagogy' => $code,
                'label' => $term['label'],
                'node_count' => $count,
                'share' => $total > 0 ? round($count / $total, 3) : 0.0,
                'primary_h5p' => array_values((array) ($term['metadata']['primary_h5p'] ?? [])),
            ];
        }

        usort($out, fn ($a, $b) => $b['node_count'] <=> $a['node_count']);

        return $out;
    }

    /** How much of the chapter is tagged, and by whom. */
    protected function taggingHealth(array $tags): array
    {
        $total = count($tags);
        if ($total === 0) {
            return ['total' => 0, 'stored' => 0, 'derived_only' => 0, 'ai_draft' => 0, 'approved' => 0, 'avg_completeness' => null];
        }

        $stored = 0;
        $aiDraft = 0;
        $approved = 0;
        $completeness = 0.0;

        foreach ($tags as $tag) {
            $completeness += (float) ($tag['completeness'] ?? 0);
            if (($tag['quality_status'] ?? 'untagged') !== 'untagged') {
                $stored++;
            }
            if (($tag['tagged_by'] ?? null) === 'ai' && ($tag['quality_status'] ?? null) === 'draft') {
                $aiDraft++;
            }
            if (($tag['quality_status'] ?? null) === 'approved') {
                $approved++;
            }
        }

        return [
            'total' => $total,
            'stored' => $stored,
            'derived_only' => $total - $stored,
            'ai_draft' => $aiDraft,
            'approved' => $approved,
            'avg_completeness' => round($completeness / $total, 3),
        ];
    }

    /** Inventory joined to the registry so the UI never has to look a type up. */
    protected function shapeInventory(array $inventory, array $engagement, int $tenant): array
    {
        $out = [];
        foreach ($this->registry->types($tenant) as $code => $type) {
            $counts = $inventory[$code] ?? null;
            $implementation = $type['metadata']['implementation'] ?? [];

            $out[] = [
                'h5p_type' => $code,
                'label' => $type['label'],
                'description' => $type['description'],
                'implementation_status' => $implementation['status'] ?? 'planned',
                'route' => $implementation['route'] ?? null,
                'node_count' => $counts['nodes'] ?? 0,
                'child_count' => $counts['children'] ?? 0,
                'child_label' => $counts['child_label'] ?? null,
                'available' => $counts['available'] ?? false,
                'unavailable_reason' => $counts['reason'] ?? null,
                'pal_use_cases' => array_values((array) ($type['metadata']['pal_use_cases'] ?? [])),
                'bloom_from' => $type['metadata']['bloom_from'] ?? null,
                'bloom_to' => $type['metadata']['bloom_to'] ?? null,
                'xapi_events' => array_values((array) ($type['metadata']['xapi_events'] ?? [])),
                'fluency_trackable' => $type['metadata']['fluency_trackable'] ?? 'no',
                'pedagogies' => $this->registry->pedagogiesForH5pType($code, $tenant),
                'engagement' => $engagement[$code] ?? null,
            ];
        }

        return $out;
    }

    // ── §1.3 pedagogy selection, executed ───────────────────────────────────

    /**
     * Select the pedagogy to serve next.
     *
     * The rules are registry rows evaluated in sort order; the first match
     * wins. `trace` records every rule that was considered and why it did or
     * did not fire, so a teacher can be shown the reasoning rather than a
     * bare answer.
     *
     * @param  array  $session  ['type' => ?string, 'engagement_trend' => ?string, 'pedagogy_required' => ?string]
     */
    public function selectPedagogy(?int $learnerId, array $context, array $session = []): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $history = $learnerId ? $this->pedagogyHistory($learnerId, $tenant) : ['ranked' => [], 'scores' => [], 'sample_size' => 0];
        $available = $this->availablePedagogies($context);

        $facts = [
            'concept.pedagogy_required' => $session['pedagogy_required'] ?? null,
            'session.type' => $session['type'] ?? null,
            'session.engagement_trend' => $session['engagement_trend'] ?? null,
            'history.ranked' => $history['ranked'],
        ];

        $trace = [];
        $selected = null;
        $selectedBy = null;

        foreach ($this->registry->selectionRules($tenant) as $code => $rule) {
            $when = (array) ($rule['metadata']['when'] ?? []);
            $then = (array) ($rule['metadata']['then'] ?? []);
            [$matched, $value, $why] = $this->evaluateRule($when, $then, $facts, $available, $tenant);

            $trace[] = [
                'rule' => $code,
                'label' => $rule['label'],
                'matched' => $matched,
                'reason' => $why,
                'would_select' => $value,
            ];

            if ($matched && $value !== null && $selected === null) {
                $selected = $value;
                $selectedBy = $code;
            }
        }

        $term = $selected ? $this->registry->pedagogy($selected, $tenant) : null;

        return [
            'pedagogy' => $selected,
            'label' => $term['label'] ?? null,
            'selected_by_rule' => $selectedBy,
            'h5p_types' => $selected ? $this->registry->h5pTypesForPedagogy($selected, $tenant) : ['primary' => [], 'secondary' => []],
            'available_in_chapter' => $available,
            'history' => $history,
            'trace' => $trace,
        ];
    }

    /**
     * @return array{0:bool,1:?string,2:string} matched, chosen pedagogy, why
     */
    protected function evaluateRule(array $when, array $then, array $facts, array $available, int $tenant): array
    {
        $operator = $when['operator'] ?? 'always';
        $field = $when['field'] ?? null;
        $actual = $field !== null ? ($facts[$field] ?? null) : null;

        $matched = match ($operator) {
            'always' => true,
            'present' => $actual !== null && $actual !== '' && $actual !== [],
            'equals' => $actual !== null && (string) $actual === (string) ($when['value'] ?? ''),
            'any_available' => is_array($actual)
                && array_intersect(array_slice($actual, 0, (int) ($when['value'] ?? 3)), array_keys($available)) !== [],
            default => false,
        };

        if (! $matched) {
            return [false, null, $this->ruleWhy($operator, $field, $actual, $when, false)];
        }

        $use = $then['use'] ?? null;
        $value = null;

        if (is_string($use) && str_starts_with($use, '@')) {
            $resolved = $facts[substr($use, 1)] ?? null;
            if (is_array($resolved)) {
                // Highest-ranked pedagogy that the chapter can actually serve.
                foreach ($resolved as $candidate) {
                    if (isset($available[$candidate])) {
                        $value = $candidate;
                        break;
                    }
                }
            } elseif (is_string($resolved) && $resolved !== '') {
                $value = $this->registry->normalize('pedagogy_tags', $resolved, $tenant);
            }
        } elseif (is_string($use)) {
            $value = $this->registry->normalize('pedagogy_tags', $use, $tenant);
        }

        return [true, $value, $this->ruleWhy($operator, $field, $actual, $when, true)];
    }

    protected function ruleWhy(string $operator, ?string $field, $actual, array $when, bool $matched): string
    {
        return match ($operator) {
            'always' => 'Fallback rule — always applies.',
            'present' => $matched
                ? "{$field} is set."
                : "{$field} is not set.",
            'equals' => $matched
                ? "{$field} is \"{$when['value']}\"."
                : sprintf('%s is %s, not "%s".', $field, $actual === null ? 'unset' : "\"{$actual}\"", $when['value'] ?? ''),
            'any_available' => $matched
                ? 'At least one of the learner\'s top-ranked pedagogies has content in this chapter.'
                : 'None of the learner\'s top-ranked pedagogies has content in this chapter.',
            default => 'Unknown operator — rule skipped.',
        };
    }

    /**
     * The learner's pedagogy engagement ranking (§1.3 step 2–4), from real
     * outcomes. Returns an empty ranking with `sample_size: 0` when the learner
     * has no history — the selector then falls through to the fallback rule
     * rather than ranking noise.
     */
    public function pedagogyHistory(int $learnerId, int $tenant, ?int $days = null): array
    {
        $days = $days ?? self::HISTORY_DAYS;
        $scores = [];
        $counts = [];

        if (Schema::hasTable('pal_pedagogy_effectiveness')) {
            $rows = DB::table('pal_pedagogy_effectiveness')
                ->where('learner_id', $learnerId)
                ->where('created_at', '>=', now()->subDays($days))
                ->whereNotNull('pedagogy_type')
                ->groupBy('pedagogy_type')
                ->selectRaw('pedagogy_type, avg(effectiveness_score) as avg_score, count(*) as n')
                ->get();

            foreach ($rows as $row) {
                $code = $this->registry->normalize('pedagogy_tags', $row->pedagogy_type, $tenant);
                if ($code === null) {
                    continue;
                }
                $scores[$code] = (float) $row->avg_score;
                $counts[$code] = (int) $row->n;
            }
        }

        // Learning events fill in pedagogies the effectiveness table has not
        // seen yet — a completed node is weaker evidence than a scored outcome,
        // so it only contributes where there is nothing better.
        if (Schema::hasTable('pal_learning_events') && Schema::hasColumn('pal_learning_events', 'pedagogy_tag')) {
            $rows = DB::table('pal_learning_events')
                ->where('learner_id', $learnerId)
                ->where('created_at', '>=', now()->subDays($days))
                ->whereNotNull('pedagogy_tag')
                ->groupBy('pedagogy_tag')
                ->selectRaw('pedagogy_tag, avg(score) as avg_score, count(*) as n')
                ->get();

            foreach ($rows as $row) {
                $code = $this->registry->normalize('pedagogy_tags', $row->pedagogy_tag, $tenant);
                if ($code === null || isset($scores[$code])) {
                    continue;
                }
                $scores[$code] = (float) $row->avg_score;
                $counts[$code] = (int) $row->n;
            }
        }

        arsort($scores);

        return [
            'ranked' => array_keys($scores),
            'scores' => array_map(fn (float $score) => round($score, 2), $scores),
            'observations' => $counts,
            'sample_size' => array_sum($counts),
            'window_days' => $days,
        ];
    }

    /**
     * Pedagogies the chapter can actually serve right now: those whose primary
     * or secondary H5P type has at least one node here.
     *
     * @return array<string,array> pedagogy => ['node_count' => int, 'types' => string[]]
     */
    public function availablePedagogies(array $context): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $inventory = $this->repository->inventory($context);

        $out = [];
        foreach ($this->registry->pedagogies($tenant) as $code => $term) {
            $types = array_merge(
                (array) ($term['metadata']['primary_h5p'] ?? []),
                (array) ($term['metadata']['secondary_h5p'] ?? [])
            );

            $nodeCount = 0;
            $withContent = [];
            foreach ($types as $type) {
                $count = $inventory[$type]['nodes'] ?? 0;
                if ($count > 0) {
                    $nodeCount += $count;
                    $withContent[] = $type;
                }
            }

            if ($nodeCount > 0) {
                $out[$code] = ['node_count' => $nodeCount, 'types' => $withContent];
            }
        }

        return $out;
    }
}
