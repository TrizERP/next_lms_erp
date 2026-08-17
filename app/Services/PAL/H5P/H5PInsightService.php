<?php

namespace App\Services\PAL\H5P;

use App\Services\PAL\ContentModel\ContentModelLlmClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DeepSeek insight layer over the H5P xAPI event stream.
 *
 * This sits ON TOP of the xAPI pipeline, it does not replace it. The pipeline
 * keeps ingesting H5P statements and keeps producing the measured engagement
 * numbers; this class reads what those events add up to and asks DeepSeek to
 * say what it means in plain language.
 *
 * Two strictly separated halves:
 *
 *   evidencePack()  ALL the numbers. Pure SQL over `pal_telemetry_events`,
 *                   `pal_learning_events` and the H5P content tables. No model
 *                   is involved and none can be. This is returned to the caller
 *                   whether or not the LLM runs, so the workspace shows real
 *                   figures even with AI switched off.
 *
 *   insight()       DeepSeek narrating THAT pack. The model receives only the
 *                   aggregated facts and the chapter's real vocabulary; it can
 *                   describe and recommend, it cannot supply a number. Every
 *                   node key, pedagogy and H5P type it returns is validated
 *                   against the pack and the registry, and anything it invents
 *                   is dropped before the response is built.
 *
 * With no events in the window there is nothing to reason about, so the LLM is
 * not called at all and the response says so — an insight generated from zero
 * observations would be fiction.
 */
class H5PInsightService
{
    public function __construct(
        protected H5PModelRegistry $registry,
        protected H5PContentRepository $repository,
        protected H5PTaggingService $tagging,
        protected H5PIntelligenceService $intelligence,
        protected ContentModelLlmClient $llm
    ) {
    }

