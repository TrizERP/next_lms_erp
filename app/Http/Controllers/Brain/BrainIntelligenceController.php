<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\EntityIntelligence;
use App\Brain\Intelligence\GraphExplorer;
use App\Brain\Intelligence\HealthScores;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\Narrative;
use App\Brain\Intelligence\TrendAnalyzer;
use App\Brain\Intelligence\LmsAnalytics;
use App\Brain\Intelligence\RuleCatalogue;
use App\Brain\Intelligence\Uuid;
use App\Brain\Ingestion\FoundationIngestor;
use App\Brain\Support\LmsQueryScope;
use App\Brain\Support\SchemaCache;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The intelligence surface: the loop, its stages, and the one place a human
 * turns a recommendation into a decision.
 *
 * EVERY READ IS SCOPED TO THE TOKEN'S TENANT, never to the tenant id in the URL.
 * BrainTenantScope already rejects a mismatch, but this controller re-derives the
 * scope from the request attributes rather than the path segment so that a route
 * change can never widen it by accident.
 */
class BrainIntelligenceController extends Controller
{
    /**
     * One definition of the active population, shared with the pipeline, the
     * rules and BrainController. Whatever the Foundation screens count is
     * exactly what the intelligence is computed over.
     */
    use LmsQueryScope;

    /** Set by tenant(); the trait reads it. */
    protected string $tenantId = '';

    /* ---------------------------------------------------------------- loop */

