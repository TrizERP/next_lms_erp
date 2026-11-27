<?php

namespace App\Services\AI\Pipeline;

use App\Domain\AI\Support\AiAuditLogger;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sequential, gated, one-agent-per-request pipeline — the Users / Attendance twin of the
 * Fees and Teach/Learn pipelines.
 *
 * What this base owns (so each module owns only its agents):
 *   · the run: step 1 opens it, steps 2..N resume it from server-held state keyed by an
 *     unguessable run id bound to this institute AND this caller — the browser carries an id,
 *     never data, so it cannot tamper with what an agent hands to the next one;
 *   · the gate after every agent, and the handoff check between agents;
 *   · the execution trace — every SQL read, graph query and tool call an agent actually made,
 *     with real row counts and durations — which the UI shows verbatim;
 *   · the one write: an `ai_agent_runs` row per executed step, only when the module has a real
 *     registered agent to attribute it to. No new tables, no migrations.
 *
 * What it never does: write a business record, send a message, or accept a tenant from the
 * request body. The tenant is the authenticated context's institute, always.
 */
abstract class ModuleAgentPipeline
{
    protected const STATE_TTL_SECONDS = 3600;

    /** @var array<int, array<string, mixed>> what the agent now running actually did, in order */
    protected array $actions = [];

    public function __construct(protected readonly ToolRegistry $registry)
    {
    }

    /** Route / cache key, e.g. `attendance`. */
    abstract public function module(): string;

    /** @return array<int, array<string, mixed>> agent descriptors keyed 1..N (see ESO). */
    abstract public function agents(): array;

    /** `ai_agents.agent_key` the step rows are attributed to, or null to persist nothing. */
    abstract protected function persistAgentKey(): ?string;

    /** @return array{year: ?int, source: string} */
    abstract protected function resolveScope(int $inst, McpRequestContext $base, array $options): array;

    /**
     * Run agent $n. Returns the step payload (see runStep for the keys that are read).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    abstract protected function execute(int $n, array $state, McpRequestContext $ctx): array;

    /** Module segment of the audit event names: `module.<this>.pipeline_step`. */
    abstract public function auditModule(): string;

    /**
     * Persist the final output of a finished run (drafts, approvals). Runs once, only when the
     * caller asked to persist. Implementations write ONLY drafts and approval requests — never a
     * business record, never a message — and put what they created into `$state['final']`.
     *
     * @param  array<string, mixed>  $state
     */
    protected function persistFinal(array &$state, McpRequestContext $ctx): void
    {
    }

    /** Live headline counts for the module (read-only, cheap). */
    abstract public function liveState(int $inst, McpRequestContext $base, array $options): array;

    /** Request option keys a run may carry from step 1 to the later steps. */
    protected function optionKeys(): array
    {
        return ['limit', 'persist'];
    }

    public function stepCount(): int
    {
        return count($this->agents());
    }

    // ───────────────────────── continuous automation ─────────────────────────
    //
    // The Teach/Learn model: "start" switches an institute's automation on, and from then on the
    // whole agent sequence runs, one cycle after another, until somebody stops it. The state is a
    // single persistent cache entry per module + institute (no table: schema changes are not allowed
    // here), and an index entry lets the scheduler find every active institute.
    //
    // Two things can run a cycle and they never overlap, because a cycle must hold the lease:
    //   · the browser, while the screen is open, so every agent is watched as it runs;
    //   · `tickActive()` from the scheduler every minute, so it continues with nobody watching.
    // Stopping clears the flag; a cycle in flight notices before its next agent and cancels itself.

    public const AUTO_INDEX = 'pipeline_auto:index';
    public const AUTO_DEFAULT_INTERVAL = 300;
    public const AUTO_MIN_INTERVAL = 60;
    public const AUTO_MAX_INTERVAL = 86400;
    private const AUTO_LEASE_SECONDS = 900;

    private function autoKey(int $inst): string
    {
        return 'pipeline_auto:' . $this->module() . ':' . $inst;
    }

