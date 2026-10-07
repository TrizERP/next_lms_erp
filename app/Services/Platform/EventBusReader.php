<?php

namespace App\Services\Platform;

use App\Services\Graph\GraphDrain;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every query behind the Event Bus screen, in one read-only place.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS IS
 * ---------------------------------------------------------------------------
 * A window onto mechanisms that already run. It creates nothing: no table, no
 * broker, no event store, no queue. Every method below is a SELECT over a table
 * some other part of this application already writes — the `sync_log` outbox the
 * database triggers feed and `neo4j:drain` consumes, the fourteen typed rows
 * `AiAuditLogger` writes, `WorkflowEngine`'s runs and steps, Laravel's own
 * `failed_jobs`, and the four communication send-logs.
 *
 * ---------------------------------------------------------------------------
 * THE ONE RULE
 * ---------------------------------------------------------------------------
 * A number leaves this class only if a table produced it. Where a column does
 * not exist the answer is null and the caller says so; where a table is empty
 * the answer is an empty list. Nothing here defaults, estimates, seeds or
 * back-fills. An operations screen is believed and acted on, which is exactly
 * why it may not guess: "Failed events: 0" is the sentence a reader is least
 * likely to question and the most dangerous one to get wrong.
 *
 * That rule is why several methods return less than the screen has room for:
 *
 *   - `sync_log` RECORDS NO ERROR TEXT. On failure `GraphDrain` updates `status`
 *     and `retry_count` only; the message goes to the log file. So outbox
 *     failures come back with `error => null`, not with a manufactured sentence.
 *   - `sms_sent_parents`, `sms_sent_staff` AND `email_sent_parents` HAVE NO
 *     STATUS COLUMN. They record that a message was composed and handed on, and
 *     nothing after that. Their deliveries come back with `status => null`.
 *     Only WhatsApp has a real receipt, refreshed by SyncWPDeliveryStatus.
 *   - `sync_log` HAS NO MODULE COLUMN, so stream rows carry `module => null`.
 *
 * ---------------------------------------------------------------------------
 * TENANCY, AND THE PLACE IT BREAKS
 * ---------------------------------------------------------------------------
 * Nine of the ten tables carry `sub_institute_id` and are filtered by it.
 * `sync_log` does not — GraphDrain says so outright: "the tenant is exactly what
 * an outbox row does not carry". The tenant appears only inside `payload_json`,
 * only for the handful of tables whose trigger declares it as a hint, and on no
 * index.
 *
 * Filtering on that JSON would silently DROP every untagged row, so the outbox
 * counts would be quietly wrong — the one failure mode this screen exists to
 * prevent. So outbox reads are estate-wide and every payload that contains one
 * says `scope => 'estate'`. The screen shows that. It is a disclosed limitation,
 * not a silent one, and §"Decisions still open" in the handover names it as the
 * thing to settle before this reaches customers.
 *
 * ---------------------------------------------------------------------------
 * DEFENSIVE ABOUT MISSING TABLES
 * ---------------------------------------------------------------------------
 * `Schema::hasTable()` guards every source, the way OutcomeController and
 * WorkflowController already do. A database that has not run a migration should
 * give a screen with one panel missing, not a 500.
 */
class EventBusReader
{
    /** Never let one page of a union pull an unbounded slice from each source. */
    private const MAX_SLICE = 2000;

    /**
     * `sync_log` and `neo4j_sync_queue` disagree about case, and both are live.
     * GraphOutbox writes 'PENDING'/'SUCCESS'/'FAILED' to one and
     * 'pending'/'done'/'failed' to the other, so every comparison has to accept
     * both spellings rather than pick one and quietly miss half the rows.
     */
    private const OUTBOX_PENDING = ['PENDING', 'pending'];
    private const OUTBOX_FAILED = ['FAILED', 'failed'];

    // =======================================================================
    // Overview
    // =======================================================================

    /**
     * The overview, assembled for the caller's tier.
     *
     * TIER 2 IS NOT COMPUTED FOR A CALLER WHO MAY NOT SEE IT. The four outbox
     * KPIs, the volume series and the recent-failures panel all read tables with
     * no tenant column, so for anyone but a Super Admin the branches below simply
     * do not run. Fetching estate data and filtering it afterwards would put a
     * leak one forgotten line away; not fetching it cannot leak at all.
     *
     * `scope` reports what the payload actually contains, so the screen is not
     * left guessing: `tenant` when every number in it came from the caller's own
     * institute, `estate` when some did not.
     *
     * The two tenant-scoped KPIs are unchanged in either tier — they were always
     * filtered by `sub_institute_id`.
     *
     * @return array{kpis: array<int, array<string, mixed>>, volume: ?array<int, array<string, mixed>>, recentFailures: ?array<int, array<string, mixed>>, generatedAt: string, scope: string, restricted: ?array<string, mixed>}
     */
    public function overview(int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to, bool $superAdmin): array
    {
        $since = CarbonImmutable::now()->subDay();

        // Tier 1 — every source here is filtered by the caller's own institute.
        $kpis = [
            $this->kpiDeliveryRate($tenantId, $since),
            $this->kpiAuditActivity($tenantId, $since),
        ];

        if (! $superAdmin) {
            return [
                'kpis' => $kpis,
                'volume' => null,
                'recentFailures' => null,
                'generatedAt' => CarbonImmutable::now()->toIso8601String(),
                'scope' => 'tenant',
                'restricted' => [
                    'restricted' => true,
                    'reason' => 'Super Admin only',
                    // Named so the screen can label each withheld panel rather
                    // than blanking the page. These are the KPI keys and section
                    // names the frontend already knows.
                    'sections' => ['captured', 'depth', 'oldest', 'failed', 'volume', 'recentFailures'],
                ],
            ];
        }

        // Tier 2 — estate-wide. Ordered ahead of the tenant pair so the row reads
        // the way it always has for the one role that sees all six.
        return [
            'kpis' => array_merge([
                $this->kpiEventsCaptured($since),
                $this->kpiOutboxDepth(),
                $this->kpiOldestPending(),
                $this->kpiFailedEvents($tenantId),
            ], $kpis),
            'volume' => $this->volume(),
            'recentFailures' => array_slice($this->failureRows($tenantId, $from, $to, 6, 0), 0, 6),
            'generatedAt' => CarbonImmutable::now()->toIso8601String(),
            'scope' => 'estate',
            'restricted' => null,
        ];
    }