    /**
     * The whole loop in one payload: what was observed, what it means, and what
     * follows from it.
     */
    public function intelligence(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'tenantId' => $tenant,
            'source' => DB::connection()->getDatabaseName(),
            'stages' => $this->stages($tenant),
            'signalsBySeverity' => $this->breakdown('hpbrain_signals', $tenant, 'severity'),
            'signalsByClassification' => $this->breakdown('hpbrain_signals', $tenant, 'classification', 20),
            'rootCauseFamilies' => $this->breakdown('hpbrain_hypotheses', $tenant, 'root_cause_family', 20),
            'recommendationsByCategory' => $this->breakdown('hpbrain_recommendations', $tenant, 'category'),
            'rules' => $this->ruleInventory($tenant),
            'lastRun' => $this->lastRun($tenant),
            'signals' => $this->signalList($tenant, 100),
        ]);
    }

    /** Re-run the loop against the live LMS data. */
    public function intelligenceRun(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        // A full pass over 3,438 students and 356,872 attendance rows across a
        // remote database legitimately outruns the default request time limit.
        @set_time_limit(600);

        $result = (new IntelligencePipeline($tenant))->run();

        $this->audit($request, 'intelligence.run', 'Intelligence', $tenant, [
            'signalsCreated' => $result['rules']['signalsCreated'],
            'signalsRefreshed' => $result['rules']['signalsRefreshed'],
            'reasoning' => $result['reasoning'],
            'elapsedMs' => $result['elapsedMs'],
        ]);

        return response()->json($result);
    }

    /** Every signal, newest first, with its rule and approved cause. */
    public function signals(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_signals');

        $query = DB::table('hpbrain_signals')->where('tenant_id', $tenant);

        foreach (['severity', 'status', 'classification'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        if ($request->filled('q')) {
            $like = '%'.$request->query('q').'%';
            $query->where(fn ($i) => $i->where('classification', 'like', $like)
                ->orWhere('rule_key', 'like', $like)
                ->orWhere('metadata', 'like', $like));
        }

        $total = (int) (clone $query)->count();

        return response()->json([
            'total' => $total,
            'severities' => $this->breakdown('hpbrain_signals', $tenant, 'severity'),
            'statuses' => $this->breakdown('hpbrain_signals', $tenant, 'status'),
            'classifications' => $this->breakdown('hpbrain_signals', $tenant, 'classification', 20),
            'data' => $this->decorateSignals(
                $query->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
                    ->orderByDesc('created_date')->limit(200)->get()
            ),
        ]);
    }

    /**
     * One signal, followed all the way down the loop.
     *
     * This is the screen that answers "why does the Brain think this?", so it
     * returns the evidence rows, the case, the hypothesis, the ordered reasoning
     * trail and the recommendation together. Splitting them across five requests
     * would let a caller render a conclusion without its support.
     */
    public function signalShow(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_signals');

        $signal = DB::table('hpbrain_signals')->where('tenant_id', $tenant)->where('id', $id)->first();
        if (! $signal) {
            return response()->json(['error' => 'signal_not_found'], 404);
        }

        $case = SchemaCache::hasTable('hpbrain_cases')
            ? DB::table('hpbrain_cases')->where('tenant_id', $tenant)->where('signal_id', $id)->first()
            : null;

        $hypothesis = ($case && SchemaCache::hasTable('hpbrain_hypotheses'))
            ? DB::table('hpbrain_hypotheses')->where('tenant_id', $tenant)->where('case_id', $case->id)->first()
            : null;

        $steps = ($case && SchemaCache::hasTable('hpbrain_reasoning_steps'))
            ? DB::table('hpbrain_reasoning_steps')->where('tenant_id', $tenant)->where('case_id', $case->id)
                ->orderBy('step_order')->get()->map(fn ($r) => (array) $r)->all()
            : [];

        $recommendations = [];
        if ($steps && SchemaCache::hasTable('hpbrain_recommendations')) {
            $recommendations = DB::table('hpbrain_recommendations')
                ->where('tenant_id', $tenant)
                ->whereIn('reasoning_step_id', array_column($steps, 'id'))
                ->get()->map(fn ($r) => (array) $r)->all();
        }

        return response()->json([
            'signal' => $this->decorateSignals(collect([$signal]))[0],
            'evidence' => SchemaCache::hasTable('hpbrain_evidence')
                ? DB::table('hpbrain_evidence')->where('tenant_id', $tenant)->where('signal_id', $id)
                    ->orderBy('ledger_sequence')->limit(200)->get()->map(function ($row) {
                        $row = (array) $row;
                        $row['content'] = json_decode((string) $row['content'], true) ?: $row['content'];
                        $row['provenance'] = json_decode((string) $row['provenance'], true) ?: $row['provenance'];

                        return $row;
                    })->all()
                : [],
            'case' => $case ? (array) $case : null,
            'hypothesis' => $hypothesis ? (array) $hypothesis : null,
            'reasoning' => $steps,
            'recommendations' => $this->decorateRecommendations($tenant, $recommendations),
            'rule' => RuleCatalogue::for((string) ($signal->rule_key ?? '')),
        ]);
    }

    /** Recommendations awaiting a decision, and the ones already decided. */
    public function recommendations(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_recommendations');

        $query = DB::table('hpbrain_recommendations')->where('tenant_id', $tenant);
        foreach (['category', 'status', 'priority'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return response()->json([
            'total' => (int) (clone $query)->count(),
            'categories' => $this->breakdown('hpbrain_recommendations', $tenant, 'category'),
            'statuses' => $this->breakdown('hpbrain_recommendations', $tenant, 'status'),
            'priorities' => $this->breakdown('hpbrain_recommendations', $tenant, 'priority'),
            'data' => $this->decorateRecommendations(
                $tenant,
                $query->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                    ->orderByDesc('confidence')->limit(200)->get()->map(fn ($r) => (array) $r)->all()
            ),
        ]);
    }

    /**
     * Record a human decision on a recommendation.
     *
     * THIS IS THE ONLY WAY A DECISION IS EVER CREATED, and it requires a named
     * LMS user from the verified token — never a system actor. The reference
     * Brain's autonomy ladder is observe|suggest|approve|autonomous, and nothing
     * in this installation sits above 'suggest' because no executor here has an
     * execution history to earn more. So the pipeline proposes and a person
     * disposes; a decision row that appeared without a person behind it would be
     * a fabricated governance record on the screen that governs real operations.
     *
     * Approving also opens an execution in 'queued' — a record that the work was
     * authorised, not a claim that it ran. It is completed through
     * executionComplete() when someone reports back.
     */
    public function decide(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_decisions');

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:approved,rejected,deferred',
            'rationale' => 'required|string|min:3|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $recommendation = DB::table('hpbrain_recommendations')->where('tenant_id', $tenant)->where('id', $id)->first();
        if (! $recommendation) {
            return response()->json(['error' => 'recommendation_not_found'], 404);
        }

        $userId = (string) $request->attributes->get('auth.userId');
        $status = (string) $request->input('status');
        $decisionId = Uuid::v4();
        $now = now()->format('Y-m-d H:i:s');
        $executionId = null;

        DB::transaction(function () use ($tenant, $recommendation, $decisionId, $status, $request, $userId, $now, &$executionId) {
            DB::table('hpbrain_decisions')->insert(SchemaCache::only('hpbrain_decisions', [
                'id' => $decisionId,
                'tenant_id' => $tenant,
                'recommendation_id' => (string) $recommendation->id,
                'decided_by' => $userId,
                'executor_type' => 'human',
                'rationale' => (string) $request->input('rationale'),
                'alternatives_considered' => json_encode((array) $request->input('alternatives', [])),
                'status' => $status,
                // The decision inherits the recommendation's confidence rather
                // than inventing one: the human is deciding whether to act on
                // that evidence, not producing a new estimate of it.
                'confidence' => (float) $recommendation->confidence,
                'explanation' => (string) $recommendation->title,
                'trace' => json_encode([
                    'recommendationId' => (string) $recommendation->id,
                    'reasoningStepId' => (string) ($recommendation->reasoning_step_id ?? ''),
                    'esoId' => (string) ($recommendation->eso_id ?? ''),
                    'decidedAt' => $now,
                ]),
                'approved_by' => $status === 'approved' ? $userId : null,
                'approved_date' => $status === 'approved' ? $now : null,
                'approval_note' => (string) $request->input('rationale'),
                'created_date' => $now,
            ]));

            DB::table('hpbrain_recommendations')->where('tenant_id', $tenant)->where('id', $recommendation->id)
                ->update(SchemaCache::only('hpbrain_recommendations', [
                    'status' => $status === 'approved' ? 'accepted' : ($status === 'rejected' ? 'rejected' : 'deferred'),
                    'updated_date' => $now,
                ]));

            if ($status !== 'approved' || ! SchemaCache::hasTable('hpbrain_eso_executions') || empty($recommendation->eso_id)) {
                return;
            }

            $executionId = Uuid::v4();
            DB::table('hpbrain_eso_executions')->insert(SchemaCache::only('hpbrain_eso_executions', [
                'id' => $executionId,
                'tenant_id' => $tenant,
                'eso_id' => (string) $recommendation->eso_id,
                'eso_definition_id' => (string) $recommendation->eso_id,
                'decision_id' => $decisionId,
                // 'queued', not 'completed'. Authorising work is not doing it,
                // and a row claiming otherwise would make the Automation screen
                // report operations that never happened.
                'status' => 'queued',
                'executed_by' => $userId,
                'executor_type' => 'human',
                'input' => json_encode(['recommendationId' => (string) $recommendation->id]),
                'created_date' => $now,
            ]));
        });

        $this->audit($request, 'recommendation.decided', 'Recommendation', $id, [
            'decisionId' => $decisionId,
            'status' => $status,
            'executionId' => $executionId,
        ]);

        return response()->json([
            'decisionId' => $decisionId,
            'executionId' => $executionId,
            'status' => $status,
        ], 201);
    }

    /**
     * Report back on an authorised execution, and capture the outcome.
     *
     * The outcome is what closes the loop: hpbrain_outcomes is what a later run
     * would use to learn whether this family of recommendation actually works
     * here. Without it the Brain proposes forever and never finds out.
     */
    public function executionComplete(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->requireTable('hpbrain_eso_executions');

        $validator = Validator::make($request->all(), [
            'result' => 'required|string|in:success,partial,failed',
            'feedback' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $execution = DB::table('hpbrain_eso_executions')->where('tenant_id', $tenant)->where('id', $id)->first();
        if (! $execution) {
            return response()->json(['error' => 'execution_not_found'], 404);
        }

        $result = (string) $request->input('result');
        $now = now()->format('Y-m-d H:i:s');
        $outcomeId = null;

        DB::transaction(function () use ($tenant, $execution, $result, $request, $now, &$outcomeId) {
            DB::table('hpbrain_eso_executions')->where('tenant_id', $tenant)->where('id', $execution->id)
                ->update(SchemaCache::only('hpbrain_eso_executions', [
                    'status' => $result === 'failed' ? 'failed' : 'completed',
                    'output' => json_encode(['result' => $result, 'feedback' => (string) $request->input('feedback', '')]),
                    'completed_date' => $now,
                ]));

            if (! SchemaCache::hasTable('hpbrain_outcomes') || empty($execution->decision_id)) {
                return;
            }

            $outcomeId = Uuid::v4();
            DB::table('hpbrain_outcomes')->insert(SchemaCache::only('hpbrain_outcomes', [
                'id' => $outcomeId,
                'tenant_id' => $tenant,
                'decision_id' => (string) $execution->decision_id,
                'result' => $result,
                'metrics' => json_encode(['executionId' => (string) $execution->id]),
                'kpis' => '{}',
                'evidence_ids' => '[]',
                'feedback' => (string) $request->input('feedback', ''),
                'confidence' => $result === 'success' ? 0.9 : ($result === 'partial' ? 0.6 : 0.3),
                'created_by' => (string) $request->attributes->get('auth.userId'),
                'created_date' => $now,
            ]));
        });

        $this->audit($request, 'execution.completed', 'Execution', $id, ['result' => $result, 'outcomeId' => $outcomeId]);

        return response()->json(['executionId' => $id, 'outcomeId' => $outcomeId, 'result' => $result]);
    }

    /* ----------------------------------------------------------- analytics */

    public function analytics(Request $request): JsonResponse
    {
        return response()->json((new LmsAnalytics($this->tenant($request)))->all());
    }

    /* ----------------------------------------------------------- knowledge */

    public function knowledge(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        $assets = SchemaCache::hasTable('hpbrain_knowledge_assets')
            ? DB::table('hpbrain_knowledge_assets')->where('tenant_id', $tenant)
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($i) => $i
                    ->where('title', 'like', '%'.$request->query('q').'%')
                    ->orWhere('content', 'like', '%'.$request->query('q').'%')))
                ->orderByDesc('reuse_count')->orderByDesc('confidence')->limit(200)
                ->get()->map(function ($row) {
                    $row = (array) $row;
                    $row['tags'] = json_decode((string) $row['tags'], true) ?: [];

                    return $row;
                })->all()
            : [];

        return response()->json([
            'tenantId' => $tenant,
            'metrics' => [
                ['key' => 'assets', 'label' => 'Knowledge assets', 'value' => $this->count('hpbrain_knowledge_assets', $tenant), 'available' => SchemaCache::hasTable('hpbrain_knowledge_assets')],
                ['key' => 'models', 'label' => 'Mental models', 'value' => $this->count('hpbrain_mental_models', $tenant), 'available' => SchemaCache::hasTable('hpbrain_mental_models')],
                ['key' => 'families', 'label' => 'Root-cause families', 'value' => count($this->breakdown('hpbrain_hypotheses', $tenant, 'root_cause_family', 50)), 'available' => SchemaCache::hasTable('hpbrain_hypotheses')],
                ['key' => 'evidence', 'label' => 'Evidence rows', 'value' => $this->count('hpbrain_evidence', $tenant), 'available' => SchemaCache::hasTable('hpbrain_evidence')],
            ],
            'assets' => $assets,
            'mentalModels' => SchemaCache::hasTable('hpbrain_mental_models')
                ? DB::table('hpbrain_mental_models')->where('tenant_id', $tenant)
                    ->orderByDesc('reinforcement_count')->limit(100)->get()->map(function ($row) {
                        $row = (array) $row;
                        $row['rules'] = json_decode((string) $row['rules'], true) ?: [];

                        return $row;
                    })->all()
                : [],
            'byCategory' => $this->breakdown('hpbrain_knowledge_assets', $tenant, 'category', 20),
        ]);
    }

    /* ---------------------------------------------------------- automation */

    public function automation(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        return response()->json([
            'tenantId' => $tenant,
            'metrics' => [
                ['key' => 'esos', 'label' => 'ESO definitions', 'value' => $this->count('hpbrain_eso_definitions', $tenant), 'available' => SchemaCache::hasTable('hpbrain_eso_definitions')],
                ['key' => 'policies', 'label' => 'Policies', 'value' => $this->count('hpbrain_policies', $tenant), 'available' => SchemaCache::hasTable('hpbrain_policies')],
                ['key' => 'decisions', 'label' => 'Decisions', 'value' => $this->count('hpbrain_decisions', $tenant), 'available' => SchemaCache::hasTable('hpbrain_decisions')],
                ['key' => 'executions', 'label' => 'Executions', 'value' => $this->count('hpbrain_eso_executions', $tenant), 'available' => SchemaCache::hasTable('hpbrain_eso_executions')],
                ['key' => 'outcomes', 'label' => 'Outcomes', 'value' => $this->count('hpbrain_outcomes', $tenant), 'available' => SchemaCache::hasTable('hpbrain_outcomes')],
            ],
            'esos' => SchemaCache::hasTable('hpbrain_eso_definitions')
                ? DB::table('hpbrain_eso_definitions')->where('tenant_id', $tenant)->orderBy('eso_code')->limit(200)
                    ->get(['id', 'eso_code', 'name', 'objective', 'trust_level', 'status', 'provenance', 'allowed_executor_classes', 'procedure_steps', 'updated_date'])
                    ->map(function ($row) {
                        $row = (array) $row;
                        foreach (['allowed_executor_classes', 'procedure_steps'] as $field) {
                            $row[$field] = json_decode((string) $row[$field], true) ?: [];
                        }

                        return $row;
                    })->all()
                : [],
            'policies' => SchemaCache::hasTable('hpbrain_policies')
                ? DB::table('hpbrain_policies')->where('tenant_id', $tenant)->limit(50)->get()->map(function ($row) {
                    $row = (array) $row;
                    foreach (['allowed_executor_classes', 'trust_levels', 'rules', 'approval_gates', 'data_access_rules', 'regulatory_constraints'] as $field) {
                        if (isset($row[$field])) {
                            $row[$field] = json_decode((string) $row[$field], true) ?: [];
                        }
                    }

                    return $row;
                })->all()
                : [],
            'decisions' => $this->decisionFeed($tenant),
            'executions' => SchemaCache::hasTable('hpbrain_eso_executions')
                ? DB::table('hpbrain_eso_executions')->where('tenant_id', $tenant)->orderByDesc('created_date')->limit(100)
                    ->get()->map(fn ($r) => (array) $r)->all()
                : [],
            'outcomes' => SchemaCache::hasTable('hpbrain_outcomes')
                ? DB::table('hpbrain_outcomes')->where('tenant_id', $tenant)->orderByDesc('created_date')->limit(100)
                    ->get()->map(fn ($r) => (array) $r)->all()
                : [],
        ]);
    }

    /* --------------------------------------------------------- executive view */

    /**
      * What a principal needs on one screen: health, what changed, what is at
      * risk, and what to do about it.
      *
      * ORDERED BY WHAT DESERVES ATTENTION FIRST, not by table. The top findings
      * are the open signals sorted by severity, each already phrased as a card;
      * the health dimensions carry their own formula and explanation. A dimension
      * that cannot be scored says why rather than showing a zero.
      */
    public function executive(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $trends = new TrendAnalyzer($tenant);
        $signals = $this->signalList($tenant, 60);

        $ranked = $signals;
        usort($ranked, function ($a, $b) {
            $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            return [$order[$a['severity']] ?? 4, -($a['confidence']['value'] ?? 0)]
                <=> [$order[$b['severity']] ?? 4, -($b['confidence']['value'] ?? 0)];
        });

        $health = (new HealthScores($tenant))->all();
        $actions = $this->prioritisedActions($tenant, $ranked);
        $loop = $this->loopStages($tenant);
        $intelligence = $this->intelligenceSummary($health, $ranked, $trends, $actions);
        $ingestion = $this->ingestionSummary($tenant);
        $graph = $this->graphSummary($tenant);
        $evidence = $this->evidenceSummary($ranked);
        $academicYear = $this->currentAcademicYear($tenant);
        $summary = $this->summaryCards($tenant);

        return response()->json([
            'tenantId' => $tenant,
            'organization' => $this->organizationName($tenant),
            'generatedAt' => now()->toIso8601String(),
            // When the RULES last ran, which is a different fact from when this
            // response was built: the counts above are read live, the findings
            // below them are only as current as the last pipeline run.
            'findingsRefreshedAt' => $this->findingsRefreshedAt($tenant),
            'academicYear' => $academicYear,
            'summary' => $summary,
            'health' => $health,
            'topFindings' => array_slice($ranked, 0, 6),
            'whatChanged' => $this->whatChanged($trends),
            'atRisk' => $this->atRisk($tenant, $trends),
            'actions' => $actions,
            'counts' => [
                'openFindings' => count($signals),
                'high' => count(array_filter($signals, fn ($s) => in_array($s['severity'], ['high', 'critical'], true))),
                'awaitingDecision' => SchemaCache::hasTable('hpbrain_recommendations')
                    ? (int) DB::table('hpbrain_recommendations')->where('tenant_id', $tenant)->where('status', 'pending')->count()
                    : 0,
            ],
            'loop' => $loop,
            'intelligence' => $intelligence,
            'ingestion' => $ingestion,
            'graph' => $graph,
            'evidence' => $evidence,
        ]);
    }

    /**
     * The most recent moment any signal for this tenant was raised or refreshed.
     *
     * Null when the pipeline has never run here — the screen then says nothing
     * about freshness rather than implying the findings are current.
     */
    private function findingsRefreshedAt(string $tenant): ?string
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return null;
        }

        $column = SchemaCache::hasColumn('hpbrain_signals', 'updated_date') ? 'updated_date' : 'created_date';
        $at = DB::table('hpbrain_signals')->where('tenant_id', $tenant)->max($column);

        return $at ? \Illuminate\Support\Carbon::parse($at)->toIso8601String() : null;
    }

    /* ------------------------------------------------- executive intelligence */

    /** @return array{strengths: array, risks: array, opportunities: array, recommendedFocus: array} */
    private function intelligenceSummary(array $health, array $ranked, TrendAnalyzer $trends, array $actions): array
    {
        $strengths = [];
        $risks = [];
        $opportunities = [];

        foreach ($health['dimensions'] as $dimension) {
            if (! $dimension['available']) {
                continue;
            }

            if ((int) ($dimension['score'] ?? 0) >= 75) {
                $strengths[] = [
                    'dimension' => $dimension['label'],
                    'score' => $dimension['score'],
                    'band' => $dimension['band'],
                    'why' => $dimension['why'],
                    'headline' => $dimension['headline'],
                ];
            } elseif ((int) ($dimension['score'] ?? 0) >= 50) {
                $opportunities[] = [
                    'dimension' => $dimension['label'],
                    'score' => $dimension['score'],
                    'band' => $dimension['band'],
                    'why' => $dimension['why'],
                    'action' => $dimension['action'],
                    'headline' => $dimension['headline'],
                ];
            } else {
                $risks[] = [
                    'type' => 'dimension',
                    'dimension' => $dimension['label'],
                    'score' => $dimension['score'],
                    'band' => $dimension['band'],
                    'why' => $dimension['why'],
                    'action' => $dimension['action'],
                    'headline' => $dimension['headline'],
                ];
            }
        }

        foreach ($ranked as $signal) {
            if (in_array($signal['severity'], ['critical', 'high'], true)) {
                $risks[] = [
                    'type' => 'signal',
                    'signalId' => $signal['id'],
                    'title' => $signal['title'],
                    'severity' => $signal['severity'],
                    'whyItMatters' => $signal['whyItMatters'],
                    'evidence' => $signal['evidence'],
                    'recommendation' => $signal['recommendation'],
                    'owner' => $signal['owner'],
                    'affected' => $signal['affected'],
                ];
            }
        }

        $recommendedFocus = $actions[0] ?? null;
        if ($recommendedFocus) {
            $recommendedFocus['expectedBenefit'] = $this->expectedBenefit($recommendedFocus, $ranked);
        }

        return [
            'strengths' => array_slice($strengths, 0, 5),
            'risks' => array_slice($risks, 0, 8),
            'opportunities' => array_slice($opportunities, 0, 5),
            'recommendedFocus' => $recommendedFocus,
        ];
    }

    private function expectedBenefit(array $action, array $ranked): string
    {
        $because = $action['because'] ?? [];

        foreach ($ranked as $signal) {
            if (in_array($signal['title'], $because, true)) {
                $affected = $signal['affected']['count'] ?? null;

                if ($affected !== null && $affected > 0) {
                    return sprintf(
                        'Directly affects %s %s.',
                        number_format($affected),
                        $signal['affected']['unit'] ?? 'people'
                    );
                }
            }
        }

        return 'Impact estimate unavailable.';
    }

    /** @return array<int, array{key: string, label: string, count: int, available: bool}> */
    private function loopStages(string $tenant): array
    {
        $stages = [
            ['key' => 'signal', 'label' => 'Signal', 'table' => 'hpbrain_signals'],
            ['key' => 'evidence', 'label' => 'Evidence', 'table' => 'hpbrain_evidence'],
            ['key' => 'case', 'label' => 'Investigation', 'table' => 'hpbrain_cases'],
            ['key' => 'hypothesis', 'label' => 'Hypothesis', 'table' => 'hpbrain_hypotheses'],
            ['key' => 'reasoning', 'label' => 'Reasoning', 'table' => 'hpbrain_reasoning_steps'],
            ['key' => 'recommendation', 'label' => 'Recommendation', 'table' => 'hpbrain_recommendations'],
            ['key' => 'decision', 'label' => 'Decision', 'table' => 'hpbrain_decisions'],
            ['key' => 'execution', 'label' => 'Execution', 'table' => 'hpbrain_eso_executions'],
            ['key' => 'outcome', 'label' => 'Outcome', 'table' => 'hpbrain_outcomes'],
            ['key' => 'learning', 'label' => 'Learning', 'table' => 'hpbrain_mental_models'],
        ];

        return array_map(function ($stage) use ($tenant) {
            $stage['count'] = $this->count($stage['table'], $tenant);
            $stage['available'] = SchemaCache::hasTable($stage['table']);

            return $stage;
        }, $stages);
    }

    /** @return array<string, mixed> */
    private function ingestionSummary(string $tenant): array
    {
        if (! class_exists(FoundationIngestor::class)) {
            return ['available' => false, 'inventory' => []];
        }

        $ingestor = new FoundationIngestor($tenant, 'system');

        return [
            'available' => true,
            'inventory' => $ingestor->inventory(),
        ];
    }

    /** @return array{available: bool, roots: array, organization: array} */
    private function graphSummary(string $tenant): array
    {
        if (! class_exists(GraphExplorer::class)) {
            return ['available' => false, 'roots' => [], 'organization' => null];
        }

        $explorer = new GraphExplorer($tenant);

        return [
            'available' => true,
            'roots' => $explorer->roots(),
            'organization' => $explorer->expand('organization', $tenant),
        ];
    }

    /** @return array<int, array{signalTitle: string, evidence: array}> */
    private function evidenceSummary(array $ranked): array
    {
        $out = [];

        foreach (array_slice($ranked, 0, 4) as $signal) {
            if (empty($signal['evidence'])) {
                continue;
            }

            $out[] = [
                'signalTitle' => $signal['title'],
                'severity' => $signal['severity'],
                'evidence' => $signal['evidence'],
                'whatHappened' => $signal['whatHappened'],
                'whyItMatters' => $signal['whyItMatters'],
                'recommendation' => $signal['recommendation'],
                'affected' => $signal['affected'],
            ];
        }

        return $out;
    }

    private function currentAcademicYear(string $tenant): array
    {
        if (! SchemaCache::hasTable('academic_year')) {
            return ['label' => 'Academic year', 'title' => null, 'syear' => null];
        }

        $today = now()->toDateString();

        $row = DB::table('academic_year')
            ->where('sub_institute_id', $tenant)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderByDesc('sort_order')
            ->first();

        if (! $row) {
            $row = DB::table('academic_year')
                ->where('sub_institute_id', $tenant)
                ->orderByDesc('sort_order')
                ->first();
        }

        if (! $row) {
            return ['label' => 'Academic year', 'title' => null, 'syear' => null];
        }

        return [
            'label' => 'Academic year',
            'title' => (string) ($row->title ?? null),
            'shortName' => (string) ($row->short_name ?? null),
            'syear' => (string) ($row->syear ?? null),
            'startDate' => (string) ($row->start_date ?? null),
            'endDate' => (string) ($row->end_date ?? null),
        ];
    }

    /** @return array{foundation: array, brain: array} */
     private function summaryCards(string $tenant): array
    {
        $foundation = [
            'departments' => $this->lmsCount('hrms_departments'),
            'people' => $this->lmsCount('tbluser'),
            'students' => $this->lmsCount('tblstudent'),
            'capabilities' => $this->count('hpbrain_capabilities', $tenant),
        ];

        $brain = [
            'signals' => $this->count('hpbrain_signals', $tenant),
            'evidence' => $this->count('hpbrain_evidence', $tenant),
            'recommendations' => $this->count('hpbrain_recommendations', $tenant),
            'decisions' => $this->count('hpbrain_decisions', $tenant),
            'executions' => $this->count('hpbrain_eso_executions', $tenant),
            'outcomes' => $this->count('hpbrain_outcomes', $tenant),
        ];

        return [
            'foundation' => $foundation,
            'brain' => $brain,
        ];
    }

    /**
      * The movements worth a principal's attention this month.
      *
      * Every entry states its own comparison window. Where there is not enough
      * history to compare, the entry says so instead of being omitted — "we
      * cannot tell yet" is information, and silently dropping the row would let
      * the reader assume the metric is fine.
      *
      * @return array<int, array<string, mixed>>
      */
    private function whatChanged(TrendAnalyzer $trends): array
    {
        $out = [];

        $attendance = $trends->attendanceTrend();
        $out[] = $attendance['available']
            ? [
                'key' => 'attendance',
                'label' => 'Student attendance',
                'available' => true,
                'value' => $this->pct($attendance['current']['rate']),
                'change' => $attendance['changePoints'],
                'unit' => 'points',
                'direction' => $attendance['direction'],
                'note' => sprintf('%s vs %s', Narrative::monthName($attendance['current']['period']), Narrative::monthName($attendance['previous']['period'])),
            ]
            : ['key' => 'attendance', 'label' => 'Student attendance', 'available' => false, 'note' => $attendance['reason']];

        $homework = $trends->homeworkTrend();
        $out[] = $homework['available']
            ? [
                'key' => 'homework',
                'label' => 'Homework submission',
                'available' => true,
                'value' => $this->pct($homework['current']['rate']),
                'change' => $homework['changePoints'],
                'unit' => 'points',
                'direction' => $homework['direction'],
                'note' => sprintf('%s vs %s', Narrative::monthName($homework['current']['period']), Narrative::monthName($homework['previous']['period'])),
            ]
            : ['key' => 'homework', 'label' => 'Homework submission', 'available' => false, 'note' => $homework['reason']];

        $fees = $trends->feeTrend();
        $out[] = $fees['available']
            ? [
                'key' => 'fees',
                'label' => 'Fee collection',
                'available' => true,
                'value' => Narrative::money($fees['current']['collected']),
                'change' => $fees['changePercent'],
                'unit' => '%',
                'direction' => $fees['direction'],
                'note' => sprintf('%s vs %s', Narrative::monthName($fees['current']['period']), Narrative::monthName($fees['previous']['period'])),
            ]
            : ['key' => 'fees', 'label' => 'Fee collection', 'available' => false, 'note' => $fees['reason']];

        return $out;
    }

    /**
     * Named things at risk — classes and students, not counts.
     *
     * "47 students are at risk" is a statistic. This returns which ones, so the
     * next step is a phone call rather than another query.
     *
     * @return array<string, mixed>
     */
    private function atRisk(string $tenant, TrendAnalyzer $trends): array
    {
        $byClass = $trends->attendanceByClass();
        $laggingClasses = array_values(array_filter(
            $byClass['classes'],
            fn ($c) => $c['gapPoints'] <= -(float) config('brain.thresholds.class_attendance_gap_points', 4.0)
        ));

        $intelligence = new EntityIntelligence($tenant);
        $departments = array_values(array_filter(
            $intelligence->departments(20),
            fn ($d) => $d['risks'] !== []
        ));

        return [
            'classes' => array_map(fn ($c) => [
                'id' => $c['classId'],
                'name' => $c['className'],
                'value' => $this->pct($c['rate']),
                'gap' => $c['gapPoints'],
                'baseline' => $byClass['baseline'],
                'students' => $c['students'],
                'note' => sprintf('%s points below the school baseline of %s%%', $this->num(abs($c['gapPoints'])), $this->num($byClass['baseline'])),
            ], array_slice($laggingClasses, 0, 6)),
            'students' => array_map(fn ($s) => [
                'id' => $s['studentId'],
                'name' => $s['name'],
                'enrollmentNo' => $s['enrollmentNo'],
                'value' => $this->pct($s['rate']),
                'note' => sprintf('%d absences across %d recorded days', $s['absences'], $s['marks']),
            ], array_slice($trends->chronicAbsentees(10), 0, 10)),
            'departments' => array_map(fn ($d) => [
                'id' => $d['id'],
                'name' => $d['name'],
                'value' => $d['score'].'/100',
                'note' => $d['risks'][0]['label'] ?? '',
                'headcount' => $d['headcount'],
            ], array_slice($departments, 0, 6)),
        ];
    }

    /**
     * What to do next, deduplicated by action.
     *
     * Several findings share one remedy — three ownership gaps all end in
     * "assign a head". Listing the remedy once, with the findings behind it,
     * turns twenty cards into a short list somebody can actually work through.
     *
     * @return array<int, array<string, mixed>>
     */
    private function prioritisedActions(string $tenant, array $rankedSignals): array
    {
        $byAction = [];

        foreach ($rankedSignals as $signal) {
            $action = $signal['recommendation'];
            if (! $action) {
                continue;
            }

            if (! isset($byAction[$action])) {
                $byAction[$action] = [
                    'action' => $action,
                    'owner' => $signal['owner'],
                    'priority' => $signal['priority'],
                    'confidence' => $signal['confidence']['band'],
                    'because' => [],
                ];
            }

            $byAction[$action]['because'][] = $signal['title'];
        }

        $actions = array_values($byAction);
        $order = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];
        usort($actions, fn ($a, $b) => [$order[$a['priority']] ?? 4, -count($a['because'])]
            <=> [$order[$b['priority']] ?? 4, -count($b['because'])]);

        return array_slice($actions, 0, 8);
    }

    /* ---------------------------------------------------- entity intelligence */

    /** Intelligence about one student. */
    public function studentIntelligence(Request $request, string $tenantId, string $id): JsonResponse
    {
        $profile = (new EntityIntelligence($this->tenant($request)))->student($id);

        return response()->json($profile, ($profile['available'] ?? false) ? 200 : 404);
    }

    /** Every class with recorded attendance, against the school baseline. */
    public function classIntelligence(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $classes = (new EntityIntelligence($tenant))->classes();
        $byClass = (new TrendAnalyzer($tenant))->attendanceByClass();

        return response()->json([
            'available' => $classes !== [],
            'reason' => $classes === [] ? 'No class has enough recorded attendance to compare.' : null,
            'baseline' => $byClass['baseline'],
            'marks' => $byClass['marks'],
            'classes' => $classes,
        ]);
    }

    /** Every department that holds staff, scored and explained. */
    public function departmentIntelligence(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $departments = (new EntityIntelligence($tenant))->departments();

        return response()->json([
            'available' => $departments !== [],
            'reason' => $departments === [] ? 'No department in this institute holds any staff.' : null,
            'departments' => $departments,
            'note' => 'Only departments that actually hold staff are listed; empty taxonomy nodes are excluded.',
        ]);
    }

    /** Teaching activity, with an honest statement of how much is attributable. */
    public function teacherIntelligence(Request $request): JsonResponse
    {
        return response()->json((new EntityIntelligence($this->tenant($request)))->teachers());
    }

    /* ------------------------------------------------------------------ graph */

    public function graph(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $explorer = new GraphExplorer($tenant);
        $type = (string) $request->query('type', '');
        $id = (string) $request->query('id', '');

        if ($type !== '' && $id !== '') {
            return response()->json($explorer->expand($type, $id));
        }

        if ($type !== '') {
            return response()->json([
                'available' => true,
                'type' => $type,
                'nodes' => $explorer->nodes($type, trim((string) $request->query('q', ''))),
            ]);
        }

        return response()->json([
            'available' => true,
            'roots' => $explorer->roots(),
            'organization' => $explorer->expand('organization', $tenant),
        ]);
    }

    /* ------------------------------------------------------------ students */

    /** The student roll, and the intelligence derived from it. */
    public function students(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if (! SchemaCache::hasTable('tblstudent')) {
            return response()->json(['total' => 0, 'data' => [], 'available' => false]);
        }

        $query = $this->lmsStudents();
        if ($request->filled('q')) {
            $like = '%'.$request->query('q').'%';
            $query->where(fn ($i) => $i->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('enrollment_no', 'like', $like)
                ->orWhere('email', 'like', $like));
        }

        $total = (int) (clone $query)->count();
        $rows = $query->orderBy('first_name')->limit(300)
            ->get(['id', 'enrollment_no', 'first_name', 'middle_name', 'last_name', 'gender', 'dob', 'mobile', 'email', 'admission_year', 'admission_date', 'status'])
            ->map(fn ($r) => (array) $r)->all();

        // Absence counts for exactly the students on this page, so the roll shows
        // the engagement signal beside the person it concerns.
        $absences = [];
        if ($rows && SchemaCache::hasTable('attendance_student')) {
            $absences = DB::table('attendance_student')
                ->where('sub_institute_id', $tenant)->where('attendance_code', 'A')
                ->whereIn('student_id', array_column($rows, 'id'))
                ->select('student_id', DB::raw('COUNT(*) as absences'))
                ->groupBy('student_id')->pluck('absences', 'student_id')->all();
        }

        return response()->json([
            'total' => $total,
            'available' => true,
            'signals' => $this->signalList($tenant, 50, ['Student']),
            'analytics' => (new LmsAnalytics($tenant))->all()['students'] ?? [],
            'data' => array_map(function ($row) use ($absences) {
                $row['absences'] = (int) ($absences[$row['id']] ?? 0);
                $row['record_complete'] = ! empty($row['enrollment_no']) && ! empty($row['dob'])
                    && (! empty($row['mobile']) || ! empty($row['email']));

                return $row;
            }, $rows),
        ]);
    }

    /* ------------------------------------------------------------- helpers */

    /** Loop stage counts, in the order the loop runs. */
    private function stages(string $tenant): array
    {
        $stages = [
            ['key' => 'signal', 'label' => 'Signal', 'table' => 'hpbrain_signals'],
            ['key' => 'evidence', 'label' => 'Evidence', 'table' => 'hpbrain_evidence'],
            ['key' => 'case', 'label' => 'Case', 'table' => 'hpbrain_cases'],
            ['key' => 'hypothesis', 'label' => 'Hypothesis', 'table' => 'hpbrain_hypotheses'],
            ['key' => 'reasoning', 'label' => 'Reasoning', 'table' => 'hpbrain_reasoning_steps'],
            ['key' => 'recommendation', 'label' => 'Recommendation', 'table' => 'hpbrain_recommendations'],
            ['key' => 'decision', 'label' => 'Decision', 'table' => 'hpbrain_decisions'],
            ['key' => 'execution', 'label' => 'Execution', 'table' => 'hpbrain_eso_executions'],
            ['key' => 'outcome', 'label' => 'Outcome', 'table' => 'hpbrain_outcomes'],
        ];

        return array_map(function ($stage) use ($tenant) {
            $stage['count'] = $this->count($stage['table'], $tenant);
            $stage['available'] = SchemaCache::hasTable($stage['table']);

            return $stage;
        }, $stages);
    }

    /**
     * Every rule the engine knows, and whether it is currently firing.
     *
     * A rule that is NOT firing is as informative as one that is — it means the
     * institute is clean on that dimension — so the inventory lists all of them
     * rather than only the ones with a signal behind them.
     */
    private function ruleInventory(string $tenant): array
    {
        $open = SchemaCache::hasTable('hpbrain_signals')
            ? DB::table('hpbrain_signals')->where('tenant_id', $tenant)->whereNotNull('rule_key')
                ->select('rule_key', 'severity', 'status', 'metadata', 'created_date')->get()->keyBy('rule_key')
            : collect();

        $out = [];
        foreach (RuleCatalogue::CAUSES as $ruleKey => $cause) {
            $signal = $open[$ruleKey] ?? null;
            $metadata = $signal ? (json_decode((string) $signal->metadata, true) ?: []) : [];

            $out[] = [
                'rule' => $ruleKey,
                'family' => $cause['family'],
                'category' => $cause['category'],
                'action' => $cause['action'],
                'firing' => $signal !== null,
                'severity' => $signal->severity ?? null,
                'status' => $signal->status ?? null,
                'title' => $metadata['title'] ?? null,
                'affectedCount' => $metadata['affectedCount'] ?? null,
                'totalCount' => $metadata['totalCount'] ?? null,
                'raisedAt' => $signal->created_date ?? null,
            ];
        }

        return $out;
    }

    private function lastRun(string $tenant): ?array
    {
        if (! SchemaCache::hasTable('hpbrain_audit_logs')) {
            return null;
        }

        $row = DB::table('hpbrain_audit_logs')->where('tenant_id', $tenant)
            ->where('action', 'intelligence.run')->orderByDesc('created_at')->first();

        if (! $row) {
            return null;
        }

        return ['at' => (string) $row->created_at, 'changes' => json_decode((string) $row->changes, true)];
    }

    /** @return array<int, array<string, mixed>> */
    private function signalList(string $tenant, int $limit, array $entityTypes = []): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return [];
        }

        $query = DB::table('hpbrain_signals')->where('tenant_id', $tenant);
        if ($entityTypes) {
            $query->whereIn('related_entity_type', $entityTypes);
        }

        return $this->decorateSignals(
            $query->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
                ->orderByDesc('created_date')->limit($limit)->get()
        );
    }

    /**
     * Give a signal row the things a screen needs but the table does not store:
     * its decoded metadata, its human title, and the approved cause behind it.
     */
    /**
     * Present signals the way a person reads them.
     *
     * The row goes through Narrative rather than out raw, so every consumer gets
     * the same plain-language card — title, what happened, why it matters,
     * labelled evidence, cause, action, owner. The engine's own vocabulary
     * survives under `technical` for support and debugging, but nothing on a
     * screen has to render `rule_key` to be useful.
     */
    private function decorateSignals($rows): array
    {
        return $rows->map(function ($row) {
            $row = (array) $row;
            $metadata = json_decode((string) ($row['metadata'] ?? '{}'), true);
            $row['metadata'] = is_array($metadata) ? $metadata : [];

            return Narrative::forSignal($row);
        })->values()->all();
    }

    /** Attach the ESO a recommendation would run, and any decision already taken. */
    private function decorateRecommendations(string $tenant, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $esoIds = array_values(array_filter(array_column($rows, 'eso_id')));
        $esos = ($esoIds && SchemaCache::hasTable('hpbrain_eso_definitions'))
            ? DB::table('hpbrain_eso_definitions')->whereIn('id', $esoIds)->pluck('eso_code', 'id')->all()
            : [];

        $decisions = SchemaCache::hasTable('hpbrain_decisions')
            ? DB::table('hpbrain_decisions')->where('tenant_id', $tenant)
                ->whereIn('recommendation_id', array_column($rows, 'id'))
                ->orderByDesc('created_date')->get()->keyBy('recommendation_id')
            : collect();

        return array_map(function ($row) use ($esos, $decisions) {
            $row['eso_code'] = $esos[$row['eso_id'] ?? ''] ?? null;
            $decision = $decisions[$row['id']] ?? null;
            $row['decision'] = $decision ? (array) $decision : null;

            return $row;
        }, $rows);
    }

    private function decisionFeed(string $tenant): array
    {
        if (! SchemaCache::hasTable('hpbrain_decisions')) {
            return [];
        }

        $rows = DB::table('hpbrain_decisions')->where('tenant_id', $tenant)
            ->orderByDesc('created_date')->limit(100)->get()->map(fn ($r) => (array) $r)->all();

        if ($rows === [] || ! SchemaCache::hasTable('tbluser')) {
            return $rows;
        }

        // A decision is only a governance record if the person behind it is
        // named, so the LMS user is resolved rather than left as a bare id.
        $names = DB::table('tbluser')->whereIn('id', array_filter(array_column($rows, 'decided_by')))
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [(string) $u->id => trim($u->first_name.' '.$u->last_name)])->all();

        return array_map(function ($row) use ($names) {
            $row['decided_by_name'] = $names[(string) $row['decided_by']] ?? null;

            return $row;
        }, $rows);
    }

    private function tenant(Request $request): string
    {
        return $this->tenantId = (string) $request->attributes->get(
            'tenantId',
            $request->attributes->get('auth.tenantId')
        );
    }

    private function organizationName(string $tenant): string
    {
        $name = SchemaCache::hasTable('hpbrain_organizations')
            ? DB::table('hpbrain_organizations')->where('tenant_id', $tenant)->value('name')
            : null;

        return (string) ($name ?: 'This organization');
    }

    private function pct($value): string
    {
        return $this->num($value).'%';
    }

    private function num($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
    }

    private function count(string $table, string $tenant): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('tenant_id', $tenant)->count();
    }

    /**
     * A GROUP BY, with the stored code rendered as words.
     *
     * The engine stores `organization_ownership` and `root_cause_family`
     * values like `process_adoption_gap`. Those are the right keys in a
     * database and the wrong labels on a chart a principal is reading, so the
     * code is humanised here — once, for every screen — while `code` keeps the
     * original for anything that needs to filter on it.
     */
    private function breakdown(string $table, string $tenant, string $column, int $limit = 10): array
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)->where('tenant_id', $tenant)
            ->select($column.' as label', DB::raw('COUNT(*) as value'))
            ->groupBy($column)->orderByDesc('value')->limit($limit)->get()
            ->map(fn ($r) => [
                'label' => self::humaniseLabel((string) ($r->label ?? '')),
                'code' => (string) ($r->label ?? ''),
                'value' => (int) $r->value,
            ])->all();
    }

    /** `process_adoption_gap` -> `Process adoption gap`. */
    public static function humaniseLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Unspecified';
        }

        return ucfirst(str_replace('_', ' ', $value));
    }

    private function requireTable(string $table): void
    {
        if (! SchemaCache::hasTable($table)) {
            abort(response()->json(['error' => 'brain_schema_missing', 'table' => $table], 503));
        }
    }

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $changes): void
    {
        try {
            if (! SchemaCache::hasTable('hpbrain_audit_logs')) {
                return;
            }

            $tenant = $this->tenant($request);

            DB::table('hpbrain_audit_logs')->insert(SchemaCache::only('hpbrain_audit_logs', [
                'id' => Uuid::v4(),
                'tenant_id' => $tenant,
                'org_id' => 'org-'.$tenant,
                'entity_type' => $entityType,
                'entity_id' => substr($entityId, 0, 36),
                'action' => $action,
                'actor_id' => (string) $request->attributes->get('auth.userId'),
                'actor_name' => (string) $request->attributes->get('auth.role'),
                'changes' => json_encode($changes),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'source' => 'lms',
                'status' => 'success',
                'created_at' => now(),
            ]));
        } catch (\Throwable $e) {
            // Never turn the business action into a 500 because audit storage is unavailable.
        }
    }
}