    /** @return array<string, mixed> */
    public function autoState(int $inst): array
    {
        $s = Cache::get($this->autoKey($inst));
        $s = is_array($s) ? $s : [];
        $lease = $s['lease'] ?? null;
        $leased = $lease && ($lease['until'] ?? 0) > time();

        return [
            'active' => (bool) ($s['active'] ?? false),
            'interval_seconds' => (int) ($s['interval_seconds'] ?? self::AUTO_DEFAULT_INTERVAL),
            'options' => $s['options'] ?? [],
            'started_at' => $s['started_at'] ?? null,
            'started_by' => $s['started_by'] ?? null,
            'stopped_at' => $s['stopped_at'] ?? null,
            'stopped_by' => $s['stopped_by'] ?? null,
            'last_cycle_at' => $s['last_cycle_at'] ?? null,
            'last_cycle_status' => $s['last_cycle_status'] ?? null,
            'last_cycle_run_id' => $s['last_cycle_run_id'] ?? null,
            'last_error' => $s['last_error'] ?? null,
            'next_run_at' => $s['next_run_at'] ?? null,
            'cycles' => (int) ($s['cycles'] ?? 0),
            'cycle_running' => $leased,
            'running_run_id' => $leased ? ($lease['run_id'] ?? null) : null,
            'server_time' => Carbon::now()->toIso8601String(),
        ];
    }

    private function autoRaw(int $inst): array
    {
        $s = Cache::get($this->autoKey($inst));

        return is_array($s) ? $s : [];
    }

    private function autoSave(int $inst, array $s): void
    {
        Cache::forever($this->autoKey($inst), $s);
        $index = Cache::get(self::AUTO_INDEX, []);
        $index = is_array($index) ? $index : [];
        $entry = $this->module() . ':' . $inst;
        $has = in_array($entry, $index, true);

        if (! empty($s['active']) && ! $has) {
            $index[] = $entry;
            Cache::forever(self::AUTO_INDEX, array_values($index));
        } elseif (empty($s['active']) && $has) {
            Cache::forever(self::AUTO_INDEX, array_values(array_diff($index, [$entry])));
        }
    }

    public function startAuto(int $inst, McpRequestContext $ctx, ?int $interval = null, array $options = []): array
    {
        $s = $this->autoRaw($inst);
        $interval = min(max($interval ?: (int) ($s['interval_seconds'] ?? self::AUTO_DEFAULT_INTERVAL), self::AUTO_MIN_INTERVAL), self::AUTO_MAX_INTERVAL);

        $s = array_merge($s, [
            'active' => true,
            'interval_seconds' => $interval,
            // What every cycle reads with (e.g. the Attendance window and threshold), kept so a
            // scheduled cycle runs exactly what the person who pressed Start chose.
            'options' => array_intersect_key($options, array_flip($this->optionKeys())),
            'started_at' => Carbon::now()->toIso8601String(),
            'started_by' => ['id' => $ctx->userId, 'name' => $this->userName($inst, $ctx->userId)],
            // What a scheduled cycle runs as: the person who started it, in this institute only.
            'ctx' => ['user' => $ctx->userId, 'role' => $ctx->role, 'profile' => $ctx->userProfileId, 'client' => $ctx->clientId, 'term' => $ctx->termId],
            'stopped_at' => null,
            'stopped_by' => null,
            'last_error' => null,
            // Due now: starting means the whole sequence begins straight away, on the latest data.
            'next_run_at' => Carbon::now()->toIso8601String(),
            'lease' => null,
        ]);
        $this->autoSave($inst, $s);

        return $this->autoState($inst);
    }

    public function stopAuto(int $inst, McpRequestContext $ctx): array
    {
        $s = $this->autoRaw($inst);
        $s = array_merge($s, [
            'active' => false,
            'stopped_at' => Carbon::now()->toIso8601String(),
            'stopped_by' => ['id' => $ctx->userId, 'name' => $this->userName($inst, $ctx->userId)],
            'next_run_at' => null,
            'lease' => null,
        ]);
        $this->autoSave($inst, $s);

        return $this->autoState($inst);
    }

    public function setAutoInterval(int $inst, int $interval): array
    {
        $s = $this->autoRaw($inst);
        $s['interval_seconds'] = min(max($interval, self::AUTO_MIN_INTERVAL), self::AUTO_MAX_INTERVAL);
        if (! empty($s['active']) && empty($s['lease'])) {
            $s['next_run_at'] = Carbon::now()->addSeconds($s['interval_seconds'])->toIso8601String();
        }
        $this->autoSave($inst, $s);

        return $this->autoState($inst);
    }