    /** Outbox rows written in the last day. `sync_log` only, as specified. */
    private function kpiEventsCaptured(CarbonImmutable $since): array
    {
        if (! Schema::hasTable('sync_log')) {
            return $this->kpi('captured', 'Events captured (24h)', null, 'Table sync_log is not present in this database.', 'gray', 'sync_log');
        }

        $count = (int) DB::table('sync_log')->where('created_at', '>=', $since)->count();

        return $this->kpi(
            'captured',
            'Events captured (24h)',
            number_format($count),
            'Outbox rows written in the last 24 hours. Estate-wide: sync_log carries no tenant column.',
            'gray',
            'sync_log',
        );
    }

    /**
     * Depth, from the service that already computes it.
     *
     * `GraphDrain::depth()` is reused rather than re-queried because
     * Console\Kernel's live alert calls the same method and compares
     * nodes + rels against 1000. A second implementation here would let the
     * dashboard and the alert disagree about what "deep" means, which is worse
     * than having no dashboard.
     */
    private function kpiOutboxDepth(): array
    {
        if (! Schema::hasTable('sync_log')) {
            return $this->kpi('depth', 'Outbox depth', null, 'Table sync_log is not present in this database.', 'gray', 'sync_log');
        }

        $depth = GraphDrain::depth();
        $total = (int) $depth['nodes'] + (int) $depth['rels'];

        return $this->kpi(
            'depth',
            'Outbox depth',
            number_format($total),
            sprintf(
                '%s node rows and %s relationship rows still pending. The existing alert fires above 1,000.',
                number_format((int) $depth['nodes']),
                number_format((int) $depth['rels']),
            ),
            $total > 1000 ? 'red' : ($total > 250 ? 'amber' : 'green'),
            'sync_log, neo4j_sync_queue (via GraphDrain::depth)',
        );
    }

    /**
     * Age of the oldest unprocessed row.
     *
     * Age rather than depth is the measure that catches a STOPPED consumer: the
     * stall Console\Kernel was taught to alert on had roughly twenty queued rows
     * — far under any sane depth threshold — while the drain had been dead for
     * forty-five minutes. Same 15-minute threshold as that alert, for the same
     * reason the depth KPI reuses its service.
     */
    private function kpiOldestPending(): array
    {
        if (! Schema::hasTable('sync_log')) {
            return $this->kpi('oldest', 'Oldest pending event', null, 'Table sync_log is not present in this database.', 'gray', 'sync_log');
        }

        $oldest = DB::table('sync_log')
            ->whereIn('status', self::OUTBOX_PENDING)
            ->min('created_at');

        if ($oldest === null) {
            return $this->kpi('oldest', 'Oldest pending event', 'None', 'Nothing is waiting — every captured row has been processed.', 'green', 'sync_log');
        }

        $minutes = CarbonImmutable::parse($oldest)->diffInMinutes(CarbonImmutable::now());

        return $this->kpi(
            'oldest',
            'Oldest pending event',
            $minutes >= 120 ? round($minutes / 60) . ' h' : $minutes . ' min',
            'Age of the oldest unprocessed outbox row. The existing alert fires above 15 minutes.',
            $minutes > 15 ? 'red' : 'green',
            'sync_log',
        );
    }

    /**
    * Failures across every place this application records one.
     *
     * DELIBERATELY WIDER THAN failed_jobs ALONE. `QUEUE_CONNECTION=sync`, so the
     * three ShouldQueue jobs run inline and a failure throws rather than landing
     * in `failed_jobs` — that table reads zero and will keep reading zero until
     * real workers exist. A KPI showing 0 directly above a Failures table
    * listing rows is a defect, so this counts the same sources the table
     * lists and the hint breaks the total down so the failed_jobs figure stays
     * legible on its own.
     */
    private function kpiFailedEvents(int $tenantId): array
    {
        $jobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $outbox = Schema::hasTable('sync_log') ? (int) DB::table('sync_log')->whereIn('status', self::OUTBOX_FAILED)->count() : 0;
        $runs = Schema::hasTable('workflow_runs')
            ? (int) DB::table('workflow_runs')->whereIn('status', ['failed', 'timed_out'])->count()
            : 0;
        $steps = $this->failedStepCount($tenantId);
        $total = $jobs + $outbox + $runs + $steps;

        return $this->kpi(
            'failed',
            'Failed events',
            number_format($total),
            sprintf('%s in failed_jobs · %s in sync_log · %s workflow runs · %s workflow steps.', number_format($jobs), number_format($outbox), number_format($runs), number_format($steps)),
            $total > 0 ? 'red' : 'green',
            'failed_jobs, sync_log, workflow_runs, workflow_steps',
        );
    }

