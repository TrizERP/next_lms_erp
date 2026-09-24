<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks, as the `task` table records them.
 *
 * ONE ROW IS ONE TASK ALLOCATED BY SOMEBODY TO SOMEBODY
 *
 * `task` carries the title and description, the date it is due, the person who allocated
 * it, the person it is allocated to, a status, a KRA/KPA, planned and actual hours, an
 * acceptance criterion and a reply.
 *
 * THE STATUS COLUMN HAS TWO SPELLINGS OF THE SAME STATE
 *
 * Across the estate: `COMPLETE` on 570 rows, `PENDING` on 329, and `COMPLETED` on 2. The
 * screens write `COMPLETE`; the two `COMPLETED` rows came from somewhere else.
 *
 * A count that matched one spelling would be wrong by two rows today and by more later,
 * and — worse — those two finished tasks would appear in an "overdue" list forever. So
 * every read here normalises, and `status_normalised` is what any judgement is made on
 * while `status` keeps the word the row actually holds. The variants are reported in
 * `status_spellings` so the office can see the data needs tidying rather than the tool
 * hiding it.
 *
 * OVERDUE IS COMPUTED, AND ONLY FROM WHAT IS RECORDED
 *
 * A task is overdue when its date is in the past and it is not complete. That is a real
 * derivation from two recorded columns. What is NOT recorded anywhere is why a task is
 * late, whether anybody is at fault, or whether the date was ever agreed — so nothing here
 * may say any of that, and a task with no date is not overdue, it is undated.
 *
 * THE PROJECT TABLES ARE A DIFFERENT, NEARLY EMPTY SYSTEM
 *
 * `task_management_projects`, `_workstreams`, `_milestones` and the rest hold single-digit
 * row counts across the whole estate and are a newer structure layered beside `task`.
 * `projects()` reads them and says plainly how little is there, rather than presenting one
 * project as a programme.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` where the caller
 * carries an academic year. Both user lookups are joined on institute.
 */
class TaskService
{
    /**
     * The spellings that mean the task is finished.
     *
     * Compared upper-cased, so a row written in any casing is matched. Extending this list
     * is how a new spelling is handled — never by matching one of them in a query.
     *
     * @var array<int, string>
     */
    private const COMPLETE = ['COMPLETE', 'COMPLETED', 'DONE', 'CLOSED'];

    /**
     * Tasks, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('task')) {
            return ['count' => 0, 'tasks' => [], 'note' => 'Tasks are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        $state = trim((string) ($filters['state'] ?? 'any'));

        if ($state === 'complete') {
            $query->whereIn(DB::raw('UPPER(TRIM(t.STATUS))'), self::COMPLETE);
        } elseif ($state === 'open') {
            $query->whereNotIn(DB::raw('UPPER(TRIM(t.STATUS))'), self::COMPLETE);
        } elseif ($state === 'overdue') {
            $this->onlyOverdue($query);
        }

        $total = (clone $query)->count();
        $complete = (clone $query)->whereIn(DB::raw('UPPER(TRIM(t.STATUS))'), self::COMPLETE)->count();
        $overdue = (clone $query)->where(fn ($inner) => $this->onlyOverdue($inner))->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('t.TASK_DATE')
            ->orderByDesc('t.ID')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'complete' => $complete,
            'open' => $total - $complete,
            'overdue' => $overdue,
            'figures_cover' => 'every task matching these filters, not only the rows listed',
            'status_spellings' => $this->statusSpellings($context, $filters),
            'tasks' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * Tasks past their date and not complete, oldest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overdue(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('task')) {
            return ['count' => 0, 'tasks' => [], 'note' => 'Tasks are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);
        $this->onlyOverdue($query);

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderBy('t.TASK_DATE')
            ->orderBy('t.ID')
            ->limit($limit)
            ->get();

        // How many have no assignee recorded, which is the one thing that makes an overdue
        // task nobody's to chase.
        $unassigned = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('t.TASK_ALLOCATED_TO')->orWhere('t.TASK_ALLOCATED_TO', 0);
            })
            ->count();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'with_no_assignee' => $unassigned,
            'figures_cover' => 'every overdue task matching these filters, not only the rows listed',
            'tasks' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => $this->rule().' Overdue here means the task date has passed and the status is not one '
                .'of the completed spellings. A task with NO date is not overdue — it is undated, and is '
                .'excluded. Nothing records why a task is late or whether its date was ever agreed, so '
                .'never attribute a delay to a person.',
        ];
    }

    /**
     * The project structure layered beside the task list.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function projects(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('task_management_projects')) {
            return ['count' => 0, 'projects' => [], 'note' => 'Task projects are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $institute = $context->selectedInstituteId;

        $query = DB::table('task_management_projects as p')
            ->leftJoin('tbluser as m', function ($join) use ($institute) {
                $join->on('m.id', '=', 'p.manager_id')->where('m.sub_institute_id', '=', $institute);
            })
            ->where('p.sub_institute_id', $institute)
            ->whereNull('p.deleted_at');

        if ($context->academicYear !== null) {
            $query->where('p.syear', $context->academicYear);
        }

        if (! empty($filters['project_id'])) {
            $query->where('p.id', (int) $filters['project_id']);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw("p.id, p.code, p.name, p.category, p.description, p.status, p.priority,
                p.start_date, p.due_date, p.team_size, p.client_name, p.manager_id,
                CONCAT_WS(' ', m.first_name, m.middle_name, m.last_name) AS manager_name")
            ->orderBy('p.name')
            ->limit($limit)
            ->get();

        $projectIds = $rows->pluck('id')->map(static fn ($id) => (int) $id)->all();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'projects' => $rows->map(static fn ($row) => [
                'project_id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'category' => $row->category,
                'description' => $row->description,
                'status' => $row->status,
                'priority' => $row->priority,
                'start_date' => $row->start_date,
                'due_date' => $row->due_date,
                'team_size' => $row->team_size === null ? null : (int) $row->team_size,
                'client_name' => $row->client_name,
                // Null when the manager is not of this institute — a record to correct,
                // not a name to borrow from elsewhere.
                'manager' => trim((string) ($row->manager_name ?? '')) ?: null,
            ])->all(),
            'workstreams' => $this->workstreams($context, $projectIds),
            'rule' => 'Projects and workstreams are a newer structure layered beside the task list, and '
                .'across this whole estate they hold single-digit row counts. Report what is there and do '
                .'not describe it as a programme, a portfolio or a plan. A project is not linked to most '
                .'tasks: `task_management_project_tasks` maps only a handful, so never present a project '
                .'as accounting for the school\'s work.',
        ];
    }

    /**
     * The workstreams under the given projects.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, array<string, mixed>>
     */
    private function workstreams(McpRequestContext $context, array $projectIds): array
    {
        if ($projectIds === [] || ! Schema::hasTable('task_management_workstreams')) {
            return [];
        }

        return DB::table('task_management_workstreams')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->whereIn('project_id', $projectIds)
            ->orderBy('sort_order')
            ->limit(100)
            ->get(['id', 'project_id', 'name', 'status', 'start_date', 'due_date'])
            ->map(static fn ($row) => [
                'workstream_id' => (int) $row->id,
                'project_id' => (int) $row->project_id,
                'name' => $row->name,
                'status' => $row->status,
                'start_date' => $row->start_date,
                'due_date' => $row->due_date,
            ])
            ->all();
    }

    /** The rule every task answer carries. */
    private function rule(): string
    {
        return 'One row is one task allocated by somebody to somebody. THE STATUS COLUMN HOLDS TWO '
            .'SPELLINGS OF THE SAME STATE — `COMPLETE` and `COMPLETED` both mean finished — so judge only '
            .'on `status_normalised`, never on the raw word, and never report a count that matches one '
            .'spelling. `status` is the word the row actually holds and is shown so the office can see '
            .'what needs tidying. Nothing records why a task is late, whether its date was agreed, or who '
            .'is at fault: report the dates and the states and attribute nothing to anybody.';
    }

    /**
     * The distinct status words actually present, so the two spellings are visible.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function statusSpellings(McpRequestContext $context, array $filters): array
    {
        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        return $query
            ->selectRaw('t.STATUS AS status, COUNT(*) AS tasks')
            ->groupBy('t.STATUS')
            ->orderByDesc('tasks')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'means_complete' => in_array(strtoupper(trim((string) $row->status)), self::COMPLETE, true),
                'tasks' => (int) $row->tasks,
            ])
            ->all();
    }

    /** Past its date and not complete. A task with no date is undated, not overdue. */
    private function onlyOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('t.TASK_DATE')
            ->whereRaw("TRIM(t.TASK_DATE) <> ''")
            ->whereDate('t.TASK_DATE', '<', now()->toDateString())
            ->whereNotIn(DB::raw('UPPER(TRIM(t.STATUS))'), self::COMPLETE);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ([
            'assigned_to' => 't.TASK_ALLOCATED_TO',
            'allocated_by' => 't.TASK_ALLOCATED',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $type = trim((string) ($filters['task_type'] ?? ''));

        if ($type !== '') {
            $query->where('t.task_type', 'like', '%'.$type.'%');
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('t.TASK_DATE', $operator, $date);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('t.TASK_TITLE', 'like', $needle)->orWhere('t.TASK_DESCRIPTION', 'like', $needle);
            });
        }
    }

    /** The task join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('task as t')
            ->leftJoin('tbluser as assignee', function ($join) use ($institute) {
                $join->on('assignee.id', '=', 't.TASK_ALLOCATED_TO')->where('assignee.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as allocator', function ($join) use ($institute) {
                $join->on('allocator.id', '=', 't.TASK_ALLOCATED')->where('allocator.sub_institute_id', '=', $institute);
            })
            ->where('t.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('t.SYEAR', $context->academicYear);
        }

        return $query;
    }

    private function columns(): string
    {
        return "t.ID AS task_id, t.TASK_TITLE, t.TASK_DESCRIPTION, t.TASK_DATE, t.STATUS,
                t.task_type, t.KRA, t.KPA, t.TASK_ALLOCATED, t.TASK_ALLOCATED_TO,
                t.planned_start_date, t.estimated_hours, t.actual_hours, t.remaining_hours,
                t.acceptance_criteria, t.reply, t.approved_by, t.approved_on, t.CREATED_ON,
                t.TASK_ATTACHMENT,
                CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name) AS assignee_name,
                CONCAT_WS(' ', allocator.first_name, allocator.middle_name, allocator.last_name) AS allocator_name,
                DATEDIFF(CURDATE(), t.TASK_DATE) AS days_past_date";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $status = trim((string) ($row->STATUS ?? ''));
        $complete = in_array(strtoupper($status), self::COMPLETE, true);
        $date = trim((string) ($row->TASK_DATE ?? ''));
        $daysPast = $row->days_past_date === null ? null : (int) $row->days_past_date;

        return [
            'task_id' => (int) $row->task_id,
            'title' => $row->TASK_TITLE,
            'description' => $row->TASK_DESCRIPTION,
            'task_date' => $date !== '' ? $date : null,
            // The word the row actually holds …
            'status' => $status !== '' ? $status : null,
            // … and the only one anything should be judged on.
            'status_normalised' => $status === '' ? 'not recorded' : ($complete ? 'complete' : 'open'),
            'overdue' => $date !== '' && ! $complete && $daysPast !== null && $daysPast > 0,
            'days_past_date' => $date === '' ? null : $daysPast,
            'task_type' => $row->task_type ?: null,
            'kra' => $row->KRA ?: null,
            'kpa' => $row->KPA ?: null,
            'assigned_to_user_id' => $row->TASK_ALLOCATED_TO === null ? null : (int) $row->TASK_ALLOCATED_TO,
            // Null when the user is not of this institute — a record to correct, not a
            // name to borrow from elsewhere.
            'assigned_to' => trim((string) ($row->assignee_name ?? '')) ?: null,
            'allocated_by' => trim((string) ($row->allocator_name ?? '')) ?: null,
            'planned_start_date' => $row->planned_start_date ?: null,
            'estimated_hours' => $row->estimated_hours === null || $row->estimated_hours === '' ? null : (float) $row->estimated_hours,
            'actual_hours' => $row->actual_hours === null || $row->actual_hours === '' ? null : (float) $row->actual_hours,
            'acceptance_criteria' => $row->acceptance_criteria ?: null,
            'reply' => $row->reply ?: null,
            'approved_on' => $row->approved_on ?: null,
            // Whether a file is attached, never the file.
            'attachment_present' => trim((string) ($row->TASK_ATTACHMENT ?? '')) !== '',
            'recorded_on' => $row->CREATED_ON,
        ];
    }
}