    /**
     * Scheduler / worker entry point: run a cycle for every active institute of this module that is due.
     *
     * $graceSeconds lets a screen that is open run the due cycle itself first (so its agents are seen
     * working one by one); a cycle overdue by more than the grace is taken here, with no screen open.
     *
     * @return int how many automations of this module are active, so a worker knows when to retire
     */
    public function tickActive(int $graceSeconds = 0): int
    {
        $active = 0;
        $index = Cache::get(self::AUTO_INDEX, []);
        foreach (is_array($index) ? $index : [] as $entry) {
            [$module, $inst] = array_pad(explode(':', (string) $entry, 2), 2, null);
            if ($module !== $this->module() || ! $inst) {
                continue;
            }

            $s = $this->autoRaw((int) $inst);
            if (empty($s['active'])) {
                continue;
            }
            $active++;

            $due = ! empty($s['next_run_at']) && Carbon::parse($s['next_run_at'])->addSeconds($graceSeconds)->lte(Carbon::now());
            if (! $due) {
                continue;
            }

            try {
                $this->runAutoCycle((int) $inst);
            } catch (Throwable $e) {
                Log::warning($this->module() . ' automation tick failed: ' . $e->getMessage());
            }
        }

        return $active;
    }

    /** One complete sequential cycle, as the person who started the automation. */
    public function runAutoCycle(int $inst): array
    {
        $s = $this->autoRaw($inst);
        if (empty($s['active']) || empty($s['ctx'])) {
            return $this->failure(1, 'Automation is not running for this institute.');
        }

        $c = $s['ctx'];
        $ctx = new McpRequestContext((int) $c['user'], (string) $c['role'], $inst, [$inst], $c['profile'] ?? null, $c['client'] ?? null, null, $c['term'] ?? null, true, false);

        return $this->runPipeline($inst, $ctx, ($s['options'] ?? []) + ['auto' => true]);
    }

    private function userName(int $inst, int $userId): ?string
    {
        $u = DB::table('tbluser')->where('sub_institute_id', $inst)->where('id', $userId)->first(['first_name', 'last_name']);

        return $u ? trim($u->first_name . ' ' . $u->last_name) : null;
    }

    /** Take the cycle lease for a new run. False when another cycle holds it. */
    private function autoLease(int $inst, string $runId): bool
    {
        $s = $this->autoRaw($inst);
        $lease = $s['lease'] ?? null;
        if ($lease && ($lease['until'] ?? 0) > time()) {
            return false;
        }
        $s['lease'] = ['run_id' => $runId, 'until' => time() + self::AUTO_LEASE_SECONDS];
        $this->autoSave($inst, $s);

        return true;
    }

    /** Close a cycle: release the lease, stamp the outcome and schedule the next one if still active. */
    private function autoEnd(array $state, string $status, ?string $error = null): void
    {
        $inst = $state['inst'];
        $s = $this->autoRaw($inst);
        if (($s['lease']['run_id'] ?? null) === $state['run_id']) {
            $s['lease'] = null;
        }
        $s['last_cycle_at'] = Carbon::now()->toIso8601String();
        $s['last_cycle_status'] = $status;
        $s['last_cycle_run_id'] = $state['run_id'];
        $s['last_error'] = $error;
        $s['cycles'] = (int) ($s['cycles'] ?? 0) + 1;
        $s['next_run_at'] = ! empty($s['active'])
            ? Carbon::now()->addSeconds((int) ($s['interval_seconds'] ?? self::AUTO_DEFAULT_INTERVAL))->toIso8601String()
            : null;
        $this->autoSave($inst, $s);
    }

    // ───────────────────────── run control ─────────────────────────

    public function runPipeline(int $inst, McpRequestContext $base, array $options = []): array
    {
        if (in_array((string) ($options['step'] ?? 'all'), array_map('strval', range(1, $this->stepCount())), true)) {
            return $this->runStep($inst, $base, (int) $options['step'], $options);
        }

        unset($options['step']);
        $result = [];
        for ($n = 1; $n <= $this->stepCount(); $n++) {
            if (isset($result['run_id'])) {
                $options['run_id'] = $result['run_id'];
            }
            $result = $this->runStep($inst, $base, $n, $options);
            if (! ($result['success'] ?? false) || isset($result['report_complete'])) {
                return $result;
            }
        }

        return $result;
    }

