<?php

namespace App\Services\PAL\Pedagogy;

use App\Models\PAL\PedagogyEngineModule;
use App\Models\PAL\PedagogyEngineRule;
use App\Models\PAL\PedagogyEngineSection;
use App\Services\PAL\Content\SemanticIntelligenceSource;
use App\Services\PAL\Framework\FrameworkCatalogService;
use Illuminate\Support\Collection;

/**
 * Pedagogy Engine read model.
 *
 * Reads the codified PAL V4 rule set out of `pal_pedagogy_engine_sections` /
 * `pal_pedagogy_engine_rules` and shapes it into the structure the PAL UI
 * renders. All transformation lives here so the frontend only fetches and
 * renders - it never derives, filters or hardcodes engine data.
 */
class PedagogyEngineService
{
    public function __construct(
        private readonly FrameworkCatalogService $catalog,
        private readonly SemanticIntelligenceSource $semantic,
        private readonly PedagogyEngineResolver $resolver
    ) {
    }

    /**
     * Full module payload: every visible section, with each rule executed
     * against the concept selected from `semantic_intelligence`.
     *
     * @param  array{include_hidden?:bool, chapterId?:string|null, concept?:string|null}  $options
     */
    public function getModule(array $options = []): array
    {
        $sections = $this->sectionQuery($options)->get();
        $rulesBySection = $this->rulesFor($sections->pluck('section_key')->all());

        // The concept under evaluation. Everything the UI shows as "served
        // content" is drawn from this record, never from the rule definitions.
        $semantic = $this->semantic->resolve($options['chapterId'] ?? null, $options['concept'] ?? null);
        $concept = $semantic['concept'] ?? null;
        $ladder = $this->ladderRules();

        $payload = $sections
            ->map(fn (PedagogyEngineSection $section) => $this->buildSection(
                $section,
                $rulesBySection->get($section->section_key, collect()),
                $concept,
                $ladder
            ))
            ->values()
            ->all();

        // Header metadata is a database row too -- nothing the UI prints is
        // hardcoded here. A missing row yields nulls, and the UI omits the
        // field rather than substituting invented text.
        $meta = PedagogyEngineModule::query()
            ->where('slug', 'pedagogy-engine')
            ->where('is_active', true)
            ->first();

        return [
            'module' => $meta?->module_name,
            'slug' => $meta?->slug ?? 'pedagogy-engine',
            'title' => $meta?->title,
            'description' => $meta?->description,
            'source' => $meta?->source_label,
            'version' => $meta?->version,
            'context' => $semantic['context'] ?? null,
            'concepts' => $semantic['concepts'] ?? [],
            'has_semantic_data' => $concept !== null,
            'stats' => $this->buildStats($payload),
            'sections' => $payload,
        ];
    }

    /**
     * A single section (tier-1 ... trigger-map) with its rows, resolved against
     * the same concept selection the module endpoint uses.
     */
    public function getSection(string $sectionKey, array $options = []): ?array
    {
        $section = $this->sectionQuery($options + ['include_hidden' => true])
            ->where('section_key', $sectionKey)
            ->first();

        if (! $section) {
            return null;
        }

        $semantic = $this->semantic->resolve($options['chapterId'] ?? null, $options['concept'] ?? null);

        return $this->buildSection(
            $section,
            $this->rulesFor([$sectionKey])->get($sectionKey, collect()),
            $semantic['concept'] ?? null,
            $this->ladderRules()
        ) + [
            'context' => $semantic['context'] ?? null,
            'has_semantic_data' => ($semantic['concept'] ?? null) !== null,
        ];
    }

    /**
     * Extracted chapters available to evaluate against, for the UI selector.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listChapters(): array
    {
        return $this->semantic->listChapters();
    }

    /**
     * Section keys the UI can navigate to, without loading every row.
     */
    public function listSections(array $options = []): array
    {
        return $this->sectionQuery($options)
            ->withCount(['rules as rule_count'])
            ->get()
            ->map(fn (PedagogyEngineSection $section) => [
                'id' => $section->section_key,
                'name' => $section->name,
                'subtitle' => $section->subtitle,
                'type' => $section->section_type,
                'implementation_status' => $section->implementation_status,
                'count' => (int) ($section->rule_count ?? 0),
            ])
            ->values()
            ->all();
    }

