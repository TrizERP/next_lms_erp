<?php

namespace App\Services\Fees;

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
    private readonly FeesQualityGateEngine $gates;

    public function __construct(private readonly ToolRegistry $registry)
    {
        $this->eso = new FeesEsoRegistry($registry);
        $this->gates = new FeesQualityGateEngine();
    }

    /**
     * Resolve the academic year the fee ledger is read for.
     *
     * Fee data is per institute AND year. Order: explicit request, the caller's own context,
     * then the latest year this institute actually has a fee structure for. Never a constant.
     *
     * @return array{year: ?int, source: string}
     */
    public function resolveAcademicYear(int $subInstituteId, ?int $requested, ?int $fromContext): array
    {
        if ($requested) {
            return ['year' => $requested, 'source' => 'request'];
        }

        if ($fromContext) {
            return ['year' => $fromContext, 'source' => 'session'];
        }

        try {
            $latest = DB::table('fees_breackoff')->where('sub_institute_id', $subInstituteId)->max('syear');
            if ($latest !== null && (int) $latest > 0) {
                return ['year' => (int) $latest, 'source' => 'latest_fee_structure'];
            }
        } catch (Throwable $e) {
            Log::warning('Fees pipeline could not resolve academic year: ' . $e->getMessage());
        }

        return ['year' => null, 'source' => 'unresolved'];
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
            $yearInfo = $this->resolveAcademicYear(
                $inst,
                isset($options['academic_year']) ? (int) $options['academic_year'] : null,
                $base->academicYear
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
        $pos = $position['data'] ?? [];

        $record = $this->stepRecord(1, 'Fee Position & Arrears Reader', 'agt_fees_position_reader', 'fees.position + fees.arrears', $gate, $ms, $arrearsArgs, [
            'total_outstanding_school' => $pos['total_outstanding'] ?? null,
            'students_owing_school' => $pos['students_owing'] ?? null,
            'students_scanned' => $arrearsData['students_checked'] ?? 0,
            'defaulters_found' => count($candidates),
        ]) + [
            'output' => [
                'ledger_headline' => $pos['headline'] ?? null,
                'total_demand' => $pos['total_demand'] ?? null,
                'total_collected' => $pos['total_collected'] ?? null,
                'total_outstanding' => $pos['total_outstanding'] ?? null,
                'collection_rate' => $pos['collection_rate'] ?? null,
                'students_owing' => $pos['students_owing'] ?? null,
                'cohort_size' => $arrearsData['cohort_size'] ?? 0,
                'students_checked' => $arrearsData['students_checked'] ?? 0,
                'truncated' => $arrearsData['truncated'] ?? false,
                'candidates' => array_slice($candidates, 0, 40),
            ],
            'actions' => $this->actions,
        ];

        $state['steps'][] = $record;
        $state['gates']['gate_1'] = $gate;
        $state['position'] = $position;
        $state['arrearsData'] = $arrearsData;
        $state['candidates'] = $candidates;
        $this->logStep($persist, $inst, $context, 1, 'fees.arrears', $arrearsArgs, $gate['status'], $ms, ['defaulters' => count($candidates)]);

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

        $gate = $this->gates->evaluateGate2($candidates, $eligible, $held, $collection, $history);
        $this->noteGate($gate);
        $ms = (int) round((microtime(true) - $t) * 1000);

        $record = $this->stepRecord(2, 'Collection Evidence & Reminder History Analyst', 'agt_fees_evidence_analyst', 'fees.getPending + fees.collection_report + fees.reminder_history', $gate, $ms, ['evidence_limit' => $evidenceLimit, 'period' => $period, 'cooldown_days' => $cooldown], [
            'candidates' => count($candidates),
            'evidence_gathered_for' => count($evidence),
            'eligible' => count($eligible),
            'held_back' => count($held),
            'collected_in_period' => $collection['data']['summary']['total_collected'] ?? null,
        ]) + [
            'output' => [
                'eligible' => $eligible,
                'held_back' => $held,
                'evidence_gathered_for' => count($evidence),
                'collection_period' => $collection['data']['period']['label'] ?? $period,
                'collected_in_period' => $collection['data']['summary']['total_collected'] ?? null,
                'receipts_in_period' => $collection['data']['summary']['total_receipts'] ?? null,
            ],
            'actions' => $this->actions,
        ];

        $state['steps'][] = $record;
        $state['gates']['gate_2'] = $gate;
        $state['eligible'] = $eligible;
        $state['held'] = $held;
        $state['collection'] = $collection['data'] ?? [];
        $this->logStep($persist, $inst, $context, 2, 'fees.reminder_history', ['cooldown_days' => $cooldown], $gate['status'], $ms, ['eligible' => count($eligible), 'held_back' => count($held)]);

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

        $gate = $this->gates->evaluateGate3($eligible, $preview, $previewAttempted);
        $this->noteGate($gate);
        $ms = (int) round((microtime(true) - $t) * 1000);
        $pd = $preview['data'] ?? [];

        $record = $this->stepRecord(3, 'Reminder Drafter', 'agt_fees_reminder_drafter', 'fees.send_reminders (preview)', $gate, $ms, $reminderArgs ?: ['channel' => $channel, 'deadline' => $deadline], [
            'channel' => $channel,
            'recipient_count' => $pd['recipient_count'] ?? 0,
            'unreachable_count' => $pd['unreachable_count'] ?? 0,
            'total_outstanding' => $pd['total_outstanding'] ?? 0,
            'sent' => 0,
        ]) + [
            'output' => [
                'attempted' => $previewAttempted,
                'message' => $preview['message'] ?? null,
                'channel' => $pd['channel'] ?? $channel,
                'deadline' => $pd['deadline'] ?? $deadline,
                'recipient_count' => $pd['recipient_count'] ?? 0,
                'unreachable_count' => $pd['unreachable_count'] ?? 0,
                'total_outstanding' => $pd['total_outstanding'] ?? 0,
                'sent' => 0,
            ],
            'actions' => $this->actions,
        ];

        $state['steps'][] = $record;
        $state['gates']['gate_3'] = $gate;
        $state['preview'] = $preview;
        $state['next_step'] = null;
        $this->saveState($state);
        $this->logStep($persist, $inst, $context, 3, 'fees.send_reminders', $reminderArgs, $gate['status'], $ms, ['recipient_count' => $pd['recipient_count'] ?? 0, 'sent' => 0]);

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

    private function finishFromState(array $s, string $status, ?string $note = null): array
    {
        $durations = array_sum(array_column($s['steps'], 'duration_ms'));

        return $this->finish(
            $s['inst'], $s['year'], $s['year_source'], microtime(true) - $durations / 1000, $s['steps'], $s['gates'],
            $s['position'] ?? [], $s['arrearsData'] ?? [], $s['candidates'] ?? [], $s['eligible'] ?? [], $s['held'] ?? [],
            $s['collection'] ?? [], $s['handoffs'] ?? [], $status, $note, $s['preview'] ?? []
        );
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
    public function getAgentState(int $subInstituteId): object
    {
        $now = Carbon::now();
        $defaultCounts = $this->calculateLiveSummaryCounts($subInstituteId);

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
            $row->summary_counts = is_array($decoded) ? $decoded : $defaultCounts;
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
            }

            if (Schema::hasTable('tblstudent')) {
                $totalAccounts = (int) DB::table('tblstudent')
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('status', 1)
                    ->count();
            }

            if (Schema::hasTable('fees_breackoff')) {
                try {
                    $feesIntel = new \App\Brain\Intelligence\FeesIntelligence($subInstituteId, $year);
                    $pos = $feesIntel->position();
                    $demand = (float) ($pos['demandAmount'] ?? 0);
                    $collected = (float) ($pos['collectedAmount'] ?? $collected);
                    $outstanding = (float) ($pos['outstandingAmount'] ?? 0);
                    $defaulterCount = (int) ($pos['defaulterAccounts'] ?? 0);
                    $totalAccounts = (int) ($pos['feeAccounts'] ?? $totalAccounts);
                } catch (Throwable) {
                    // Fallback to simple calculation if full intelligence class encounters missing join table
                    $demand = (float) DB::table('fees_breackoff')
                        ->where('sub_institute_id', $subInstituteId)
                        ->when($year, fn ($q) => $q->where('syear', $year))
                        ->sum('amount');
                    $outstanding = max(0, $demand - $collected);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Error calculating live fees summary counts: ' . $e->getMessage());
        }

        return [
            'total_demand' => round($demand, 2),
            'total_collected' => round($collected, 2),
            'total_outstanding' => round($outstanding, 2),
            'collection_rate' => $demand > 0 ? round(($collected / $demand) * 100, 1) : 0,
            'students_owing' => $defaulterCount,
            'defaulters_count' => $defaulterCount,
            'fee_accounts' => $totalAccounts,
            'receipts_count' => $receiptCount,
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
        array $collection, array $handoffs, string $status, ?string $note = null, array $preview = []
    ): array {
        $pd = $preview['data'] ?? [];
        $pos = $position['data'] ?? [];

        return [
            'success' => true,
            'pipeline_status' => $status,
            'mode' => 'sequential_fees_pipeline',
            'sub_institute_id' => $inst,
            'academic_year' => $year,
            'academic_year_source' => $yearSource,
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'note' => $note,
            'quality_scorecard' => $this->gates->computeCompositeScore($gates),
            'handoffs' => $handoffs,
            'ledger_position' => [
                'headline' => $pos['headline'] ?? null,
                'total_demand' => $pos['total_demand'] ?? null,
                'total_collected' => $pos['total_collected'] ?? null,
                'total_outstanding' => $pos['total_outstanding'] ?? null,
                'overdue_amount' => $pos['overdue_amount'] ?? null,
                'collection_rate' => $pos['collection_rate'] ?? null,
                'students_owing' => $pos['students_owing'] ?? null,
                'students_fully_paid' => $pos['students_fully_paid'] ?? null,
                'aging' => $pos['aging'] ?? [],
                'classes_with_most_pending' => $pos['classes_with_most_pending'] ?? [],
            ],
            'arrears_scan' => [
                'cohort_size' => $arrearsData['cohort_size'] ?? 0,
                'students_checked' => $arrearsData['students_checked'] ?? 0,
                'truncated' => $arrearsData['truncated'] ?? false,
                'defaulter_count' => $arrearsData['defaulter_count'] ?? 0,
                'total_outstanding_in_scan' => $arrearsData['total_outstanding'] ?? 0,
                'failed_lookups' => count($arrearsData['failed'] ?? []),
                'candidates' => $candidates,
            ],
            'evidence_analysis' => [
                'eligible' => $eligible,
                'held_back' => $held,
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
                'recipient_count' => $pd['recipient_count'] ?? 0,
                'unreachable_count' => $pd['unreachable_count'] ?? 0,
                'total_outstanding' => $pd['total_outstanding'] ?? 0,
                'recipients' => $pd['recipients'] ?? [],
                'unreachable' => $pd['unreachable'] ?? [],
                'sample_message' => $pd['final_message'] ?? ($pd['sample_message'] ?? null),
                'warnings' => $pd['warnings'] ?? [],
                'fingerprint' => $pd['fingerprint'] ?? null,
                'sent' => 0,
                'next_action' => 'Nothing has been sent. An administrator approves this fingerprint through the Fees assistant to send.',
            ],
            'pipeline_execution' => [
                'steps' => $steps,
                'gates' => array_values($gates),
            ],
        ];
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

    private function logStep(bool $persist, int $inst, McpRequestContext $ctx, int $n, string $tool, array $input, string $gateStatus, int $ms, array $output): void
    {
        if (! $persist || ! Schema::hasTable('ai_agent_runs')) {
            return;
        }

        try {
            // The real k12_fees agent row, never a hard-coded id.
            $agentId = Schema::hasTable('ai_agents')
                ? DB::table('ai_agents')->where('agent_key', 'k12_fees')->orderBy('id')->value('id')
                : null;
            if (! $agentId) {
                return;
            }

            $now = Carbon::now();
            DB::table('ai_agent_runs')->insert([
                'run_reference' => 'fees_pipe_' . $now->format('Ymd_His') . '_' . $n . '_' . substr(md5(uniqid('', true)), 0, 6),
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
        } catch (Throwable $e) {
            Log::warning('Failed to log Fees pipeline step: ' . $e->getMessage());
        }
    }
}