    public function runStep(int $inst, McpRequestContext $base, int $n, array $options = []): array
    {
        @set_time_limit(600);

        if ($n < 1 || $n > $this->stepCount()) {
            return $this->failure(max($n, 1), 'There is no such agent in this pipeline.');
        }

        if ($n === 1) {
            $scope = $this->resolveScope($inst, $base, $options);
            if (! $scope['year']) {
                return $this->failure(1, 'No academic year could be resolved for this institute: ' . ($scope['error'] ?? 'no records exist for it.'));
            }

            $state = [
                'run_id' => (string) Str::uuid(),
                'module' => $this->module(),
                'inst' => $inst,
                'user' => $base->userId,
                'year' => $scope['year'],
                'year_source' => $scope['source'],
                'options' => array_intersect_key($options, array_flip(array_merge($this->optionKeys(), ['auto']))),
                'steps' => [],
                'gates' => [],
                'handoffs' => [],
                'data' => [],
                'next_step' => 1,
                'started_at' => microtime(true),
            ];

            if (! empty($options['auto'])) {
                if (empty($this->autoRaw($inst)['active'])) {
                    return $this->failure(1, 'Automation is stopped for this institute.', ['automation_stopped' => true]);
                }
                if (! $this->autoLease($inst, $state['run_id'])) {
                    return $this->failure(1, 'A cycle is already running for this institute.', ['cycle_in_progress' => true]);
                }
                $state['auto'] = true;
            }
        } else {
            $state = Cache::get($this->stateKey($inst, (string) ($options['run_id'] ?? '')));
            if (! is_array($state) || ($state['inst'] ?? null) !== $inst || ($state['user'] ?? null) !== $base->userId) {
                return $this->failure($n - 1, 'This run was not found or has expired. Start again from Agent 1.');
            }
            if (($state['next_step'] ?? null) !== $n) {
                $expected = $state['next_step'] ?? null;

                return $this->failure($n - 1, $expected === null
                    ? 'This run has already finished or stopped at a gate. Start again from Agent 1.'
                    : "Agent {$n} cannot run yet: the pipeline expects Agent {$expected} next.", ['run_id' => $state['run_id']]);
            }
        }

        // Stop means stop: a cycle in flight is cancelled before its next agent starts.
        if ($n > 1 && ! empty($state['auto']) && empty($this->autoRaw($inst)['active'])) {
            $state['next_step'] = null;
            Cache::put($this->stateKey($inst, $state['run_id']), $state, self::STATE_TTL_SECONDS);
            $this->autoEnd($state, 'cancelled', 'Stopped by the user before Agent ' . $n . '.');

            return $this->failure($n - 1, 'Automation was stopped; this cycle was cancelled before Agent ' . $n . '.', ['run_id' => $state['run_id'], 'cancelled' => true]);
        }

        $ctx = new McpRequestContext(
            $base->userId, $base->role, $inst, $base->allowedInstituteIds ?: [$inst],
            $base->userProfileId, $base->clientId, $state['year'], $base->termId, $base->isAdmin, $base->isStudent
        );

        $this->actions = [];
        $t = microtime(true);

        try {
            $r = $this->execute($n, $state, $ctx);
        } catch (Throwable $e) {
            Log::warning($this->module() . ' pipeline agent ' . $n . ' failed: ' . $e->getMessage());
            $state['next_step'] = null;
            Cache::put($this->stateKey($inst, $state['run_id']), $state, self::STATE_TTL_SECONDS);
            if (! empty($state['auto'])) {
                $this->autoEnd($state, 'failed', 'Agent ' . $n . ': ' . $e->getMessage());
            }

            return $this->failure($n, 'Agent ' . $n . ' stopped with an error: ' . $e->getMessage(), ['run_id' => $state['run_id']]);
        }

        $ms = (int) round((microtime(true) - $t) * 1000);
        $agent = $this->agents()[$n];
        $gate = PipelineGate::evaluate($n, $agent['gate_name'], $r['gate_checks'] ?? []);

        $record = [
            'step' => $n,
            'agent_name' => $agent['name'],
            'agent_id' => $agent['agent_id'],
            'tool' => $r['tool'] ?? '',
            'status' => $gate['status'] === 'passed' ? 'success' : 'failed',
            'gate' => $gate['gate'],
            'gate_score' => $gate['score'],
            'duration_ms' => $ms,
            'input' => $r['input'] ?? [],
            'output_summary' => $r['summary'] ?? [],
            'records' => $r['records'] ?? ['fetched' => 0, 'processed' => 0, 'produced' => 0],
            'metrics' => $r['metrics'] ?? [],
            'tables' => $r['tables'] ?? [],
            'findings' => array_values(array_filter($r['findings'] ?? [])),
            'sources' => $r['sources'] ?? [],
            'actions' => $this->actions,
        ];

        $record['received'] = $n > 1 ? ($state['handoffs']['step_' . ($n - 1) . '_to_step_' . $n] ?? null) : null;
        $state['steps'][] = $record;
        $state['gates']['gate_' . $n] = $gate;
        $state['data'] = array_merge($state['data'], $r['data'] ?? []);
        $agentRunId = $this->logStep($state, $n, $r['tool'] ?? '', $r['input'] ?? [], $gate['status'], $ms, $r['summary'] ?? [], $ctx, $record, $gate);
        $state['agent_run_ids'][$n] = $agentRunId;
        $this->auditStep($state, $n, $record, $gate, $agentRunId, $ctx);

        if ($gate['status'] !== 'passed') {
            $state['next_step'] = null;
            Cache::put($this->stateKey($inst, $state['run_id']), $state, self::STATE_TTL_SECONDS);
            if (! empty($state['auto'])) {
                $this->autoEnd($state, 'failed', "Gate {$n} failed: " . $this->firstFailure($gate));
            }

            return $this->failure($n, "Gate {$n} failed: " . $this->firstFailure($gate), [
                'run_id' => $state['run_id'], 'gate_' . $n => $gate, 'steps' => $state['steps'], 'step_result' => $record,
            ]);
        }

        $isLast = $n === $this->stepCount();

        if (! empty($r['stop']) || $isLast) {
            $state['next_step'] = null;
            $state['final'] = $r['final'] ?? ['headline' => (string) ($r['stop'] ?? ''), 'next_action' => null];
            $this->finalize($state, $ctx, ! empty($r['stop']) ? 'completed_no_action' : 'completed');
            Cache::put($this->stateKey($inst, $state['run_id']), $state, self::STATE_TTL_SECONDS);
            if (! empty($state['auto'])) {
                $this->autoEnd($state, ! empty($r['stop']) ? 'completed_no_action' : 'completed');
            }

            return $this->report($state, ! empty($r['stop']) ? 'completed_no_action' : 'completed', $r['stop'] ?? null)
                + ['report_complete' => true, 'run_id' => $state['run_id'], 'step' => $n, 'step_result' => $record, 'gate' => $gate];
        }

        $handoffGate = PipelineGate::evaluate($n, 'Handoff Agent ' . $n . ' → Agent ' . ($n + 1), $r['handoff'] ?? []);
        $handoff = [
            'from' => $n,
            'to' => $n + 1,
            'valid' => $handoffGate['status'] === 'passed',
            'message' => $r['handoff_message'] ?? '',
            'checks' => $handoffGate['checks'],
        ];
        $state['handoffs']['step_' . $n . '_to_step_' . ($n + 1)] = $handoff;

        if (! $handoff['valid']) {
            $state['next_step'] = null;
            Cache::put($this->stateKey($inst, $state['run_id']), $state, self::STATE_TTL_SECONDS);
            if (! empty($state['auto'])) {
                $this->autoEnd($state, 'failed', 'Handoff to Agent ' . ($n + 1) . ' was rejected.');
            }

            return $this->failure($n, 'Handoff to Agent ' . ($n + 1) . ' was rejected: ' . $this->firstFailure($handoffGate), [
                'run_id' => $state['run_id'], 'steps' => $state['steps'], 'step_result' => $record, 'handoff' => $handoff,
            ]);
        }

        $record['passed_to_next'] = $handoff;
        $state['steps'][array_key_last($state['steps'])] = $record;
        $state['next_step'] = $n + 1;
        Cache::put($this->stateKey($inst, $state['run_id']), $state, self::STATE_TTL_SECONDS);
        $next = $this->agents()[$n + 1];

        return [
            'success' => true,
            'run_id' => $state['run_id'],
            'step' => $n,
            'pipeline_status' => 'step_' . $n . '_completed',
            'academic_year' => $state['year'],
            'academic_year_source' => $state['year_source'],
            'step_result' => $record,
            'gate' => $gate,
            'handoff' => $handoff,
            'next' => ['agent' => $n + 1, 'name' => $next['name'], 'message' => $r['handoff_message'] ?? ''],
        ];
    }

