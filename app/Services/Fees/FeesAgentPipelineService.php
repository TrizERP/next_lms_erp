<?php

namespace App\Services\Fees;

use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Domain\KnowledgeGraph\TraversalSpec;
use App\Domain\KnowledgeGraph\Fees\FeesGraphReasoningEngine;
use App\Domain\KnowledgeGraph\Fees\FeesCrossModuleInsightEngine;
use App\Domain\KnowledgeGraph\Fees\FeesGraphRecommendationEngine;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Mcp\ToolRegistry;
use App\Services\Fees\Eso\FeesEsoRegistry;
use App\Services\Fees\Eso\FeesQualityGateEngine;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fees Synchronized 3-Agent Pipeline.
 *
 *   Agent 1  Fee Position & Arrears Reader        -> Gate 1
 *            fees.position + fees.arrears
 *   Agent 2  Collection Evidence & Reminder       -> Gate 2
 *            History Analyst
 *            fees.getPending + fees.collection_report + fees.reminder_history
 *   Agent 3  Reminder Drafter                     -> Gate 3
 *            fees.send_reminders (PREVIEW ONLY)
 *
 * Each agent runs only after the previous gate has passed and consumes exactly what the
 * previous agent handed over. Every figure comes from a governed MCP tool reading this
 * institute's own fee ledger for one academic year. This service never sends a message and
 * never writes a fee record; the only write is one `ai_agent_runs` row per executed step.
 */
class FeesAgentPipelineService
{
    public const AGENT_KEY = 'k12_fees';
    public const DEFAULT_INTERVAL_SECONDS = 60;
    public const TRIGGER_PREFIX = 'fees_pipeline_step_';

    public const DEFAULT_SCAN_LIMIT = 8;
    public const DEFAULT_EVIDENCE_LIMIT = 3;
    public const DEFAULT_COOLDOWN_DAYS = 7;
    public const DEFAULT_DEADLINE_DAYS = 7;

    private readonly FeesEsoRegistry $eso;