    private function sectionQuery(array $options)
    {
        $query = PedagogyEngineSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (empty($options['include_hidden'])) {
            $query->where('ui_visible', true);
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $sectionKeys
     * @return Collection<string, Collection<int, PedagogyEngineRule>>
     */
    private function rulesFor(array $sectionKeys): Collection
    {
        if ($sectionKeys === []) {
            return collect();
        }

        return PedagogyEngineRule::query()
            ->whereIn('section_key', $sectionKeys)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('section_key');
    }

    /**
     * The practice ladder is engine configuration, not a UI section: it is the
     * lookup Tier 1 uses to turn a mastery band into a Bloom / DOK filter over
     * the concept's real assessment_blueprint rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ladderRules(): array
    {
        return $this->rulesFor(['practice-ladder'])
            ->get('practice-ladder', collect())
            ->map(fn (PedagogyEngineRule $rule) => $this->buildRule($rule))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PedagogyEngineRule>  $rules
     * @param  array<string, mixed>|null  $concept
     * @param  array<int, array<string, mixed>>  $ladder
     */
    private function buildSection(
        PedagogyEngineSection $section,
        Collection $rules,
        ?array $concept = null,
        array $ladder = []
    ): array {
        $base = [
            'id' => $section->section_key,
            'name' => $section->name,
            'subtitle' => $section->subtitle,
            'summary' => $section->summary,
            'type' => $section->section_type,
            'badges' => $section->badges ?? [],
            'implementation_status' => $section->implementation_status,
            'current_state' => $section->current_state,
            'gap' => $section->gap,
        ];

        // Execute each rule against the selected concept. `resolved` carries the
        // content the rule would actually serve, drawn from semantic_intelligence.
        $built = $rules
            ->map(function (PedagogyEngineRule $rule) use ($section, $concept, $ladder) {
                $row = $this->buildRule($rule);
                $row['resolved'] = $concept === null
                    ? null
                    : $this->resolver->resolveRule($section->section_key, $row, $concept, $ladder);

                return $this->withConceptContent($row);
            })
            ->values()
            ->all();

        $base['resolved_count'] = count(array_filter($built, fn ($row) => ($row['resolved']['matched'] ?? false) === true));
        $base['evidence_count'] = array_sum(array_map(fn ($row) => (int) ($row['resolved']['count'] ?? 0), $built));

        // The three section shapes the UI renders differently. Keys are additive
        // so a client that only reads `rules` still sees every row.
        return match ($section->section_type) {
            'signals' => $base + [
                'signals' => $rules->where('group_label', 'signal')->map(fn ($rule) => $this->buildSignal($rule))->values()->all(),
                'interpretation' => $rules->where('group_label', 'band')->map(fn ($rule) => $this->buildBand($rule))->values()->all(),
                'formula' => $section->summary,
                'observed' => $this->resolver->engagementObservations(),
                'rules' => $built,
            ],
            'triggers' => $base + [
                'triggers' => array_map(
                    fn (array $trigger) => $this->withTriggerConceptContent($trigger, $built),
                    $this->resolver->resolveTriggers(
                        $rules->map(fn ($rule) => $this->buildTrigger($rule))->values()->all(),
                        $concept ?? []
                    )
                ),
                'rules' => $built,
            ],
            default => $base + ['rules' => $built],
        };
    }

    /**
     * What each extracted collection is called when the Action column counts it.
     *
     * @var array<string, array{0:string, 1:string}>
     */
    private const EVIDENCE_NOUNS = [
        'concepts[].concept' => ['concept definition', 'concept definitions'],
        'learning_objectives[]' => ['learning objective', 'learning objectives'],
        'learning_outcomes[]' => ['learning outcome', 'learning outcomes'],
        'prerequisites[]' => ['prerequisite gate', 'prerequisite gates'],
        'evidence[]' => ['source-evidence extract', 'source-evidence extracts'],
        'knowledge_items[]' => ['knowledge item', 'knowledge items'],
        'abilities[]' => ['ability', 'abilities'],
        'skills[]' => ['skill', 'skills'],
        'competencies[]' => ['competency', 'competencies'],
        'misconceptions[]' => ['misconception', 'misconceptions'],
        'pedagogy_recommendations[]' => ['extracted pedagogy', 'extracted pedagogies'],
        'real_world_applications[]' => ['real-world application', 'real-world applications'],
        'concept_relationships[]' => ['cross-concept link', 'cross-concept links'],
        'assessment_blueprint[]' => ['blueprint item', 'blueprint items'],
        'assessment_rubrics.items[]' => ['scored rubric item', 'scored rubric items'],
        'assessment_rubrics.items[].answer_key[]' => ['rubric distractor', 'rubric distractors'],
        'assessment_rubrics.items[].common_errors[]' => ['recorded common error', 'recorded common errors'],
    ];

    /**
     * Replace the row's content-bearing columns with what the rule resolved to
     * for the selected concept.
     *
     * The condition and the guardrails (scaffolding, trigger, reason, do-not,
     * avoid) are the rule itself and stay as stored; the Action, Pedagogy and
     * H5P columns describe *content*, so they are printed from
     * `semantic_intelligence` instead of the stored specification wording. With
     * no concept resolved the row is left untouched and the UI already says the
     * rules are unresolved.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withConceptContent(array $row): array
    {
        $resolved = $row['resolved'] ?? null;

        if (! is_array($resolved) || ($resolved['matched'] ?? false) !== true) {
            return $row;
        }

        $row['action'] = $this->describeServedContent($resolved) ?? $row['action'];

        return $this->withSelectedPedagogy($row, $resolved['pedagogy'] ?? []);
    }

    /**
     * Print the one pedagogy the engine actually selected, in the concept's own
     * wording when the extraction named it, and that pedagogy's H5P types from
     * the framework catalog.
     *
     * A rule whose pedagogy column is an instruction rather than a tag ("Teacher
     * decides", "Unchanged") resolves to no selection, and is left alone - the
     * instruction is the rule's meaning, not content to be replaced.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $pedagogy
     * @return array<string, mixed>
     */
    private function withSelectedPedagogy(array $row, array $pedagogy): array
    {
        $selected = $pedagogy['selected'] ?? null;

        if ($selected === null) {
            return $row;
        }

        $row['pedagogy'] = (string) ($pedagogy['selected_strategy'] ?: ucwords(str_replace('_', ' ', (string) $selected)));
        $row['pedagogy_tags'] = [(string) $selected];

        if (! empty($pedagogy['selected_h5p_labels'])) {
            $row['h5p_type'] = implode(', ', $pedagogy['selected_h5p_labels']);
        }

        return $row;
    }

    /**
     * The trigger table's Pedagogy and H5P columns get the same treatment, taken
     * from the matching rule row so both halves of the section agree.
     *
     * @param  array<string, mixed>  $trigger
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function withTriggerConceptContent(array $trigger, array $rules): array
    {
        foreach ($rules as $rule) {
            if (($rule['id'] ?? null) !== ($trigger['id'] ?? null)) {
                continue;
            }

            return $this->withSelectedPedagogy($trigger, $rule['resolved']['pedagogy'] ?? []);
        }

        return $trigger;
    }

    /**
     * "Serves 3 learning objectives, 2 prerequisite gates and 16 source-evidence
     * extracts" - counted from the records the rule actually resolved to, so the
     * Action column names this concept's content rather than the specification's
     * generic instruction.
     *
     * @param  array<string, mixed>  $resolved
     */
    private function describeServedContent(array $resolved): ?string
    {
        $counts = [];
        foreach ($resolved['items'] ?? [] as $item) {
            $path = (string) ($item['from'] ?? '');
            $counts[$path] = ($counts[$path] ?? 0) + 1;
        }

        $parts = [];
        foreach ($counts as $path => $count) {
            $nouns = self::EVIDENCE_NOUNS[$path] ?? null;
            $parts[] = $count . ' ' . ($nouns === null
                ? rtrim($path, '[]')
                : ($count === 1 ? $nouns[0] : $nouns[1]));
        }

        if ($parts === []) {
            return null;
        }

        $last = array_pop($parts);

        return 'Serves ' . ($parts === [] ? $last : implode(', ', $parts) . ' and ' . $last) . '.';
    }

    private function buildRule(PedagogyEngineRule $rule): array
    {
        $meta = $rule->rule_meta ?? [];

        return [
            'id' => $rule->rule_key,
            'group' => $rule->group_label,
            'condition' => $rule->condition,
            'action' => $rule->action,
            'pedagogy' => $rule->pedagogy,
            'pedagogy_tags' => $this->normalizePedagogyTags($rule->pedagogy),
            'h5p_type' => $rule->h5p_type,
            'scaffolding' => $rule->scaffolding,
            'trigger' => $rule->trigger_action,
            'reason' => $rule->reason,
            'implementation_status' => $rule->implementation_status,
            'avoid' => array_values((array) ($meta['avoid'] ?? [])),
            'do_not' => array_values((array) ($meta['do_not'] ?? [])),
            'h5p_priority' => array_values((array) ($meta['h5p_priority'] ?? [])),
            'meta' => $meta,
        ];
    }

    private function buildSignal(PedagogyEngineRule $rule): array
    {
        $meta = $rule->rule_meta ?? [];

        return [
            'id' => $rule->rule_key,
            'key' => $meta['key'] ?? $rule->rule_key,
            'name' => $rule->condition,
            'description' => $rule->action,
            'weight' => isset($meta['weight']) ? (float) $meta['weight'] : null,
            'weight_percent' => isset($meta['weight_percent']) ? (float) $meta['weight_percent'] : null,
        ];
    }

    private function buildBand(PedagogyEngineRule $rule): array
    {
        $meta = $rule->rule_meta ?? [];

        return [
            'id' => $rule->rule_key,
            'range' => $rule->condition,
            'min' => isset($meta['range']['min']) ? (float) $meta['range']['min'] : null,
            'max' => isset($meta['range']['max']) ? (float) $meta['range']['max'] : null,
            'label' => $meta['label'] ?? $rule->action,
            'tone' => $meta['tone'] ?? 'neutral',
            'action' => $rule->trigger_action,
        ];
    }

    private function buildTrigger(PedagogyEngineRule $rule): array
    {
        $meta = $rule->rule_meta ?? [];

        return [
            'id' => $rule->rule_key,
            'trigger' => $rule->condition,
            'outcome' => $rule->action,
            'pedagogy' => $rule->pedagogy,
            'pedagogy_tags' => $this->normalizePedagogyTags($rule->pedagogy),
            'h5p_type' => $rule->h5p_type,
            'duration' => $meta['duration'] ?? null,
            'source_tier' => $meta['source_tier'] ?? null,
        ];
    }

    /**
     * A rule may name more than one pedagogy ("activity_based or flashcard") and
     * may use a label outside the canonical 12 ("concept_based"). Resolve them
     * through the existing framework catalog so the UI can badge canonical tags
     * without the frontend owning that mapping.
     *
     * @return array<int, string>
     */
    private function normalizePedagogyTags(?string $pedagogy): array
    {
        if (! $pedagogy) {
            return [];
        }

        $candidates = preg_split('/\s*(?:,| or | and |\/)\s*/i', $pedagogy) ?: [];

        $tags = [];
        foreach ($candidates as $candidate) {
            $candidate = trim(preg_replace('/\(.*?\)/', '', (string) $candidate));
            // Only resolve machine-style tags; prose like "Unchanged" or
            // "Teacher decides" would otherwise all collapse to inquiry_based.
            if ($candidate === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $candidate)) {
                continue;
            }
            $tags[] = $this->catalog->normalizePedagogy($candidate);
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function buildStats(array $sections): array
    {
        $ruleCount = 0;
        $implemented = 0;
        $partial = 0;

        foreach ($sections as $section) {
            $ruleCount += count($section['rules'] ?? []);
            $status = strtolower((string) ($section['implementation_status'] ?? ''));
            if ($status === 'implemented') {
                $implemented++;
            } elseif (str_contains($status, 'partial')) {
                $partial++;
            }
        }

        return [
            'sections' => count($sections),
            'rules' => $ruleCount,
            'implemented_sections' => $implemented,
            'partial_sections' => $partial,
        ];
    }
}