    // ───────────────────────── report ─────────────────────────

    protected function report(array $state, string $status, ?string $note): array
    {
        $steps = $state['steps'];

        return [
            'success' => true,
            'pipeline_status' => $status,
            'mode' => 'sequential_' . $this->module() . '_pipeline',
            'module' => $this->module(),
            'sub_institute_id' => $state['inst'],
            'academic_year' => $state['year'],
            'academic_year_source' => $state['year_source'],
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_duration_ms' => (int) array_sum(array_column($steps, 'duration_ms')),
            'note' => $note,
            'quality_scorecard' => PipelineGate::composite($state['gates']),
            'handoffs' => $state['handoffs'],
            'final_output' => $state['final'] ?? null,
            'pipeline_execution' => ['steps' => $steps, 'gates' => array_values($state['gates'])],
        ];
    }

    protected function failure(int $gate, string $reason, array $extra = []): array
    {
        return [
            'success' => false,
            'pipeline_status' => 'failed',
            'failed_at_gate' => $gate,
            'error' => $reason,
            'details' => $extra,
        ];
    }

    private function firstFailure(array $gate): string
    {
        foreach ($gate['checks'] as $name => $check) {
            if ($check['status'] !== 'passed') {
                return str_replace('_', ' ', $name) . ' — ' . $check['message'];
            }
        }

        return 'a check did not pass.';
    }

