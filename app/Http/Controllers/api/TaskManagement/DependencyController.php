<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\DependencyController`.
 *
 * Task dependencies (predecessor/successor pairs) and project milestones.
 */
class DependencyController extends Controller
{
    use ResolvesTaskManagementContext;

    private const TYPES = ['FS', 'SS', 'FF', 'SF'];
    private const MILESTONE_STATUSES = ['UPCOMING', 'AT RISK', 'COMPLETED'];

    public function index(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $request->validate([
            'project_id' => 'nullable|integer',
            'assignee_id' => 'nullable|integer',
            'status' => 'nullable|in:PENDING,IN-PROGRESS,ON HOLD,COMPLETED',
        ]);

        $query = $this->dependencyQuery($context);
        // Filtered on the stored column, not the join - see dependencyQuery()'s
        // select() for why the two can no longer collide. Rows written before
        // the column existed fall back to the derived value so nothing that
        // used to match this filter disappears from it.
        if ($request->filled('project_id')) {
            $projectId = $request->integer('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->where('d.project_id', $projectId)
                    ->orWhere(function ($legacy) use ($projectId) {
                        $legacy->whereNull('d.project_id')->where('project.id', $projectId);
                    });
            });
        }
        if ($request->filled('assignee_id')) {
            $query->where('successor.TASK_ALLOCATED_TO', $request->integer('assignee_id'));
        }
        if ($request->filled('status')) {
            $query->whereRaw('UPPER(successor.STATUS) = ?', [$request->input('status')]);
        }

        $dependencies = $query->orderBy('successor.TASK_DATE')->get()->map(fn ($row) => $this->resource($row));

        $taskIds = $dependencies->flatMap(fn ($item) => [$item['predecessor']['id'], $item['successor']['id']])->unique()->values();
        $tasks = $this->taskNodes($context, $taskIds->all());

        $milestones = DB::table('task_management_milestones as m')
            ->join('task_management_projects as p', 'p.id', '=', 'm.project_id')
            ->leftJoin('task_management_workstreams as w', 'w.id', '=', 'm.workstream_id')
            ->where('m.sub_institute_id', $context['sub_institute_id'])->where('m.syear', $context['syear'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('m.project_id', $request->integer('project_id')))
            ->orderBy('m.target_date')->select('m.*', 'p.name as project_name', 'w.name as workstream_name')->get();
        $milestones = $this->withMilestoneCounts($context, $milestones);

        $blocking = $dependencies->filter(fn ($item) => $item['blocking'])->count();
        $atRisk = $tasks->filter(fn ($task) => $task['at_risk'])->count();

        return $this->taskManagementResponse([
            'dependencies' => $dependencies, 'tasks' => $tasks, 'milestones' => $milestones,
            'summary' => [
                'total' => $dependencies->count(), 'blocking' => $blocking, 'at_risk' => $atRisk,
                'on_track' => max(0, $dependencies->count() - $blocking - $atRisk),
                'milestones' => $milestones->count(), 'critical_path' => $this->criticalPathCount($dependencies),
            ],
            'options' => [
                'types' => self::TYPES,
                'projects' => DB::table('task_management_projects')->where('sub_institute_id', $context['sub_institute_id'])
                    ->where('syear', $context['syear'])->whereNull('archived_at')->orderBy('name')->select('id', 'name')->get(),
                'tasks' => $this->taskOptions($context),
                'users' => DB::table('tbluser')->where('sub_institute_id', $context['sub_institute_id'])
                    ->where('status', 1)->orderBy('first_name')
                    ->select('id', DB::raw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) as name"))->get(),
            ],
        ], 'Dependencies retrieved successfully.');
    }

    public function store(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        $predecessor = $request->integer('predecessor_task_id');
        $successor = $request->integer('successor_task_id');

        if ($predecessor === $successor) {
            return $this->taskManagementError('A task cannot depend on itself.', 422);
        }
        if (!$this->validTasks($context, [$predecessor, $successor])) {
            return $this->taskManagementError('One or more selected tasks are invalid.', 422);
        }
        if (!$this->shareProject($context, $predecessor, $successor)) {
            return $this->taskManagementError('Dependencies require two tasks from the same project.', 422);
        }
        if ($this->createsCycle($context, $predecessor, $successor)) {
            return $this->taskManagementError('This dependency would create a cycle.', 422);
        }
        // Duplicates used to fall through to the tm_dependency_pair_unique
        // constraint and throw an uncaught QueryException - a 500 for what is
        // simply "you already linked these two".
        if ($this->duplicate($context, $predecessor, $successor)) {
            return $this->taskManagementError('These two tasks are already linked in that direction.', 422);
        }
        if (!$this->projectMatchesTasks($context, $request, $predecessor, $successor)) {
            return $this->taskManagementError('The selected project does not contain both of these tasks.', 422);
        }

        $id = DB::table('task_management_dependencies')->insertGetId($this->payload($request, $context) + [
            'created_by' => $context['user_id'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->taskManagementResponse(['id' => (string) $id], 'Dependency created successfully.', 201);
    }

    public function update(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->owned($context, $id)) {
            return $this->taskManagementError('Dependency not found.', 404);
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        $predecessor = $request->integer('predecessor_task_id');
        $successor = $request->integer('successor_task_id');

        if ($predecessor === $successor || !$this->validTasks($context, [$predecessor, $successor])) {
            return $this->taskManagementError('Invalid dependency tasks.', 422);
        }
        if (!$this->shareProject($context, $predecessor, $successor)) {
            return $this->taskManagementError('Dependencies require two tasks from the same project.', 422);
        }
        if ($this->createsCycle($context, $predecessor, $successor, $id)) {
            return $this->taskManagementError('This dependency would create a cycle.', 422);
        }
        if ($this->duplicate($context, $predecessor, $successor, $id)) {
            return $this->taskManagementError('These two tasks are already linked in that direction.', 422);
        }
        if (!$this->projectMatchesTasks($context, $request, $predecessor, $successor)) {
            return $this->taskManagementError('The selected project does not contain both of these tasks.', 422);
        }

        DB::table('task_management_dependencies')->where('id', $id)->update($this->payload($request, $context) + [
            'updated_by' => $context['user_id'], 'updated_at' => now(),
        ]);

        return $this->taskManagementResponse(null, 'Dependency updated successfully.');
    }

    public function destroy(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        if (!$this->owned($context, $id)) {
            return $this->taskManagementError('Dependency not found.', 404);
        }

        DB::table('task_management_dependencies')->where('id', $id)->delete();

        return $this->taskManagementResponse(null, 'Dependency deleted successfully.');
    }

    /**
     * List milestones on their own, independently of the dependency graph.
     *
     * They were previously reachable only as a side-effect of GET
     * /dependencies, so a screen showing milestones had to fetch the entire
     * dependency graph to find them - and a tenant with no dependencies at
     * all could never manage a milestone, because no GET existed to list
     * one. POST/PUT/DELETE already exist; this completes the set.
     */
    public function indexMilestones(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $milestones = DB::table('task_management_milestones as m')
            ->join('task_management_projects as p', 'p.id', '=', 'm.project_id')
            ->leftJoin('task_management_workstreams as w', 'w.id', '=', 'm.workstream_id')
            ->where('m.sub_institute_id', $context['sub_institute_id'])->where('m.syear', $context['syear'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('m.project_id', $request->integer('project_id')))
            ->orderBy('m.target_date')->select('m.*', 'p.name as project_name', 'w.name as workstream_name')->get();

        return $this->taskManagementResponse([
            'milestones' => $this->withMilestoneCounts($context, $milestones),
            'options' => [
                'projects' => DB::table('task_management_projects')->where('sub_institute_id', $context['sub_institute_id'])
                    ->where('syear', $context['syear'])->whereNull('archived_at')->orderBy('name')->select('id', 'name')->get(),
                'workstreams' => DB::table('task_management_workstreams as w')
                    ->join('task_management_projects as p', 'p.id', '=', 'w.project_id')
                    ->where('p.sub_institute_id', $context['sub_institute_id'])->where('p.syear', $context['syear'])
                    ->orderBy('w.name')->select('w.id', 'w.name', 'w.project_id')->get(),
                // The legal vocabulary comes from the server, not a hardcoded
                // list on the client that can drift from what the validator
                // actually accepts.
                'statuses' => self::MILESTONE_STATUSES,
            ],
        ], 'Milestones retrieved successfully.');
    }

    public function storeMilestone(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $validator = $this->milestoneValidator($request);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }
        if (!$this->validProject($context, $request->integer('project_id'))) {
            return $this->taskManagementError('Project not found.', 422);
        }

        $id = DB::table('task_management_milestones')->insertGetId($this->milestonePayload($request, $context) + [
            'created_by' => $context['user_id'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->taskManagementResponse(['id' => (string) $id], 'Milestone created successfully.', 201);
    }

    public function updateMilestone(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $validator = $this->milestoneValidator($request);
        if ($validator->fails()) {
            return $this->taskManagementError($validator->errors()->first(), 422, ['errors' => $validator->errors()]);
        }

        $updated = DB::table('task_management_milestones')->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])->where('syear', $context['syear'])
            ->update($this->milestonePayload($request, $context) + ['updated_by' => $context['user_id'], 'updated_at' => now()]);

        return $updated
            ? $this->taskManagementResponse(null, 'Milestone updated successfully.')
            : $this->taskManagementError('Milestone not found.', 404);
    }

    public function destroyMilestone(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $deleted = DB::table('task_management_milestones')->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])->where('syear', $context['syear'])->delete();

        return $deleted
            ? $this->taskManagementResponse(null, 'Milestone deleted successfully.')
            : $this->taskManagementError('Milestone not found.', 404);
    }

    private function dependencyQuery(array $context)
    {
        return DB::table('task_management_dependencies as d')
            ->join('task as predecessor', 'predecessor.ID', '=', 'd.predecessor_task_id')
            ->join('task as successor', 'successor.ID', '=', 'd.successor_task_id')
            ->leftJoin('tbluser as assignee', 'assignee.id', '=', 'successor.TASK_ALLOCATED_TO')
            ->leftJoin('task_management_project_tasks as pt', 'pt.task_id', '=', 'successor.ID')
            ->leftJoin('task_management_projects as project', 'project.id', '=', 'pt.project_id')
            ->where('d.sub_institute_id', $context['sub_institute_id'])->where('d.syear', $context['syear'])
            ->whereNull('predecessor.deleted_at')->whereNull('successor.deleted_at')
            // Columns are listed explicitly, and `project.id` is aliased to
            // `derived_project_id`. This used to select `d.*` AND
            // `project.id as project_id` - two columns of the same name, and
            // PDO's object hydration keeps the LAST one, so `$row->project_id`
            // was always the join-derived value and the stored `d.project_id`
            // (added by the 2026_08_20_101100 migration) could never be read
            // back - a column written on every save and returned by nothing.
            ->select(
                'd.id', 'd.predecessor_task_id', 'd.successor_task_id', 'd.dependency_type', 'd.lag_days',
                'd.notes', 'd.project_id', 'd.workstream_id',
                'predecessor.TASK_TITLE as predecessor_title', 'predecessor.STATUS as predecessor_status',
                'predecessor.TASK_DATE as predecessor_due_date', 'predecessor.planned_start_date as predecessor_start_date',
                'successor.TASK_TITLE as successor_title',
                'successor.STATUS as successor_status', 'successor.TASK_DATE as successor_due_date',
                'successor.planned_start_date as successor_start_date',
                'successor.TASK_ALLOCATED_TO as assignee_id',
                'project.id as derived_project_id', 'project.name as project_name',
                DB::raw("TRIM(CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name)) as assignee_name")
            );
    }

    private function resource(object $row): array
    {
        $predecessorComplete = strtoupper((string) $row->predecessor_status) === 'COMPLETED';
        $successorComplete = strtoupper((string) $row->successor_status) === 'COMPLETED';

        return [
            'id' => (string) $row->id, 'type' => $row->dependency_type, 'lag_days' => (int) $row->lag_days,
            'notes' => $row->notes,
            // The STORED column, now actually readable. `derived_project_id`
            // is the one inferred from the successor's project link; they are
            // kept in step by projectMatchesTasks() on write, and both are
            // returned so a divergence would be visible rather than silent.
            'project_id' => $row->project_id ? (string) $row->project_id : null,
            'derived_project_id' => $row->derived_project_id ? (string) $row->derived_project_id : null,
            'workstream_id' => $row->workstream_id ? (string) $row->workstream_id : null,
            'project' => $row->project_name ?: 'Unassigned', 'assignee_id' => $row->assignee_id ? (string) $row->assignee_id : null,
            'assignee' => $row->assignee_name ?: 'Unassigned',
            'predecessor' => ['id' => (string) $row->predecessor_task_id, 'title' => $row->predecessor_title,
                'status' => $row->predecessor_status, 'due_date' => $row->predecessor_due_date,
                'start_date' => $row->predecessor_start_date],
            'successor' => ['id' => (string) $row->successor_task_id, 'title' => $row->successor_title,
                'status' => $row->successor_status, 'due_date' => $row->successor_due_date,
                'start_date' => $row->successor_start_date],
            'blocking' => !$predecessorComplete && !$successorComplete,
            'schedule' => $this->schedule($row),
        ];
    }

    /**
     * Turn `dependency_type` + `lag_days` into a date.
     *
     * These two columns were previously write-only: stored on every save,
     * echoed back once, and never fed into any arithmetic - "lag 2 days"
     * moved no date, ever, and FS/SS/FF/SF were behaviourally identical.
     *
     * This computes what the successor's date SHOULD be:
     *
     *   FS  finish -> start   predecessor due   + lag + 1 day -> successor START
     *   SS  start  -> start   predecessor start + lag         -> successor START
     *   FF  finish -> finish  predecessor due   + lag         -> successor DUE
     *   SF  start  -> finish  predecessor start + lag         -> successor DUE
     *
     * Nothing is moved here - the value is returned with a `violates` flag so
     * the UI can offer an explicit "Apply" action rather than silently
     * rewriting a deadline someone is measured against.
     *
     * SS and SF anchor on the predecessor's `planned_start_date`, which not
     * every task has set; those return a null date and a `reason` naming
     * what is missing rather than guessing.
     */
    private function schedule(object $row): array
    {
        $type = strtoupper((string) $row->dependency_type) ?: 'FS';
        $lag = (int) $row->lag_days;

        $anchorsOnStart = in_array($type, ['SS', 'SF'], true);
        $anchor = $anchorsOnStart ? $row->predecessor_start_date : $row->predecessor_due_date;

        if (!$anchor) {
            return [
                'type' => $type, 'lag_days' => $lag, 'target_field' => null,
                'implied_date' => null, 'current_date' => null, 'violates' => false,
                'reason' => $anchorsOnStart
                    ? 'This relationship is measured from the predecessor\'s planned start date, which has not been set.'
                    : 'The predecessor has no due date, so no date can be implied.',
            ];
        }

        // FS is the only one with an implicit +1: "starts after it finishes"
        // means the next day, not the same day.
        $offset = $lag + ($type === 'FS' ? 1 : 0);
        $implied = \Illuminate\Support\Carbon::parse($anchor)->addDays($offset)->toDateString();

        // FS and SS drive the successor's START; FF and SF drive its DUE date.
        $targetField = in_array($type, ['FS', 'SS'], true) ? 'planned_start_date' : 'due_date';
        $current = $targetField === 'due_date' ? $row->successor_due_date : $row->successor_start_date;

        return [
            'type' => $type,
            'lag_days' => $lag,
            'target_field' => $targetField,
            'implied_date' => $implied,
            'current_date' => $current,
            // Only a date EARLIER than implied breaks the relationship.
            // Starting later than required is a delay, not a contradiction.
            'violates' => $current !== null && $current < $implied,
            'reason' => $current === null
                ? 'The successor has no ' . ($targetField === 'due_date' ? 'due date' : 'planned start date') . ' set yet.'
                : null,
        ];
    }

    private function taskNodes(array $context, array $ids)
    {
        if (!$ids) {
            return collect();
        }

        return DB::table('task as t')->leftJoin('tbluser as u', 'u.id', '=', 't.TASK_ALLOCATED_TO')
            ->leftJoin('task_management_project_tasks as pt', 'pt.task_id', '=', 't.ID')
            ->leftJoin('task_management_projects as p', 'p.id', '=', 'pt.project_id')
            ->where('t.sub_institute_id', $context['sub_institute_id'])->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at')->whereIn('t.ID', $ids)->select('t.ID', 't.TASK_TITLE as title',
                't.STATUS', 't.task_type as priority', 't.TASK_DATE as due_date', 'p.name as project',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) as assignee"))->get()
            ->map(fn ($task) => [
                'id' => (string) $task->ID, 'title' => $task->title, 'status' => $task->STATUS,
                'priority' => $task->priority, 'due_date' => $task->due_date, 'project' => $task->project ?: 'Unassigned',
                'assignee' => $task->assignee ?: 'Unassigned',
                'at_risk' => $task->due_date && $task->due_date < now()->toDateString() && strtoupper((string) $task->STATUS) !== 'COMPLETED',
            ])->unique('id')->values();
    }

    private function taskOptions(array $context)
    {
        return DB::table('task as t')->leftJoin('task_management_project_tasks as pt', 'pt.task_id', '=', 't.ID')
            ->where('t.sub_institute_id', $context['sub_institute_id'])->where('t.SYEAR', $context['syear'])
            ->whereNull('t.deleted_at')->orderByDesc('t.ID')->limit(500)
            ->groupBy('t.ID', 't.TASK_TITLE', 't.STATUS', 't.TASK_DATE')
            ->select('t.ID as id', 't.TASK_TITLE as title', 't.STATUS', 't.TASK_DATE as due_date', DB::raw('MIN(pt.project_id) as project_id'))->get();
    }

    /**
     * Per-milestone task counts (total/completed/open/blocked/overdue),
     * scoped to each milestone's own project (and workstream, when it has
     * one) - not the whole tenant. This used to print one tenant-wide
     * blocking-dependency count on EVERY milestone card, so three different
     * milestones all read e.g. "Blocked: 3 tasks" - one number, repeated,
     * describing none of them.
     *
     * Two queries serve every milestone: the task rows in the projects
     * involved, and the set of task ids currently blocked by an open
     * predecessor. Counting happens in PHP so the number of milestones does
     * not become a number of queries.
     *
     * TENANT SCOPING: task_management_project_tasks has no tenant column, so
     * the project join carries it - the same rule as everywhere else here.
     */
    private function withMilestoneCounts(array $context, $milestones)
    {
        if ($milestones->isEmpty()) {
            return $milestones;
        }

        $projectIds = $milestones->pluck('project_id')->filter()->unique()->values()->all();

        $rows = DB::table('task_management_project_tasks as pt')
            ->join('task as t', 't.ID', '=', 'pt.task_id')
            ->join('task_management_projects as p', 'p.id', '=', 'pt.project_id')
            ->where('p.sub_institute_id', $context['sub_institute_id'])->where('p.syear', $context['syear'])
            ->whereNull('t.deleted_at')->whereIn('pt.project_id', $projectIds)
            ->select('pt.project_id', 'pt.workstream_id', 't.ID', 't.STATUS', 't.TASK_DATE')->get();

        $blockedIds = DB::table('task_management_dependencies as d')
            ->join('task as pred', 'pred.ID', '=', 'd.predecessor_task_id')
            ->where('d.sub_institute_id', $context['sub_institute_id'])->where('d.syear', $context['syear'])
            ->whereNull('pred.deleted_at')
            ->whereRaw("UPPER(COALESCE(pred.STATUS, 'PENDING')) <> 'COMPLETED'")
            ->pluck('d.successor_task_id')->map(fn ($id) => (int) $id)->flip();

        $today = now()->toDateString();

        return $milestones->map(function ($milestone) use ($rows, $blockedIds, $today) {
            $scope = $rows->filter(function ($row) use ($milestone) {
                if ((int) $row->project_id !== (int) $milestone->project_id) {
                    return false;
                }
                // A milestone with no workstream covers the whole project.
                return $milestone->workstream_id === null
                    || (int) $row->workstream_id === (int) $milestone->workstream_id;
            });

            $completed = $scope->filter(fn ($row) => strtoupper((string) $row->STATUS) === 'COMPLETED');
            $milestone->counts = [
                'total' => $scope->count(),
                'completed' => $completed->count(),
                'open' => $scope->count() - $completed->count(),
                'blocked' => $scope->filter(fn ($row) => $blockedIds->has((int) $row->ID))->count(),
                'overdue' => $scope->filter(fn ($row) => $row->TASK_DATE && $row->TASK_DATE < $today
                    && strtoupper((string) $row->STATUS) !== 'COMPLETED')->count(),
            ];
            return $milestone;
        });
    }

    private function criticalPathCount($dependencies): int
    {
        $outgoing = [];
        foreach ($dependencies as $dependency) {
            $outgoing[$dependency['predecessor']['id']][] = $dependency['successor']['id'];
        }

        $memo = [];
        // `$visiting` is a hang guard, not an optimisation. $memo is only
        // written after the recursion returns, so a cycle already present in
        // the table would recurse forever and take the request down with it.
        // The store/update cycle checks are the only thing keeping this safe
        // today, and they cannot protect rows inserted before those checks
        // existed, or written by any other path.
        $visiting = [];
        $depth = function ($id) use (&$depth, &$memo, &$visiting, $outgoing) {
            if (isset($memo[$id])) {
                return $memo[$id];
            }
            if (isset($visiting[$id])) {
                return 1; // cycle: stop descending
            }
            $visiting[$id] = true;
            $best = 1;
            foreach ($outgoing[$id] ?? [] as $next) {
                $best = max($best, 1 + $depth($next));
            }
            unset($visiting[$id]);
            return $memo[$id] = $best;
        };

        $max = 0;
        foreach (array_keys($outgoing) as $id) {
            $max = max($max, $depth($id));
        }
        return $max;
    }

    private function shareProject(array $context, int $predecessor, int $successor): bool
    {
        return DB::table('task_management_project_tasks as first')
            ->join('task_management_project_tasks as second', 'second.project_id', '=', 'first.project_id')
            ->join('task_management_projects as project', 'project.id', '=', 'first.project_id')
            ->where('first.task_id', $predecessor)->where('second.task_id', $successor)
            ->where('project.sub_institute_id', $context['sub_institute_id'])->where('project.syear', $context['syear'])
            ->whereNull('project.archived_at')->exists();
    }

    /**
     * Does this exact directed pair already exist?
     *
     * Mirrors the unique index tm_dependency_pair_unique - tenant, syear and
     * BOTH task ids in order. The index is directional by design: A->B and
     * B->A are different rows, and createsCycle() is what refuses the
     * reverse. $ignoreId lets update() exclude the row being edited.
     */
    private function duplicate(array $context, int $predecessor, int $successor, ?int $ignoreId = null): bool
    {
        $query = DB::table('task_management_dependencies')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('syear', $context['syear'])
            ->where('predecessor_task_id', $predecessor)
            ->where('successor_task_id', $successor);

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * The stored project must agree with the tasks.
     *
     * project_id is now a column AND still derivable by joining
     * task_management_project_tasks on the successor - two sources for one
     * fact. This is what stops them drifting: a submitted project is
     * accepted only if BOTH tasks actually belong to it, so the column can
     * never hold an answer the join would contradict.
     *
     * Omitting project_id is allowed; shareProject() has already proved the
     * two tasks share SOME project, and the read path can resolve it via
     * derived_project_id.
     *
     * The workstream, if given, must belong to that same project - otherwise
     * a dependency could be filed under a workstream from an unrelated
     * project.
     */
    private function projectMatchesTasks(array $context, Request $request, int $predecessor, int $successor): bool
    {
        $projectId = (int) $request->input('project_id');

        if ($projectId <= 0) {
            return true;
        }

        $holdsBoth = DB::table('task_management_project_tasks as first')
            ->join('task_management_project_tasks as second', 'second.project_id', '=', 'first.project_id')
            ->join('task_management_projects as project', 'project.id', '=', 'first.project_id')
            ->where('first.task_id', $predecessor)
            ->where('second.task_id', $successor)
            ->where('first.project_id', $projectId)
            ->where('project.sub_institute_id', $context['sub_institute_id'])
            ->where('project.syear', $context['syear'])
            ->exists();

        if (!$holdsBoth) {
            return false;
        }

        $workstreamId = (int) $request->input('workstream_id');

        if ($workstreamId <= 0) {
            return true;
        }

        return DB::table('task_management_workstreams')
            ->where('id', $workstreamId)
            ->where('project_id', $projectId)
            ->exists();
    }

    private function createsCycle(array $context, int $predecessor, int $successor, ?int $ignoreId = null): bool
    {
        $rows = DB::table('task_management_dependencies')->where('sub_institute_id', $context['sub_institute_id'])
            ->where('syear', $context['syear'])->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->get(['predecessor_task_id', 'successor_task_id']);

        $graph = [];
        foreach ($rows as $row) {
            $graph[(int) $row->predecessor_task_id][] = (int) $row->successor_task_id;
        }
        $graph[$predecessor][] = $successor;

        $stack = [$successor];
        $seen = [];
        while ($stack) {
            $node = array_pop($stack);
            if ($node === $predecessor) {
                return true;
            }
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            foreach ($graph[$node] ?? [] as $next) {
                $stack[] = $next;
            }
        }
        return false;
    }

    private function validator(Request $request)
    {
        return \Illuminate\Support\Facades\Validator::make($request->all(), [
            'predecessor_task_id' => 'required|integer', 'successor_task_id' => 'required|integer',
            'dependency_type' => ['required', Rule::in(self::TYPES)], 'lag_days' => 'required|integer|min:-365|max:365',
            'notes' => 'nullable|string|max:5000',
            // The modal has always sent these; until now nothing accepted
            // them and both were silently dropped on every save.
            'project_id' => 'nullable|integer',
            'workstream_id' => 'nullable|integer',
        ]);
    }

    private function milestoneValidator(Request $request)
    {
        return \Illuminate\Support\Facades\Validator::make($request->all(), [
            'project_id' => 'required|integer', 'workstream_id' => 'nullable|integer',
            'name' => 'required|string|max:191', 'description' => 'nullable|string|max:5000',
            'target_date' => 'required|date', 'status' => ['required', Rule::in(self::MILESTONE_STATUSES)],
        ]);
    }

    private function payload(Request $request, array $context): array
    {
        return ['predecessor_task_id' => $request->integer('predecessor_task_id'),
            'successor_task_id' => $request->integer('successor_task_id'), 'dependency_type' => $request->input('dependency_type'),
            'lag_days' => $request->integer('lag_days'), 'notes' => $request->input('notes'),
            // Persisted at last: the modal has always sent these and the
            // controller has always dropped them.
            'project_id' => $request->input('project_id') ?: null,
            'workstream_id' => $request->input('workstream_id') ?: null,
            'sub_institute_id' => $context['sub_institute_id'], 'syear' => $context['syear']];
    }

    private function milestonePayload(Request $request, array $context): array
    {
        return ['project_id' => $request->integer('project_id'), 'workstream_id' => $request->input('workstream_id'),
            'name' => trim($request->input('name')), 'description' => $request->input('description'),
            // Normalised through dateOnly() (ResolvesTaskManagementContext) -
            // `required|date` accepts an ISO datetime with a Z suffix, and
            // storing that as-is into this `date` column can shift the day
            // for a tenant east of Greenwich.
            'target_date' => $this->dateOnly($request->input('target_date')), 'status' => $request->input('status'),
            'sub_institute_id' => $context['sub_institute_id'], 'syear' => $context['syear']];
    }

    private function validTasks(array $context, array $ids): bool
    {
        return DB::table('task')->where('sub_institute_id', $context['sub_institute_id'])->where('SYEAR', $context['syear'])
            ->whereNull('deleted_at')->whereIn('ID', $ids)->count() === count(array_unique($ids));
    }

    private function validProject(array $context, int $id): bool
    {
        return DB::table('task_management_projects')->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])
            ->where('syear', $context['syear'])->whereNull('archived_at')->exists();
    }

    private function owned(array $context, int $id): bool
    {
        return DB::table('task_management_dependencies')->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])->where('syear', $context['syear'])->exists();
    }
}
