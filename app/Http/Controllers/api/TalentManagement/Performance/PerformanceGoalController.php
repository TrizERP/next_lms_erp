<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\TalentManagement\PerformanceGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceGoalController`.
 *
 * The Goals tab (and the sidebar's Goals tab): weighted, measurable goals -
 * KRA / KPI / OKR - per employee per cycle.
 *
 * `user_id` on the request is the CONTEXT ACTOR; the goal's owner travels as
 * `user_id_target` on create and is filtered with `user_id_filter`.
 */
class PerformanceGoalController extends Controller
{
    use ResolvesPerformanceContext;

    private const SORTABLE = ['title', 'category', 'weightage', 'progress', 'due_date', 'status', 'created_at'];

    private const AUDIT_LABELS = [
        'title'            => 'Goal',
        'description'      => 'Description',
        'category'         => 'Category',
        'weightage'        => 'Weightage',
        'metric'           => 'Metric',
        'target_value'     => 'Target',
        'achieved_value'   => 'Achieved',
        'unit'             => 'Unit',
        'start_date'       => 'Start Date',
        'due_date'         => 'Due Date',
        'progress'         => 'Progress',
        'status'           => 'Status',
        'self_rating'      => 'Self Rating',
        'manager_rating'   => 'Manager Rating',
        'manager_comments' => 'Manager Comments',
    ];

    /** GET /api/performance/goals */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $filters = $this->performanceFilters($request);
        ['page' => $page, 'per_page' => $perPage] = $this->performancePaging($request);
        [$sortBy, $sortDir] = $this->performanceSort($request, self::SORTABLE, 'due_date', 'asc');

        $query = $this->baseQuery($tenant, $filters, $request);
        $total = (clone $query)->count('g.id');

        $rows = $query
            ->orderBy('g.' . $sortBy, $sortDir)
            ->orderByDesc('g.id')
            ->forPage($page, $perPage)
            ->get();

        return $this->performanceResponse(
            $rows->map(fn ($row) => $this->present($row))->all(),
            'Success',
            200,
            [
                'pagination' => [
                    'page'      => $page,
                    'per_page'  => $perPage,
                    'total'     => $total,
                    'last_page' => (int) max(1, ceil($total / $perPage)),
                ],
                'summary' => $this->summary($tenant, $filters, $request),
            ]
        );
    }

    /** POST /api/performance/goals */
    public function store(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'title'          => 'required|string|max:191',
            'description'    => 'nullable|string',
            'category'       => 'nullable|in:kra,kpi,okr,competency,project',
            'weightage'      => 'nullable|numeric|min:0|max:100',
            'metric'         => 'nullable|string|max:191',
            'target_value'   => 'nullable|string|max:100',
            'achieved_value' => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:30',
            'start_date'     => 'nullable|date',
            'due_date'       => 'nullable|date',
            'progress'       => 'nullable|integer|min:0|max:100',
            'status'         => 'nullable|in:draft,active,achieved,partially_achieved,missed,cancelled',
            'cycle_id'       => 'nullable|integer',
            // TL-02(a). Until now `category` could be the STRING 'competency'
            // while nothing pointed at a competency record - G-FLOW-26's second
            // broken promise, "performance reviews informed by competency",
            // existing as a validator value and a dropdown label.
            //
            // NULLABLE ON PURPOSE: "deliver the Q3 migration" develops no
            // particular competency, and forcing every author to pick one
            // produces worse data than an absence.
            'competency_id'  => 'nullable|integer|min:1',
            'review_id'      => 'nullable|integer',
            // The goal owner. NOT `user_id` - that is the context actor.
            'user_id_target' => 'required|integer',
        ]);

        $ownerId = (int) $validated['user_id_target'];

        // THE COMPETENCY MUST BE THE CALLER'S OWN. An id is unique to the table,
        // not to a tenant, so an unchecked value here would let one organisation
        // point a goal at another's competency - and the goal would look valid.
        if (!empty($validated['competency_id'])) {
            $ownsIt = DB::table('competency')
                ->where('id', $validated['competency_id'])
                ->where('sub_institute_id', $context['sub_institute_id'])
                ->exists();
            if (!$ownsIt) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'That competency is not in your organisation.',
                ], 422);
            }
        }
        unset($validated['user_id_target']);

        // Derive cycle/department from the linked review so the Goals tab can be
        // filtered by the same dimensions as the reviews table.
        $review = !empty($validated['review_id'])
            ? DB::table('s_performance_reviews')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->find($validated['review_id'])
            : null;

        $goal = PerformanceGoal::create(array_merge($validated, [
            'sub_institute_id' => $tenant,
            'user_id'          => $ownerId,
            'cycle_id'         => $validated['cycle_id'] ?? ($review->cycle_id ?? null),
            'department_id'    => $review->department_id ?? $this->departmentOf($tenant, $ownerId),
            'category'         => $validated['category'] ?? 'kra',
            'status'           => $validated['status'] ?? 'draft',
            'progress'         => $validated['progress'] ?? 0,
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]));

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'created_goal',
            'added goal "' . $goal->title . '" for ' . ($this->resolveActorName($ownerId) ?? 'an employee'),
            'goal',
            $goal->id,
            $goal->title,
            null,
            $goal->review_id ? (int) $goal->review_id : null,
            $goal->cycle_id ? (int) $goal->cycle_id : null
        );

        return $this->performanceResponse($this->presentModel($tenant, $goal), 'Goal created', 201);
    }

    /** PUT /api/performance/goals/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $goal = PerformanceGoal::where('sub_institute_id', $tenant)->find($id);

        if (!$goal) {
            return $this->performanceError('Goal not found', 404);
        }

        $validated = $request->validate([
            'title'          => 'sometimes|required|string|max:191',
            'description'    => 'nullable|string',
            'category'       => 'nullable|in:kra,kpi,okr,competency,project',
            'weightage'      => 'nullable|numeric|min:0|max:100',
            'metric'         => 'nullable|string|max:191',
            'target_value'   => 'nullable|string|max:100',
            'achieved_value' => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:30',
            'start_date'     => 'nullable|date',
            'due_date'       => 'nullable|date',
            'progress'       => 'nullable|integer|min:0|max:100',
            'status'         => 'nullable|in:draft,active,achieved,partially_achieved,missed,cancelled',
            'self_rating'    => 'nullable|numeric|min:0|max:10',
            'manager_rating' => 'nullable|numeric|min:0|max:10',
            'manager_comments' => 'nullable|string',
        ]);

        // The owner is never reassigned by an edit: accepting a user column here
        // is exactly how a record gets silently transferred to the actor.
        $changes = $this->diffPerformanceChanges($goal->getOriginal(), $validated, self::AUDIT_LABELS);

        $goal->fill($validated);
        $goal->updated_by = $context['user_id'];
        $goal->save();

        if (!empty($changes)) {
            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'updated_goal',
                'updated goal "' . $goal->title . '"',
                'goal',
                $goal->id,
                $goal->title,
                $changes,
                $goal->review_id ? (int) $goal->review_id : null,
                $goal->cycle_id ? (int) $goal->cycle_id : null
            );
        }

        return $this->performanceResponse($this->presentModel($tenant, $goal), 'Goal updated');
    }

    /** DELETE /api/performance/goals/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $goal = PerformanceGoal::where('sub_institute_id', $tenant)->find($id);

        if (!$goal) {
            return $this->performanceError('Goal not found', 404);
        }

        $title = $goal->title;
        $reviewId = $goal->review_id;
        $cycleId = $goal->cycle_id;

        $goal->deleted_by = $context['user_id'];
        $goal->save();
        $goal->delete();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_goal',
            'deleted goal "' . $title . '"',
            'goal',
            (int) $id,
            $title,
            null,
            $reviewId ? (int) $reviewId : null,
            $cycleId ? (int) $cycleId : null
        );

        return $this->performanceResponse(['id' => (int) $id], 'Goal deleted');
    }

    /* ------------------------------------------------------------------ */

    private function baseQuery(int $tenant, array $filters, Request $request)
    {
        $query = DB::table('s_performance_goals as g')
            ->leftJoin('tbluser as emp', 'emp.id', '=', 'g.user_id')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 'g.department_id')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 'g.cycle_id')
            ->where('g.sub_institute_id', $tenant)
            ->whereNull('g.deleted_at')
            ->select([
                'g.*',
                'emp.first_name as emp_first_name',
                'emp.last_name as emp_last_name',
                'emp.user_name as emp_user_name',
                'emp.employee_no as emp_employee_no',
                'dept.department as department_name',
                'c.name as cycle_name',
            ]);

        if (!empty($filters['cycle_id'])) {
            $query->where('g.cycle_id', $filters['cycle_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('g.department_id', $filters['department_id']);
        }

        if (!empty($filters['category'])) {
            $query->where('g.category', $filters['category']);
        }

        if (!empty($filters['user_id_filter'])) {
            $query->where('g.user_id', $filters['user_id_filter']);
        }

        // Goal status uses its own vocabulary, so it is read separately from the
        // review status filter the shared filter bar sends.
        $goalStatus = $this->activePerfFilter($request->input('goal_status') ?? $request->input('status'));
        if ($goalStatus && in_array($goalStatus, ['draft', 'active', 'achieved', 'partially_achieved', 'missed', 'cancelled'], true)) {
            $query->where('g.status', $goalStatus);
        }

        if (!empty($request->input('review_id'))) {
            $query->where('g.review_id', $request->input('review_id'));
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('g.title', 'like', $search)
                    ->orWhere('g.metric', 'like', $search)
                    ->orWhere('emp.first_name', 'like', $search)
                    ->orWhere('emp.last_name', 'like', $search)
                    ->orWhere('emp.employee_no', 'like', $search);
            });
        }

        return $query;
    }

    /** The Goals tab header strip: totals by status plus average progress. */
    private function summary(int $tenant, array $filters, Request $request): array
    {
        $scope = $this->baseQuery($tenant, $filters, $request);

        return [
            'total'        => (clone $scope)->count('g.id'),
            'active'       => (clone $scope)->where('g.status', 'active')->count('g.id'),
            'achieved'     => (clone $scope)->where('g.status', 'achieved')->count('g.id'),
            'missed'       => (clone $scope)->where('g.status', 'missed')->count('g.id'),
            'avg_progress' => (int) round((float) (clone $scope)->avg('g.progress')),
            'total_weight' => round((float) (clone $scope)->sum('g.weightage'), 2),
        ];
    }

    private function present($row): array
    {
        $employeeName = trim(($row->emp_first_name ?? '') . ' ' . ($row->emp_last_name ?? ''));
        $employeeName = $employeeName !== '' ? $employeeName : ($row->emp_user_name ?? 'Unknown');

        return [
            'id'            => (int) $row->id,
            'title'         => $row->title,
            'description'   => $row->description,
            'category'      => $row->category,
            'category_label'=> strtoupper((string) $row->category) === 'KRA' || strtoupper((string) $row->category) === 'KPI' || strtoupper((string) $row->category) === 'OKR'
                ? strtoupper((string) $row->category)
                : ucfirst((string) $row->category),
            'weightage'     => $row->weightage !== null ? (float) $row->weightage : null,
            'metric'        => $row->metric,
            'target_value'  => $row->target_value,
            'achieved_value'=> $row->achieved_value,
            'unit'          => $row->unit,
            'progress'      => (int) $row->progress,
            'status'        => $row->status,
            'status_label'  => $this->goalStatusLabel($row->status),
            'start_date'    => $row->start_date,
            'start_label'   => $this->dateLabel($row->start_date),
            'due_date'      => $row->due_date,
            'due_label'     => $this->dateLabel($row->due_date),
            'is_overdue'    => (bool) ($row->due_date
                && !in_array($row->status, ['achieved', 'cancelled'], true)
                && \Carbon\Carbon::parse($row->due_date)->lt(now()->startOfDay())),
            'self_rating'   => $row->self_rating !== null ? (float) $row->self_rating : null,
            'manager_rating'=> $row->manager_rating !== null ? (float) $row->manager_rating : null,
            'manager_comments' => $row->manager_comments,
            'user_id'       => (int) $row->user_id,
            'employee_name' => $employeeName,
            'employee_initials' => $this->initialsOf($employeeName),
            'employee_no'   => $row->emp_employee_no,
            'department_id'  => $row->department_id ? (int) $row->department_id : null,
            'department'    => $row->department_name,
            'cycle_id'      => $row->cycle_id ? (int) $row->cycle_id : null,
            'cycle'         => $row->cycle_name,
            'review_id'     => $row->review_id ? (int) $row->review_id : null,
        ];
    }

    /** Re-read through the same joins so create/update return the list row shape. */
    private function presentModel(int $tenant, PerformanceGoal $goal): array
    {
        $row = DB::table('s_performance_goals as g')
            ->leftJoin('tbluser as emp', 'emp.id', '=', 'g.user_id')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 'g.department_id')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 'g.cycle_id')
            ->where('g.id', $goal->id)
            ->select([
                'g.*',
                'emp.first_name as emp_first_name',
                'emp.last_name as emp_last_name',
                'emp.user_name as emp_user_name',
                'emp.employee_no as emp_employee_no',
                'dept.department as department_name',
                'c.name as cycle_name',
            ])
            ->first();

        return $this->present($row);
    }

    private function goalStatusLabel(?string $status): ?string
    {
        return match ($status) {
            'draft'              => 'Draft',
            'active'             => 'In Progress',
            'achieved'           => 'Achieved',
            'partially_achieved' => 'Partially Achieved',
            'missed'             => 'Missed',
            'cancelled'          => 'Cancelled',
            default              => $this->statusLabel($status),
        };
    }

    /** The employee's department, preferring one that resolves in the master. */
    private function departmentOf(int $tenant, int $userId): ?int
    {
        $departmentId = DB::table('tbluser')->where('id', $userId)->value('department_id');

        if ($departmentId && DB::table('hrms_departments')
            ->where('id', $departmentId)
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->exists()) {
            return (int) $departmentId;
        }

        // Fall back to the department on the employee's latest review.
        $reviewDepartment = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('department_id');

        return $reviewDepartment ? (int) $reviewDepartment : null;
    }
}