    private function stateKey(int $inst, string $runId): string
    {
        return $this->module() . '_pipeline:' . $inst . ':' . $runId;
    }

    // ───────────────────────── trace helpers ─────────────────────────

    /**
     * Run a database read and record it in the execution trace.
     *
     * @param  array<int, string>  $tables
     */
    protected function sql(string $label, array $tables, callable $fn): mixed
    {
        $t = microtime(true);
        $result = $fn();
        $rows = is_countable($result) ? count($result) : (is_object($result) || is_array($result) ? 1 : null);
        $this->actions[] = [
            'type' => 'sql',
            'tool' => $label,
            'tables' => $tables,
            'rows' => $rows,
            'duration_ms' => (int) round((microtime(true) - $t) * 1000),
            'success' => true,
            'summary' => 'Read ' . implode(', ', $tables) . ($rows === null ? '' : " ({$rows} row(s))"),
        ];

        return $result;
    }

    /** Run a Knowledge Graph read and record it. `$fn` returns an array of rows. */
    protected function graph(string $label, string $pattern, callable $fn, ?callable $measure = null): array
    {
        $t = microtime(true);
        try {
            $rows = $fn();
            $ok = true;
            $summary = ($measure ? $measure($rows) : count($rows)) . ' row(s) from the graph';
        } catch (Throwable $e) {
            $rows = [];
            $ok = false;
            $summary = 'Graph unavailable: ' . mb_substr($e->getMessage(), 0, 160);
        }

        $this->actions[] = [
            'type' => 'graph',
            'tool' => $label,
            'pattern' => $pattern,
            'rows' => $measure ? $measure($rows) : count($rows),
            'duration_ms' => (int) round((microtime(true) - $t) * 1000),
            'success' => $ok,
            'summary' => $summary,
        ];

        return ['ok' => $ok, 'rows' => $rows, 'error' => $ok ? null : $summary];
    }

    /** Record a local check the agent made (a comparison, a recomputation). */
    protected function note(string $label, string $summary, bool $ok = true): void
    {
        $this->actions[] = ['type' => 'check', 'tool' => $label, 'duration_ms' => 0, 'success' => $ok, 'summary' => $summary];
    }