    /**
     * Delivery rate, computable for exactly one channel.
     *
     * WhatsApp is the only channel in this estate whose rows carry an outcome —
     * `message_status` is refreshed by the SyncWPDeliveryStatus command polling
     * Twilio. `sms_sent_parents`, `sms_sent_staff` and `email_sent_parents` have
     * no status column at all, so counting their rows as "delivered" would be
     * asserting something no table knows.
     *
     * So the rate is WhatsApp's, stated as WhatsApp's, and the other three
     * contribute their real volume to the hint and nothing to the numerator.
     * With no WhatsApp rows in the window there is no rate, and the tile says
     * so rather than showing 0% or 100%.
     */
    private function kpiDeliveryRate(int $tenantId, CarbonImmutable $since): array
    {
        $sent = $this->sentVolume($tenantId, $since);

        if (! Schema::hasTable('whatsapp_sent_messages')) {
            return $this->kpi('delivery', 'Notification delivery rate', null, 'Table whatsapp_sent_messages is not present in this database.', 'gray', 'whatsapp_sent_messages');
        }

        $base = DB::table('whatsapp_sent_messages')
            ->where('sub_institute_id', $tenantId)
            ->whereDate('sent_date', '>=', $since->toDateString());

        $total = (int) (clone $base)->count();

        if ($total === 0) {
            return $this->kpi(
                'delivery',
                'Notification delivery rate',
                null,
                sprintf('No WhatsApp messages in the last 24 hours, so there is no rate to report. Other channels: %s sent, none of which record delivery.', number_format($sent['other'])),
                'gray',
                'whatsapp_sent_messages',
            );
        }

        // Anything that is not an explicit failure counts as got-through. The
        // vocabulary is Twilio's / Meta's, not ours, so failures are named and
        // everything else is accepted rather than the reverse — an unrecognised
        // new status should not silently count as a failure.
        $failed = (int) (clone $base)
            ->whereIn(DB::raw('LOWER(message_status)'), ['failed', 'undelivered', 'rejected'])
            ->count();

        $rate = round((($total - $failed) / $total) * 100, 1);

        return $this->kpi(
            'delivery',
            'Notification delivery rate',
            $rate . '%',
            sprintf('WhatsApp only — %s of %s got through. SMS and email record no receipt (%s sent, delivery unknown).', number_format($total - $failed), number_format($total), number_format($sent['other'])),
            $rate < 90 ? 'amber' : 'green',
            'whatsapp_sent_messages',
        );
    }

    /** Rows written by the three audit-bearing tables in the window. */
    private function kpiAuditActivity(int $tenantId, CarbonImmutable $since): array
    {
        $audit = Schema::hasTable('ai_audit_logs')
            ? (int) DB::table('ai_audit_logs')
                ->where('created_at', '>=', $since)
                ->where(fn ($q) => $q->where('sub_institute_id', $tenantId)->orWhereNull('sub_institute_id'))
                ->count()
            : 0;

        $runs = Schema::hasTable('workflow_runs')
            ? (int) DB::table('workflow_runs')->where('sub_institute_id', $tenantId)->where('created_at', '>=', $since)->count()
            : 0;

        $steps = 0;
        if (Schema::hasTable('workflow_steps') && Schema::hasTable('workflow_runs')) {
            $steps = (int) DB::table('workflow_steps as s')
                ->join('workflow_runs as r', 'r.id', '=', 's.run_id')
                ->where('r.sub_institute_id', $tenantId)
                ->where('s.created_at', '>=', $since)
                ->count();
        }

        $total = $audit + $runs + $steps;

        return $this->kpi(
            'audit',
            'Audit activity (24h)',
            number_format($total),
            sprintf('%s audit entries · %s workflow runs · %s workflow steps.', number_format($audit), number_format($runs), number_format($steps)),
            'gray',
            'ai_audit_logs, workflow_runs, workflow_steps',
        );
    }

