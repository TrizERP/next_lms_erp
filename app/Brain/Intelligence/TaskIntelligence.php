<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Task Management Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `task` is ONE PIECE OF WORK: a title, who it is allocated to, who
 * allocated it, a due date, a status and — since the planning-columns port —
 * a priority, an effort estimate and a delay reason when it is on hold. The
 * table predates Task Management (`2023_03_05_115658_create_task_table.php`)
 * and carries the legacy uppercase column set; three later migrations bolt on
 * the planning/priority/approval/soft-delete columns this class and the rest
 * of the Task Management API layer actually read.
 *
 * ── THIS MODULE'S ANALYTICS ARE THE THINNEST OF THE SIX ─────────────────────
 *
 * There is no per-status timestamp on this table — no `started_at`, no
 * `completed_at`. `WorkspaceController::summary()` already leans on
 * `updated_at` as a stand-in for "when this task was last moved", which is how
 * its "completed this month" card is built. This class follows the same
 * precedent for `avgCycleTimeDays`: it is a real column, read the same way it
 * is read elsewhere in this codebase, but it is a PROXY — any later edit to an
 * already-completed row (a remark, a re-approval) also moves `updated_at`, so
 * the figure is named and documented as an estimate rather than presented as
 * an exact cycle time. Nothing here invents a `completed_at` that does not
 * exist.
 *
 * ── STATUS IS NORMALISED, NOT TRUSTED VERBATIM ──────────────────────────────
 *
 * `task.STATUS` carries historical spellings — `IN PROGRESS`, `IN-PROGRES` —
 * alongside the four system categories `ResolvesTaskManagementContext` writes
 * (`PENDING`, `IN-PROGRESS`, `ON HOLD`, `COMPLETED`). Every query in this class
 * normalises the column with the same CASE expression that trait's
 * `normaliseTaskStatus()` applies in PHP, so a task titled "In Progress" by an
 * older import is counted with the rest of its cohort rather than silently
 * excluded from every status-based figure.
 *
 * ── OVERDUE, SCOPING AND SOFT DELETES ────────────────────────────────────────
 *
 * "Overdue" here is exactly `ReportController::productivity()`'s definition —
 * `TASK_DATE < today AND status normalises to something other than COMPLETED`
 * — so a finding raised from this class agrees with the report screen a
 * manager already reads. Every query starts from `sub_institute_id` +
 * `SYEAR` (both present on `task` as plain int columns; `SYEAR` is NOT a
 * varchar academic-year label here) and excludes `deleted_at IS NOT NULL`,
 * matching `WorkspaceController`/`ResolvesTaskManagementContext` throughout.
 */
final class TaskIntelligence
{
    private const TASK_TABLE = 'task';

    private const USER_TABLE = 'tbluser';

    private const PROJECT_TASK_TABLE = 'task_management_project_tasks';

    /** The four system status categories `task.STATUS` is normalised onto. */
    private const STATUS_CATEGORIES = ['PENDING', 'IN-PROGRESS', 'ON HOLD', 'COMPLETED'];

    /** `approve_status` spellings that count as "approved" — mirrors ResolvesTaskManagementContext::APPROVED_VALUES. */
    private const APPROVED_VALUES = ['approved', '1', 'yes'];

    /** Below this many tasks, a per-assignee or per-status comparison is about the handful of rows in it. */
    public const MIN_COHORT = 5;

    /** Share of OPEN tasks overdue before it is a backlog rather than the ordinary tail of a working list. */
    public const OVERDUE_ALERT_SHARE = 20.0;

    /** Share of the tenant's assigned task volume one person can carry before it is a concentration. */
    public const WORKLOAD_CONCENTRATION_SHARE = 40.0;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /* -------------------------------------------------------- L0: coverage */