    /** Call a governed MCP read tool and record it. Never throws. */
    protected function tool(string $tool, array $args, McpRequestContext $ctx): array
    {
        $t = microtime(true);
        try {
            $raw = $this->registry->execute($tool, $args, $ctx);
            $result = $raw['result'] ?? $raw;
            if (! is_array($result)) {
                $result = ['success' => false, 'message' => 'Tool returned no result.'];
            } elseif (! array_key_exists('success', $result)) {
                $result = ['success' => true, 'message' => (string) ($result['headline'] ?? ''), 'data' => $result];
            }
        } catch (Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        }

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

    /** Standard column/row table block for the UI. @param array<int, array{0:string,1:string}> $cols */
    protected function table(string $key, string $title, array $cols, array $rows, ?int $total = null, ?string $note = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'columns' => array_map(static fn (array $c) => ['key' => $c[0], 'label' => $c[1]], $cols),
            'rows' => array_values($rows),
            'total' => $total ?? count($rows),
            'note' => $note,
        ];
    }

    // ───────────────────────── persistence ─────────────────────────

    private function persists(array $state): bool
    {
        return ($state['options']['persist'] ?? true) !== false;
    }

    private function finalize(array &$state, McpRequestContext $ctx, string $status): void
    {
        if (! $this->persists($state)) {
            return;
        }

        try {
            $this->persistFinal($state, $ctx);
        } catch (Throwable $e) {
            Log::warning($this->module() . ' pipeline could not persist its final output: ' . $e->getMessage());
            $state['final']['persist_error'] = 'The drafts could not be saved for review: ' . $e->getMessage();
        }

        $final = $state['final'] ?? [];
        $finalEvent = 'module.' . $this->auditModule() . '.pipeline_final';
        $existing = $this->auditId($finalEvent, (string) $state['run_id'], null, (int) $state['inst']);
        if ($existing !== null) {
            $state['final']['audit_id'] = $existing;

            return;
        }

        $id = app(AiAuditLogger::class)->record($finalEvent, $ctx, [
            'related_type' => 'pipeline_run',
            'message' => (string) ($final['headline'] ?? ''),
            'duration_ms' => (int) array_sum(array_column($state['steps'], 'duration_ms')),
            'knowledge_graph_used' => true,
            'payload' => [
                'pipeline_run_id' => $state['run_id'],
                'status' => $status,
                'academic_year' => $state['year'],
                'academic_year_source' => $state['year_source'],
                'options' => $state['options'],
                'final' => $final,
                'agent_run_ids' => $state['agent_run_ids'] ?? [],
                'gates' => array_map(static fn (array $g) => ['name' => $g['name'], 'status' => $g['status'], 'score' => $g['score']], $state['gates']),
                'steps' => array_map(static fn (array $s) => [
                    'step' => $s['step'], 'agent_id' => $s['agent_id'], 'agent_name' => $s['agent_name'], 'status' => $s['status'],
                    'duration_ms' => $s['duration_ms'], 'records' => $s['records'], 'gate_score' => $s['gate_score'],
                ], $state['steps']),
            ],
        ]);
        $state['final']['audit_id'] = $id;
    }