    /**
     * Twenty-four hourly buckets of outbox capture, split by outcome.
     *
     * One GROUP BY rather than twenty-four counts, then zero-filled in PHP so an
     * hour in which nothing happened is a real zero in the series rather than a
     * missing bar the chart would silently close up.
     */
    private function volume(): array
    {
        $base = CarbonImmutable::now()->startOfHour();
        $buckets = [];

        for ($i = 23; $i >= 0; $i--) {
            $at = $base->subHours($i);
            $buckets[$at->format('Y-m-d H')] = [
                'label' => $at->format('H:i'),
                'completed' => 0,
                'pending' => 0,
                'failed' => 0,
            ];
        }

        if (! Schema::hasTable('sync_log')) {
            return array_values($buckets);
        }

        $rows = DB::table('sync_log')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H') as bucket, status, COUNT(*) as total")
            ->where('created_at', '>=', $base->subHours(23))
            ->groupBy('bucket', 'status')
            ->get();

        foreach ($rows as $row) {
            if (! isset($buckets[$row->bucket])) {
                continue;
            }

            $status = strtolower((string) $row->status);
            $key = match (true) {
                in_array($status, ['success', 'done'], true) => 'completed',
                $status === 'failed' => 'failed',
                default => 'pending',
            };

            $buckets[$row->bucket][$key] += (int) $row->total;
        }

        return array_values($buckets);
    }

    // =======================================================================
    // Event stream — sync_log
    // =======================================================================