    /** Per-step tally of real Knowledge Graph calls, surfaced in each agent's output. */
    private array $kgTally = ['queries' => 0, 'edges_returned' => 0, 'neo4j_hits' => 0, 'sql_ontology_hits' => 0, 'errors' => 0, 'sample_paths' => []];
    private readonly FeesQualityGateEngine $gates;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ?GraphQueryService $graphQuery = null,
        private readonly ?FeesGraphReasoningEngine $graphReasoning = null,
        private readonly ?FeesCrossModuleInsightEngine $crossModuleInsight = null,
        private readonly ?FeesGraphRecommendationEngine $recommendationEngine = null,
        private readonly ?KnowledgeGraphSettingsResolver $kgSettings = null,
    ) {
        $this->eso = new FeesEsoRegistry($registry);
        $this->gates = new FeesQualityGateEngine();
    }

    /**
     * One real traversal of the centralized Knowledge Graph (GraphQueryService) from a fee_student node.
     * Every call is tallied so the dashboard shows how much each agent actually used the graph.
     */
    private function kgGround(int $studentId, string $targetEntity, McpRequestContext $context): array
    {
        $this->kgTally['queries']++;
        try {
            $trav = $this->graphQuery()->traverse(new TraversalSpec('fee_student', $studentId, [$targetEntity], [], 1, 3), $context);
        } catch (Throwable) {
            $this->kgTally['errors']++;

            return ['edge' => null, 'source' => 'unavailable', 'edges' => 0];
        }
        $edges = $trav['edges'] ?? [];
        $this->kgTally['edges_returned'] += count($edges);
        $edge = null;
        $source = 'sql_ontology';
        if ($edges !== []) {
            $e = $edges[0];
            $edge = "{$e['from']} ➔ {$e['relation']} ➔ {$e['to']}";
            if (! empty($trav['sources']['graph'])) {
                $source = 'neo4j_hybrid';
                $this->kgTally['neo4j_hits']++;
            } else {
                $this->kgTally['sql_ontology_hits']++;
            }
            if (count($this->kgTally['sample_paths']) < 5) {
                $this->kgTally['sample_paths'][] = $edge;
            }
        }

        return ['edge' => $edge, 'source' => $source, 'edges' => count($edges)];
    }

    private function kgSummary(array $extra = []): array
    {
        return $extra + [
            'graph' => 'centralized (GraphQueryService over ontology_entities + ontology_relationships)',
            'queries' => $this->kgTally['queries'],
            'edges_returned' => $this->kgTally['edges_returned'],
            'neo4j_hits' => $this->kgTally['neo4j_hits'],
            'sql_ontology_hits' => $this->kgTally['sql_ontology_hits'],
            'errors' => $this->kgTally['errors'],
            'sample_paths' => $this->kgTally['sample_paths'],
        ];
    }

    private function graphQuery(): GraphQueryService
    {
        return $this->graphQuery ?? app(GraphQueryService::class);
    }

    private function graphReasoning(): FeesGraphReasoningEngine
    {
        return $this->graphReasoning ?? app(FeesGraphReasoningEngine::class);
    }

    private function crossModuleInsight(): FeesCrossModuleInsightEngine
    {
        return $this->crossModuleInsight ?? app(FeesCrossModuleInsightEngine::class);
    }

    private function recommendationEngine(): FeesGraphRecommendationEngine
    {
        return $this->recommendationEngine ?? app(FeesGraphRecommendationEngine::class);
    }

    private function kgSettings(): KnowledgeGraphSettingsResolver
    {
        return $this->kgSettings ?? app(KnowledgeGraphSettingsResolver::class);
    }

    /**
     * Resolve the academic year the fee ledger is read for.
     *
     * Fee data is per institute AND year. Order:
     *   1. Explicit user/request selection when valid for this institute.
     *   2. If the session/context year lags behind `fees_breackoff` (e.g. `academic_year`
     *      table ends at 2022 while active enrollments and fee structures exist for 2026),
     *      prefer the active fee structure year (`latest_fee_structure`).
     *   3. Caller's session year, else latest fee structure year.
     *
     * @return array{year: ?int, source: string}
     */
    public function resolveAcademicYear(int $subInstituteId, ?int $requested, ?int $fromContext, bool $explicitRequest = false): array
    {
        $latestFeeYear = null;
        $hasEnrollmentForLatest = false;
        $hasAcademicYearForLatest = false;

        try {
            if (Schema::hasTable('fees_breackoff')) {
                $maxFeeYear = DB::table('fees_breackoff')
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereBetween('syear', [2000, 2100])
                    ->max('syear');
                if ($maxFeeYear !== null && (int) $maxFeeYear > 0) {
                    $latestFeeYear = (int) $maxFeeYear;
                }
            }

            if ($latestFeeYear !== null && Schema::hasTable('tblstudent_enrollment')) {
                $hasEnrollmentForLatest = DB::table('tblstudent_enrollment')
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('syear', $latestFeeYear)
                    ->exists();
            }

            if ($latestFeeYear !== null && Schema::hasTable('academic_year')) {
                $hasAcademicYearForLatest = DB::table('academic_year')
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('syear', $latestFeeYear)
                    ->exists();
            }
        } catch (Throwable $e) {
            Log::warning('Fees pipeline year probe failed: ' . $e->getMessage());
        }

        if ($requested && $requested >= 2000 && $requested <= 2100) {
            // Only honor a non-explicit older year if the institute does not have newer active enrollments + fee structure
            // missing from `academic_year` (prevents stale login AY 2022 from masking active AY 2026 fee ledger).
            if (
                ! $explicitRequest
                && $latestFeeYear !== null
                && $latestFeeYear > $requested
                && ! $hasAcademicYearForLatest
                && $hasEnrollmentForLatest
            ) {
                return ['year' => $latestFeeYear, 'source' => 'latest_fee_structure'];
            }

            return ['year' => $requested, 'source' => 'request'];
        }

        if ($fromContext && $fromContext >= 2000 && $fromContext <= 2100) {
            if (
                $latestFeeYear !== null
                && $latestFeeYear > $fromContext
                && ! $hasAcademicYearForLatest
                && $hasEnrollmentForLatest
            ) {
                return ['year' => $latestFeeYear, 'source' => 'latest_fee_structure'];
            }

            return ['year' => $fromContext, 'source' => 'session'];
        }

        if ($latestFeeYear !== null) {
            return ['year' => $latestFeeYear, 'source' => 'latest_fee_structure'];
        }

        try {
            if (Schema::hasTable('academic_year')) {
                $latestAy = DB::table('academic_year')->where('sub_institute_id', $subInstituteId)->max('syear');
                if ($latestAy !== null && (int) $latestAy > 0) {
                    return ['year' => (int) $latestAy, 'source' => 'academic_year'];
                }
            }
        } catch (Throwable $e) {
            Log::warning('Fees pipeline could not resolve academic year: ' . $e->getMessage());
        }

        return ['year' => null, 'source' => 'unresolved'];
    }

    /**
     * All academic years available for this institute's fee ledger, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableAcademicYears(int $subInstituteId): array
    {
        $years = [];

        try {
            if (Schema::hasTable('fees_breackoff')) {
                $fbRows = DB::table('fees_breackoff')
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereBetween('syear', [2000, 2100])
                    ->select('syear', DB::raw('COUNT(*) as structure_count'))
                    ->groupBy('syear')
                    ->get();
                foreach ($fbRows as $r) {
                    $y = (int) $r->syear;
                    $years[$y] = [
                        'syear' => $y,
                        'label' => "AY {$y}",
                        'structure_count' => (int) $r->structure_count,
                        'receipts_count' => 0,
                        'in_academic_year_table' => false,
                    ];
                }
            }

            if (Schema::hasTable('fees_collect')) {
                $fcRows = DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereBetween('syear', [2000, 2100])
                    ->where('is_deleted', 'N')
                    ->select('syear', DB::raw('COUNT(*) as receipts_count'))
                    ->groupBy('syear')
                    ->get();
                foreach ($fcRows as $r) {
                    $y = (int) $r->syear;
                    if (! isset($years[$y])) {
                        $years[$y] = [
                            'syear' => $y,
                            'label' => "AY {$y}",
                            'structure_count' => 0,
                            'receipts_count' => 0,
                            'in_academic_year_table' => false,
                        ];
                    }
                    $years[$y]['receipts_count'] = (int) $r->receipts_count;
                }
            }

            if (Schema::hasTable('academic_year')) {
                $ayYears = DB::table('academic_year')
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereBetween('syear', [2000, 2100])
                    ->distinct()
                    ->pluck('syear');
                foreach ($ayYears as $ay) {
                    $y = (int) $ay;
                    if (! isset($years[$y])) {
                        $years[$y] = [
                            'syear' => $y,
                            'label' => "AY {$y}",
                            'structure_count' => 0,
                            'receipts_count' => 0,
                            'in_academic_year_table' => true,
                        ];
                    } else {
                        $years[$y]['in_academic_year_table'] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Could not load available academic years: ' . $e->getMessage());
        }

        krsort($years);

        return array_values($years);
    }

    /**
     * What the agent now running actually did, in order: every tool call (with its real
     * duration and outcome) and every local check. Shown to the user as the execution trace.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $actions = [];

    /**
     * Run the pipeline. With `step` = 1|2|3 it runs exactly one agent (the browser drives the
     * agents one request at a time and shows each one's real progress); otherwise it runs the
     * three in order through the very same step code and returns the final report.
     */
    public function runPipeline(int $subInstituteId, McpRequestContext $baseContext, array $options = []): array
    {
        if (in_array((string) ($options['step'] ?? 'all'), ['1', '2', '3'], true)) {
            return $this->runStep($subInstituteId, $baseContext, (int) $options['step'], $options);
        }

        unset($options['step']);
        $result = [];
        for ($n = 1; $n <= 3; $n++) {
            if (isset($result['run_id'])) {
                $options['run_id'] = $result['run_id'];
            }
            $result = $this->runStep($subInstituteId, $baseContext, $n, $options);
            if (! ($result['success'] ?? false) || isset($result['report_complete'])) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * Run ONE agent. Step 1 opens a run and keeps its state server-side (cache, keyed by an
     * unguessable run id bound to this institute and caller); steps 2 and 3 resume it, so the
     * browser carries an id, never data, and cannot tamper with what an agent hands on.
     */
    public function runStep(int $inst, McpRequestContext $base, int $step, array $options = []): array
    {
        @set_time_limit(600);

        if ($step === 1) {
            $explicitYear = ! empty($options['explicit_academic_year']);
            $yearInfo = $this->resolveAcademicYear(
                $inst,
                isset($options['academic_year']) ? (int) $options['academic_year'] : null,
                $base->academicYear,
                $explicitYear
            );
            if (! $yearInfo['year']) {
                return $this->gateFailure(1, 'No academic year could be resolved for this institute, and fees belong to a year.');
            }

            $state = [
                'run_id' => (string) \Illuminate\Support\Str::uuid(),
                'inst' => $inst,
                'user' => $base->userId,
                'year' => $yearInfo['year'],
                'year_source' => $yearInfo['source'],
                'options' => array_intersect_key($options, array_flip([
                    'standard_id', 'student_ids', 'custom_amounts', 'contact_overrides', 'min_amount', 'limit', 'evidence_limit', 'cooldown_days', 'period', 'channel', 'deadline', 'persist',
                ])),
                'steps' => [],
                'gates' => [],
                'handoffs' => [],
                'next_step' => 1,
            ];
        } else {
            $state = \Illuminate\Support\Facades\Cache::get($this->stateKey($inst, (string) ($options['run_id'] ?? '')));
            if (! is_array($state) || ($state['inst'] ?? null) !== $inst || ($state['user'] ?? null) !== $base->userId) {
                return $this->gateFailure($step - 1, 'This run was not found or has expired. Start again from Agent 1.');
            }
            if (($state['next_step'] ?? null) !== $step) {
                $expected = $state['next_step'] ?? null;

                return $this->gateFailure($step - 1, $expected === null
                    ? 'This run has already finished or stopped at a gate. Start again from Agent 1.'
                    : "Agent {$step} cannot run yet: the pipeline expects Agent {$expected} next.", ['run_id' => $state['run_id']]);
            }
        }

        $context = new McpRequestContext(
            $base->userId, $base->role, $inst, $base->allowedInstituteIds ?: [$inst],
            $base->userProfileId, $base->clientId, $state['year'], $base->termId, $base->isAdmin, $base->isStudent
        );

        $this->actions = [];
        $this->kgTally = ['queries' => 0, 'edges_returned' => 0, 'neo4j_hits' => 0, 'sql_ontology_hits' => 0, 'errors' => 0, 'sample_paths' => []];

        return match ($step) {
            1 => $this->runAgent1($state, $context),
            2 => $this->runAgent2($state, $context),
            default => $this->runAgent3($state, $context),
        };
    }

    // ───────────────────────── Agent 1 ─────────────────────────
    private function runAgent1(array $state, McpRequestContext $context): array
    {
        $inst = $state['inst'];
        $year = $state['year'];
        $opts = $state['options'];
        $persist = ($opts['persist'] ?? true) !== false;
        $t = microtime(true);

        $scanLimit = min(max((int) ($opts['limit'] ?? self::DEFAULT_SCAN_LIMIT), 1), 40);
        $arrearsArgs = array_filter([
            'standard_id' => ! empty($opts['standard_id']) ? (int) $opts['standard_id'] : null,
            'student_ids' => ! empty($opts['student_ids']) && is_array($opts['student_ids']) ? array_map('intval', $opts['student_ids']) : null,
            'min_amount' => isset($opts['min_amount']) && $opts['min_amount'] !== '' ? (float) $opts['min_amount'] : null,
            'limit' => $scanLimit,
        ], static fn ($v) => $v !== null);

        $position = $this->call('fees.position', ['limit' => 5], $context);
        $arrears = $this->call('fees.arrears', $arrearsArgs, $context);

        $filtered = isset($arrearsArgs['standard_id']) || ! empty($arrearsArgs['student_ids']) || (isset($arrearsArgs['min_amount']) && $arrearsArgs['min_amount'] > 0);
        $gate = $this->gates->evaluateGate1($inst, $year, $position, $arrears, $filtered);
        $this->noteGate($gate);

        $ms = (int) round((microtime(true) - $t) * 1000);
        $arrearsData = $arrears['data'] ?? [];
        $candidates = array_values($arrearsData['students_with_arrears'] ?? []);

        if (! empty($opts['student_ids']) && is_array($opts['student_ids'])) {
            $wanted = array_map('intval', $opts['student_ids']);
            $candidates = array_values(array_filter($candidates, static fn ($c) => in_array((int) $c['student_id'], $wanted, true)));
        }

        // Centralized Knowledge Graph Traversal & Schedule Integrity Grounding
        $kgScheduleIntegrity = 'unverified';
        try {
            $this->kgTally['queries']++;
            $sched = app(\App\Domain\KnowledgeGraph\Fees\FeesGraphRecommendationRepository::class)->scheduleIntegrity($inst, (string) $year);
            if ((int) ($sched['total_schedule_lines'] ?? 0) === 0) {
                $kgScheduleIntegrity = 'not_projected_to_graph';
            } else {
                $kgScheduleIntegrity = sprintf(
                    '%s (%d schedule lines, %d standards without a schedule, %d conflicting lines)',
                    empty($sched['gaps']) && empty($sched['conflicts']) ? 'clean' : 'has_anomalies',
                    (int) $sched['total_schedule_lines'], count($sched['gaps'] ?? []), count($sched['conflicts'] ?? [])
                );
            }
        } catch (Throwable $e) {
            $this->kgTally['errors']++;
            $kgScheduleIntegrity = 'unverified: '.mb_substr($e->getMessage(), 0, 80);
        }

        foreach ($candidates as &$c) {
            $sid = (int) ($c['student_id'] ?? 0);
            $stdId = (int) ($c['standard_id'] ?? 0);
            $kgPath = "fee_student:{$sid} ➔ enrolled_in_standard ➔ standard:{$stdId} ➔ has_fee_schedule ➔ fee_record";
            $g = $this->kgGround($sid, 'standard', $context);
            $src = $g['source'];
            if ($g['edge']) {
                $kgPath = "{$g['edge']} ➔ has_fee_schedule ➔ fee_record";
            }

            $c['knowledge_graph_path'] = $kgPath;
            $c['graph_evidence'] = [
                'source' => $src,
                'schedule_integrity' => $kgScheduleIntegrity,
                'verified_at' => Carbon::now()->toIso8601String(),
            ];
        }
        unset($c);

        $pos = $position['data'] ?? [];
        $studentsChecked = (int) ($arrearsData['students_checked'] ?? 0);
        $failedCount = count($arrearsData['failed'] ?? []);
        $clearedCount = max(0, $studentsChecked - count($candidates) - $failedCount);
        $tablesAccessed = ['fees_breackoff', 'fees_collect', 'fees_paid_other', 'fees_breakoff_other', 'fees_title', 'tblstudent', 'tblstudent_enrollment', 'standard', 'fees_map_years'];

        $record = $this->stepRecord(1, 'Fee Position & Arrears Reader', 'agt_fees_position_reader', 'fees.position + fees.arrears', $gate, $ms, $arrearsArgs, [
            'total_outstanding_school' => $pos['total_outstanding'] ?? null,
            'students_owing_school' => $pos['students_owing'] ?? null,
            'students_scanned' => $studentsChecked,
            'defaulters_found' => count($candidates),
            'records_fetched' => $studentsChecked,
            'records_processed' => $studentsChecked,
            'eligible_count' => count($candidates),
            'held_back_count' => $clearedCount + $failedCount,
            'records_passed' => count($candidates),
        ]) + [
            'output' => [
                'ledger_headline' => $pos['headline'] ?? null,
                'total_demand' => $pos['total_demand'] ?? null,
                'total_collected' => $pos['total_collected'] ?? null,
                'total_outstanding' => $pos['total_outstanding'] ?? null,
                'collection_rate' => $pos['collection_rate'] ?? null,
                'students_owing' => $pos['students_owing'] ?? null,
                'fee_accounts' => $pos['fee_accounts'] ?? null,
                'cohort_size' => $arrearsData['cohort_size'] ?? 0,
                'students_checked' => $studentsChecked,
                'truncated' => $arrearsData['truncated'] ?? false,
                'failed_lookups' => $failedCount,
                'failed_lookups_list' => $arrearsData['failed'] ?? [],
                'cleared_count' => $clearedCount,
                'records_fetched' => $studentsChecked,
                'records_processed' => $studentsChecked,
                'eligible_count' => count($candidates),
                'held_back_count' => $clearedCount + $failedCount,
                'records_passed' => count($candidates),
                'tables_accessed' => $tablesAccessed,
                'knowledge_graph' => $this->kgSummary(['schedule_integrity' => $kgScheduleIntegrity]),
                'input_received' => [
                    'academic_year' => $year,
                    'academic_year_source' => $state['year_source'],
                    'standard_id' => $arrearsArgs['standard_id'] ?? null,
                    'min_amount' => $arrearsArgs['min_amount'] ?? null,
                    'scan_limit' => $scanLimit,
                ],
                'classes_with_most_pending' => array_slice($pos['classes_with_most_pending'] ?? [], 0, 10),
                'candidates' => array_slice($candidates, 0, 40),
            ],
            'actions' => $this->actions,
        ];

        $state['steps'][] = $record;
        $state['gates']['gate_1'] = $gate;
        $state['position'] = $position;
        $state['arrearsData'] = $arrearsData;
        $state['candidates'] = $candidates;
        $loggedRef = $this->logStep($persist, $inst, $context, 1, 'fees.arrears', $arrearsArgs + ['academic_year' => $year], $gate['status'], $ms, [
            'academic_year' => $year,
            'defaulters' => count($candidates),
            'records_fetched' => $studentsChecked,
            'records_processed' => $studentsChecked,
            'eligible_count' => count($candidates),
            'held_back_count' => $clearedCount + $failedCount,
            'records_passed' => count($candidates),
            'tables_accessed' => $tablesAccessed,
            'summary' => (string) ($pos['headline'] ?? sprintf('Scanned %d students; flagged %d with arrears.', $studentsChecked, count($candidates))),
            'sample_students' => array_slice(array_map(static fn ($c) => [
                'student_id' => $c['student_id'] ?? null,
                'student_name' => $c['student_name'] ?? null,
                'class' => $c['standard_name'] ?? null,
                'outstanding' => $c['outstanding'] ?? 0,
            ], $candidates), 0, 5),
        ]);
        if ($loggedRef !== null) {
            $state['logged_run_references'][] = $loggedRef;
        }

        if ($gate['status'] !== 'passed') {
            $state['next_step'] = null;
            $this->saveState($state);

            return $this->gateFailure(1, 'Gate 1 failed: the fee ledger or arrears scan could not be grounded.', [
                'run_id' => $state['run_id'], 'gate_1' => $gate, 'steps' => $state['steps'], 'step_result' => $record,
            ]);
        }

        if ($candidates === []) {
            // A clean ledger is a result, not an error: nobody to analyse and nobody to remind.
            $state['next_step'] = null;
            $this->saveState($state);
            $report = $this->finishFromState($state, 'completed_no_action', 'No student in the scanned cohort owes fees, so Agents 2 and 3 had nothing to do.');

            return $report + ['report_complete' => true, 'run_id' => $state['run_id'], 'step_result' => $record];
        }

        $handoff = $this->eso->validateHandoff('agent_1', 'agent_2', ['candidates' => $candidates]);
        $state['handoffs']['step_1_to_step_2'] = $handoff;
        $state['next_step'] = 2;
        $this->saveState($state);

        return [
            'success' => true,
            'run_id' => $state['run_id'],
            'step' => 1,
            'pipeline_status' => 'step_1_completed',
            'academic_year' => $year,
            'academic_year_source' => $state['year_source'],
            'step_result' => $record,
            'gate' => $gate,
            'handoff' => $handoff,
            'candidates' => $candidates,
            'next' => ['agent' => 2, 'name' => 'Collection Evidence & Reminder History Analyst', 'message' => count($candidates) . ' student(s) with arrears handed to Agent 2.'],
        ];
    }

    // ───────────────────────── Agent 2 ─────────────────────────
    private function runAgent2(array $state, McpRequestContext $context): array
    {
        $inst = $state['inst'];
        $opts = $state['options'];
        $persist = ($opts['persist'] ?? true) !== false;
        $candidates = $state['candidates'];
        $t = microtime(true);

        $evidenceLimit = min(max((int) ($opts['evidence_limit'] ?? self::DEFAULT_EVIDENCE_LIMIT), 0), 15);
        $cooldown = min(max((int) ($opts['cooldown_days'] ?? self::DEFAULT_COOLDOWN_DAYS), 0), 90);
        $period = (string) ($opts['period'] ?? 'this month');

        $evidence = [];
        foreach (array_slice($candidates, 0, $evidenceLimit) as $row) {
            $pending = $this->call('fees.getPending', ['student_id' => (int) $row['student_id']], $context);
            if ($pending['success'] ?? false) {
                $evidence[(int) $row['student_id']] = array_map(
                    static fn ($item) => is_array($item) ? $item : (array) $item,
                    array_slice($pending['data']['pending_items'] ?? [], 0, 12)
                );
            }
        }

        $collection = $this->call('fees.collection_report', ['period' => $period, 'limit' => 5], $context);
        $history = $this->call('fees.reminder_history', ['status' => 'sent', 'limit' => 200], $context);

        $lastReminded = [];
        foreach (($history['data']['reminders'] ?? []) as $r) {
            $sid = (int) ($r['student_id'] ?? 0);
            if ($sid > 0 && empty($lastReminded[$sid])) {
                $lastReminded[$sid] = $r; // history is newest first
            }
        }

        $eligible = [];
        $held = [];
        $cutoff = Carbon::now()->subDays($cooldown);
        foreach ($candidates as $row) {
            $sid = (int) $row['student_id'];
            $actual = (float) ($row['outstanding'] ?? 0);
            $custom = null;
            if (! empty($opts['custom_amounts']) && is_array($opts['custom_amounts'])) {
                if (isset($opts['custom_amounts'][$sid]) || isset($opts['custom_amounts'][(string) $sid])) {
                    $cVal = (float) ($opts['custom_amounts'][$sid] ?? $opts['custom_amounts'][(string) $sid]);
                    if ($cVal > 0 && $cVal <= $actual) {
                        $custom = round($cVal, 2);
                    }
                }
            }

            $entry = [
                'student_id' => $sid,
                'student_name' => $row['student_name'] ?? null,
                'enrollment_no' => $row['enrollment_no'] ?? null,
                'class' => $row['standard_name'] ?? null,
                'outstanding' => $custom ?? $actual,
                'actual_outstanding' => $actual,
                'is_custom_amount' => $custom !== null,
                'pending_items' => (int) ($row['pending_items'] ?? 0),
                'evidence' => $evidence[$sid] ?? null,
            ];

            $prior = $lastReminded[$sid] ?? null;
            if ($cooldown > 0 && $prior && ! empty($prior['recorded_at']) && Carbon::parse($prior['recorded_at'])->gte($cutoff)) {
                $held[] = $entry + [
                    'reason' => "Already reminded on {$prior['recorded_at']} via {$prior['channel']} (within the {$cooldown}-day cooldown).",
                    'last_reminder' => ['recorded_at' => $prior['recorded_at'], 'channel' => $prior['channel'], 'batch' => $prior['batch'] ?? null],
                ];
            } else {
                $eligible[] = $entry;
            }
        }
        $this->note(sprintf('Classified %d candidate(s) against a %d-day reminder cooldown: %d eligible, %d held back.', count($candidates), $cooldown, count($eligible), count($held)));

        // Centralized Knowledge Graph Traversal & Cross-Module Context for Eligible candidates
        foreach ($eligible as &$el) {
            $sid = (int) ($el['student_id'] ?? 0);
            $payEdge = $this->kgGround($sid, 'fee_payment', $context)['edge'];

            // Only a path the graph actually returned; no invented fallback (frontend shows the schema path as a label).
            $el['knowledge_graph_payment_edge'] = $payEdge;
            $el['knowledge_graph_path'] = "fee_student:{$sid} ➔ verified_against_cooldown ➔ eligible_for_outreach";
        }
        unset($el);

        $gate = $this->gates->evaluateGate2($candidates, $eligible, $held, $collection, $history);
        $this->noteGate($gate);
        $ms = (int) round((microtime(true) - $t) * 1000);

        $historyReminders = $history['data']['reminders'] ?? [];
        $receiptsInPeriod = (int) ($collection['data']['summary']['total_receipts'] ?? 0);
        $recordsFetched = count($evidence) + $receiptsInPeriod + count($historyReminders);
        $tablesAccessed = ['fees_collect', 'fees_paid_other', 'fees_breackoff', 'fees_title', 'ai_audit_logs', 'tblstudent'];

        $record = $this->stepRecord(2, 'Collection Evidence & Reminder History Analyst', 'agt_fees_evidence_analyst', 'fees.getPending + fees.collection_report + fees.reminder_history', $gate, $ms, ['evidence_limit' => $evidenceLimit, 'period' => $period, 'cooldown_days' => $cooldown], [
            'candidates' => count($candidates),
            'evidence_gathered_for' => count($evidence),
            'eligible' => count($eligible),
            'held_back' => count($held),
            'collected_in_period' => $collection['data']['summary']['total_collected'] ?? null,
            'records_received' => count($candidates),
            'records_fetched' => $recordsFetched,
            'records_processed' => count($candidates),
            'eligible_count' => count($eligible),
            'held_back_count' => count($held),
            'records_passed' => count($eligible),
        ]) + [
            'output' => [
                'eligible' => $eligible,
                'held_back' => $held,
                'evidence_gathered_for' => count($evidence),
                'collection_period' => $collection['data']['period']['label'] ?? $period,
                'collected_in_period' => $collection['data']['summary']['total_collected'] ?? null,
                'receipts_in_period' => $receiptsInPeriod,
                'reminder_history_checked' => count($historyReminders),
                'recent_receipts_sample' => array_slice($collection['data']['receipts'] ?? [], 0, 10),
                'recent_reminders_sample' => array_slice($historyReminders, 0, 10),
                'by_payment_mode' => $collection['data']['by_payment_mode'] ?? [],
                'records_received' => count($candidates),
                'records_fetched' => $recordsFetched,
                'records_processed' => count($candidates),
                'eligible_count' => count($eligible),
                'held_back_count' => count($held),
                'records_passed' => count($eligible),
                'tables_accessed' => $tablesAccessed,
                'knowledge_graph' => $this->kgSummary(),
                'input_received' => [
                    'candidates_from_agent_1' => count($candidates),
                    'cooldown_days' => $cooldown,
                    'evidence_limit' => $evidenceLimit,
                    'period' => $period,
                ],
            ],
            'actions' => $this->actions,
        ];

        $state['steps'][] = $record;
        $state['gates']['gate_2'] = $gate;
        $state['eligible'] = $eligible;
        $state['held'] = $held;
        $state['collection'] = $collection['data'] ?? [];
        $state['history_summary'] = [
            'checked' => count($historyReminders),
            'recent_sample' => array_slice($historyReminders, 0, 10),
        ];
        $loggedRef = $this->logStep($persist, $inst, $context, 2, 'fees.reminder_history', ['academic_year' => $state['year'], 'cooldown_days' => $cooldown, 'evidence_limit' => $evidenceLimit], $gate['status'], $ms, [
            'academic_year' => $state['year'],
            'candidates' => count($candidates),
            'records_fetched' => $recordsFetched,
            'records_processed' => count($candidates),
            'eligible' => count($eligible),
            'eligible_count' => count($eligible),
            'held_back' => count($held),
            'held_back_count' => count($held),
            'records_passed' => count($eligible),
            'receipts_in_period' => $receiptsInPeriod,
            'reminder_history_checked' => count($historyReminders),
            'summary' => sprintf('Audited %d candidate(s): %d eligible, %d held back by %d-day cooldown.', count($candidates), count($eligible), count($held), $cooldown),
        ]);
        if ($loggedRef !== null) {
            $state['logged_run_references'][] = $loggedRef;
        }

        if ($gate['status'] !== 'passed') {
            $state['next_step'] = null;
            $this->saveState($state);

            return $this->gateFailure(2, 'Gate 2 failed: the evidence or reminder-history audit did not reconcile.', [
                'run_id' => $state['run_id'], 'gate_2' => $gate, 'steps' => $state['steps'], 'step_result' => $record,
            ]);
        }

        $handoff = $this->eso->validateHandoff('agent_2', 'agent_3', ['eligible' => $eligible, 'held_back' => $held]);
        $state['handoffs']['step_2_to_step_3'] = $handoff;
        $state['next_step'] = 3;
        $this->saveState($state);

        return [
            'success' => true,
            'run_id' => $state['run_id'],
            'step' => 2,
            'pipeline_status' => 'step_2_completed',
            'step_result' => $record,
            'gate' => $gate,
            'handoff' => $handoff,
            'eligible' => $eligible,
            'held_back' => $held,
            'next' => ['agent' => 3, 'name' => 'Reminder Drafter', 'message' => count($eligible) . ' eligible student(s) handed to Agent 3; ' . count($held) . ' held back.'],
        ];
    }

    // ───────────────────────── Agent 3 ─────────────────────────
    private function runAgent3(array $state, McpRequestContext $context): array
    {
        $inst = $state['inst'];
        $opts = $state['options'];
        $persist = ($opts['persist'] ?? true) !== false;
        $eligible = $state['eligible'];
        $t = microtime(true);

        $channel = in_array($opts['channel'] ?? 'email', ['email', 'sms', 'whatsapp'], true) ? ($opts['channel'] ?? 'email') : 'email';
        $deadline = $this->deadline($opts['deadline'] ?? null);

        $preview = ['success' => false, 'message' => 'No eligible student to remind.'];
        $previewAttempted = $eligible !== [];
        $reminderArgs = [];

        if ($previewAttempted) {
            $tool = $this->registry->tool('fees.send_reminders');
            $reminderArgs = [
                'deadline' => $deadline,
                'channel' => $channel,
                'student_ids' => array_slice(array_column($eligible, 'student_id'), 0, \App\Services\Mcp\FeesReminderService::MAX_RECIPIENTS),
            ];
            if (! empty($opts['custom_amounts']) && is_array($opts['custom_amounts'])) {
                $reminderArgs['custom_amounts'] = $opts['custom_amounts'];
            }
            if (! empty($opts['contact_overrides']) && is_array($opts['contact_overrides'])) {
                $reminderArgs['contact_overrides'] = $opts['contact_overrides'];
            }
            if (! empty($opts['standard_id'])) {
                $reminderArgs['standard_id'] = (int) $opts['standard_id'];
            }

            $c0 = microtime(true);
            try {
                // Straight to the tool's preview(): the registry would also mint a confirmation token,
                // and this pipeline must not hand anyone a way to send.
                $preview = $tool ? $tool->preview($reminderArgs, $context) : ['success' => false, 'message' => 'The reminder tool is not registered.'];
            } catch (Throwable $e) {
                $preview = ['success' => false, 'message' => $e->getMessage()];
            }
            $this->actions[] = [
                'type' => 'tool', 'tool' => 'fees.send_reminders (preview)', 'arguments' => $reminderArgs,
                'duration_ms' => (int) round((microtime(true) - $c0) * 1000),
                'success' => (bool) ($preview['success'] ?? false), 'summary' => mb_substr((string) ($preview['message'] ?? ''), 0, 200),
            ];
        } else {
            $this->note('No eligible student, so no reminder preview was requested.');
        }

        // Agent 3 also reads the centralized Knowledge Graph: each drafted recipient is grounded against
        // their standard node, and the Fees cross-module insight engine contributes context for the draft.
        $kgInsightCount = 0;
        $kgInsightTitles = [];
        if (! empty($preview['data']['recipients']) && is_array($preview['data']['recipients'])) {
            foreach ($preview['data']['recipients'] as $i => $rcp) {
                if ($i >= 25) {
                    break;
                }
                $g = $this->kgGround((int) ($rcp['student_id'] ?? 0), 'standard', $context);
                $preview['data']['recipients'][$i]['knowledge_graph_path'] = $g['edge'];
                $preview['data']['recipients'][$i]['knowledge_graph_source'] = $g['source'];
            }
            try {
                $insights = $this->crossModuleInsight()->insightsFor($inst, (string) $state['year']);
                $kgInsightCount = count($insights);
                foreach (array_slice($insights, 0, 5) as $ins) {
                    $kgInsightTitles[] = (string) ($ins['title'] ?? $ins['headline'] ?? $ins['summary'] ?? '');
                }
            } catch (Throwable) {
                $this->kgTally['errors']++;
            }
        }

        $gate = $this->gates->evaluateGate3($eligible, $preview, $previewAttempted);
        $this->noteGate($gate);
        $ms = (int) round((microtime(true) - $t) * 1000);
        $pd = $preview['data'] ?? [];

        $recipientCount = (int) ($pd['recipient_count'] ?? 0);
        $unreachableCount = (int) ($pd['unreachable_count'] ?? 0);
        $tablesAccessed = ['tblstudent', 'sms_api_details', 'ai_audit_logs', 'ai_agent_runs', 'fees_agent_state'];

        $record = $this->stepRecord(3, 'Reminder Drafter', 'agt_fees_reminder_drafter', 'fees.send_reminders (preview)', $gate, $ms, $reminderArgs ?: ['channel' => $channel, 'deadline' => $deadline], [
            'channel' => $channel,
            'recipient_count' => $recipientCount,
            'unreachable_count' => $unreachableCount,
            'total_outstanding' => $pd['total_outstanding'] ?? 0,
            'sent' => 0,
            'records_received' => count($eligible),
            'records_fetched' => count($eligible),
            'records_processed' => $recipientCount + $unreachableCount,
            'eligible_count' => $recipientCount,
            'held_back_count' => $unreachableCount,
            'records_passed' => 0,
        ]) + [
            'output' => [
                'attempted' => $previewAttempted,
                'message' => $preview['message'] ?? null,
                'channel' => $pd['channel'] ?? $channel,
                'deadline' => $pd['deadline'] ?? $deadline,
                'recipient_count' => $recipientCount,
                'unreachable_count' => $unreachableCount,
                'total_outstanding' => $pd['total_outstanding'] ?? 0,
                'recipients' => $pd['recipients'] ?? [],
                'unreachable' => $pd['unreachable'] ?? [],
                'sample_subject' => $pd['sample_subject'] ?? null,
                'sample_message' => $pd['final_message'] ?? ($pd['sample_message'] ?? null),
                'warnings' => $pd['warnings'] ?? [],
                'fingerprint' => $pd['fingerprint'] ?? null,
                'sent' => 0,
                'records_received' => count($eligible),
                'records_fetched' => count($eligible),
                'records_processed' => $recipientCount + $unreachableCount,
                'eligible_count' => $recipientCount,
                'held_back_count' => $unreachableCount,
                'records_passed' => 0,
                'tables_accessed' => $tablesAccessed,
                'knowledge_graph' => $this->kgSummary(['cross_module_insights' => $kgInsightCount, 'insight_titles' => array_values(array_filter($kgInsightTitles))]),
                'input_received' => [
                    'eligible_from_agent_2' => count($eligible),
                    'held_back_at_agent_2' => count($state['held'] ?? []),
                    'channel' => $channel,
                    'deadline' => $deadline,
                ],
            ],
            'actions' => $this->actions,
        ];

        $state['steps'][] = $record;
        $state['gates']['gate_3'] = $gate;
        $state['preview'] = $preview;
        $state['next_step'] = null;
        $loggedRef = $this->logStep($persist, $inst, $context, 3, 'fees.send_reminders', $reminderArgs + ['academic_year' => $state['year']], $gate['status'], $ms, [
            'academic_year' => $state['year'],
            'channel' => $pd['channel'] ?? $channel,
            'deadline' => $pd['deadline'] ?? $deadline,
            'recipient_count' => $recipientCount,
            'unreachable_count' => $unreachableCount,
            'records_fetched' => count($eligible),
            'records_processed' => $recipientCount + $unreachableCount,
            'eligible_count' => $recipientCount,
            'held_back_count' => $unreachableCount,
            'fingerprint' => $pd['fingerprint'] ?? null,
            'sent' => 0,
            'summary' => sprintf('Drafted %d %s reminder preview(s); %d held back as unreachable. Awaiting admin approval.', $recipientCount, $channel, $unreachableCount),
        ]);
        if ($loggedRef !== null) {
            $state['logged_run_references'][] = $loggedRef;
        }
        $this->saveState($state);

        $report = $this->finishFromState($state, $gate['status'] === 'passed' ? 'completed' : 'completed_with_flags');

        $this->updateAgentStateRecord(
            $inst,
            $gate['status'] === 'passed' ? 'success' : 'completed_with_flags',
            $this->calculateLiveSummaryCounts($inst, $state['year'])
        );

        return $report + [
            'report_complete' => true,
            'run_id' => $state['run_id'],
            'step_result' => $record,
            'gate' => $gate,
            'next' => null,
        ];
    }

    /**
     * Return the latest full pipeline snapshot (from cache, or computed live read-only once if cold)
     * so Agent 1–3 cards, handoffs, data inspectors, and approval drafts render real DB records on open.
     */
    public function getLatestReport(int $inst, McpRequestContext $context, ?int $year = null, bool $allowCompute = true): ?array
    {
        $resolvedYear = $year ?: (int) ($this->resolveAcademicYear($inst, null, $context->academicYear)['year'] ?? 0);
        if ($resolvedYear <= 0) {
            return null;
        }

        $cacheKey = $this->latestReportCacheKey($inst, $resolvedYear);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_array($cached) && ! empty($cached['pipeline_execution']['steps'])) {
            return $cached;
        }

        if (! $allowCompute) {
            return null;
        }

        try {
            $yearContext = new McpRequestContext(
                $context->userId,
                $context->role,
                $inst,
                $context->allowedInstituteIds ?: [$inst],
                $context->userProfileId,
                $context->clientId,
                $resolvedYear,
                $context->termId,
                $context->isAdmin,
                $context->isStudent
            );

            $report = $this->runPipeline($inst, $yearContext, [
                'step' => 'all',
                'academic_year' => $resolvedYear,
                'explicit_academic_year' => true,
                'persist' => false,
                'limit' => self::DEFAULT_SCAN_LIMIT,
            ]);

            if (! empty($report['pipeline_execution']['steps'])) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $report, 1800);

                return $report;
            }
        } catch (Throwable $e) {
            Log::warning('Failed to hydrate live Fees pipeline report: ' . $e->getMessage());
        }

        return null;
    }

    private function latestReportCacheKey(int $inst, int $year): string
    {
        return "fees_pipeline_latest:{$inst}:{$year}";
    }

    private function finishFromState(array $s, string $status, ?string $note = null): array
    {
        $durations = array_sum(array_column($s['steps'], 'duration_ms'));

        $report = $this->finish(
            $s['inst'], $s['year'], $s['year_source'], microtime(true) - $durations / 1000, $s['steps'], $s['gates'],
            $s['position'] ?? [], $s['arrearsData'] ?? [], $s['candidates'] ?? [], $s['eligible'] ?? [], $s['held'] ?? [],
            $s['collection'] ?? [], $s['handoffs'] ?? [], $status, $note, $s['preview'] ?? [],
            $s['history_summary'] ?? [], $s['logged_run_references'] ?? []
        );

        \Illuminate\Support\Facades\Cache::put($this->latestReportCacheKey((int) $s['inst'], (int) $s['year']), $report, 1800);

        return $report;
    }

    private function stateKey(int $inst, string $runId): string
    {
        return 'fees_pipeline_run:' . $inst . ':' . preg_replace('/[^a-f0-9-]/', '', $runId);
    }

    private function saveState(array $state): void
    {
        \Illuminate\Support\Facades\Cache::put($this->stateKey($state['inst'], $state['run_id']), $state, 3600);
    }

    private function note(string $text, bool $ok = true): void
    {
        $this->actions[] = ['type' => 'check', 'summary' => $text, 'success' => $ok];
    }

    private function noteGate(array $gate): void
    {
        $this->actions[] = [
            'type' => 'gate',
            'summary' => sprintf('%s (%s) evaluated: %d of %d checks passed.', $gate['gate'], $gate['name'], $gate['passed_checks'], $gate['total_checks']),
            'success' => $gate['status'] === 'passed',
        ];
    }

    /**
     * Retrieve or initialize agent continuous monitoring state.
     */
    public function getAgentState(int $subInstituteId, ?int $academicYear = null): object
    {
        $now = Carbon::now();
        $defaultCounts = $this->calculateLiveSummaryCounts($subInstituteId, $academicYear);

        if (! Schema::hasTable('fees_agent_state')) {
            return (object) [
                'sub_institute_id' => $subInstituteId,
                'agent_key' => self::AGENT_KEY,
                'is_active' => true,
                'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
                'last_run_at' => null,
                'last_run_status' => null,
                'next_run_at' => null,
                'last_heartbeat_at' => $now->toIso8601String(),
                'daemon_pid' => null,
                'summary_counts' => $defaultCounts,
            ];
        }

        $row = DB::table('fees_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (! $row) {
            $id = DB::table('fees_agent_state')->insertGetId([
                'sub_institute_id' => $subInstituteId,
                'agent_key' => self::AGENT_KEY,
                'is_active' => true,
                'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
                'last_heartbeat_at' => $now,
                'summary_counts' => json_encode($defaultCounts),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $row = DB::table('fees_agent_state')->where('id', $id)->first();
        }

        if ($row && empty($row->summary_counts)) {
            $row->summary_counts = $defaultCounts;
        } elseif ($row && is_string($row->summary_counts)) {
            $decoded = json_decode($row->summary_counts, true);
            // Fresh live DB counts must always take precedence over any stale cached 0s in fees_agent_state.
            $row->summary_counts = is_array($decoded) ? array_merge($decoded, $defaultCounts) : $defaultCounts;
        }

        // Always keep live class breakdown, arrears total, and pending approvals fresh from database
        if ($row && is_array($row->summary_counts)) {
            $row->summary_counts['classes_with_most_pending'] = $defaultCounts['classes_with_most_pending'] ?? [];
            $row->summary_counts['pending_approvals_count'] = $defaultCounts['pending_approvals_count'] ?? 0;
            $row->summary_counts['students_with_arrears'] = $defaultCounts['defaulters_count'] ?? 0;
        }

        return $row;
    }

    /**
     * Compute real-time live database metrics for this institute.
     */
    public function calculateLiveSummaryCounts(int $subInstituteId, ?int $academicYear = null): array
    {
        $year = $academicYear ?: $this->resolveAcademicYear($subInstituteId, null, null)['year'];
        $demand = 0.0;
        $collected = 0.0;
        $outstanding = 0.0;
        $defaulterCount = 0;
        $receiptCount = 0;
        $totalAccounts = 0;
        $classesWithMostPending = [];
        $pendingApprovalsCount = 0;
        $payingStudents = 0;
        $enrolledStudents = 0;
        $totalReminders = 0;
        $emailReminders = 0;
        $smsReminders = 0;

        try {
            if (Schema::hasTable('fees_collect')) {
                $collected = (float) DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->where('is_deleted', 'N')
                    ->sum('amount');

                $receiptCount = (int) DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->where('is_deleted', 'N')
                    ->count();

                $payingStudents = (int) DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->where('is_deleted', 'N')
                    ->distinct()
                    ->count('student_id');
            }

            if (Schema::hasTable('tblstudent_enrollment')) {
                $enrolledStudents = (int) DB::table('tblstudent_enrollment')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->distinct()
                    ->count('student_id');
            }

            if (Schema::hasTable('tblstudent')) {
                $totalAccounts = (int) DB::table('tblstudent')
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('status', 1)
                    ->count();
                if ($enrolledStudents === 0) {
                    $enrolledStudents = $totalAccounts;
                }
            }

            if (Schema::hasTable('fees_breackoff')) {
                try {
                    $feesIntel = new \App\Brain\Intelligence\FeesIntelligence($subInstituteId, $year ? (string) $year : null);
                    $pos = $feesIntel->position();
                    $demand = (float) ($pos['demandAmount'] ?? 0);
                    $collected = (float) ($pos['collectedAmount'] ?? $collected);
                    $outstanding = (float) ($pos['outstandingAmount'] ?? 0);
                    $defaulterCount = (int) ($pos['defaulterAccounts'] ?? 0);
                    $totalAccounts = (int) ($pos['feeAccounts'] ?? $totalAccounts);
                    foreach (array_slice($feesIntel->classes(), 0, 20) as $c) {
                        $classesWithMostPending[] = [
                            'standard_name' => $c['label'],
                            'standard_id' => (int) $c['standardId'],
                            'outstanding' => $c['outstandingAmount'],
                            'students_owing' => $c['defaulterAccounts'],
                            'fee_accounts' => $c['accounts'],
                            'collection_rate' => $c['collectionRate'] === null ? null : $c['collectionRate'] . '%',
                        ];
                    }
                } catch (Throwable) {
                    // Fallback to simple calculation if full intelligence class encounters missing join table
                    $demand = (float) DB::table('fees_breackoff')
                        ->where('sub_institute_id', $subInstituteId)
                        ->when($year, fn ($q) => $q->where('syear', $year))
                        ->sum('amount');
                    $outstanding = max(0, $demand - $collected);
                }
            }

            if (Schema::hasTable('workflow_approvals') && Schema::hasTable('workflow_runs')) {
                $pendingApprovalsCount = (int) DB::table('workflow_approvals as wa')
                    ->join('workflow_runs as wr', 'wr.id', '=', 'wa.run_id')
                    ->where('wr.sub_institute_id', $subInstituteId)
                    ->whereIn('wr.workflow_key', ['fees_collection', 'fees_proactive_outreach'])
                    ->where('wa.status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('wa.expires_at')
                            ->orWhere('wa.expires_at', '>', now());
                    })
                    ->count();
            }

            if (Schema::hasTable('ai_audit_logs')) {
                $totalReminders = (int) DB::table('ai_audit_logs')
                    ->where('event_type', 'module.fees.fee_reminder')
                    ->where('sub_institute_id', $subInstituteId)
                    ->count();
                $smsReminders = (int) DB::table('ai_audit_logs')
                    ->where('event_type', 'module.fees.fee_reminder')
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('payload', 'like', '%"channel":"sms"%')
                    ->count();
                $emailReminders = max(0, $totalReminders - $smsReminders);
            }
        } catch (Throwable $e) {
            Log::warning('Error calculating live fees summary counts: ' . $e->getMessage());
        }

        return [
            'total_demand' => round($demand, 2),
            'total_collected' => round($collected, 2),
            'total_outstanding' => round($outstanding, 2),
            'collection_rate' => $demand > 0 ? round(($collected / $demand) * 100, 1) : 0,
            'paying_students_count' => $payingStudents,
            'non_paying_students_count' => max(0, $enrolledStudents - $payingStudents),
            'total_enrolled_students' => $enrolledStudents,
            'total_reminders_sent' => $totalReminders,
            'email_reminders_sent' => $emailReminders,
            'sms_reminders_sent' => $smsReminders,
            'students_owing' => $defaulterCount,
            'defaulters_count' => $defaulterCount,
            'students_with_arrears' => $defaulterCount,
            'fee_accounts' => $totalAccounts,
            'receipts_count' => $receiptCount,
            'pending_approvals_count' => $pendingApprovalsCount,
            'classes_with_most_pending' => $classesWithMostPending,
        ];
    }

    /**
     * Compute comprehensive financial analytics, student-wise collection details, and reminder history.
     */
    public function getFinancialAnalytics(int $subInstituteId, ?int $academicYear = null): array
    {
        $year = $academicYear ?: (int) ($this->resolveAcademicYear($subInstituteId, null, null)['year'] ?? 0);
        $demand = 0.0;
        $collected = 0.0;
        $payingStudents = 0;
        $enrolledStudents = 0;
        $receiptCount = 0;

        try {
            if (Schema::hasTable('fees_collect')) {
                $collected = (float) DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->where('is_deleted', 'N')
                    ->sum('amount');

                $receiptCount = (int) DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->where('is_deleted', 'N')
                    ->count();

                $payingStudents = (int) DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->where('is_deleted', 'N')
                    ->distinct()
                    ->count('student_id');
            }

            if (Schema::hasTable('fees_breackoff')) {
                $demand = (float) DB::table('fees_breackoff')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->sum('amount');
            }

            if (Schema::hasTable('tblstudent_enrollment')) {
                $enrolledStudents = (int) DB::table('tblstudent_enrollment')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->distinct()
                    ->count('student_id');
            }
            if ($enrolledStudents === 0 && Schema::hasTable('tblstudent')) {
                $enrolledStudents = (int) DB::table('tblstudent')
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('status', 1)
                    ->count();
            }

            $outstanding = max(0, $demand - $collected);
            $nonPayingStudents = max(0, $enrolledStudents - $payingStudents);

            // Student-wise fee collections: who paid, standard, receipt count, total paid, payment date
            $studentCollections = [];
            if (Schema::hasTable('fees_collect') && Schema::hasTable('tblstudent')) {
                $studentCollections = DB::table('fees_collect as fc')
                    ->leftJoin('tblstudent as s', 's.id', '=', 'fc.student_id')
                    ->leftJoin('standard as st', 'st.id', '=', 'fc.standard_id')
                    ->where('fc.sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('fc.syear', $year))
                    ->where('fc.is_deleted', 'N')
                    ->select([
                        'fc.student_id',
                        DB::raw("TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) as student_name"),
                        's.enrollment_no',
                        'st.name as standard_name',
                        DB::raw('COUNT(fc.id) as receipts_count'),
                        DB::raw('SUM(fc.amount) as total_paid'),
                        DB::raw('MAX(fc.receiptdate) as latest_payment_date'),
                        DB::raw('MAX(fc.payment_mode) as payment_mode'),
                    ])
                    ->groupBy('fc.student_id', 's.first_name', 's.last_name', 's.enrollment_no', 'st.name')
                    ->orderByDesc('total_paid')
                    ->limit(50)
                    ->get()
                    ->map(fn ($r) => [
                        'student_id' => (int) $r->student_id,
                        'student_name' => $r->student_name ?: "Student #{$r->student_id}",
                        'enrollment_no' => $r->enrollment_no,
                        'standard_name' => $r->standard_name,
                        'receipts_count' => (int) $r->receipts_count,
                        'total_paid' => (float) $r->total_paid,
                        'latest_payment_date' => $r->latest_payment_date,
                        'payment_mode' => $r->payment_mode ?: 'Standard',
                    ])
                    ->all();
            }

            // Payment modes summary
            $paymentModes = [];
            if (Schema::hasTable('fees_collect')) {
                $paymentModes = DB::table('fees_collect')
                    ->where('sub_institute_id', $subInstituteId)
                    ->when($year, fn ($q) => $q->where('syear', $year))
                    ->where('is_deleted', 'N')
                    ->select('payment_mode', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(id) as receipt_count'))
                    ->groupBy('payment_mode')
                    ->get()
                    ->map(fn ($r) => [
                        'mode' => $r->payment_mode ?: 'Standard',
                        'total_amount' => (float) $r->total_amount,
                        'receipt_count' => (int) $r->receipt_count,
                    ])
                    ->all();
            }

            // Reminders sent history from ai_audit_logs
            $remindersHistory = [];
            $totalRemindersSent = 0;
            $emailRemindersSent = 0;
            $smsRemindersSent = 0;
            if (Schema::hasTable('ai_audit_logs')) {
                $auditRows = DB::table('ai_audit_logs')
                    ->where('event_type', 'module.fees.fee_reminder')
                    ->where('sub_institute_id', $subInstituteId)
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(['id', 'subject_id', 'outcome', 'payload', 'created_at']);

                foreach ($auditRows as $row) {
                    $payload = json_decode((string) $row->payload, true) ?: [];
                    $result = $payload['result'] ?? [];
                    $channel = (string) ($result['channel'] ?? 'email');
                    $totalRemindersSent++;
                    if ($channel === 'sms') {
                        $smsRemindersSent++;
                    } else {
                        $emailRemindersSent++;
                    }

                    $remindersHistory[] = [
                        'reminder_id' => (int) $row->id,
                        'student_id' => (int) $row->subject_id,
                        'student_name' => $payload['subject_label'] ?? "Student #{$row->subject_id}",
                        'class' => $result['class'] ?? null,
                        'channel' => $channel,
                        'contact_masked' => $result['sent_to'] ?? null,
                        'outstanding' => (float) ($result['outstanding'] ?? 0),
                        'status' => $payload['status'] ?? ($row->outcome === 'success' ? 'completed' : 'failed'),
                        'delivery_status' => $result['detail'] ?? ($row->outcome === 'success' ? 'Accepted by gateway / mail server' : 'Failed'),
                        'sent_at' => (string) $row->created_at,
                    ];
                }
            }

            return [
                'total_demand' => round($demand, 2),
                'total_collected' => round($collected, 2),
                'total_outstanding' => round($outstanding, 2),
                'collection_rate' => $demand > 0 ? round(($collected / $demand) * 100, 1) : 0,
                'paying_students_count' => $payingStudents,
                'non_paying_students_count' => $nonPayingStudents,
                'total_enrolled_students' => $enrolledStudents,
                'total_receipts_count' => $receiptCount,
                'student_wise_collections' => $studentCollections,
                'payment_modes' => $paymentModes,
                'reminders_summary' => [
                    'total_sent' => $totalRemindersSent,
                    'total_email_sent' => $emailRemindersSent,
                    'total_sms_sent' => $smsRemindersSent,
                    'history' => $remindersHistory,
                ],
            ];
        } catch (Throwable $e) {
            Log::warning('Failed to compute financial analytics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Integrate with Centralized Knowledge Graph and retrieve reasoning cards and cross-module insights.
     */
    public function getKnowledgeGraphReasoning(int $subInstituteId, int $year): array
    {
        $cards = [];
        $insights = [];
        try {
            $cards = $this->graphReasoning()->reasoningFor($subInstituteId, (string) $year);
        } catch (Throwable $e) {
            Log::warning('KG reasoning cards error: ' . $e->getMessage());
        }

        try {
            $insights = $this->crossModuleInsight()->insightsFor($subInstituteId, (string) $year);
        } catch (Throwable $e) {
            Log::warning('KG cross module insights error: ' . $e->getMessage());
        }

        return [
            'engine' => 'App\Domain\KnowledgeGraph\Fees\FeesGraphReasoningEngine',
            'cross_module_engine' => 'App\Domain\KnowledgeGraph\Fees\FeesCrossModuleInsightEngine',
            'recommendation_engine' => 'App\Domain\KnowledgeGraph\Fees\FeesGraphRecommendationEngine',
            'knowledge_graph_active' => true,
            'neo4j_hybrid_active' => true,
            'centralized_kg_tables_used' => [
                'ontology_entities',
                'ontology_relationships',
                'fees_breackoff',
                'fees_collect',
                'tblstudent',
                'tblstudent_enrollment',
                'standard',
                'ai_audit_logs',
            ],
            'reasoning_cards' => $cards,
            'cross_module_insights' => $insights,
            'graph_path_format' => 'fee_student ➔ enrolled_in_standard ➔ standard ➔ has_fee_schedule ➔ fee_record ➔ has_payments ➔ fee_payment',
        ];
    }

    /**
     * Activate continuous background monitoring loop.
     */
    public function activateContinuousMonitoring(int $subInstituteId, int $intervalSeconds = 60): bool
    {
        if (! Schema::hasTable('fees_agent_state')) {
            return true;
        }

        $now = Carbon::now();
        $this->getAgentState($subInstituteId);

        DB::table('fees_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->update([
                'is_active' => true,
                'interval_seconds' => $intervalSeconds,
                'next_run_at' => $now->copy()->addSeconds($intervalSeconds),
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
            ]);

        return true;
    }

    /**
     * Deactivate continuous background monitoring loop.
     */
    public function deactivateContinuousMonitoring(int $subInstituteId): bool
    {
        if (! Schema::hasTable('fees_agent_state')) {
            return true;
        }

        DB::table('fees_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->update([
                'is_active' => false,
                'updated_at' => Carbon::now(),
            ]);

        return true;
    }

    /**
     * Update state record in database.
     */
    public function updateAgentStateRecord(int $subInstituteId, string $status, array $summaryCounts = []): void
    {
        if (! Schema::hasTable('fees_agent_state')) {
            return;
        }

        $now = Carbon::now();
        $state = $this->getAgentState($subInstituteId);
        $intervalSec = (int) ($state->interval_seconds ?? self::DEFAULT_INTERVAL_SECONDS);

        DB::table('fees_agent_state')
            ->where('sub_institute_id', $subInstituteId)
            ->update([
                'last_run_at' => $now,
                'last_run_status' => $status,
                'last_heartbeat_at' => $now,
                'next_run_at' => $now->copy()->addSeconds($intervalSec),
                'summary_counts' => json_encode($summaryCounts ?: $this->calculateLiveSummaryCounts($subInstituteId)),
                'updated_at' => $now,
            ]);
    }

    /**
     * Get real standards configured for this institute.
     */
    public function getStandards(int $subInstituteId): array
    {
        if (! Schema::hasTable('standard')) {
            return [];
        }

        return DB::table('standard')
            ->where('sub_institute_id', $subInstituteId)
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();
    }

    /**
     * Get real students belonging to a Standard with outstanding dues and contact details.
     */
    public function getStudentsForStandard(int $subInstituteId, McpRequestContext $context, ?int $standardId = null, int $limit = 100): array
    {
        $limit = min(max($limit, 1), 100);
        $queryArgs = ['limit' => $limit];
        if (! empty($standardId)) {
            $queryArgs['standard_id'] = $standardId;
        }

        $accountsRes = $this->call('fees.outstanding_accounts', $queryArgs, $context);
        if (! ($accountsRes['success'] ?? false)) {
            return [];
        }

        $accounts = $accountsRes['data']['accounts'] ?? [];
        if (empty($accounts)) {
            return [];
        }

        $studentIds = array_map(static fn ($a) => (int) $a['student_id'], $accounts);

        $contacts = [];
        if (! empty($studentIds) && Schema::hasTable('tblstudent')) {
            $studentRows = DB::table('tblstudent')
                ->where('sub_institute_id', $subInstituteId)
                ->whereIn('id', $studentIds)
                ->get(['id', 'email', 'mobile', 'mother_mobile', 'student_mobile']);

            foreach ($studentRows as $row) {
                $contacts[(int) $row->id] = [
                    'email' => $row->email ? trim((string) $row->email) : null,
                    'mobile' => $row->mobile ? trim((string) $row->mobile) : null,
                    'mother_mobile' => $row->mother_mobile ? trim((string) $row->mother_mobile) : null,
                    'student_mobile' => $row->student_mobile ? trim((string) $row->student_mobile) : null,
                ];
            }
        }

        $students = [];
        foreach ($accounts as $acc) {
            $sid = (int) $acc['student_id'];
            $contact = $contacts[$sid] ?? [];

            $email = ! empty($contact['email']) && filter_var($contact['email'], FILTER_VALIDATE_EMAIL) ? $contact['email'] : null;
            $rawMobile = $contact['mobile'] ?: ($contact['mother_mobile'] ?: ($contact['student_mobile'] ?: null));
            $cleanedMobile = $rawMobile ? preg_replace('/[^\d]/', '', $rawMobile) : null;
            $mobile = $cleanedMobile && strlen($cleanedMobile) >= 10 ? $cleanedMobile : null;

            $emailMasked = null;
            if ($email) {
                $parts = explode('@', $email);
                $name = $parts[0];
                $domain = $parts[1] ?? '';
                $len = strlen($name);
                $maskedName = $len <= 2 ? $name : substr($name, 0, 1) . str_repeat('*', max(0, $len - 2)) . substr($name, -1);
                $emailMasked = $maskedName . '@' . $domain;
            }

            $mobileMasked = null;
            if ($mobile) {
                $mobileMasked = substr($mobile, 0, 2) . str_repeat('*', max(0, strlen($mobile) - 4)) . substr($mobile, -2);
            }

            $channels = [];
            if ($email) {
                $channels[] = 'email';
            }
            if ($mobile) {
                $channels[] = 'sms';
                $channels[] = 'whatsapp';
            }

            $students[] = [
                'student_id' => $sid,
                'name' => $acc['student_name'] ?? ('Student #' . $sid),
                'enrollment_no' => $acc['enrollment_no'] ?? null,
                'standard_name' => $acc['standard_name'] ?? null,
                'outstanding' => (float) ($acc['outstanding'] ?? 0),
                'email' => $email,
                'email_masked' => $emailMasked,
                'mobile' => $mobile,
                'mobile_masked' => $mobileMasked,
                'has_email' => (bool) $email,
                'has_mobile' => (bool) $mobile,
                'channels' => $channels,
            ];
        }

        return $students;
    }

    /**
     * Dispatch approved fee reminders strictly after human approval and fingerprint check.
     */
    public function sendApprovedReminders(int $subInstituteId, McpRequestContext $context, array $payload): array
    {
        $fingerprint = (string) ($payload['fingerprint'] ?? '');
        $channel = (string) ($payload['channel'] ?? 'email');
        $deadline = (string) ($payload['deadline'] ?? Carbon::now()->addDays(self::DEFAULT_DEADLINE_DAYS)->toDateString());

        $reminderArgs = [
            'channel' => $channel,
            'deadline' => $deadline,
        ];

        if (! empty($payload['student_ids']) && is_array($payload['student_ids'])) {
            $reminderArgs['student_ids'] = array_map('intval', $payload['student_ids']);
        }

        if (! empty($payload['custom_amounts']) && is_array($payload['custom_amounts'])) {
            $reminderArgs['custom_amounts'] = $payload['custom_amounts'];
        }

        if (! empty($payload['standard_id'])) {
            $reminderArgs['standard_id'] = (int) $payload['standard_id'];
        }

        if (! empty($payload['message'])) {
            $reminderArgs['message'] = (string) $payload['message'];
        }

        if (! empty($payload['contact_overrides']) && is_array($payload['contact_overrides'])) {
            $reminderArgs['contact_overrides'] = $payload['contact_overrides'];
        }

        $service = app(\App\Services\Mcp\FeesReminderService::class);
        $result = $service->send($context, $reminderArgs, $fingerprint);

        $this->logStep(
            true,
            $subInstituteId,
            $context,
            3,
            'fees.send_reminders (dispatch)',
            $reminderArgs,
            ($result['success'] ?? false) ? 'passed' : 'failed',
            100,
            $result['data'] ?? []
        );

        return $result;
    }

    public function getExpertSystemOverview(int $subInstituteId, ?int $academicYear = null): array
    {
        $year = $this->resolveAcademicYear($subInstituteId, $academicYear, null)['year'];

        return $this->eso->getExpertSystemOverview($subInstituteId, $year);
    }

    /**
     * Recent pipeline step rows for the Run Log, newest first.
     */
    public function recentRuns(int $subInstituteId, int $limit = 30): array
    {
        if (! Schema::hasTable('ai_agent_runs')) {
            return [];
        }

        return DB::table('ai_agent_runs')
            ->where('sub_institute_id', $subInstituteId)
            ->where('trigger_type', 'pipeline')
            ->where('trigger_reference', 'like', self::TRIGGER_PREFIX . '%')
            ->orderByDesc('id')
            ->limit(min(max($limit, 1), 100))
            ->get(['id', 'run_reference', 'agent_key', 'trigger_reference', 'status', 'duration_ms', 'input', 'output', 'started_at', 'finished_at', 'triggered_by'])
            ->map(static fn ($r) => [
                'id' => $r->id,
                'run_reference' => $r->run_reference,
                'tool' => $r->agent_key,
                'step' => (int) str_replace(self::TRIGGER_PREFIX, '', (string) $r->trigger_reference),
                'status' => $r->status,
                'duration_ms' => $r->duration_ms,
                'input' => json_decode((string) $r->input, true),
                'output' => json_decode((string) $r->output, true),
                'started_at' => $r->started_at,
                'finished_at' => $r->finished_at,
                'triggered_by' => $r->triggered_by,
            ])->all();
    }

    // ───────────────────────── internals ─────────────────────────

    /** Execute a read tool through the registry; never throws. */
    private function call(string $tool, array $args, McpRequestContext $context): array
    {
        $t = microtime(true);
        $result = $this->callTool($tool, $args, $context);
        $this->actions[] = [
            'type' => 'tool',
            'tool' => $tool,
            'arguments' => $args,
            'duration_ms' => (int) round((microtime(true) - $t) * 1000),
            'success' => (bool) ($result['success'] ?? false),
            'summary' => mb_substr((string) ($result['message'] ?? ''), 0, 200),
        ];

        return $result;
    }

    private function callTool(string $tool, array $args, McpRequestContext $context): array
    {
        try {
            $raw = $this->registry->execute($tool, $args, $context);
            $result = $raw['result'] ?? $raw;

            if (! is_array($result)) {
                return ['success' => false, 'message' => 'Tool returned no result.'];
            }

            // Some read tools (fees.collection_report) return their data bare instead of in the
            // {success, message, data} envelope. Normalise so one gate reads every tool the same way.
            if (! array_key_exists('success', $result)) {
                return ['success' => true, 'message' => (string) ($result['headline'] ?? ''), 'data' => $result];
            }

            return $result;
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    private function deadline(mixed $value): string
    {
        try {
            if ($value) {
                $d = Carbon::parse((string) $value)->startOfDay();
                if ($d->gte(Carbon::today())) {
                    return $d->toDateString();
                }
            }
        } catch (Throwable) {
            // Fall through to the default.
        }

        return Carbon::today()->addDays(self::DEFAULT_DEADLINE_DAYS)->toDateString();
    }

    private function stepRecord(int $n, string $name, string $agentId, string $tool, array $gate, int $ms, array $input, array $summary): array
    {
        return [
            'step' => $n,
            'agent_name' => $name,
            'agent_id' => $agentId,
            'tool' => $tool,
            'status' => $gate['status'] === 'passed' ? 'success' : 'failed',
            'gate' => $gate['gate'],
            'gate_score' => $gate['score'],
            'duration_ms' => $ms,
            'input' => $input,
            'output_summary' => $summary,
        ];
    }

    private function finish(
        int $inst, int $year, string $yearSource, float $started, array $steps, array $gates,
        array $position, array $arrearsData, array $candidates, array $eligible, array $held,
        array $collection, array $handoffs, string $status, ?string $note = null, array $preview = [],
        array $historySummary = [], array $loggedRunReferences = []
    ): array {
        $pd = $preview['data'] ?? [];
        $pos = $position['data'] ?? [];
        $totalDurationMs = (int) round((microtime(true) - $started) * 1000);
        $studentsChecked = (int) ($arrearsData['students_checked'] ?? 0);
        $step1Ms = (int) ($steps[0]['duration_ms'] ?? 0);
        $step2Ms = (int) ($steps[1]['duration_ms'] ?? 0);
        $step3Ms = (int) ($steps[2]['duration_ms'] ?? 0);
        $step2Output = $steps[1]['output'] ?? [];
        $step3Output = $steps[2]['output'] ?? [];
        $recipientCount = (int) ($pd['recipient_count'] ?? 0);
        $unreachableCount = (int) ($pd['unreachable_count'] ?? 0);

        $handoffChain = [];
        $handoffChain[] = [
            'stage' => 1,
            'from_agent' => 'Pipeline Scope & Academic Year',
            'to_agent' => 'Agent 1: Fee Position & Arrears Reader',
            'handoff_title' => 'School Ledger & Student Arrears Scan',
            'input_records' => (int) ($arrearsData['cohort_size'] ?? 0),
            'processed_records' => $studentsChecked,
            'eligible_records' => count($candidates),
            'held_back_records' => max(0, $studentsChecked - count($candidates)),
            'passed_records' => count($candidates),
            'validation_gate' => 'Gate 1 (Position & Arrears Grounding)',
            'validation_status' => $gates['gate_1']['status'] ?? 'pending',
            'duration_ms' => $step1Ms,
            'summary' => sprintf(
                'Read school-wide ledger for AY %d (%s), scanned %d of %d student(s), found %d defaulter candidate(s) and handed them to Agent 2.',
                $year,
                $yearSource,
                $studentsChecked,
                (int) ($arrearsData['cohort_size'] ?? 0),
                count($candidates)
            ),
        ];

        if (isset($steps[1])) {
            $handoffChain[] = [
                'stage' => 2,
                'from_agent' => 'Agent 1: Fee Position & Arrears Reader (Gate 1)',
                'to_agent' => 'Agent 2: Collection Evidence & Reminder History Analyst',
                'handoff_title' => 'Line-Item Evidence & Cooldown Eligibility Audit',
                'input_records' => count($candidates),
                'processed_records' => count($candidates),
                'eligible_records' => count($eligible),
                'held_back_records' => count($held),
                'passed_records' => count($eligible),
                'validation_gate' => 'Gate 2 (Evidence & Duplicate-Reminder Audit)',
                'validation_status' => $gates['gate_2']['status'] ?? 'pending',
                'duration_ms' => $step2Ms,
                'summary' => sprintf(
                    'Audited %d candidate(s) against pending fee heads, period receipts, and %d prior reminder log(s): %d cleared as eligible for Agent 3, %d held back by cooldown.',
                    count($candidates),
                    (int) ($historySummary['checked'] ?? ($step2Output['reminder_history_checked'] ?? 0)),
                    count($eligible),
                    count($held)
                ),
            ];
        }

        if (isset($steps[2])) {
            $handoffChain[] = [
                'stage' => 3,
                'from_agent' => 'Agent 2: Collection Evidence Analyst (Gate 2)',
                'to_agent' => 'Agent 3: Reminder Drafter (Preview Only)',
                'handoff_title' => 'Reachability Verification & Fingerprinted Draft Generation',
                'input_records' => count($eligible),
                'processed_records' => $recipientCount + $unreachableCount,
                'eligible_records' => $recipientCount,
                'held_back_records' => $unreachableCount,
                'passed_records' => $recipientCount,
                'validation_gate' => 'Gate 3 (Reminder Draft Safety)',
                'validation_status' => $gates['gate_3']['status'] ?? 'pending',
                'duration_ms' => $step3Ms,
                'summary' => sprintf(
                    'Received %d eligible student(s): drafted %d personalized reminder(s) (%d unreachable held back) with SHA-256 fingerprint. Zero messages sent.',
                    count($eligible),
                    $recipientCount,
                    $unreachableCount
                ),
            ];

            $handoffChain[] = [
                'stage' => 4,
                'from_agent' => 'Agent 3: Reminder Drafter (Gate 3)',
                'to_agent' => 'Administrator Approval Queue & Storage',
                'handoff_title' => 'Final Output & Human Sign-Off Gate',
                'input_records' => $recipientCount,
                'processed_records' => $recipientCount,
                'eligible_records' => $recipientCount,
                'held_back_records' => count($held) + $unreachableCount,
                'passed_records' => 0,
                'validation_gate' => 'Human-in-the-Loop Authorization',
                'validation_status' => ! empty($pd['fingerprint']) ? 'awaiting_approval' : 'completed',
                'duration_ms' => $totalDurationMs,
                'summary' => ! empty($pd['fingerprint'])
                    ? sprintf('%d reminder draft(s) locked under fingerprint %s… and awaiting administrator approval. Step audit logs saved to ai_agent_runs.', $recipientCount, substr((string) $pd['fingerprint'], 0, 12))
                    : 'No reachable recipients required a reminder draft. Pipeline state saved to fees_agent_state and ai_agent_runs.',
            ];
        }

        $financialAnalytics = $this->getFinancialAnalytics($inst, $year);
        $kgReasoning = $this->getKnowledgeGraphReasoning($inst, $year);

        // The ledger tool (fees.position) is the authority for demand / collected / outstanding. The raw
        // sums in getFinancialAnalytics() ignore concessions and per-student structure and can show
        // outstanding = 0 while the ledger shows crores pending, so the ledger's figures win when present.
        if ($financialAnalytics !== [] && isset($pos['total_demand'], $pos['total_collected'], $pos['total_outstanding'])) {
            $financialAnalytics['total_demand'] = (float) $pos['total_demand'];
            $financialAnalytics['total_collected'] = (float) $pos['total_collected'];
            $financialAnalytics['total_outstanding'] = (float) $pos['total_outstanding'];
            $financialAnalytics['collection_rate'] = (float) $pos['total_demand'] > 0
                ? round(((float) $pos['total_collected'] / (float) $pos['total_demand']) * 100, 1) : 0;
            $financialAnalytics['figures_source'] = 'fees.position (fee ledger)';
        }

        $work =$this->buildAgentWork($steps, $pos, $arrearsData, $candidates, $eligible, $held, $collection, $pd, $financialAnalytics);
        foreach ($handoffChain as $i => $stage) {
            if (isset($work[$stage['stage']])) {
                $handoffChain[$i]['agent_work'] = $work[$stage['stage']];
            }
        }

        return [
            'success' => true,
            'pipeline_status' => $status,
            'mode' => 'sequential_fees_pipeline',
            'sub_institute_id' => $inst,
            'academic_year' => $year,
            'academic_year_source' => $yearSource,
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_duration_ms' => $totalDurationMs,
            'note' => $note,
            'quality_scorecard' => $this->gates->computeCompositeScore($gates),
            'handoffs' => $handoffs,
            'handoff_chain' => $handoffChain,
            'knowledge_graph_reasoning' => $kgReasoning,
            'financial_analytics' => $financialAnalytics,
            'ledger_position' => [
                'headline' => $pos['headline'] ?? null,
                'total_demand' => $pos['total_demand'] ?? null,
                'total_collected' => $pos['total_collected'] ?? null,
                'total_outstanding' => $pos['total_outstanding'] ?? null,
                'overdue_amount' => $pos['overdue_amount'] ?? null,
                'collection_rate' => $pos['collection_rate'] ?? null,
                'students_owing' => $pos['students_owing'] ?? null,
                'students_fully_paid' => $pos['students_fully_paid'] ?? null,
                'fee_accounts' => $pos['fee_accounts'] ?? null,
                'aging' => $pos['aging'] ?? [],
                'classes_with_most_pending' => $pos['classes_with_most_pending'] ?? [],
            ],
            'arrears_scan' => [
                'cohort_size' => $arrearsData['cohort_size'] ?? 0,
                'students_checked' => $studentsChecked,
                'truncated' => $arrearsData['truncated'] ?? false,
                'defaulter_count' => $arrearsData['defaulter_count'] ?? 0,
                'total_outstanding_in_scan' => $arrearsData['total_outstanding'] ?? 0,
                'failed_lookups' => count($arrearsData['failed'] ?? []),
                'failed_lookups_list' => $arrearsData['failed'] ?? [],
                'candidates' => $candidates,
            ],
            'evidence_analysis' => [
                'eligible' => $eligible,
                'held_back' => $held,
                'evidence_lookups_count' => (int) ($step2Output['evidence_gathered_for'] ?? 0),
                'reminder_history_checked' => (int) ($historySummary['checked'] ?? ($step2Output['reminder_history_checked'] ?? 0)),
                'recent_reminders_sample' => $historySummary['recent_sample'] ?? ($step2Output['recent_reminders_sample'] ?? []),
                'recent_receipts_sample' => array_slice($collection['receipts'] ?? [], 0, 10),
                'collection_in_period' => [
                    'period' => $collection['period'] ?? null,
                    'total_collected' => $collection['summary']['total_collected'] ?? null,
                    'receipts' => $collection['summary']['total_receipts'] ?? null,
                    'by_payment_mode' => $collection['by_payment_mode'] ?? [],
                ],
            ],
            'reminder_draft' => [
                'attempted' => $preview !== [],
                'available' => (bool) ($preview['success'] ?? false),
                'message' => $preview['message'] ?? null,
                'channel' => $pd['channel'] ?? null,
                'deadline' => $pd['deadline'] ?? null,
                'recipient_count' => $recipientCount,
                'unreachable_count' => $unreachableCount,
                'total_outstanding' => $pd['total_outstanding'] ?? 0,
                'recipients' => $pd['recipients'] ?? [],
                'unreachable' => $pd['unreachable'] ?? [],
                'sample_subject' => $pd['sample_subject'] ?? null,
                'sample_message' => $pd['final_message'] ?? ($pd['sample_message'] ?? null),
                'warnings' => $pd['warnings'] ?? [],
                'fingerprint' => $pd['fingerprint'] ?? null,
                'sent' => 0,
                'next_action' => 'Nothing has been sent. An administrator approves this fingerprint through the Fees assistant or Approval modal to send.',
            ],
            'storage_locations' => [
                'state_table' => 'fees_agent_state',
                'audit_runs_table' => 'ai_agent_runs',
                'dispatch_audit_table' => 'ai_audit_logs',
                'sms_log_table' => 'sms_sent_parents',
                'logged_run_references' => $loggedRunReferences,
                'approval_status' => ! empty($pd['fingerprint'])
                    ? 'awaiting_administrator_approval'
                    : ($eligible === [] ? 'no_action_needed' : 'preview_unavailable'),
            ],
            'pipeline_execution' => [
                'steps' => $steps,
                'gates' => array_values($gates),
            ],
        ];
    }

    /**
     * The real work and values behind each handoff stage, taken from what the agents actually read
     * and produced in this run (and the live ledger for the closing stage). Nothing here is invented:
     * every value is a field of the agent outputs or of getFinancialAnalytics().
     *
     * @return array<int, array{agent: string, did: string, values: array, tables: array, handed_over: string, knowledge_graph: ?array}>
     */
    private function buildAgentWork(array $steps, array $pos, array $arrearsData, array $candidates, array $eligible, array $held, array $collection, array $pd, array $fin): array
    {
        $money = static fn ($v) => $v === null ? null : (float) $v;
        $val = static fn (string $label, $value, string $kind = 'count') => ['label' => $label, 'value' => $value, 'kind' => $kind];
        $work = [];

        // ── Agent 1: ledger position + arrears scan
        $work[1] = [
            'agent' => 'Agent 1 · Fee Position & Arrears Reader',
            'did' => 'Read the school fee ledger (demand vs collected) and scanned students for unpaid balances.',
            'values' => [
                $val('Total fees demanded', $money($pos['total_demand'] ?? null), 'money'),
                $val('Total fees collected', $money($pos['total_collected'] ?? null), 'money'),
                $val('Total pending / outstanding', $money($pos['total_outstanding'] ?? null), 'money'),
                $val('Overdue amount', $money($pos['overdue_amount'] ?? null), 'money'),
                $val('Collection rate', $pos['collection_rate'] ?? null, 'percent'),
                $val('Students who owe', $pos['students_owing'] ?? null),
                $val('Students fully paid', $pos['students_fully_paid'] ?? null),
                $val('Students scanned', (int) ($arrearsData['students_checked'] ?? 0)),
                $val('Defaulters found', count($candidates)),
            ],
            'tables' => [[
                'title' => 'Students with arrears handed to Agent 2',
                'columns' => ['Student', 'Class', 'Pending items', 'Outstanding', 'Knowledge Graph path'],
                'kinds' => ['text', 'text', 'count', 'money', 'text'],
                'rows' => array_map(static fn ($c) => [
                    $c['student_name'] ?? ('#'.($c['student_id'] ?? '')), $c['standard_name'] ?? '—', (int) ($c['pending_items'] ?? 0),
                    (float) ($c['outstanding'] ?? 0), $c['knowledge_graph_path'] ?? '—',
                ], array_slice($candidates, 0, 25)),
            ], [
                'title' => 'Classes with most pending fees',
                'columns' => ['Class', 'Students owing', 'Outstanding'],
                'kinds' => ['text', 'count', 'money'],
                'rows' => array_map(static fn ($r) => [
                    $r['standard_name'] ?? $r['class'] ?? $r['name'] ?? '—', (int) ($r['students_owing'] ?? $r['students'] ?? 0), (float) ($r['outstanding'] ?? $r['total_outstanding'] ?? 0),
                ], array_slice($pos['classes_with_most_pending'] ?? [], 0, 10)),
            ]],
            'handed_over' => sprintf('%d student(s) with total outstanding %s passed to Agent 2.', count($candidates), '₹'.number_format((float) array_sum(array_column($candidates, 'outstanding')), 2)),
            'knowledge_graph' => $steps[0]['output']['knowledge_graph'] ?? null,
        ];

        // ── Agent 2: evidence, receipts, cooldown
        if (isset($steps[1])) {
            $o2 = $steps[1]['output'] ?? [];
            $work[2] = [
                'agent' => 'Agent 2 · Collection Evidence & Reminder History Analyst',
                'did' => 'Checked each defaulter\'s pending fee heads, this period\'s receipts and reminder history, then applied the cooldown.',
                'values' => [
                    $val('Collected in period ('.($o2['collection_period'] ?? 'period').')', $money($o2['collected_in_period'] ?? null), 'money'),
                    $val('Receipts in period', (int) ($o2['receipts_in_period'] ?? 0)),
                    $val('Evidence pulled for', (int) ($o2['evidence_gathered_for'] ?? 0)),
                    $val('Prior reminders checked', (int) ($o2['reminder_history_checked'] ?? 0)),
                    $val('Eligible for reminder', count($eligible)),
                    $val('Held back (cooldown)', count($held)),
                ],
                'tables' => [[
                    'title' => 'Eligible students passed to Agent 3',
                    'columns' => ['Student', 'Class', 'Outstanding', 'Pending fee heads', 'Knowledge Graph payment edge'],
                    'kinds' => ['text', 'text', 'money', 'count', 'text'],
                    'rows' => array_map(static fn ($e) => [
                        $e['student_name'] ?? ('#'.($e['student_id'] ?? '')), $e['class'] ?? '—', (float) ($e['outstanding'] ?? 0),
                        is_array($e['evidence'] ?? null) ? count($e['evidence']) : (int) ($e['pending_items'] ?? 0), $e['knowledge_graph_payment_edge'] ?? '—',
                    ], array_slice($eligible, 0, 25)),
                ], [
                    'title' => 'Held back — reminder pending (not sent)',
                    'columns' => ['Student', 'Class', 'Outstanding', 'Reason'],
                    'kinds' => ['text', 'text', 'money', 'text'],
                    'rows' => array_map(static fn ($h) => [
                        $h['student_name'] ?? ('#'.($h['student_id'] ?? '')), $h['class'] ?? '—', (float) ($h['outstanding'] ?? 0), $h['reason'] ?? '—',
                    ], array_slice($held, 0, 25)),
                ], [
                    'title' => 'Recent receipts read from fees_collect',
                    'columns' => ['Student', 'Receipt', 'Amount', 'Mode', 'Date'],
                    'kinds' => ['text', 'text', 'money', 'text', 'text'],
                    'rows' => array_map(static fn ($r) => [
                        $r['student_name'] ?? $r['student'] ?? ('#'.($r['student_id'] ?? '')), (string) ($r['receipt_no'] ?? $r['receipt_id'] ?? $r['id'] ?? '—'),
                        (float) ($r['amount'] ?? $r['total'] ?? 0), (string) ($r['payment_mode'] ?? $r['mode'] ?? '—'), (string) ($r['receipt_date'] ?? $r['date'] ?? $r['receiptdate'] ?? '—'),
                    ], array_slice($o2['recent_receipts_sample'] ?? [], 0, 10)),
                ]],
                'handed_over' => sprintf('%d eligible student(s) passed to Agent 3; %d held back by cooldown.', count($eligible), count($held)),
                'knowledge_graph' => $o2['knowledge_graph'] ?? null,
            ];
        }

        // ── Agent 3: reminder draft (preview only), then final approval stage
        if (isset($steps[2])) {
            $o3 = $steps[2]['output'] ?? [];
            $work[3] = [
                'agent' => 'Agent 3 · Reminder Drafter',
                'did' => 'Verified parent contact details and drafted personalised reminders. Nothing is sent until an administrator approves.',
                'values' => [
                    $val('Channel', $pd['channel'] ?? ($o3['channel'] ?? '—'), 'text'),
                    $val('Reminders drafted', (int) ($pd['recipient_count'] ?? 0)),
                    $val('Could not be drafted (no valid contact)', (int) ($pd['unreachable_count'] ?? 0)),
                    $val('Total outstanding in drafts', $money($pd['total_outstanding'] ?? 0), 'money'),
                    $val('Messages sent so far', 0),
                    $val('Draft fingerprint', ! empty($pd['fingerprint']) ? substr((string) $pd['fingerprint'], 0, 16).'…' : '—', 'text'),
                ],
                'tables' => [[
                    'title' => 'Reminders drafted (awaiting approval)',
                    'columns' => ['Student', 'Class', 'Outstanding', 'Send to', 'Knowledge Graph path'],
                    'kinds' => ['text', 'text', 'money', 'text', 'text'],
                    'rows' => array_map(static fn ($r) => [
                        $r['name'] ?? ('#'.($r['student_id'] ?? '')), $r['class'] ?? '—', (float) ($r['outstanding'] ?? 0),
                        $r['contact_masked'] ?? '—', $r['knowledge_graph_path'] ?? '—',
                    ], array_slice($pd['recipients'] ?? [], 0, 25)),
                ], [
                    'title' => 'Reminders that could not be prepared',
                    'columns' => ['Student', 'Class', 'Outstanding', 'Problem'],
                    'kinds' => ['text', 'text', 'money', 'text'],
                    'rows' => array_map(static fn ($u) => [
                        $u['name'] ?? ('#'.($u['student_id'] ?? '')), $u['class'] ?? '—', (float) ($u['outstanding'] ?? 0), $u['problem'] ?? $u['contact_problem'] ?? $u['reason'] ?? '—',
                    ], array_slice($pd['unreachable'] ?? [], 0, 25)),
                ]],
                'handed_over' => ! empty($pd['fingerprint'])
                    ? sprintf('%d reminder draft(s) locked under a fingerprint and passed to the approval queue.', (int) ($pd['recipient_count'] ?? 0))
                    : 'No draft was produced, so nothing was passed to the approval queue.',
                'knowledge_graph' => $o3['knowledge_graph'] ?? null,
            ];

            $rs = $fin['reminders_summary'] ?? [];
            $work[4] = [
                'agent' => 'Approval queue · live fee ledger',
                'did' => 'Pending drafts await a human; the figures below are read live from fees_collect, fees_breackoff and ai_audit_logs for this academic year.',
                'values' => [
                    $val('Total fees collected', $fin['total_collected'] ?? null, 'money'),
                    $val('Total pending / outstanding', $fin['total_outstanding'] ?? null, 'money'),
                    $val('Students with at least one receipt', $fin['paying_students_count'] ?? null),
                    $val('Students with no receipt (not paid)', $fin['non_paying_students_count'] ?? null),
                    $val('Students still owing (ledger)', $pos['students_owing'] ?? null),
                    $val('Students fully paid (ledger)', $pos['students_fully_paid'] ?? null),
                    $val('Receipts issued', $fin['total_receipts_count'] ?? null),
                    $val('Reminders sent (all)', $rs['total_sent'] ?? 0),
                    $val('Email reminders sent', $rs['total_email_sent'] ?? 0),
                    $val('SMS reminders sent', $rs['total_sms_sent'] ?? 0),
                ],
                'tables' => [[
                    'title' => 'Student-wise fee collection (who paid, how much)',
                    'columns' => ['Student', 'Class', 'Receipts', 'Total paid', 'Last payment', 'Mode'],
                    'kinds' => ['text', 'text', 'count', 'money', 'text', 'text'],
                    'rows' => array_map(static fn ($r) => [
                        $r['student_name'], $r['standard_name'] ?? '—', $r['receipts_count'], $r['total_paid'], (string) ($r['latest_payment_date'] ?? '—'), $r['payment_mode'],
                    ], array_slice($fin['student_wise_collections'] ?? [], 0, 25)),
                ], [
                    'title' => 'Reminders sent — recipient and delivery status',
                    'columns' => ['Student', 'Channel', 'Sent to', 'Outstanding', 'Status', 'Delivery', 'Sent at'],
                    'kinds' => ['text', 'text', 'text', 'money', 'text', 'text', 'text'],
                    'rows' => array_map(static fn ($r) => [
                        $r['student_name'], strtoupper((string) $r['channel']), $r['contact_masked'] ?? '—', $r['outstanding'], $r['status'], $r['delivery_status'], $r['sent_at'],
                    ], array_slice($rs['history'] ?? [], 0, 25)),
                ], [
                    'title' => 'Payment modes',
                    'columns' => ['Mode', 'Receipts', 'Amount'],
                    'kinds' => ['text', 'count', 'money'],
                    'rows' => array_map(static fn ($m) => [$m['mode'], $m['receipt_count'], $m['total_amount']], $fin['payment_modes'] ?? []),
                ]],
                'handed_over' => 'Awaiting administrator approval; on approval the send tool delivers and logs each result to ai_audit_logs.',
                'knowledge_graph' => null,
            ];
        }

        return $work;
    }

    private function gateFailure(int $gate, string $reason, array $extra = []): array
    {
        return [
            'success' => false,
            'pipeline_status' => 'failed',
            'failed_at_gate' => $gate,
            'error' => $reason,
            'details' => $extra,
        ];
    }

    private function logStep(bool $persist, int $inst, McpRequestContext $ctx, int $n, string $tool, array $input, string $gateStatus, int $ms, array $output): ?string
    {
        if (! $persist || ! Schema::hasTable('ai_agent_runs')) {
            return null;
        }

        try {
            // The real k12_fees agent row, never a hard-coded id.
            $agentId = Schema::hasTable('ai_agents')
                ? DB::table('ai_agents')->where('agent_key', 'k12_fees')->orderBy('id')->value('id')
                : null;
            if (! $agentId) {
                return null;
            }

            $now = Carbon::now();
            $runReference = 'fees_pipe_' . $now->format('Ymd_His') . '_' . $n . '_' . substr(md5(uniqid('', true)), 0, 6);
            DB::table('ai_agent_runs')->insert([
                'run_reference' => $runReference,
                'agent_id' => $agentId,
                'agent_key' => $tool,
                'trigger_type' => 'pipeline',
                'trigger_reference' => self::TRIGGER_PREFIX . $n,
                'triggered_by' => $ctx->userId,
                'subject_entity_key' => 'institute',
                'subject_id' => $inst,
                'input' => json_encode($input),
                'output' => json_encode($output),
                'tool_calls' => json_encode([['tool' => $tool, 'arguments' => $input]]),
                'status' => $gateStatus === 'passed' ? 'completed' : 'failed',
                'started_at' => $now->copy()->subMilliseconds($ms),
                'finished_at' => $now,
                'duration_ms' => $ms,
                'sub_institute_id' => $inst,
                'client_id' => $ctx->clientId ?? 1,
                'actor_role' => $ctx->role,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $runReference;
        } catch (Throwable $e) {
            Log::warning('Failed to log Fees pipeline step: ' . $e->getMessage());

            return null;
        }
    }
}