    /**
     * The audit row already written for this run (and step), if any. Scoped to the institute, so one
     * tenant's run id can never satisfy another's check.
     */
    private function auditId(string $event, string $runId, ?int $step, int $inst): ?int
    {
        try {
            $query = DB::table('ai_audit_logs')
                ->where('event_type', $event)
                ->where('sub_institute_id', $inst)
                ->where('payload', 'like', '%"pipeline_run_id":"' . str_replace(['%', '_', '"'], '', $runId) . '"%');
            if ($step !== null) {
                $query->where('related_id', $step);
            }
            $id = $query->orderBy('id')->value('id');

            return $id ? (int) $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** One audit event per executed agent: the durable, tenant-scoped history the dashboards read. */
    private function auditStep(array $state, int $n, array $record, array $gate, ?int $agentRunId, McpRequestContext $ctx): void
    {
        if (! $this->persists($state)) {
            return;
        }

        // A retried request for a step that is already on record must not write it twice.
        $event = 'module.' . $this->auditModule() . '.pipeline_step';
        if ($this->auditId($event, (string) $state['run_id'], $n, (int) $state['inst']) !== null) {
            return;
        }

        $tables = array_map(static fn (array $t) => array_merge($t, ['rows' => array_slice($t['rows'], 0, 50)]), $record['tables']);
        app(AiAuditLogger::class)->record('module.' . $this->auditModule() . '.pipeline_step', $ctx, [
            'related_type' => $record['agent_id'],
            'related_id' => $n,
            'outcome' => $record['status'] === 'success' ? 'success' : 'failed',
            'message' => $record['agent_name'],
            'duration_ms' => $record['duration_ms'],
            'knowledge_graph_used' => count(array_filter($record['actions'], static fn (array $a) => $a['type'] === 'graph')) > 0,
            'payload' => [
                'pipeline_run_id' => $state['run_id'],
                'agent_run_id' => $agentRunId,
                'step' => $n,
                'agent_id' => $record['agent_id'],
                'agent_name' => $record['agent_name'],
                'tool' => $record['tool'],
                'academic_year' => $state['year'],
                'input' => $record['input'],
                'records' => $record['records'],
                'metrics' => $record['metrics'],
                'findings' => $record['findings'],
                'sources' => $record['sources'],
                'actions' => $record['actions'],
                'tables' => $tables,
                'gate' => $gate,
            ],
        ]);
    }

    private function logStep(array $state, int $n, string $tool, array $input, string $gateStatus, int $ms, array $output, McpRequestContext $ctx, array $record = [], array $gate = []): ?int
    {
        $persistKey = $this->persistAgentKey();
        if ($persistKey === null || ! $this->persists($state) || ! Schema::hasTable('ai_agent_runs')) {
            return null;
        }

        try {
            $agentId = Schema::hasTable('ai_agents')
                ? DB::table('ai_agents')->where('agent_key', $persistKey)->where('status', 1)->orderBy('id')->value('id')
                : null;
            if (! $agentId) {
                return null;
            }

            $now = Carbon::now();

            return (int) DB::table('ai_agent_runs')->insertGetId([
                'run_reference' => $this->module() . '_pipe_' . $now->format('Ymd_His') . '_' . $n . '_' . substr(md5(uniqid('', true)), 0, 6),
                'agent_id' => $agentId,
                'agent_key' => $persistKey,
                'trigger_type' => 'pipeline',
                'trigger_reference' => $this->module() . '_pipeline_step_' . $n,
                'triggered_by' => $ctx->userId,
                'subject_entity_key' => 'institute',
                'subject_id' => $state['inst'],
                'input' => json_encode($input + ['pipeline_run_id' => $state['run_id'], 'step' => $n]),
                'output' => json_encode($output + ['records' => $record['records'] ?? null, 'gate' => ['score' => $gate['score'] ?? null, 'status' => $gateStatus]]),
                'tool_calls' => json_encode(array_map(static fn (array $a) => ['tool' => $a['tool'], 'type' => $a['type'], 'rows' => $a['rows'] ?? null], $this->actions)),
                'status' => $gateStatus === 'passed' ? 'completed' : 'failed',
                'started_at' => $now->copy()->subMilliseconds($ms),
                'finished_at' => $now,
                'duration_ms' => $ms,
                'sub_institute_id' => $state['inst'],
                'client_id' => $ctx->clientId ?? 1,
                'academic_year' => $state['year'],
                'actor_role' => $ctx->role,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            Log::warning($this->module() . ' pipeline could not log step ' . $n . ': ' . $e->getMessage());

            return null;
        }
    }

    /** Recent persisted steps for this module (state endpoint). */
    public function recentRuns(int $inst, int $limit = 20): array
    {
        $key = $this->persistAgentKey();
        if ($key === null || ! Schema::hasTable('ai_agent_runs')) {
            return [];
        }

        return DB::table('ai_agent_runs')
            ->where('sub_institute_id', $inst)
            ->where('agent_key', $key)
            ->where('trigger_type', 'pipeline')
            ->where('trigger_reference', 'like', $this->module() . '_pipeline_step_%')
            ->orderByDesc('id')->limit($limit)
            ->get(['id', 'run_reference', 'trigger_reference', 'status', 'duration_ms', 'started_at'])
            ->map(static fn ($r) => [
                'id' => (int) $r->id,
                'run_reference' => $r->run_reference,
                'step' => (int) substr((string) $r->trigger_reference, -1),
                'status' => $r->status,
                'duration_ms' => $r->duration_ms,
                'started_at' => $r->started_at,
            ])->all();
    }
}