    /**
     * @return array{rows: array<int, array<string, mixed>>, page: int, pageSize: int, total: int, scope: string, visible_events?: int, excluded_events?: int, partial?: bool}
     */
    public function stream(?int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to, ?string $status, ?string $eventType, string $search, int $page, int $pageSize): array
    {
        if (! Schema::hasTable('sync_log')) {
            return $this->emptyPage($page, $pageSize);
        }

        $query = DB::table('sync_log');

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }
        if ($eventType !== null && $eventType !== '') {
            $query->where('table_name', $eventType);
        }
        if ($status !== null && $status !== '') {
            $query->whereIn('status', $this->statusSpellings($status));
        }
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('record_id', 'like', '%' . $search . '%')
                    ->orWhere('table_name', 'like', '%' . $search . '%');
            });
        }

        $total = (int) (clone $query)->count();

        if ($tenantId !== null) {
            // JSON_UNQUOTE, and the tenant bound as a STRING, on purpose.
            //
            // `JSON_EXTRACT(...) = 1` is a typed comparison: it matches the JSON
            // number 1 and NOT the JSON string "1". Most projections emit this
            // column as a number — GraphSchema casts `_id`-suffixed properties to
            // int, and the database triggers copy an INT column — but not all of
            // them are guaranteed to, and a projection that ever emitted a string
            // would have its rows drop silently into `excluded_events` with no
            // way to tell that from a row that genuinely has no institute.
            // Unquoting both sides compares '1' with '1' either way.
            $visible = (clone $query)
                ->whereRaw('JSON_VALID(payload_json) = 1')
                ->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.data.sub_institute_id')) = ?",
                    [(string) $tenantId]
                );
            $visibleTotal = (int) (clone $visible)->count();

            $rows = $visible
                ->orderByDesc('id')
                ->forPage($page, $pageSize)
                ->get()
                ->map(fn ($row) => $this->streamRow($row))
                ->all();

            return [
                'rows' => $rows,
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $visibleTotal,
                'scope' => 'tenant',
                'visible_events' => $visibleTotal,
                'excluded_events' => max(0, $total - $visibleTotal),
                'partial' => true,
            ];
        }

        $rows = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(fn ($row) => $this->streamRow($row))
            ->all();

        return [
            'rows' => $rows,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'scope' => 'estate',
        ];
    }

    private function streamRow(object $row): array
    {
        return [
                'id' => 'sync_log:' . $row->id,
                'occurredAt' => $this->iso($row->created_at),
                'eventType' => (string) $row->table_name,
                // sync_log has no module column and no reliable derivation. A
                // table name is not a module, and mapping one to the other here
                // would be this file inventing an attribution the database does
                // not hold.
                'module' => null,
                'entityType' => (string) $row->table_name,
                'entityId' => (string) $row->record_id,
                'status' => $this->normaliseStatus((string) $row->status),
                'retryCount' => (int) ($row->retry_count ?? 0),
                'source' => 'sync_log',
        ];
    }

    // =======================================================================
    // Failures — failed_jobs + sync_log + workflow_runs + workflow_steps
    // =======================================================================

    public function failures(int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to, string $search, int $page, int $pageSize): array
    {
        $total = $this->failureTotal($tenantId, $from, $to);
        $rows = $this->failureRows($tenantId, $from, $to, $pageSize, ($page - 1) * $pageSize, $search);

        return [
            'rows' => $rows,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'scope' => 'estate',
        ];
    }

    /**
     * Merge three differently-shaped sources into one ordered page.
     *
     * Each source is asked for offset+limit rows, merged, sorted by time and
     * sliced. That is what makes paging across a union correct: taking `limit`
     * from each and slicing would drop rows whenever one source dominates a
     * page. The per-source pull is capped so a deep page cannot ask for an
     * unbounded read.
     */
    private function failureRows(int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to, int $limit, int $offset, string $search = ''): array
    {
        $slice = min($offset + $limit, self::MAX_SLICE);
        $rows = [];

        if (Schema::hasTable('failed_jobs')) {
            $query = DB::table('failed_jobs');
            if ($from !== null) {
                $query->where('failed_at', '>=', $from);
            }
            if ($to !== null) {
                $query->where('failed_at', '<=', $to);
            }
            if ($search !== '') {
                $query->where(fn ($i) => $i->where('queue', 'like', '%' . $search . '%')->orWhere('exception', 'like', '%' . $search . '%'));
            }

            foreach ($query->orderByDesc('id')->limit($slice)->get() as $row) {
                $rows[] = [
                    'id' => 'failed_jobs:' . $row->id,
                    'event' => sprintf('%s (%s)', $row->queue, $row->connection),
                    'error' => $this->firstLine((string) $row->exception),
                    'retryCount' => 0,
                    'maxRetries' => null,
                    'failedAt' => $this->iso($row->failed_at),
                    'source' => 'failed_jobs',
                ];
            }
        }

        if (Schema::hasTable('sync_log')) {
            $query = DB::table('sync_log')->whereIn('status', self::OUTBOX_FAILED);
            if ($from !== null) {
                $query->where('created_at', '>=', $from);
            }
            if ($to !== null) {
                $query->where('created_at', '<=', $to);
            }
            if ($search !== '') {
                $query->where(fn ($i) => $i->where('table_name', 'like', '%' . $search . '%')->orWhere('record_id', 'like', '%' . $search . '%'));
            }

            foreach ($query->orderByDesc('id')->limit($slice)->get() as $row) {
                $rows[] = [
                    'id' => 'sync_log:' . $row->id,
                    'event' => sprintf('%s #%s', $row->table_name, $row->record_id),
                    // Not a manufactured sentence. GraphDrain updates status and
                    // retry_count on failure and writes the message to the log
                    // file, so this row genuinely has no stored error text.
                    'error' => null,
                    'retryCount' => (int) ($row->retry_count ?? 0),
                    'maxRetries' => 5,
                    'failedAt' => $this->iso($row->created_at),
                    'source' => 'sync_log',
                ];
            }
        }

        if (Schema::hasTable('workflow_runs')) {
            $query = DB::table('workflow_runs')
                ->whereIn('status', ['failed', 'timed_out']);

            if ($from !== null) {
                $query->where('finished_at', '>=', $from);
            }
            if ($to !== null) {
                $query->where('finished_at', '<=', $to);
            }
            if ($search !== '') {
                $query->where(fn ($i) => $i
                    ->where('run_reference', 'like', '%' . $search . '%')
                    ->orWhere('workflow_key', 'like', '%' . $search . '%')
                    ->orWhere('error_message', 'like', '%' . $search . '%'));
            }

            foreach ($query->orderByDesc('id')->limit($slice)->get() as $row) {
                $rows[] = [
                    'id' => 'workflow_runs:' . $row->id,
                    'event' => sprintf('%s (%s)', $row->run_reference, $row->workflow_key),
                    'error' => $row->error_message !== null && $row->error_message !== '' ? (string) $row->error_message : null,
                    'retryCount' => (int) ($row->attempt ?? 0),
                    'maxRetries' => null,
                    'failedAt' => $this->iso($row->finished_at ?? $row->updated_at ?? $row->created_at),
                    'source' => 'workflow_runs',
                ];
            }
        }

        if (Schema::hasTable('workflow_steps') && Schema::hasTable('workflow_runs')) {
            $query = DB::table('workflow_steps as s')
                ->join('workflow_runs as r', 'r.id', '=', 's.run_id')
                ->where('r.sub_institute_id', $tenantId)
                ->where('s.status', 'failed');

            if ($from !== null) {
                $query->where('s.finished_at', '>=', $from);
            }
            if ($to !== null) {
                $query->where('s.finished_at', '<=', $to);
            }
            if ($search !== '') {
                $query->where(fn ($i) => $i->where('s.step_key', 'like', '%' . $search . '%')->orWhere('s.error_message', 'like', '%' . $search . '%'));
            }

            $found = $query->orderByDesc('s.id')
                ->limit($slice)
                ->get(['s.id', 's.step_key', 's.step_type', 's.error_message', 's.attempt', 's.max_retries', 's.finished_at', 's.created_at', 'r.run_reference']);

            foreach ($found as $row) {
                $rows[] = [
                    'id' => 'workflow_steps:' . $row->id,
                    'event' => sprintf('%s · %s (%s)', $row->run_reference, $row->step_key, $row->step_type),
                    'error' => $row->error_message !== null && $row->error_message !== '' ? (string) $row->error_message : null,
                    'retryCount' => (int) ($row->attempt ?? 0),
                    'maxRetries' => $row->max_retries !== null ? (int) $row->max_retries : null,
                    'failedAt' => $this->iso($row->finished_at ?? $row->created_at),
                    'source' => 'workflow_steps',
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['failedAt'], (string) $a['failedAt']));

        return array_slice($rows, $offset, $limit);
    }

    private function failureTotal(int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to): int
    {
        $total = 0;

        if (Schema::hasTable('failed_jobs')) {
            $q = DB::table('failed_jobs');
            if ($from !== null) {
                $q->where('failed_at', '>=', $from);
            }
            if ($to !== null) {
                $q->where('failed_at', '<=', $to);
            }
            $total += (int) $q->count();
        }

        if (Schema::hasTable('sync_log')) {
            $q = DB::table('sync_log')->whereIn('status', self::OUTBOX_FAILED);
            if ($from !== null) {
                $q->where('created_at', '>=', $from);
            }
            if ($to !== null) {
                $q->where('created_at', '<=', $to);
            }
            $total += (int) $q->count();
        }

        if (Schema::hasTable('workflow_runs')) {
            $q = DB::table('workflow_runs')->whereIn('status', ['failed', 'timed_out']);
            if ($from !== null) {
                $q->where('finished_at', '>=', $from);
            }
            if ($to !== null) {
                $q->where('finished_at', '<=', $to);
            }
            $total += (int) $q->count();
        }

        $total += $this->failedStepCount($tenantId, $from, $to);

        return $total;
    }

    private function failedStepCount(int $tenantId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): int
    {
        if (! Schema::hasTable('workflow_steps') || ! Schema::hasTable('workflow_runs')) {
            return 0;
        }

        $q = DB::table('workflow_steps as s')
            ->join('workflow_runs as r', 'r.id', '=', 's.run_id')
            ->where('r.sub_institute_id', $tenantId)
            ->where('s.status', 'failed');

        if ($from !== null) {
            $q->where('s.finished_at', '>=', $from);
        }
        if ($to !== null) {
            $q->where('s.finished_at', '<=', $to);
        }

        return (int) $q->count();
    }

    // =======================================================================
    // Deliveries — the four send-logs
    // =======================================================================

    /**
     * WhatsApp, SMS (parents and staff) and email, merged.
     *
     * The channels are not equal and the payload does not pretend they are.
     * WhatsApp rows carry `message_status` and `message_error`; the other three
     * tables have neither column, so their rows come back with `status => null`
     * and `error => null`. The screen renders that as "not tracked" — which is
     * the truth, and is the reason a school cannot today tell a delivered SMS
     * from one the gateway dropped.
     */
    public function deliveries(int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to, ?string $channel, string $search, int $page, int $pageSize): array
    {
        $offset = ($page - 1) * $pageSize;
        $slice = min($offset + $pageSize, self::MAX_SLICE);
        $rows = [];
        $total = 0;

        $wants = fn (string $key) => $channel === null || $channel === '' || $channel === $key;

        if ($wants('whatsapp') && Schema::hasTable('whatsapp_sent_messages')) {
            $q = DB::table('whatsapp_sent_messages')->where('sub_institute_id', $tenantId);
            // sent_date is a DATE column, not a datetime, so the range is
            // compared as dates. Ordering within a day falls back to the id.
            if ($from !== null) {
                $q->whereDate('sent_date', '>=', $from->toDateString());
            }
            if ($to !== null) {
                $q->whereDate('sent_date', '<=', $to->toDateString());
            }
            if ($search !== '') {
                $q->where(fn ($i) => $i->where('whatsapp_number', 'like', '%' . $search . '%')->orWhere('message_status', 'like', '%' . $search . '%')->orWhere('message_error', 'like', '%' . $search . '%'));
            }

            $total += (int) (clone $q)->count();

            foreach ($q->orderByDesc('id')->limit($slice)->get() as $row) {
                $rows[] = [
                    'id' => 'whatsapp:' . $row->id,
                    'channel' => 'whatsapp',
                    'recipient' => (string) ($row->whatsapp_number ?? ''),
                    'status' => $row->message_status !== null && $row->message_status !== '' ? (string) $row->message_status : null,
                    'error' => $row->message_error !== null && $row->message_error !== '' ? (string) $row->message_error : null,
                    'sentAt' => $this->iso($row->sent_date),
                    'source' => 'whatsapp_sent_messages',
                ];
            }
        }

        if ($wants('sms') && Schema::hasTable('sms_sent_parents')) {
            $q = DB::table('sms_sent_parents')->where('sub_institute_id', $tenantId);
            if ($from !== null) {
                $q->where('CREATED_ON', '>=', $from);
            }
            if ($to !== null) {
                $q->where('CREATED_ON', '<=', $to);
            }
            if ($search !== '') {
                $q->where(fn ($i) => $i->where('SMS_NO', 'like', '%' . $search . '%')->orWhere('MODULE_NAME', 'like', '%' . $search . '%'));
            }

            $total += (int) (clone $q)->count();

            foreach ($q->orderByDesc('ID')->limit($slice)->get() as $row) {
                $rows[] = [
                    'id' => 'sms_parents:' . $row->ID,
                    'channel' => 'sms',
                    'recipient' => (string) ($row->SMS_NO ?? ''),
                    // No status column exists on this table.
                    'status' => null,
                    'error' => null,
                    'sentAt' => $this->iso($row->CREATED_ON),
                    'source' => 'sms_sent_parents',
                ];
            }
        }

        if ($wants('sms') && Schema::hasTable('sms_sent_staff')) {
            $q = DB::table('sms_sent_staff')->where('sub_institute_id', $tenantId);
            if ($from !== null) {
                $q->where('created_on', '>=', $from);
            }
            if ($to !== null) {
                $q->where('created_on', '<=', $to);
            }
            if ($search !== '') {
                $q->where(fn ($i) => $i->where('sms_no', 'like', '%' . $search . '%')->orWhere('module_name', 'like', '%' . $search . '%'));
            }

            $total += (int) (clone $q)->count();

            foreach ($q->orderByDesc('id')->limit($slice)->get() as $row) {
                $rows[] = [
                    'id' => 'sms_staff:' . $row->id,
                    'channel' => 'sms',
                    'recipient' => (string) ($row->sms_no ?? ''),
                    'status' => null,
                    'error' => null,
                    'sentAt' => $this->iso($row->created_on),
                    'source' => 'sms_sent_staff',
                ];
            }
        }

        if ($wants('email') && Schema::hasTable('email_sent_parents')) {
            $q = DB::table('email_sent_parents')->where('sub_institute_id', $tenantId);
            if ($from !== null) {
                $q->where('CREATED_ON', '>=', $from);
            }
            if ($to !== null) {
                $q->where('CREATED_ON', '<=', $to);
            }
            if ($search !== '') {
                $q->where(fn ($i) => $i->where('EMAIL', 'like', '%' . $search . '%')->orWhere('SUBJECT', 'like', '%' . $search . '%'));
            }

            $total += (int) (clone $q)->count();

            foreach ($q->orderByDesc('ID')->limit($slice)->get() as $row) {
                $rows[] = [
                    'id' => 'email:' . $row->ID,
                    'channel' => 'email',
                    'recipient' => (string) ($row->EMAIL ?? ''),
                    'status' => null,
                    'error' => null,
                    'sentAt' => $this->iso($row->CREATED_ON),
                    'source' => 'email_sent_parents',
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['sentAt'], (string) $a['sentAt']));

        return [
            'rows' => array_slice($rows, $offset, $pageSize),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'scope' => 'tenant',
        ];
    }

    /** Volume across the three receipt-less channels, for the delivery-rate hint. */
    private function sentVolume(int $tenantId, CarbonImmutable $since): array
    {
        $other = 0;

        if (Schema::hasTable('sms_sent_parents')) {
            $other += (int) DB::table('sms_sent_parents')->where('sub_institute_id', $tenantId)->where('CREATED_ON', '>=', $since)->count();
        }
        if (Schema::hasTable('sms_sent_staff')) {
            $other += (int) DB::table('sms_sent_staff')->where('sub_institute_id', $tenantId)->where('created_on', '>=', $since)->count();
        }
        if (Schema::hasTable('email_sent_parents')) {
            $other += (int) DB::table('email_sent_parents')->where('sub_institute_id', $tenantId)->where('CREATED_ON', '>=', $since)->count();
        }

        return ['other' => $other];
    }

    // =======================================================================
    // Audit — ai_audit_logs
    // =======================================================================

    public function audit(int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to, ?string $eventType, string $search, int $page, int $pageSize): array
    {
        if (! Schema::hasTable('ai_audit_logs')) {
            return $this->emptyPage($page, $pageSize, 'tenant');
        }

        // Rows with a null institute are platform-level entries that belong to
        // every tenant's trail, which is how OutcomeController::auditLogs
        // already scopes this table. Matching it keeps two views of one table
        // from disagreeing about what an administrator is entitled to see.
        $query = DB::table('ai_audit_logs')
            ->where(fn ($inner) => $inner->where('sub_institute_id', $tenantId)->orWhereNull('sub_institute_id'));

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }
        if ($eventType !== null && $eventType !== '') {
            $query->where('event_type', $eventType);
        }
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('event_type', 'like', '%' . $search . '%')
                    ->orWhere('actor_label', 'like', '%' . $search . '%')
                    ->orWhere('subject_entity_key', 'like', '%' . $search . '%')
                    ->orWhere('message', 'like', '%' . $search . '%');
            });
        }

        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(fn ($row) => [
                'id' => 'ai_audit:' . $row->id,
                'eventType' => (string) $row->event_type,
                // actor_label is denormalised at write time precisely so it
                // still reads correctly after a user is renamed or deactivated.
                // Falling back to the id rather than inventing a name.
                'actor' => $row->actor_label !== null && $row->actor_label !== ''
                    ? (string) $row->actor_label
                    : ($row->actor_id !== null ? 'User ' . $row->actor_id : 'system'),
                'actorType' => (string) ($row->actor_type ?? 'system'),
                // The nearest real grouping this table holds. It is the subject
                // entity ("students", "fees"), not a declared module — there is
                // no module column here to read.
                'module' => $row->subject_entity_key !== null && $row->subject_entity_key !== '' ? (string) $row->subject_entity_key : null,
                'outcome' => (string) ($row->outcome ?? 'success'),
                'occurredAt' => $this->iso($row->created_at),
                'source' => 'ai_audit_logs',
            ])
            ->all();

        return [
            'rows' => $rows,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'scope' => 'tenant',
        ];
    }

    // =======================================================================
    // Integrations — derived from recorded activity
    // =======================================================================

    /**
     * What each outbound integration has actually done, from its own log.
     *
     * NOT A CONFIGURATION VIEW. None of the ten tables this class is allowed to
     * read holds a credential or an enabled flag, so this cannot say whether an
     * integration is configured — only whether it has recorded activity. The
     * two are different facts, and an "idle" badge on an integration nobody has
     * set up would send an administrator looking in the wrong place. Credential
     * state stays with Integration Management, which owns it.
     *
     * Payment gateways come from `fees_reconciliation` grouped by
     * `payer_opted_mode`, which is the settlement file's own name for the
     * channel the payer used — real rows, not a gateway catalogue.
     */
    public function integrations(int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to, string $search): array
    {
        $rows = [];

        $rows[] = $this->activityRow('whatsapp', 'WhatsApp Cloud API', 'Communication', 'whatsapp_sent_messages', 'sent_date', $tenantId, $from, $to);
        $rows[] = $this->activityRow('sms_parents', 'SMS gateway — parents', 'Communication', 'sms_sent_parents', 'CREATED_ON', $tenantId, $from, $to);
        $rows[] = $this->activityRow('sms_staff', 'SMS gateway — staff', 'Communication', 'sms_sent_staff', 'created_on', $tenantId, $from, $to);
        $rows[] = $this->activityRow('email', 'Email (SMTP)', 'Communication', 'email_sent_parents', 'CREATED_ON', $tenantId, $from, $to);

        if (Schema::hasTable('fees_reconciliation')) {
            $q = DB::table('fees_reconciliation')->where('sub_institute_id', $tenantId);
            if ($from !== null) {
                $q->where('tran_date', '>=', $from->toDateString());
            }
            if ($to !== null) {
                $q->where('tran_date', '<=', $to->toDateString());
            }

            $modes = $q->selectRaw('payer_opted_mode as mode, COUNT(*) as total, MAX(tran_date) as last_at')
                ->groupBy('payer_opted_mode')
                ->orderByDesc('total')
                ->limit(20)
                ->get();

            foreach ($modes as $mode) {
                $label = trim((string) ($mode->mode ?? ''));
                $rows[] = $this->describeActivity(
                    'gateway:' . ($label !== '' ? $label : 'unspecified'),
                    $label !== '' ? $label : 'Unspecified payment mode',
                    'Payment gateway',
                    'fees_reconciliation',
                    (int) $mode->total,
                    $mode->last_at !== null ? $this->iso($mode->last_at) : null,
                );
            }
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter(
                $rows,
                fn ($row) => str_contains(mb_strtolower((string) $row['name']), $needle)
                    || str_contains(mb_strtolower((string) $row['category']), $needle)
                    || str_contains(mb_strtolower((string) $row['source']), $needle),
            ));
        }

        return [
            'rows' => $rows,
            'page' => 1,
            'pageSize' => count($rows),
            'total' => count($rows),
            'scope' => 'tenant',
        ];
    }

    private function activityRow(string $id, string $name, string $category, string $table, string $timeColumn, int $tenantId, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        if (! Schema::hasTable($table)) {
            return $this->describeActivity($id, $name, $category, $table, 0, null, false);
        }

        $q = DB::table($table)->where('sub_institute_id', $tenantId);
        if ($from !== null) {
            $q->where($timeColumn, '>=', $from);
        }
        if ($to !== null) {
            $q->where($timeColumn, '<=', $to);
        }

        $total = (int) (clone $q)->count();
        $last = $q->max($timeColumn);

        return $this->describeActivity($id, $name, $category, $table, $total, $last !== null ? $this->iso($last) : null);
    }

    private function describeActivity(string $id, string $name, string $category, string $source, int $events, ?string $lastActivityAt, bool $tablePresent = true): array
    {
        $status = match (true) {
            ! $tablePresent => 'unavailable',
            $lastActivityAt === null => 'no_activity',
            CarbonImmutable::parse($lastActivityAt)->greaterThan(CarbonImmutable::now()->subDay()) => 'active',
            default => 'idle',
        };

        return [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'events' => $events,
            'lastActivityAt' => $lastActivityAt,
            'status' => $status,
            'source' => $source,
        ];
    }

    // =======================================================================
    // Helpers
    // =======================================================================

    /** @param mixed $value */
    private function iso($value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Both halves of the outbox spell their statuses differently; accept both. */
    private function statusSpellings(string $status): array
    {
        return match (strtolower($status)) {
            'pending' => self::OUTBOX_PENDING,
            'processing' => ['PROCESSING', 'processing'],
            'completed' => ['SUCCESS', 'success', 'done', 'DONE'],
            'failed', 'dead' => self::OUTBOX_FAILED,
            default => [$status],
        };
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'done' => 'completed',
            'failed' => 'failed',
            'processing' => 'processing',
            default => 'pending',
        };
    }

    /** Stack traces are long and the table shows one line; keep the first. */
    private function firstLine(string $text): ?string
    {
        $line = trim(strtok($text, "\n") ?: '');

        return $line !== '' ? mb_substr($line, 0, 500) : null;
    }

    private function emptyPage(int $page, int $pageSize, string $scope = 'estate'): array
    {
        return ['rows' => [], 'page' => $page, 'pageSize' => $pageSize, 'total' => 0, 'scope' => $scope];
    }

    private function kpi(string $key, string $label, ?string $value, string $hint, string $tone, string $source): array
    {
        return [
            'key' => $key,
            'label' => $label,
            // A measure with no value stays null and the screen renders a dash.
            // It must never fall through to 0 — "Failed events: 0" is the most
            // reassuring thing this page can say and the most dangerous thing
            // for it to say without having counted.
            'value' => $value,
            'available' => $value !== null,
            'hint' => $hint,
            'tone' => $tone,
            'source' => $source,
        ];
    }
}
