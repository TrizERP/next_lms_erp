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
        if ($request->filled('project_id')) {
            $query->where('project.id', $request->integer('project_id'));
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
            ->select('d.*', 'predecessor.TASK_TITLE as predecessor_title', 'predecessor.STATUS as predecessor_status',
                'predecessor.TASK_DATE as predecessor_due_date', 'successor.TASK_TITLE as successor_title',
                'successor.STATUS as successor_status', 'successor.TASK_DATE as successor_due_date',
                'successor.TASK_ALLOCATED_TO as assignee_id', 'project.id as project_id', 'project.name as project_name',
                DB::raw("TRIM(CONCAT_WS(' ', assignee.first_name, assignee.middle_name, assignee.last_name)) as assignee_name"));
    }

    private function resource(object $row): array
    {
        $predecessorComplete = strtoupper((string) $row->predecessor_status) === 'COMPLETED';
        $successorComplete = strtoupper((string) $row->successor_status) === 'COMPLETED';

        return [
            'id' => (string) $row->id, 'type' => $row->dependency_type, 'lag_days' => (int) $row->lag_days,
            'notes' => $row->notes, 'project_id' => $row->project_id ? (string) $row->project_id : null,
            'project' => $row->project_name ?: 'Unassigned', 'assignee_id' => $row->assignee_id ? (string) $row->assignee_id : null,
            'assignee' => $row->assignee_name ?: 'Unassigned',
            'predecessor' => ['id' => (string) $row->predecessor_task_id, 'title' => $row->predecessor_title,
                'status' => $row->predecessor_status, 'due_date' => $row->predecessor_due_date],
            'successor' => ['id' => (string) $row->successor_task_id, 'title' => $row->successor_title,
                'status' => $row->successor_status, 'due_date' => $row->successor_due_date],
            'blocking' => !$predecessorComplete && !$successorComplete,
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

    private function criticalPathCount($dependencies): int
    {
        $outgoing = [];
        foreach ($dependencies as $dependency) {
            $outgoing[$dependency['predecessor']['id']][] = $dependency['successor']['id'];
        }

        $memo = [];
        $depth = function ($id) use (&$depth, &$memo, $outgoing) {
            if (isset($memo[$id])) {
                return $memo[$id];
            }
            $best = 1;
            foreach ($outgoing[$id] ?? [] as $next) {
                $best = max($best, 1 + $depth($next));
            }
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
            'sub_institute_id' => $context['sub_institute_id'], 'syear' => $context['syear']];
    }

    private function milestonePayload(Request $request, array $context): array
    {
        return ['project_id' => $request->integer('project_id'), 'workstream_id' => $request->input('workstream_id'),
            'name' => trim($request->input('name')), 'description' => $request->input('description'),
            'target_date' => $request->input('target_date'), 'status' => $request->input('status'),
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
