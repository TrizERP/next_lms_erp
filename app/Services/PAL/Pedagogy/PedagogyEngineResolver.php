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
    /** The Bloom levels the engine treats as "most intellectually demanding". */
    private const TOP_BLOOMS = ['analyze', 'evaluate', 'create'];

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
            'engagement-score' => $this->resolveEngagement($rule, $concept),
            'trigger-map' => $this->resolveTriggerRule($rule, $concept),
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

            // The schema is built on the source material the extraction quoted,
            // so the grounding evidence is served with the definition.
            $this->append($items, $sources, $this->evidenceRows($concept));
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

        // Bands above the practice floor also serve what their own action names:
        // assessment-ready outcomes while consolidating, and the competencies and
        // abilities a peer-teaching learner has to demonstrate.
        $this->append($items, $sources, match ((string) ($meta['state'] ?? '')) {
            'consolidating' => $this->outcomeRows($concept, true),
            'approaching mastery' => array_merge($this->competencyRows($concept), $this->abilityRows($concept)),
            default => [],
        });

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
                $this->append($items, $sources, $this->topBloomBlueprint($concept));
                $this->append($items, $sources, $this->rubricRows($concept, self::TOP_BLOOMS));
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

                if ($rule['id'] === 'class-wide-misconception') {
                    // The majority signal is counted over the concept's own rubric
                    // distractors and recorded common errors -- those are the items
                    // a class answers, so they are what a class-wide rate is read
                    // from. No worked example is substituted for them.
                    $note = 'The misconceptions this concept carries, plus the rubric distractors and common errors the class-wide rate is measured over.';
                    $this->append($items, $sources, $this->misconceptionProbes($concept));
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
                $note = 'Fluency drilling targets the concept\'s extracted knowledge items and the abilities they support.';
                foreach ($concept['knowledge_items'] as $item) {
                    $items[] = ['label' => (string) ($item['knowledge'] ?? $item['value'] ?? ''), 'detail' => null, 'tags' => ['fluency'], 'from' => 'knowledge_items[]'];
                    $sources[] = 'knowledge_items[]';
                }
                $this->append($items, $sources, $this->abilityRows($concept));
                break;

            case 'fast-and-right':
                $note = 'Escalates to the concept\'s highest-Bloom blueprint rows and scored rubric items.';
                $this->append($items, $sources, $this->topBloomBlueprint($concept));
                $this->append($items, $sources, $this->rubricRows($concept, self::TOP_BLOOMS));
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

            // The rubric items are the scored form of the same diagnostic, so
            // they are what a first session can actually be marked against.
            $this->append($items, $sources, array_slice($this->rubricRows($concept), 0, 5));
        }

        if ($rule['id'] === 'teacher-override') {
            // A teacher overrides *towards* something: the options are this
            // concept's own extracted pedagogy recommendations.
            $this->append($items, $sources, $this->pedagogyRows($concept, 'teacher option'));
        }

        return $this->resolution($items, $sources, $this->pedagogyFromConcept($concept, $rule), '');
    }

    // ─── Engagement score: the concept content behind each signal and band ───

    /**
     * The composition weights and the band thresholds are engine configuration,
     * but what each row *measures over* is the selected concept's own extracted
     * content: the items a session is spent on, the deck a return session opens
     * with, the optional material a voluntary extension can reach into.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private function resolveEngagement(array $rule, array $concept): array
    {
        $items = [];
        $sources = [];

        [$rows, $note] = match ($rule['group'] === 'signal' ? (string) ($rule['meta']['key'] ?? '') : $rule['id']) {
            'time_on_task_ratio' => [
                $this->blueprintRows($concept),
                'Measured against the expected duration of the blueprint items this concept actually carries.',
            ],
            'interaction_rate' => [
                $this->rubricRows($concept),
                'Counted over the scored rubric items the learner answers for this concept.',
            ],
            'session_return_rate' => [
                $this->knowledgeRows($concept, 'return deck'),
                'The familiar deck a returning session re-opens with for this concept.',
            ],
            'voluntary_extension_rate' => [
                array_merge($this->applicationRows($concept), $this->relationshipRows($concept)),
                'The optional material a learner can voluntarily continue into beyond the required content.',
            ],
            'band-critical' => [
                $this->knowledgeRows($concept, 'game deck'),
                'A game injection in this band is built from this concept\'s knowledge items.',
            ],
            'band-low' => [
                $this->pedagogyRows($concept, 'alternative'),
                'The pedagogies this concept can be switched to when engagement sits in this band.',
            ],
            'band-normal', 'band-good' => [
                $this->blueprintRows($concept),
                'No intervention: the concept keeps serving its own blueprint items.',
            ],
            'band-flow' => [
                array_merge($this->topBloomBlueprint($concept), $this->rubricRows($concept, self::TOP_BLOOMS)),
                'The hardest content this concept carries, which is what a flow state is allowed to escalate to.',
            ],
            default => [[], ''],
        };

        $this->append($items, $sources, $rows);

        return $this->resolution($items, $sources, $this->pedagogyFromConcept($concept, $rule), $note);
    }

    // ─── Pedagogy x Trigger map: which triggers this concept actually fires ──

    /**
     * The rule-row form of the trigger map, so every row in the section reports
     * the same extracted evidence the trigger table shows.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private function resolveTriggerRule(array $rule, array $concept): array
    {
        [$key, $path] = $this->triggerEvidenceMap()[$rule['id']] ?? [null, null];

        $items = [];
        $sources = [];

        foreach ($key === null ? [] : ($concept[$key] ?? []) as $row) {
            $items[] = [
                'label' => $this->rowLabel($row),
                'detail' => null,
                'tags' => [],
                'from' => $path,
            ];
            $sources[] = $path;
        }

        return $this->resolution(
            $items,
            $sources,
            $this->pedagogyFromConcept($concept, $rule),
            $path === null ? '' : "Fires only when the concept carries {$path} records."
        );
    }

    /**
     * Which extracted collection decides whether each trigger fires.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    private function triggerEvidenceMap(): array
    {
        return [
            'trigger-misconception-confirmed' => ['misconceptions', 'misconceptions[]'],
            'trigger-mastery-above-85' => ['real_world_applications', 'real_world_applications[]'],
            'trigger-spaced-review-due' => ['knowledge_items', 'knowledge_items[]'],
            'trigger-cross-curricular-link' => ['concept_relationships', 'concept_relationships[]'],
            'trigger-riasec-career-signal' => ['real_world_applications', 'real_world_applications[]'],
            'trigger-art-integration' => ['skills', 'skills[]'],
            'trigger-engagement-below-40' => ['knowledge_items', 'knowledge_items[]'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $triggers
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    public function resolveTriggers(array $triggers, array $concept): array
    {
        $evidence = $this->triggerEvidenceMap();

        return array_map(function (array $trigger) use ($concept, $evidence) {
            [$key, $path] = $evidence[$trigger['id']] ?? [null, null];
            $rows = $key === null ? [] : ($concept[$key] ?? []);

            $trigger['fires_for_concept'] = $rows !== [];
            $trigger['evidence_count'] = count($rows);
            $trigger['evidence_source'] = $path;
            $trigger['evidence'] = array_map(fn ($row) => [
                'label' => $this->rowLabel($row),
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

    // ─── extracted-collection readers ───────────────────────────────────────
    //
    // One reader per `semantic_intelligence` collection. Each returns evidence
    // rows carrying the JSON path they came from, so nothing a rule serves can
    // be traced back to anywhere but the extraction.

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $sources
     */
    private function append(array &$items, array &$sources, array $rows): void
    {
        foreach ($rows as $row) {
            $items[] = $row;
            $sources[] = (string) $row['from'];
        }
    }

    /**
     * The label an evidence row prints, whichever collection it came from.
     *
     * @param  array<string, mixed>  $row
     */
    private function rowLabel(array $row): string
    {
        foreach (['misconception', 'example', 'application', 'knowledge', 'skill', 'ability', 'competency', 'outcome', 'objective', 'question', 'source_text', 'relation_type', 'concept_name', 'value'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
                return (string) $row[$key];
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function evidenceRows(array $concept): array
    {
        return array_map(fn ($row) => [
            'label' => (string) ($row['source_text'] ?? $row['value'] ?? ''),
            'detail' => null,
            'tags' => array_values(array_filter([$row['source_type'] ?? null, 'source evidence'])),
            'from' => 'evidence[]',
        ], $concept['evidence']);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function outcomeRows(array $concept, bool $assessmentReadyOnly = false): array
    {
        $rows = $assessmentReadyOnly
            ? $this->pick($concept['learning_outcomes'], fn ($outcome) => ! empty($outcome['assessment_ready']))
            : $concept['learning_outcomes'];

        return array_map(fn ($row) => [
            'label' => (string) ($row['outcome'] ?? $row['value'] ?? ''),
            'detail' => $row['outcome_type'] ?? null,
            'tags' => array_values(array_filter([
                ! empty($row['measurable']) ? 'measurable' : null,
                ! empty($row['assessment_ready']) ? 'assessment ready' : null,
            ])),
            'from' => 'learning_outcomes[]',
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function competencyRows(array $concept): array
    {
        return array_map(fn ($row) => [
            'label' => (string) ($row['competency'] ?? $row['value'] ?? ''),
            'detail' => $row['statement'] ?? null,
            'tags' => ['competency'],
            'from' => 'competencies[]',
        ], $concept['competencies']);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function abilityRows(array $concept): array
    {
        return array_map(fn ($row) => [
            'label' => (string) ($row['ability'] ?? $row['value'] ?? ''),
            'detail' => $row['description'] ?? null,
            'tags' => array_values(array_filter([$row['verb'] ?? null, 'ability'])),
            'from' => 'abilities[]',
        ], $concept['abilities']);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function knowledgeRows(array $concept, string $tag): array
    {
        return array_map(fn ($row) => [
            'label' => (string) ($row['knowledge'] ?? $row['value'] ?? ''),
            'detail' => null,
            'tags' => [$tag],
            'from' => 'knowledge_items[]',
        ], $concept['knowledge_items']);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function pedagogyRows(array $concept, string $tag): array
    {
        return array_map(fn ($row) => [
            'label' => (string) ($row['strategy'] ?? $row['value'] ?? ''),
            'detail' => $row['why_effective'] ?? null,
            'tags' => array_merge([$tag], array_values((array) ($row['concept_characteristics'] ?? []))),
            'from' => 'pedagogy_recommendations[]',
        ], $concept['pedagogy_recommendations']);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function applicationRows(array $concept): array
    {
        return array_map(fn ($row) => [
            'label' => (string) ($row['example'] ?? $row['application'] ?? ''),
            'detail' => $row['application_type'] ?? null,
            'tags' => array_values(array_filter([$row['relevance'] ?? null])),
            'from' => 'real_world_applications[]',
        ], $concept['real_world_applications']);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function relationshipRows(array $concept): array
    {
        return array_map(fn ($row) => [
            'label' => trim(($row['source_concept'] ?? '') . ' → ' . ($row['target_concept'] ?? '')),
            'detail' => $row['relation_type'] ?? null,
            'tags' => ['cross-concept'],
            'from' => 'concept_relationships[]',
        ], $concept['concept_relationships']);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function blueprintRows(array $concept, array $blooms = []): array
    {
        $rows = $blooms === []
            ? $concept['assessment_blueprint']
            : $this->pick($concept['assessment_blueprint'], fn ($item) => in_array(strtolower((string) ($item['bloom_level'] ?? '')), $blooms, true));

        return array_map(fn ($row) => [
            'label' => (string) ($row['recommended_question'] ?? $row['assessment_type'] ?? ''),
            'detail' => $row['assessment_type'] ?? null,
            'tags' => array_values(array_filter([
                isset($row['bloom_level']) ? 'Bloom: ' . $row['bloom_level'] : null,
                isset($row['dok_level']) ? 'DOK: ' . $row['dok_level'] : null,
                $row['difficulty'] ?? null,
                isset($row['marks']) ? $row['marks'] . ' marks' : null,
            ])),
            'from' => 'assessment_blueprint[]',
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function topBloomBlueprint(array $concept): array
    {
        return $this->blueprintRows($concept, self::TOP_BLOOMS);
    }

    /**
     * The scored rubric items the extraction wrote for this concept, optionally
     * narrowed to a set of Bloom levels.
     *
     * @param  array<string, mixed>  $concept
     * @param  array<int, string>  $blooms
     * @return array<int, array<string, mixed>>
     */
    private function rubricRows(array $concept, array $blooms = []): array
    {
        $rows = $concept['rubric_items'] ?? [];

        if ($blooms !== []) {
            $rows = $this->pick($rows, fn ($item) => in_array(strtolower((string) ($item['bloom_level'] ?? '')), $blooms, true));
        }

        return array_map(fn ($row) => [
            'label' => (string) ($row['question'] ?? $row['item_id'] ?? ''),
            'detail' => $row['skill_phrase'] ?? null,
            'tags' => array_values(array_filter([
                $row['assessment_type'] ?? null,
                $row['rubric_type'] ?? null,
                isset($row['bloom_level']) ? 'Bloom: ' . $row['bloom_level'] : null,
                isset($row['dok_level']) ? 'DOK: ' . $row['dok_level'] : null,
                $row['difficulty'] ?? null,
                isset($row['marks']) ? $row['marks'] . ' marks' : null,
            ])),
            'from' => 'assessment_rubrics.items[]',
        ], $rows);
    }

    /**
     * The misconceptions this concept's own rubric distractors probe for, plus
     * the common errors each item records. This is the measured class-wide error
     * signal - it replaces nothing and invents nothing.
     *
     * @param  array<string, mixed>  $concept
     * @return array<int, array<string, mixed>>
     */
    private function misconceptionProbes(array $concept): array
    {
        $rows = [];

        foreach ($concept['rubric_items'] ?? [] as $item) {
            // Several distractors of one item often probe the same misconception.
            // Collapse them into a single row so the count reads as "distinct
            // misconceptions probed", with every distractor listed as a tag.
            $probes = [];
            foreach ((array) ($item['answer_key'] ?? []) as $option) {
                if (empty($option['misconception_tested'])) {
                    continue;
                }

                $label = (string) $option['misconception_tested'];
                $probes[$label] ??= [
                    'label' => $label,
                    'detail' => $option['rationale'] ?? null,
                    'tags' => array_values(array_filter([$item['item_id'] ?? null])),
                    'from' => 'assessment_rubrics.items[].answer_key[]',
                ];

                if (isset($option['option_label'])) {
                    $probes[$label]['tags'][] = 'Distractor ' . $option['option_label'];
                }
            }
            $rows = array_merge($rows, array_values($probes));

            foreach ((array) ($item['common_errors'] ?? []) as $error) {
                $rows[] = [
                    'label' => (string) $error,
                    'detail' => null,
                    'tags' => array_values(array_filter([$item['item_id'] ?? null, 'common error'])),
                    'from' => 'assessment_rubrics.items[].common_errors[]',
                ];
            }
        }

        return $rows;
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
        $h5p = $selected === null ? [] : array_values($this->catalog->allowedH5pForPedagogy($selected));

        return [
            'selected' => $selected,
            // The concept's own wording for the strategy the engine picked, so the
            // UI can print what the extraction said rather than the canonical tag.
            'selected_strategy' => $selected === null ? null : ($conceptTags[$selected] ?? null),
            'selected_h5p' => $h5p,
            'selected_h5p_labels' => array_map(fn ($type) => $this->h5pLabel($type), $h5p),
            'rule_routes_to' => $ruleTags,
            'concept_offers' => array_keys($conceptTags),
            'concept_strategies' => array_values($conceptTags),
            'agrees_with_concept' => $shared !== [],
        ];
    }

    /**
     * The display name the H5P registry gives a content type.
     */
    private function h5pLabel(string $type): string
    {
        return (string) config("pal_h5p.registry.{$type}.label", ucwords(str_replace('_', ' ', $type)));
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