    // ══════════════════════════════════════════════════════════════════════
    // The deterministic half
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Everything the event stream says about one chapter, optionally narrowed
     * to a single learner.
     *
     * @param  array     $context    chapter_id / subject_id / standard_id / sub_institute_id
     * @param  int|null  $learnerId  null = the whole class
     */
    public function evidencePack(array $context, ?int $learnerId = null, ?int $windowDays = null): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);
        $window = $windowDays ?? (int) config('pal_h5p.insight.window_days', 30);

        $nodes = $this->repository->nodesForContext($context, null, (int) config('pal_h5p.insight.max_nodes_in_pack', 25));
        $tags = $this->tagging->tagNodes($nodes, $context);
        $nodeKeys = array_column($nodes, 'node_key');

        $ready = $this->telemetryReady();
        $perNode = $ready ? $this->perNodeStats($nodeKeys, $learnerId, $window, $tenant) : [];
        $perPedagogy = $ready ? $this->perPedagogyStats($nodeKeys, $learnerId, $window, $tenant) : [];
        $signals = $ready ? $this->importantSignals($nodeKeys, $learnerId, $window, $tenant) : [];
        $totals = $ready ? $this->totals($nodeKeys, $learnerId, $window, $tenant) : null;

        $nodeRows = [];
        foreach ($nodes as $node) {
            $key = $node['node_key'];
            $stats = $perNode[$key] ?? null;
            $values = $tags[$key]['values'] ?? [];

            $nodeRows[] = [
                'node_key' => $key,
                'title' => $node['title'],
                'h5p_type' => $node['h5p_type'],
                'pedagogy_tag' => $values['pedagogy_tag'] ?? null,
                'bloom_level' => $values['bloom_level'] ?? null,
                'difficulty_1_to_5' => $values['difficulty_1_to_5'] ?? null,
                'attempts' => $stats['attempts'] ?? 0,
                'correct' => $stats['correct'] ?? 0,
                'incorrect' => $stats['incorrect'] ?? 0,
                'accuracy' => $stats['accuracy'] ?? null,
                'completions' => $stats['completions'] ?? 0,
                'learners' => $stats['learners'] ?? 0,
                'avg_seconds' => $stats['avg_seconds'] ?? null,
            ];
        }

        return [
            'context' => $context + $this->repository->resolveContextNames($context),
            'learner_id' => $learnerId,
            'window_days' => $window,
            'telemetry_available' => $ready,
            'telemetry_reason' => $ready
                ? null
                : 'The xAPI event store (pal_telemetry_events) is not present or not yet migrated on this database.',
            'totals' => $totals ?? ['events' => 0, 'learners' => 0, 'sessions' => 0, 'total_seconds' => 0, 'last_event_at' => null],
            'nodes' => $nodeRows,
            'by_pedagogy' => $perPedagogy,
            'attention_signals' => $signals,
            'struggles' => $this->struggles($nodeRows),
            'coverage_gaps' => $this->coverageGaps($context, $tags),
            'has_evidence' => ($totals['events'] ?? 0) > 0,
        ];
    }

    /** Per-node attempt / accuracy / latency, straight from the event store. */
    protected function perNodeStats(array $nodeKeys, ?int $learnerId, int $window, int $tenant): array
    {
        if ($nodeKeys === []) {
            return [];
        }

        $rows = $this->events($learnerId, $window, $tenant)
            ->whereIn('object_id', $nodeKeys)
            ->groupBy('object_id')
            ->selectRaw('object_id')
            ->selectRaw('count(*) as attempts')
            ->selectRaw('count(distinct actor_id) as learners')
            ->selectRaw('avg(nullif(duration_seconds, 0)) as avg_seconds')
            ->selectRaw($this->successExpr('true') . ' as correct')
            ->selectRaw($this->successExpr('false') . ' as incorrect')
            ->selectRaw($this->completionExpr() . ' as completions')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $correct = (int) $row->correct;
            $incorrect = (int) $row->incorrect;
            $judged = $correct + $incorrect;

            $out[(string) $row->object_id] = [
                'attempts' => (int) $row->attempts,
                'learners' => (int) $row->learners,
                'correct' => $correct,
                'incorrect' => $incorrect,
                'accuracy' => $judged > 0 ? round($correct / $judged, 3) : null,
                'completions' => (int) $row->completions,
                'avg_seconds' => $row->avg_seconds !== null ? round((float) $row->avg_seconds, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * How each pedagogy is performing for this learner/class.
     *
     * Telemetry carries the pedagogy tag on the event, so this is the
     * behavioural answer to "which pedagogy is working" rather than the
     * authored one.
     */
    protected function perPedagogyStats(array $nodeKeys, ?int $learnerId, int $window, int $tenant): array
    {
        if ($nodeKeys === []) {
            return [];
        }

        $rows = $this->events($learnerId, $window, $tenant)
            ->whereIn('object_id', $nodeKeys)
            ->whereNotNull('pedagogy_tag')
            ->where('pedagogy_tag', '!=', '')
            ->groupBy('pedagogy_tag')
            ->selectRaw('pedagogy_tag')
            ->selectRaw('count(*) as attempts')
            ->selectRaw('count(distinct actor_id) as learners')
            ->selectRaw('avg(nullif(duration_seconds, 0)) as avg_seconds')
            ->selectRaw($this->successExpr('true') . ' as correct')
            ->selectRaw($this->successExpr('false') . ' as incorrect')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $code = $this->registry->normalize('pedagogy_tags', $row->pedagogy_tag, $tenant);
            if ($code === null) {
                continue;
            }

            $correct = (int) $row->correct;
            $incorrect = (int) $row->incorrect;
            $judged = $correct + $incorrect;

            $out[] = [
                'pedagogy_tag' => $code,
                'label' => $this->registry->pedagogy($code, $tenant)['label'] ?? $code,
                'attempts' => (int) $row->attempts,
                'learners' => (int) $row->learners,
                'accuracy' => $judged > 0 ? round($correct / $judged, 3) : null,
                'avg_seconds' => $row->avg_seconds !== null ? round((float) $row->avg_seconds, 1) : null,
            ];
        }

        usort($out, fn ($a, $b) => $b['attempts'] <=> $a['attempts']);

        return $out;
    }

    /**
     * Counts of the verbs the registry flags `important` — hints opened, rapid
     * guessing, repeated failure, replays. These are the behavioural tells the
     * accuracy numbers alone do not show.
     */
    protected function importantSignals(array $nodeKeys, ?int $learnerId, int $window, int $tenant): array
    {
        $verbs = $this->registry->importantVerbs($tenant);
        if ($verbs === [] || $nodeKeys === []) {
            return [];
        }

        $rows = $this->events($learnerId, $window, $tenant)
            ->whereIn('object_id', $nodeKeys)
            ->whereIn('verb', $verbs)
            ->groupBy('verb')
            ->selectRaw('verb, count(*) as occurrences, count(distinct actor_id) as learners')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $term = $this->registry->term('xapi_verbs', $row->verb, $tenant);
            $out[] = [
                'verb' => (string) $row->verb,
                'label' => $term['label'] ?? $row->verb,
                'occurrences' => (int) $row->occurrences,
                'learners' => (int) $row->learners,
            ];
        }

        usort($out, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        return $out;
    }

    protected function totals(array $nodeKeys, ?int $learnerId, int $window, int $tenant): array
    {
        if ($nodeKeys === []) {
            return ['events' => 0, 'learners' => 0, 'sessions' => 0, 'total_seconds' => 0, 'last_event_at' => null];
        }

        $row = $this->events($learnerId, $window, $tenant)
            ->whereIn('object_id', $nodeKeys)
            ->selectRaw('count(*) as events')
            ->selectRaw('count(distinct actor_id) as learners')
            ->selectRaw('count(distinct session_id) as sessions')
            ->selectRaw('sum(duration_seconds) as total_seconds')
            ->selectRaw('max(`timestamp`) as last_event_at')
            ->first();

        return [
            'events' => (int) ($row->events ?? 0),
            'learners' => (int) ($row->learners ?? 0),
            'sessions' => (int) ($row->sessions ?? 0),
            'total_seconds' => (int) ($row->total_seconds ?? 0),
            'last_event_at' => $row->last_event_at ?? null,
        ];
    }

    /**
     * Nodes worth a teacher's attention, ranked. Deliberately computed here
     * rather than asked of the model: which node is failing is arithmetic, and
     * arithmetic should not be delegated to an LLM.
     */
    protected function struggles(array $nodeRows): array
    {
        $threshold = (float) config('pal_h5p.insight.struggle_accuracy_below', 0.6);
        $minAttempts = (int) config('pal_h5p.insight.struggle_min_attempts', 3);

        $out = [];
        foreach ($nodeRows as $node) {
            if ($node['accuracy'] === null || $node['attempts'] < $minAttempts) {
                continue;
            }
            if ($node['accuracy'] >= $threshold) {
                continue;
            }

            $out[] = [
                'node_key' => $node['node_key'],
                'title' => $node['title'],
                'h5p_type' => $node['h5p_type'],
                'pedagogy_tag' => $node['pedagogy_tag'],
                'accuracy' => $node['accuracy'],
                'attempts' => $node['attempts'],
                'learners' => $node['learners'],
                'avg_seconds' => $node['avg_seconds'],
            ];
        }

        usort($out, fn ($a, $b) => $a['accuracy'] <=> $b['accuracy']);

        return $out;
    }

    /** The framework tags this chapter's content does not yet generate. */
    protected function coverageGaps(array $context, array $tags): array
    {
        $out = [];
        foreach ($this->intelligence->coverage($context, $tags) as $framework => $block) {
            $missing = [];
            foreach ($block['tags'] as $tag) {
                if (! $tag['covered']) {
                    $missing[] = $tag['code'];
                }
            }
            if ($missing !== []) {
                $out[$framework] = ['missing' => $missing, 'covered' => $block['covered'], 'total' => $block['total']];
            }
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════════════
    // The DeepSeek half
    // ══════════════════════════════════════════════════════════════════════

    public function available(): bool
    {
        return (bool) config('pal_h5p.insight.enabled', true) && $this->llm->enabled();
    }

    public function unavailableReason(): ?string
    {
        if (! config('pal_h5p.insight.enabled', true)) {
            return 'H5P insight generation is switched off for this deployment (PAL_H5P_INSIGHT).';
        }

        return $this->llm->unavailableReason();
    }

    /**
     * Ask DeepSeek what the event stream means.
     *
     * @param  array  $pack  from evidencePack()
     */
    public function insight(array $pack, array $context, ?int $userId = null): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);

        if (! $pack['telemetry_available']) {
            return $this->noInsight('unavailable', $pack['telemetry_reason']);
        }

        if (! $pack['has_evidence']) {
            return $this->noInsight(
                'insufficient_evidence',
                sprintf(
                    'No H5P activity has been recorded for this chapter in the last %d days, so there is nothing to interpret. '
                    . 'Insight is generated from the xAPI event stream — it is not produced from the content alone.',
                    $pack['window_days']
                )
            );
        }

        if (! $this->available()) {
            return $this->noInsight('unavailable', $this->unavailableReason());
        }

        $input = $this->promptInput($pack, $tenant);
        $fingerprint = $this->llm->fingerprint($input);
        $kind = (string) config('pal_h5p.insight.cache_kind', 'h5p_insight');
        $cacheKey = sprintf('H5P.%d.insight%s', (int) ($context['chapter_id'] ?? 0), $pack['learner_id'] ? '.' . $pack['learner_id'] : '');

        // Keyed on a fingerprint of the exact evidence the model saw, so new
        // events invalidate the answer and a page reload never re-bills.
        $cached = $this->llm->cached($cacheKey, $kind, null, $fingerprint, $tenant);
        $data = $cached['payload'] ?? null;
        $model = $cached['model'] ?? null;

        if ($data === null) {
            $response = $this->llm->json($this->systemPrompt(), json_encode($input, JSON_UNESCAPED_UNICODE));

            if (empty($response['ok'])) {
                return $this->noInsight('failed', $response['error'] ?? 'The AI provider did not return a usable response.');
            }

            $data = (array) ($response['data'] ?? []);
            $model = $response['model'] ?? $this->llm->model();

            $this->llm->remember(
                $cacheKey, $kind, null, $fingerprint, $tenant,
                $data, null, $model, (array) ($response['usage'] ?? []), $userId
            );
        }

        return $this->validate($data, $pack, $tenant) + [
            'status' => 'ok',
            'reason' => null,
            'provider' => $this->llm->providerName(),
            'model' => $model,
            'cached' => $cached !== null,
            'generated_from' => [
                'events' => $pack['totals']['events'],
                'nodes' => count($pack['nodes']),
                'window_days' => $pack['window_days'],
            ],
        ];
    }

    /** The compact, factual view of the pack the model is allowed to see. */
    protected function promptInput(array $pack, int $tenant): array
    {
        $nodes = array_values(array_filter(
            $pack['nodes'],
            fn (array $node) => $node['attempts'] > 0
        ));

        return [
            'chapter' => $pack['context']['chapter_name'] ?? ('Chapter #' . ($pack['context']['chapter_id'] ?? '?')),
            'subject' => $pack['context']['subject_name'] ?? null,
            'grade' => $pack['context']['standard_name'] ?? null,
            'scope' => $pack['learner_id'] ? 'one learner' : 'whole class',
            'window_days' => $pack['window_days'],
            'totals' => $pack['totals'],
            'nodes_with_activity' => $nodes,
            'by_pedagogy' => $pack['by_pedagogy'],
            'attention_signals' => $pack['attention_signals'],
            'struggling_nodes' => $pack['struggles'],
            'framework_gaps' => $pack['coverage_gaps'],
            'allowed_pedagogies' => array_keys($this->registry->pedagogies($tenant)),
            'allowed_h5p_types' => array_keys($this->registry->types($tenant)),
        ];
    }

    protected function systemPrompt(): string
    {
        return implode("\n", [
            'You interpret H5P learning telemetry for teachers in Indian K-12 schools, for the PAL V4 engine.',
            'You are given ONLY aggregated facts already computed from the xAPI event stream. Every number is final:',
            'never restate a figure that is not in the input, never estimate one that is missing, never invent a node.',
            '',
            'Write for a class teacher: plain, specific, no jargon, no praise padding. Reference nodes by their node_key.',
            'If the evidence is thin, say so rather than over-reading it — a low sample size is itself worth reporting.',
            '',
            'Use ONLY codes from allowed_pedagogies and allowed_h5p_types. Reference ONLY node_key values present in the input.',
            '',
            'Return strict JSON and nothing else:',
            '{"headline":"one sentence on the state of this chapter",',
            ' "observations":[{"text":"…","node_keys":["…"]}],',
            ' "what_is_working":[{"text":"…","pedagogy_tag":"…"}],',
            ' "struggles":[{"node_key":"…","why":"…","suggested_pedagogy":"…","suggested_h5p_type":"…"}],',
            ' "next_actions":[{"action":"…","pedagogy_tag":"…","h5p_type":"…","rationale":"…"}],',
            ' "evidence_caveat":"one sentence naming the limits of this data",',
            ' "confidence":0.0}',
        ]);
    }

    /**
     * Drop anything the model invented.
     *
     * A node key not in the pack, or a pedagogy / H5P type not in the registry,
     * is removed rather than shown — a recommendation pointing at content that
     * does not exist is worse than no recommendation.
     */
    protected function validate(array $data, array $pack, int $tenant): array
    {
        $knownNodes = array_column($pack['nodes'], 'node_key');
        $dropped = [];

        $ped = function ($value) use ($tenant, &$dropped) {
            $code = $this->registry->normalize('pedagogy_tags', is_string($value) ? $value : null, $tenant);
            if ($value && $code === null) {
                $dropped[] = "pedagogy:{$value}";
            }
            return $code;
        };

        $type = function ($value) use ($tenant, &$dropped) {
            $code = $this->registry->normalize('h5p_types', is_string($value) ? $value : null, $tenant);
            if ($value && $code === null) {
                $dropped[] = "h5p_type:{$value}";
            }
            return $code;
        };

        $observations = [];
        foreach ((array) ($data['observations'] ?? []) as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $keys = array_values(array_intersect((array) ($item['node_keys'] ?? []), $knownNodes));
            foreach ((array) ($item['node_keys'] ?? []) as $key) {
                if (! in_array($key, $knownNodes, true)) {
                    $dropped[] = "node:{$key}";
                }
            }
            $observations[] = ['text' => $text, 'node_keys' => $keys];
        }

        $working = [];
        foreach ((array) ($data['what_is_working'] ?? []) as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $working[] = ['text' => $text, 'pedagogy_tag' => $ped($item['pedagogy_tag'] ?? null)];
        }

        $struggles = [];
        foreach ((array) ($data['struggles'] ?? []) as $item) {
            $key = (string) ($item['node_key'] ?? '');
            if (! in_array($key, $knownNodes, true)) {
                if ($key !== '') {
                    $dropped[] = "node:{$key}";
                }
                continue;
            }
            $struggles[] = [
                'node_key' => $key,
                'why' => trim((string) ($item['why'] ?? '')),
                'suggested_pedagogy' => $ped($item['suggested_pedagogy'] ?? null),
                'suggested_h5p_type' => $type($item['suggested_h5p_type'] ?? null),
            ];
        }

        $actions = [];
        foreach ((array) ($data['next_actions'] ?? []) as $item) {
            $action = trim((string) ($item['action'] ?? ''));
            if ($action === '') {
                continue;
            }
            $actions[] = [
                'action' => $action,
                'pedagogy_tag' => $ped($item['pedagogy_tag'] ?? null),
                'h5p_type' => $type($item['h5p_type'] ?? null),
                'rationale' => trim((string) ($item['rationale'] ?? '')),
            ];
        }

        return [
            'headline' => trim((string) ($data['headline'] ?? '')),
            'observations' => $observations,
            'what_is_working' => $working,
            'struggles' => $struggles,
            'next_actions' => $actions,
            'evidence_caveat' => trim((string) ($data['evidence_caveat'] ?? '')),
            'confidence' => isset($data['confidence']) ? round((float) $data['confidence'], 2) : null,
            'dropped_invalid_references' => array_values(array_unique($dropped)),
        ];
    }

    protected function noInsight(string $status, ?string $reason): array
    {
        return [
            'status' => $status,
            'reason' => $reason,
            'headline' => '',
            'observations' => [],
            'what_is_working' => [],
            'struggles' => [],
            'next_actions' => [],
            'evidence_caveat' => '',
            'confidence' => null,
            'dropped_invalid_references' => [],
            'provider' => null,
            'model' => null,
            'cached' => false,
            'generated_from' => null,
        ];
    }

    // ── Query plumbing ──────────────────────────────────────────────────────

    /**
     * Base event query: window, optional learner, and the tenant boundary
     * resolved through the learner tables (pal_telemetry_events has no tenant
     * column of its own — the same rule PalApiAuth uses).
     */
    protected function events(?int $learnerId, int $window, int $tenant)
    {
        $query = DB::table('pal_telemetry_events')
            ->where('timestamp', '>=', now()->subDays($window));

        if ($learnerId !== null) {
            return $query->where('actor_id', $learnerId);
        }

        if ($tenant > 0) {
            $tables = array_values(array_filter(
                ['tblstudent', 'tbluser'],
                fn (string $table) => Schema::hasTable($table) && Schema::hasColumn($table, 'sub_institute_id')
            ));

            if ($tables !== []) {
                $query->where(function ($outer) use ($tables, $tenant) {
                    foreach ($tables as $table) {
                        $outer->orWhereIn('actor_id', function ($sub) use ($table, $tenant) {
                            $sub->from($table)->select('id')->where('sub_institute_id', $tenant);
                        });
                    }
                });
            }
        }

        return $query;
    }

    /** COUNT of statements whose xAPI result.success is true / false. */
    protected function successExpr(string $expected): string
    {
        $values = $expected === 'true' ? "('true','1')" : "('false','0')";

        return "sum(case when JSON_UNQUOTE(JSON_EXTRACT(result, '$.success')) in {$values} then 1 else 0 end)";
    }

    /** COUNT of statements that report completion, by verb or by result flag. */
    protected function completionExpr(): string
    {
        $verbs = $this->registry->verbEventTypeMap();
        $completed = [];
        foreach ($verbs as $verb => $eventType) {
            if (in_array($eventType, ['content_completed', 'portfolio_submitted', 'content_passed'], true)) {
                $completed[] = DB::getPdo()->quote($verb);
            }
        }
        $in = $completed === [] ? "''" : implode(',', $completed);

        return "sum(case when verb in ({$in}) "
            . "or JSON_UNQUOTE(JSON_EXTRACT(result, '$.completion')) in ('true','1') then 1 else 0 end)";
    }

    protected function telemetryReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('pal_telemetry_events')
            && Schema::hasColumn('pal_telemetry_events', 'h5p_type');
    }
}