    /** @return array<string,mixed> */
    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    /** @return array<string,mixed> */
    private function computeCoverage(): array
    {
        $empty = ['sources' => [], 'counts' => []];

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
            ];
        }

        if (! SchemaCache::hasTable(self::TASK_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::TASK_TABLE."' does not exist in this deployment.",
            ];
        }

        $statusExpr = $this->statusExpr();

        $shape = $this->scoped()
            ->selectRaw(
                "COUNT(*) as rows_total,
                 SUM(CASE WHEN TASK_ALLOCATED_TO IS NOT NULL AND TASK_ALLOCATED_TO > 0 THEN 1 ELSE 0 END) as with_assignee,
                 SUM(CASE WHEN TASK_DATE IS NOT NULL THEN 1 ELSE 0 END) as with_due_date,
                 SUM(CASE WHEN task_type IS NOT NULL AND task_type <> '' THEN 1 ELSE 0 END) as with_priority,
                 COUNT(DISTINCT COALESCE(TASK_ALLOCATED_TO, 0)) as assignees,
                 SUM(CASE WHEN {$statusExpr} = 'COMPLETED' THEN 1 ELSE 0 END) as completed"
            )
            ->first();

        $rows = (int) ($shape->rows_total ?? 0);

        if ($rows === 0) {
            return $empty + [
                'available' => false,
                'reason' => "No task records exist for academic year {$this->syear}.",
            ];
        }

        // Whether this tenant links tasks to Task Management's own Projects at
        // all — some tenants use `task` purely as a flat to-do list and never
        // touch the project pivot.
        $linkedToProject = SchemaCache::hasTable(self::PROJECT_TASK_TABLE)
            ? (int) DB::table(self::PROJECT_TASK_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->distinct()
                ->count('task_id')
            : 0;

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'tasks' => true,
                'assignees' => (int) $shape->with_assignee > 0,
                'dueDates' => (int) $shape->with_due_date > 0,
                'priorities' => (int) $shape->with_priority > 0,
                'projects' => $linkedToProject > 0,
            ],
            'counts' => [
                'rows' => $rows,
                'withAssignee' => (int) $shape->with_assignee,
                'withDueDate' => (int) $shape->with_due_date,
                'withPriority' => (int) $shape->with_priority,
                'assignees' => (int) $shape->assignees,
                'completed' => (int) $shape->completed,
                'linkedToProject' => $linkedToProject,
            ],
        ];
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed>|null */
    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed>|null */
    private function computePosition(): ?array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return null;
        }

        $today = now()->toDateString();
        $statusExpr = $this->statusExpr();

        // Overdue matches ReportController::productivity()'s own definition
        // exactly — TASK_DATE compared against a bare date string, which is a
        // "before today's midnight" test on the DATETIME column, same as that
        // report already runs.
        $row = $this->scoped()
            ->selectRaw(
                "COUNT(*) as total,
                 SUM(CASE WHEN {$statusExpr} = 'PENDING' THEN 1 ELSE 0 END) as pending,
                 SUM(CASE WHEN {$statusExpr} = 'IN-PROGRESS' THEN 1 ELSE 0 END) as in_progress,
                 SUM(CASE WHEN {$statusExpr} = 'ON HOLD' THEN 1 ELSE 0 END) as on_hold,
                 SUM(CASE WHEN {$statusExpr} = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
                 SUM(CASE WHEN {$statusExpr} <> 'COMPLETED' AND TASK_DATE IS NOT NULL AND TASK_DATE < ? THEN 1 ELSE 0 END) as overdue,
                 SUM(CASE WHEN {$statusExpr} <> 'COMPLETED' AND TASK_DATE IS NOT NULL AND TASK_DATE < ? THEN DATEDIFF(?, TASK_DATE) ELSE 0 END) as overdue_days_total,
                 AVG(CASE WHEN {$statusExpr} <> 'COMPLETED' THEN DATEDIFF(?, CREATED_ON) END) as avg_open_age_days,
                 AVG(CASE WHEN {$statusExpr} = 'COMPLETED' AND updated_at IS NOT NULL AND updated_at >= CREATED_ON THEN DATEDIFF(updated_at, CREATED_ON) END) as avg_cycle_days",
                [$today, $today, $today, $today]
            )
            ->first();

        $total = (int) ($row->total ?? 0);
        if ($total === 0) {
            return null;
        }

        $completed = (int) $row->completed;
        $open = $total - $completed;
        $overdue = (int) $row->overdue;

        return [
            'total' => $total,
            'assignees' => (int) $coverage['counts']['assignees'],
            'pending' => (int) $row->pending,
            'inProgress' => (int) $row->in_progress,
            'onHold' => (int) $row->on_hold,
            'completed' => $completed,
            'completionRate' => round($completed / $total * 100, 1),
            'openTasks' => $open,
            'overdueTasks' => $overdue,
            // NULL, NOT ZERO: with no open tasks the share of them overdue is
            // undefined, not clean.
            'overdueShareOfOpen' => $open > 0 ? round($overdue / $open * 100, 1) : null,
            'avgOverdueDays' => $overdue > 0 ? round((float) $row->overdue_days_total / $overdue, 1) : null,
            'avgOpenTaskAgeDays' => $row->avg_open_age_days !== null ? round((float) $row->avg_open_age_days, 1) : null,
            // See the class note: a real column read the way the rest of this
            // codebase already reads it, but a proxy for a completion
            // timestamp this table does not carry.
            'avgCycleTimeDays' => $row->avg_cycle_days !== null ? round((float) $row->avg_cycle_days, 1) : null,
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Workload by assignee — extends `WorkspaceController::workload()`'s exact
     * grouping (`COALESCE(TASK_ALLOCATED_TO, 0)`, same tenant+SYEAR scope,
     * `whereNull(deleted_at)`) with completion and overdue counts per person.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byAssignee(): array
    {
        return $this->memo['byAssignee'] ??= $this->computeByAssignee();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByAssignee(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $today = now()->toDateString();
        $statusExpr = $this->statusExpr('t');

        $rows = DB::table(self::TASK_TABLE.' as t')
            ->leftJoin(self::USER_TABLE.' as u', 'u.id', '=', 't.TASK_ALLOCATED_TO')
            ->where('t.sub_institute_id', $this->tenantId)
            ->where('t.SYEAR', $this->syear)
            ->whereNull('t.deleted_at')
            ->groupBy('user_id', 'u.first_name', 'u.middle_name', 'u.last_name')
            ->selectRaw(
                "COALESCE(t.TASK_ALLOCATED_TO, 0) as user_id,
                 TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) as name,
                 COUNT(*) as total,
                 SUM(CASE WHEN {$statusExpr} = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
                 SUM(CASE WHEN {$statusExpr} <> 'COMPLETED' AND t.TASK_DATE IS NOT NULL AND t.TASK_DATE < ? THEN 1 ELSE 0 END) as overdue",
                [$today]
            )
            ->orderByDesc('total')
            ->get();

        $totalTasks = (int) $rows->sum('total');

        $out = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $total = (int) $row->total;
            $completed = (int) $row->completed;

            $out[] = [
                'key' => (string) $userId,
                'label' => $userId > 0 ? (string) ($row->name !== '' ? $row->name : "User #{$userId}") : 'Unassigned',
                'unassigned' => $userId === 0,
                'total' => $total,
                'completed' => $completed,
                'completionRate' => $total > 0 ? round($completed / $total * 100, 1) : null,
                'overdue' => (int) $row->overdue,
                'shareOfTenantVolume' => $totalTasks > 0 ? round($total / $totalTasks * 100, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * Status distribution, read straight off {@see position()} so the two
     * never disagree.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byStatus(): array
    {
        return $this->memo['byStatus'] ??= $this->computeByStatus();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByStatus(): array
    {
        $position = $this->position();
        if ($position === null) {
            return [];
        }

        $total = $position['total'];
        $counts = [
            'PENDING' => $position['pending'],
            'IN-PROGRESS' => $position['inProgress'],
            'ON HOLD' => $position['onHold'],
            'COMPLETED' => $position['completed'],
        ];

        $out = [];
        foreach ($counts as $status => $count) {
            $out[] = [
                'key' => $status,
                'label' => $status,
                'tasks' => $count,
                'share' => $total > 0 ? round($count / $total * 100, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * Priority distribution, grouped on whatever values `task_type` actually
     * holds for this tenant — the three system priorities
     * (`ResolvesTaskManagementContext::SYSTEM_PRIORITIES`) plus any custom name
     * the tenant has added in `task_management_priorities`. Nothing here
     * assumes the three system values are the only ones in use.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byPriority(): array
    {
        return $this->memo['byPriority'] ??= $this->computeByPriority();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByPriority(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $rows = $this->scoped()
            ->selectRaw("COALESCE(NULLIF(task_type, ''), 'Unset') as priority, COUNT(*) as total")
            ->groupBy('priority')
            ->orderByDesc('total')
            ->get();

        $total = (int) $rows->sum('total');

        return $rows->map(fn ($row) => [
            'key' => (string) $row->priority,
            'label' => (string) $row->priority,
            'tasks' => (int) $row->total,
            'share' => $total > 0 ? round((int) $row->total / $total * 100, 1) : null,
        ])->all();
    }

    /**
     * Tasks currently ON HOLD, by `delay_category` — the same grouping
     * `ReportController::delays()` already reports, kept here as the evidence
     * behind the "stalled work" signal rule.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byDelayCategory(): array
    {
        return $this->memo['byDelayCategory'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $statusExpr = $this->statusExpr();

            $rows = $this->scoped()
                ->whereRaw("{$statusExpr} = 'ON HOLD'")
                ->selectRaw("COALESCE(NULLIF(delay_category, ''), 'Uncategorised') as category, COUNT(*) as total")
                ->groupBy('category')
                ->orderByDesc('total')
                ->get();

            return $rows->map(fn ($row) => [
                'key' => (string) $row->category,
                'label' => (string) $row->category,
                'tasks' => (int) $row->total,
            ])->all();
        })();
    }

    /* ------------------------------------------------------- the DQ ledger */

    /** @return array<string,mixed> */
    public function dataQuality(): array
    {
        return $this->memo['dataQuality'] ??= $this->computeDataQuality();
    }

    /** @return array<string,mixed> */
    private function computeDataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $counts = $coverage['counts'];
        $rows = (int) $counts['rows'];
        $statusExpr = $this->statusExpr();

        $noAssignee = $rows - (int) $counts['withAssignee'];
        $noDueDate = $rows - (int) $counts['withDueDate'];
        $noPriority = $rows - (int) $counts['withPriority'];
        $notLinkedToProject = $rows - (int) $counts['linkedToProject'];

        // Completed with no usable timestamp: the cycle-time figure above is
        // silently short of these rows, and this is the count of exactly how
        // many.
        $completedNoTimestamp = (int) $this->scoped()
            ->whereRaw("{$statusExpr} = 'COMPLETED'")
            ->where(function ($q) {
                $q->whereNull('updated_at')->orWhereColumn('updated_at', '<', 'CREATED_ON');
            })
            ->count();

        // Completed but never reviewed — the same "pending_review" idea
        // WorkspaceController::summary() already surfaces as a KPI card, read
        // directly from approve_status here.
        $awaitingReview = (int) $this->scoped()
            ->whereRaw("{$statusExpr} = 'COMPLETED'")
            ->where(function ($q) {
                $q->whereNull('approve_status')
                    ->orWhere('approve_status', '')
                    ->orWhereRaw('LOWER(TRIM(approve_status)) NOT IN (?, ?, ?)', self::APPROVED_VALUES);
            })
            ->count();

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'unassigned_tasks',
                    'label' => 'Tasks with no assignee',
                    'value' => $noAssignee,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($noAssignee / $rows * 100, 2) : null,
                    'shareLabel' => 'of tasks',
                    'state' => $noAssignee > 0 ? 'attention' : 'ok',
                    'note' => $noAssignee > 0
                        ? 'These tasks carry no TASK_ALLOCATED_TO, so they appear in the tenant totals and in no '
                            .'person\'s workload below.'
                        : 'Every task this year has an assignee.',
                ],
                [
                    'key' => 'no_due_date',
                    'label' => 'Tasks with no due date',
                    'value' => $noDueDate,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($noDueDate / $rows * 100, 2) : null,
                    'shareLabel' => 'of tasks',
                    'state' => $noDueDate > 0 ? 'attention' : 'ok',
                    'note' => $noDueDate > 0
                        ? 'A task with no TASK_DATE can never be counted as overdue, however old it is — it is missing '
                            .'from the overdue figures above, not clean against them.'
                        : 'Every task this year has a due date.',
                ],
                [
                    'key' => 'no_priority',
                    'label' => 'Tasks with no priority set',
                    'value' => $noPriority,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($noPriority / $rows * 100, 2) : null,
                    'shareLabel' => 'of tasks',
                    'state' => $noPriority > 0 ? 'attention' : 'ok',
                    'note' => $noPriority > 0
                        ? 'These tasks have no task_type recorded, so they cannot be triaged by priority.'
                        : 'Every task this year has a priority.',
                ],
                [
                    'key' => 'not_linked_to_project',
                    'label' => 'Tasks not linked to a project',
                    'value' => $notLinkedToProject,
                    'format' => 'count',
                    'sharePercent' => $rows > 0 ? round($notLinkedToProject / $rows * 100, 2) : null,
                    'shareLabel' => 'of tasks',
                    'state' => 'ok',
                    'note' => 'Task Management does not require a task to sit inside a project. This is a count, not '
                        .'a defect — many tenants use `task` as a flat list.',
                ],
                [
                    'key' => 'completed_without_timestamp',
                    'label' => 'Completed tasks with no usable completion timestamp',
                    'value' => $completedNoTimestamp,
                    'format' => 'count',
                    'sharePercent' => (int) $counts['completed'] > 0
                        ? round($completedNoTimestamp / (int) $counts['completed'] * 100, 2)
                        : null,
                    'shareLabel' => 'of completed tasks',
                    'state' => $completedNoTimestamp > 0 ? 'attention' : 'ok',
                    'note' => $completedNoTimestamp > 0
                        ? 'This table has no completed_at column; the cycle-time figure above uses updated_at as a '
                            .'stand-in and these rows have no updated_at on or after CREATED_ON, so they are excluded '
                            .'from it rather than allowed to distort it.'
                        : 'Every completed task has an updated_at on or after it was created.',
                ],
                [
                    'key' => 'completed_awaiting_review',
                    'label' => 'Completed tasks with no approval decision',
                    'value' => $awaitingReview,
                    'format' => 'count',
                    'sharePercent' => (int) $counts['completed'] > 0
                        ? round($awaitingReview / (int) $counts['completed'] * 100, 2)
                        : null,
                    'shareLabel' => 'of completed tasks',
                    'state' => $awaitingReview > 0 ? 'attention' : 'ok',
                    'note' => $awaitingReview > 0
                        ? 'These tasks report as COMPLETED but carry no approve_status, so they sit in the same '
                            .'pending-review queue WorkspaceController surfaces on the dashboard.'
                        : 'Every completed task has an approval decision recorded.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * The CASE expression `task.STATUS` is read through everywhere in this
     * class — the same normalisation
     * `ResolvesTaskManagementContext::normaliseTaskStatus()` applies in PHP,
     * so a historically-spelled status is folded into its system category
     * rather than silently excluded from every status-based figure.
     */
    private function statusExpr(string $alias = ''): string
    {
        $column = $alias === '' ? 'STATUS' : "{$alias}.STATUS";

        return "CASE
            WHEN UPPER(TRIM(COALESCE({$column}, ''))) IN ('IN PROGRESS', 'IN-PROGRES') THEN 'IN-PROGRESS'
            WHEN TRIM(COALESCE({$column}, '')) = '' THEN 'PENDING'
            ELSE UPPER(TRIM({$column}))
        END";
    }

    /** Every query in this class starts here, so no figure can escape the tenant-year filter. */
    private function scoped(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::TASK_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('SYEAR', $this->syear)
            ->whereNull('deleted_at');
    }
}
