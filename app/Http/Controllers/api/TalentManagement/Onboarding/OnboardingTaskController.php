<?php

namespace App\Http\Controllers\api\TalentManagement\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Onboarding\Concerns\ResolvesOnboardingContext;
use App\Models\TalentManagement\TalentOnboardingJourney;
use App\Models\TalentManagement\TalentOnboardingTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Onboarding\OnboardingTaskController`.
 *
 * Preboarding / onboarding tasks - the main table on the Preboarding tab, its
 * row actions, the Add Task sheet and the "Onboarding Integrations & Tasks"
 * cards.
 *
 * The integration cards are a rollup over `category` rather than their own
 * entity: a workstream IS its tasks, so a separate table would only ever hold a
 * status that could disagree with them.
 */
class OnboardingTaskController extends Controller
{
    use ResolvesOnboardingContext;

    private const AUDIT_LABELS = [
        'title'       => 'Task',
        'description' => 'Description',
        'category'    => 'Category',
        'owner_id'    => 'Owner',
        'owner_label' => 'Owner',
        'due_date'    => 'Due Date',
        'status'      => 'Status',
        'sort_order'  => 'Order',
    ];

    private const SORTABLE = ['due_date', 'title', 'category', 'status', 'created_at', 'sort_order'];

    /**
     * GET /api/onboarding/tasks
     *
     * Query: journey_id, category, status, owner_id (or `label:HR Team`),
     *        search, overdue_only, page, per_page, sort_by, sort_dir
     */
    public function index(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        [$sortBy, $sortDir] = $this->onboardingSort($request, self::SORTABLE, 'due_date', 'asc');
        $paging = $this->onboardingPaging($request);

        $query = $this->filteredTasks($request, $tenant);

        $total = (clone $query)->count();

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total, SUM(status = "completed") as completed, SUM(status = "pending") as pending, SUM(status = "sent") as sent')
            ->first();

        $tasks = $query
            ->orderBy($sortBy, $sortDir)
            ->orderBy('id')
            ->forPage($paging['page'], $paging['per_page'])
            ->get();

        $directory = $this->onboardingDirectory($tenant, $tasks->pluck('owner_id')->all());

        $data = $tasks->map(fn ($task) => $this->presentTask($task, $directory))->all();

        return $this->onboardingResponse($data, 'Success', 200, [
            'pagination' => [
                'page'      => $paging['page'],
                'per_page'  => $paging['per_page'],
                'total'     => $total,
                'last_page' => max(1, (int) ceil($total / $paging['per_page'])),
            ],
            'summary' => [
                'total'     => (int) ($summary->total ?? 0),
                'completed' => (int) ($summary->completed ?? 0),
                'pending'   => (int) ($summary->pending ?? 0),
                'sent'      => (int) ($summary->sent ?? 0),
            ],
        ]);
    }

    /** POST /api/onboarding/tasks */
    public function store(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'journey_id'  => 'required|integer',
            'title'       => 'required|string|max:191',
            'description' => 'nullable|string',
            'category'    => 'nullable|in:' . implode(',', array_keys(self::TASK_CATEGORIES)),
            'owner_id'    => 'nullable|integer',
            'owner_label' => 'nullable|string|max:100',
            'due_date'    => 'nullable|date',
            'status'      => 'nullable|in:pending,in_progress,sent,completed,blocked',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $journey = $this->findJourney($tenant, $validated['journey_id']);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $task = TalentOnboardingTask::create(array_merge($validated, [
            'sub_institute_id' => $tenant,
            'status'           => $validated['status'] ?? 'pending',
            'sort_order'       => $validated['sort_order'] ?? $this->nextSortOrder($tenant, (int) $journey->id),
            'completed_at'     => ($validated['status'] ?? null) === 'completed' ? now() : null,
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]));

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            'created_task',
            'added task ' . $task->title,
            'task',
            $task->id,
            $task->title,
            null,
            (int) $journey->id
        );

        $directory = $this->onboardingDirectory($tenant, [$task->owner_id]);

        return $this->onboardingResponse($this->presentTask($task, $directory), 'Task added', 201);
    }

    /** PUT /api/onboarding/tasks/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $task = TalentOnboardingTask::where('sub_institute_id', $tenant)->find($id);

        if (!$task) {
            return $this->onboardingError('Task not found', 404);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:191',
            'description' => 'nullable|string',
            'category'    => 'nullable|in:' . implode(',', array_keys(self::TASK_CATEGORIES)),
            'owner_id'    => 'nullable|integer',
            'owner_label' => 'nullable|string|max:100',
            'due_date'    => 'nullable|date',
            'status'      => 'nullable|in:pending,in_progress,sent,completed,blocked',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $changes = $this->diffOnboardingChanges($task->getOriginal(), $validated, self::AUDIT_LABELS);

        $task->fill($validated);

        if (array_key_exists('status', $validated)) {
            $task->completed_at = $validated['status'] === 'completed' ? ($task->completed_at ?? now()) : null;
        }

        $task->updated_by = $context['user_id'];
        $task->save();

        if (!empty($changes)) {
            $this->logOnboardingActivity(
                $tenant,
                $context['user_id'],
                'updated_task',
                'updated task ' . $task->title,
                'task',
                $task->id,
                $task->title,
                $changes,
                (int) $task->journey_id
            );
        }

        $directory = $this->onboardingDirectory($tenant, [$task->owner_id]);

        return $this->onboardingResponse($this->presentTask($task, $directory), 'Task updated');
    }

    /**
     * POST /api/onboarding/tasks/{id}/complete
     *
     * The row checkbox and the "Mark Complete" menu item. `reopen=1` unticks it,
     * so the checkbox is a real two-way control rather than a one-way switch.
     */
    public function complete(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $task = TalentOnboardingTask::where('sub_institute_id', $tenant)->find($id);

        if (!$task) {
            return $this->onboardingError('Task not found', 404);
        }

        $reopen = $request->boolean('reopen');
        $previous = $task->status;

        $task->status = $reopen ? 'pending' : 'completed';
        $task->completed_at = $reopen ? null : now();
        $task->updated_by = $context['user_id'];
        $task->save();

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            $reopen ? 'reopened_task' : 'completed_task',
            ($reopen ? 'reopened' : 'completed') . ' task ' . $task->title,
            'task',
            $task->id,
            $task->title,
            [['field' => 'status', 'label' => 'Status', 'old' => $previous, 'new' => $task->status]],
            (int) $task->journey_id
        );

        $directory = $this->onboardingDirectory($tenant, [$task->owner_id]);

        return $this->onboardingResponse(
            $this->presentTask($task, $directory),
            $reopen ? 'Task reopened' : 'Task marked complete'
        );
    }

    /** DELETE /api/onboarding/tasks/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $task = TalentOnboardingTask::where('sub_institute_id', $tenant)->find($id);

        if (!$task) {
            return $this->onboardingError('Task not found', 404);
        }

        $title = $task->title;
        $journeyId = (int) $task->journey_id;

        $task->deleted_by = $context['user_id'];
        $task->save();
        $task->delete();

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            'deleted_task',
            'deleted task ' . $title,
            'task',
            (int) $id,
            $title,
            null,
            $journeyId
        );

        return $this->onboardingResponse(['id' => (int) $id], 'Task deleted');
    }

    /**
     * POST /api/onboarding/tasks/bulk
     *
     * The header's bulk actions: complete / reopen / reassign / delete a
     * selection, and "Send Reminders" for whatever is still open.
     */
    public function bulk(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'action'      => 'required|in:complete,reopen,delete,reassign,remind',
            'task_ids'    => 'required|array|min:1',
            'task_ids.*'  => 'integer',
            'owner_id'    => 'nullable|integer|required_if:action,reassign',
            'owner_label' => 'nullable|string|max:100',
        ]);

        $tasks = TalentOnboardingTask::where('sub_institute_id', $tenant)
            ->whereIn('id', $validated['task_ids'])
            ->get();

        if ($tasks->isEmpty()) {
            return $this->onboardingError('No matching tasks found', 404);
        }

        $action = $validated['action'];
        $affected = 0;

        DB::transaction(function () use ($tasks, $action, $validated, $context, $tenant, &$affected) {
            foreach ($tasks as $task) {
                switch ($action) {
                    case 'complete':
                        $task->status = 'completed';
                        $task->completed_at = $task->completed_at ?? now();
                        break;
                    case 'reopen':
                        $task->status = 'pending';
                        $task->completed_at = null;
                        break;
                    case 'reassign':
                        $task->owner_id = $validated['owner_id'];
                        $task->owner_label = $validated['owner_label'] ?? null;
                        break;
                    case 'remind':
                        // Reminders are recorded on the feed; delivery is the
                        // notification layer's job, which this module does not own.
                        $this->logOnboardingActivity(
                            $tenant,
                            $context['user_id'],
                            'reminded_task',
                            'sent a reminder for task ' . $task->title,
                            'task',
                            $task->id,
                            $task->title,
                            null,
                            (int) $task->journey_id
                        );
                        $affected++;
                        continue 2;
                    case 'delete':
                        $task->deleted_by = $context['user_id'];
                        $task->save();
                        $task->delete();
                        $affected++;
                        continue 2;
                }

                $task->updated_by = $context['user_id'];
                $task->save();
                $affected++;
            }
        });

        if ($action !== 'remind') {
            $this->logOnboardingActivity(
                $tenant,
                $context['user_id'],
                'bulk_' . $action . '_tasks',
                $action . 'd ' . $affected . ' task(s)',
                'task',
                null,
                null,
                null,
                (int) $tasks->first()->journey_id
            );
        }

        return $this->onboardingResponse(
            ['affected' => $affected],
            $affected . ' task(s) ' . match ($action) {
                'complete' => 'marked complete',
                'reopen'   => 'reopened',
                'reassign' => 'reassigned',
                'remind'   => 'reminded',
                'delete'   => 'deleted',
            }
        );
    }

    /**
     * GET /api/onboarding/workstreams
     *
     * The five "Onboarding Integrations & Tasks" cards. Each card's status is
     * derived from its tasks: no tasks or none touched => Not Started, all done
     * => Completed, anything in between => In Progress. The description is the
     * live count, not a fixed caption.
     *
     * Query: journey_id (optional - omit for the whole tenant)
     */
    public function workstreams(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journeyId = $this->activeOnbFilter($request->input('journey_id'));

        $rows = DB::table('talent_onboarding_tasks')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->when($journeyId, fn ($q) => $q->where('journey_id', $journeyId))
            ->whereIn('category', array_keys(self::WORKSTREAMS))
            ->select(
                'category',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(status = "completed") as completed'),
                DB::raw('SUM(status != "completed" AND status != "pending") as active')
            )
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $data = [];

        foreach (self::WORKSTREAMS as $key => $meta) {
            $row = $rows[$key] ?? null;
            $total = (int) ($row->total ?? 0);
            $completed = (int) ($row->completed ?? 0);
            $active = (int) ($row->active ?? 0);

            if ($total === 0) {
                $status = 'Not Started';
            } elseif ($completed >= $total) {
                $status = 'Completed';
            } elseif ($completed > 0 || $active > 0) {
                $status = 'In Progress';
            } else {
                $status = 'Not Started';
            }

            $data[] = [
                'id'           => $key,
                'category'     => $key,
                'title'        => $meta['title'],
                'icon'         => $meta['icon'],
                'description'  => $total === 0
                    ? 'No tasks yet'
                    : $completed . ' of ' . $total . ' tasks complete',
                'status'       => $status,
                'total'        => $total,
                'completed'    => $completed,
                'progress_pct' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            ];
        }

        return $this->onboardingResponse($data);
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function filteredTasks(Request $request, int $tenant)
    {
        $journeyId = $this->activeOnbFilter($request->input('journey_id'));
        $category = $this->activeOnbFilter($request->input('category'));
        $status = $this->activeOnbFilter($request->input('status'));
        $owner = $this->activeOnbFilter($request->input('owner_id'));
        $search = $this->activeOnbFilter($request->input('search'));
        $overdueOnly = $request->boolean('overdue_only');
        $dueFrom = $this->activeOnbFilter($request->input('due_from'));
        $dueTo = $this->activeOnbFilter($request->input('due_to'));

        return TalentOnboardingTask::where('sub_institute_id', $tenant)
            ->when($journeyId, fn ($q) => $q->where('journey_id', $journeyId))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($status, fn ($q) => $q->where('status', $status))
            // The owner filter accepts both a user id and a `label:HR Team` token,
            // because the screen lists team owners alongside people.
            ->when($owner, function ($q) use ($owner) {
                if (str_starts_with($owner, 'label:')) {
                    $q->where('owner_label', substr($owner, 6));
                } else {
                    $q->where('owner_id', $owner);
                }
            })
            ->when($dueFrom, fn ($q) => $q->whereDate('due_date', '>=', $dueFrom))
            ->when($dueTo, fn ($q) => $q->whereDate('due_date', '<=', $dueTo))
            ->when($overdueOnly, fn ($q) => $q
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString()))
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('owner_label', 'like', "%{$search}%");
            }));
    }

    private function nextSortOrder(int $tenant, int $journeyId): int
    {
        return (int) TalentOnboardingTask::where('sub_institute_id', $tenant)
            ->where('journey_id', $journeyId)
            ->max('sort_order') + 1;
    }

    /** @param array<int, array<string, mixed>> $directory */
    private function presentTask(TalentOnboardingTask $task, array $directory): array
    {
        $owner = $task->owner_id ? ($directory[(int) $task->owner_id] ?? null) : null;
        $ownerName = $owner['name'] ?? $task->owner_label;

        $isOverdue = $task->status !== 'completed'
            && $task->due_date
            && $task->due_date->toDateString() < now()->toDateString();

        return [
            'id'             => (int) $task->id,
            'journey_id'     => (int) $task->journey_id,
            'title'          => $task->title,
            'description'    => $task->description,
            'category'       => $task->category,
            'category_label' => $this->onbCategoryLabel($task->category),
            'owner_id'       => $task->owner_id ? (int) $task->owner_id : null,
            'owner_label'    => $task->owner_label,
            'owner_name'     => $ownerName,
            'owner_initials' => $owner['initials'] ?? $this->onbInitialsOf($task->owner_label),
            'owner_image'    => $owner['image'] ?? null,
            'due_date'       => optional($task->due_date)->toDateString(),
            'due_date_label' => $this->onbDateLabel($task->due_date),
            'status'         => $task->status,
            'status_label'   => $this->onbStatusLabel($task->status),
            'is_overdue'     => (bool) $isOverdue,
            'completed_at'   => optional($task->completed_at)->toDateTimeString(),
            'sort_order'     => (int) $task->sort_order,
        ];
    }
}
