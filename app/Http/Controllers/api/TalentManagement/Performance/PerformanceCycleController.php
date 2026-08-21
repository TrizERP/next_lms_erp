<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\TalentManagement\PerformanceCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceCycleController`.
 *
 * Review cycles - the header cycle selector and the "Create / Launch" button.
 *
 * Launching a cycle is what materialises s_performance_reviews rows for its
 * participants; until then a cycle is a draft with no reviews.
 */
class PerformanceCycleController extends Controller
{
    use ResolvesPerformanceContext;

    /** Columns whose edits are recorded in the audit trail. */
    private const AUDIT_LABELS = [
        'name'               => 'Cycle Name',
        'code'               => 'Code',
        'cycle_type'         => 'Cycle Type',
        'description'        => 'Description',
        'period_start'       => 'Period Start',
        'period_end'         => 'Period End',
        'self_review_due'    => 'Self Review Due',
        'manager_review_due' => 'Manager Review Due',
        'calibration_due'    => 'Calibration Due',
        'final_review_due'   => 'Final Review Due',
        'status'             => 'Status',
        'rating_scale_max'   => 'Rating Scale Max',
    ];

    /** GET /api/performance/cycles */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $status = $this->activePerfFilter($request->input('status'));

        $cycles = PerformanceCycle::where('sub_institute_id', $tenant)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get();

        // Participant counts for every cycle in one grouped query.
        $counts = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('cycle_id', $cycles->pluck('id')->all() ?: [0])
            ->select('cycle_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(stage = "completed") as completed'))
            ->groupBy('cycle_id')
            ->get()
            ->keyBy('cycle_id');

        $data = $cycles->map(function ($cycle) use ($counts) {
            $row = $counts[$cycle->id] ?? null;
            $total = (int) ($row->total ?? 0);
            $completed = (int) ($row->completed ?? 0);

            return $this->presentCycle($cycle, $total, $completed);
        })->all();

        return $this->performanceResponse($data);
    }

    /** GET /api/performance/cycles/{id} */
    public function show(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $cycle = PerformanceCycle::where('sub_institute_id', $context['sub_institute_id'])->find($id);

        if (!$cycle) {
            return $this->performanceError('Cycle not found', 404);
        }

        $total = DB::table('s_performance_reviews')
            ->where('cycle_id', $cycle->id)->whereNull('deleted_at')->count();
        $completed = DB::table('s_performance_reviews')
            ->where('cycle_id', $cycle->id)->whereNull('deleted_at')->where('stage', 'completed')->count();

        return $this->performanceResponse($this->presentCycle($cycle, $total, $completed));
    }

    /** POST /api/performance/cycles */
    public function store(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validated = $request->validate([
            'name'               => 'required|string|max:191',
            'code'               => 'nullable|string|max:60',
            'cycle_type'         => 'nullable|in:annual,half_yearly,quarterly,probation,project',
            'description'        => 'nullable|string',
            'period_start'       => 'nullable|date',
            'period_end'         => 'nullable|date|after_or_equal:period_start',
            'self_review_due'    => 'nullable|date',
            'manager_review_due' => 'nullable|date',
            'calibration_due'    => 'nullable|date',
            'final_review_due'   => 'nullable|date',
            'status'             => 'nullable|in:draft,active,calibration,closed',
            'rating_scale_max'   => 'nullable|integer|min:3|max:10',
        ]);

        $cycle = PerformanceCycle::create(array_merge($validated, [
            'sub_institute_id' => $context['sub_institute_id'],
            'cycle_type'       => $validated['cycle_type'] ?? 'annual',
            'status'           => $validated['status'] ?? 'draft',
            'rating_scale_max' => $validated['rating_scale_max'] ?? 5,
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]));

        $this->logPerformanceActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'created_cycle',
            'created review cycle ' . $cycle->name,
            'cycle',
            $cycle->id,
            $cycle->name,
            null,
            null,
            $cycle->id
        );

        return $this->performanceResponse($this->presentCycle($cycle, 0, 0), 'Review cycle created', 201);
    }

    /** PUT /api/performance/cycles/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $cycle = PerformanceCycle::where('sub_institute_id', $context['sub_institute_id'])->find($id);

        if (!$cycle) {
            return $this->performanceError('Cycle not found', 404);
        }

        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:191',
            'code'               => 'nullable|string|max:60',
            'cycle_type'         => 'nullable|in:annual,half_yearly,quarterly,probation,project',
            'description'        => 'nullable|string',
            'period_start'       => 'nullable|date',
            'period_end'         => 'nullable|date|after_or_equal:period_start',
            'self_review_due'    => 'nullable|date',
            'manager_review_due' => 'nullable|date',
            'calibration_due'    => 'nullable|date',
            'final_review_due'   => 'nullable|date',
            'status'             => 'nullable|in:draft,active,calibration,closed',
            'rating_scale_max'   => 'nullable|integer|min:3|max:10',
        ]);

        $before = $cycle->getOriginal();
        $changes = $this->diffPerformanceChanges($before, $validated, self::AUDIT_LABELS);

        $cycle->fill($validated);
        $cycle->updated_by = $context['user_id'];
        $cycle->save();

        if (!empty($changes)) {
            $this->logPerformanceActivity(
                $context['sub_institute_id'],
                $context['user_id'],
                'updated_cycle',
                'updated review cycle ' . $cycle->name,
                'cycle',
                $cycle->id,
                $cycle->name,
                $changes,
                null,
                $cycle->id
            );
        }

        $total = DB::table('s_performance_reviews')->where('cycle_id', $cycle->id)->whereNull('deleted_at')->count();
        $completed = DB::table('s_performance_reviews')->where('cycle_id', $cycle->id)->whereNull('deleted_at')->where('stage', 'completed')->count();

        return $this->performanceResponse($this->presentCycle($cycle, $total, $completed), 'Review cycle updated');
    }

    /**
     * POST /api/performance/cycles/{id}/launch
     *
     * Materialises one review row per participant and flips the cycle to active.
     * Participants come from `user_ids` when given, else every employee in the
     * requested departments, else the whole tenant. Idempotent: an employee who
     * already has a review in this cycle is skipped, so re-launching to add a
     * department cannot duplicate rows.
     */
    public function launch(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $cycle = PerformanceCycle::where('sub_institute_id', $tenant)->find($id);

        if (!$cycle) {
            return $this->performanceError('Cycle not found', 404);
        }

        if ($cycle->status === 'closed') {
            return $this->performanceError('This cycle is closed and cannot be launched', 422);
        }

        $validated = $request->validate([
            'user_ids'       => 'nullable|array',
            'user_ids.*'     => 'integer',
            'department_ids' => 'nullable|array',
            'department_ids.*'=> 'integer',
            'due_date'       => 'nullable|date',
        ]);

        // No `deleted_at` on this target's `tbluser` (unlike the G2G source) -
        // it tracks active employees via `status` instead.
        $participantQuery = DB::table('tbluser')
            ->where('sub_institute_id', $tenant);

        if (!empty($validated['user_ids'])) {
            $participantQuery->whereIn('id', $validated['user_ids']);
        } elseif (!empty($validated['department_ids'])) {
            $participantQuery->whereIn('department_id', $validated['department_ids']);
        }

        $participants = $participantQuery->get(['id', 'department_id', 'employee_id']);

        if ($participants->isEmpty()) {
            return $this->performanceError('No employees matched the selection', 422);
        }

        $existing = DB::table('s_performance_reviews')
            ->where('cycle_id', $cycle->id)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->all();

        $existing = array_flip($existing);
        $dueDate = $validated['due_date'] ?? $cycle->self_review_due ?? $cycle->period_end;
        $now = now();
        $rows = [];

        // Real department ids for the tenant, so a dangling tbluser.department_id
        // falls back instead of poisoning the filter.
        $validDepartments = DB::table('hrms_departments')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip();

        foreach ($participants as $participant) {
            if (isset($existing[$participant->id])) {
                continue;
            }

            // tbluser.employee_id is this ERP's supervisor link; 0 means unset.
            $managerId = ($participant->employee_id && (int) $participant->employee_id > 0)
                ? (int) $participant->employee_id
                : null;

            $placement = $this->resolvePlacement($tenant, (int) $participant->id);

            $departmentId = ($participant->department_id && isset($validDepartments[(int) $participant->department_id]))
                ? (int) $participant->department_id
                : $placement['department_id'];

            $rows[] = [
                'sub_institute_id' => $tenant,
                'cycle_id'         => $cycle->id,
                'user_id'          => (int) $participant->id,
                'manager_id'       => $managerId,
                'department_id'    => $departmentId,
                'jobrole'          => $placement['jobrole'],
                'stage'            => 'self_review',
                'status'           => 'pending',
                'is_draft'         => true,
                'due_date'         => $dueDate,
                'created_by'       => $context['user_id'],
                'updated_by'       => $context['user_id'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('s_performance_reviews')->insert($chunk);
            }
        }

        $cycle->status = $cycle->status === 'draft' ? 'active' : $cycle->status;
        $cycle->launched_at = $cycle->launched_at ?? $now;
        $cycle->updated_by = $context['user_id'];
        $cycle->save();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'launched_cycle',
            'launched review cycle ' . $cycle->name . ' for ' . count($rows) . ' employee(s)',
            'cycle',
            $cycle->id,
            $cycle->name,
            null,
            null,
            $cycle->id
        );

        $total = DB::table('s_performance_reviews')->where('cycle_id', $cycle->id)->whereNull('deleted_at')->count();

        return $this->performanceResponse([
            'cycle'            => $this->presentCycle($cycle->fresh(), $total, 0),
            'created_reviews'  => count($rows),
            'skipped_existing' => $participants->count() - count($rows),
        ], count($rows) . ' review(s) launched');
    }

    /** POST /api/performance/cycles/{id}/close */
    public function close(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $cycle = PerformanceCycle::where('sub_institute_id', $context['sub_institute_id'])->find($id);

        if (!$cycle) {
            return $this->performanceError('Cycle not found', 404);
        }

        $pending = DB::table('s_performance_reviews')
            ->where('cycle_id', $cycle->id)
            ->whereNull('deleted_at')
            ->where('stage', '!=', 'completed')
            ->count();

        // Closing with unfinished reviews is allowed but must be deliberate.
        if ($pending > 0 && !$request->boolean('force')) {
            return $this->performanceError(
                $pending . ' review(s) are not yet completed. Re-send with force=1 to close anyway.',
                422,
                ['pending_reviews' => $pending]
            );
        }

        $cycle->status = 'closed';
        $cycle->closed_at = now();
        $cycle->updated_by = $context['user_id'];
        $cycle->save();

        $this->logPerformanceActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'closed_cycle',
            'closed review cycle ' . $cycle->name,
            'cycle',
            $cycle->id,
            $cycle->name,
            [['field' => 'status', 'label' => 'Status', 'old' => 'active', 'new' => 'closed']],
            null,
            $cycle->id
        );

        return $this->performanceResponse($this->presentCycle($cycle, 0, 0), 'Review cycle closed');
    }

    /** DELETE /api/performance/cycles/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $cycle = PerformanceCycle::where('sub_institute_id', $context['sub_institute_id'])->find($id);

        if (!$cycle) {
            return $this->performanceError('Cycle not found', 404);
        }

        $reviewCount = DB::table('s_performance_reviews')
            ->where('cycle_id', $cycle->id)->whereNull('deleted_at')->count();

        if ($reviewCount > 0) {
            return $this->performanceError(
                'This cycle has ' . $reviewCount . ' review(s) and cannot be deleted. Close it instead.',
                422
            );
        }

        $name = $cycle->name;
        $cycle->deleted_by = $context['user_id'];
        $cycle->save();
        $cycle->delete();

        $this->logPerformanceActivity(
            $context['sub_institute_id'],
            $context['user_id'],
            'deleted_cycle',
            'deleted review cycle ' . $name,
            'cycle',
            (int) $id,
            $name
        );

        return $this->performanceResponse(['id' => (int) $id], 'Review cycle deleted');
    }

    /**
     * The employee's job role and department.
     *
     * There is no user -> jobrole table in this ERP: the role is derived from
     * the employee's most recent competency assessment - the same derivation the
     * Competency module's Career Path Explorer relies on in the source system -
     * with org_designation.designation as the fallback.
     *
     * @return array{jobrole:?string, department_id:?int}
     */
    private function resolvePlacement(int $tenant, int $userId): array
    {
        // s_competency_assessments belongs to the out-of-scope Competency
        // Assessment Cycle feature and does not exist on this target - guard
        // it the same way CareerPathController/EmployeeCompetencyProfileController
        // already guard their reads of it, and fall straight through to the
        // tbluser-based placement below.
        $assessment = Schema::hasTable('s_competency_assessments')
            ? DB::table('s_competency_assessments')
                ->where('sub_institute_id', $tenant)
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->whereNotNull('jobrole')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first(['jobrole', 'department_id'])
            : null;

        if ($assessment) {
            return [
                'jobrole'       => $assessment->jobrole,
                'department_id' => $assessment->department_id ? (int) $assessment->department_id : null,
            ];
        }

        // No `org_designation` table on this target - falls back to
        // `tbluser.jobtitle_id` -> `s_user_jobrole.jobrole`/`department_id`
        // instead, matching every other ported Talent Management controller.
        $user = DB::table('tbluser')
            ->where('id', $userId)
            ->where('sub_institute_id', $tenant)
            ->first(['jobtitle_id', 'department_id']);

        $jobrole = $user && $user->jobtitle_id
            ? DB::table('s_user_jobrole')->where('id', $user->jobtitle_id)->whereNull('deleted_at')->value('jobrole')
            : null;

        return [
            'jobrole'       => $jobrole,
            'department_id' => $user && $user->department_id ? (int) $user->department_id : null,
        ];
    }

    private function presentCycle(PerformanceCycle $cycle, int $total, int $completed): array
    {
        return [
            'id'                 => (int) $cycle->id,
            'name'               => $cycle->name,
            'code'               => $cycle->code,
            'cycle_type'         => $cycle->cycle_type,
            'cycle_type_label'   => ucwords(str_replace('_', ' ', (string) $cycle->cycle_type)),
            'description'        => $cycle->description,
            'period_start'       => optional($cycle->period_start)->toDateString(),
            'period_end'         => optional($cycle->period_end)->toDateString(),
            'period_label'       => ($cycle->period_start && $cycle->period_end)
                ? $cycle->period_start->format("M'y") . '-' . $cycle->period_end->format("M'y")
                : null,
            'self_review_due'    => optional($cycle->self_review_due)->toDateString(),
            'manager_review_due' => optional($cycle->manager_review_due)->toDateString(),
            'calibration_due'    => optional($cycle->calibration_due)->toDateString(),
            'final_review_due'   => optional($cycle->final_review_due)->toDateString(),
            'status'             => $cycle->status,
            'status_label'       => $this->statusLabel($cycle->status),
            'rating_scale_max'   => (int) $cycle->rating_scale_max,
            'launched_at'        => optional($cycle->launched_at)->toDateTimeString(),
            'launched_label'     => $this->dateTimeLabel($cycle->launched_at),
            'closed_at'          => optional($cycle->closed_at)->toDateTimeString(),
            'total_reviews'      => $total,
            'completed_reviews'  => $completed,
            'completion_pct'     => $total > 0 ? (int) round($completed / $total * 100) : 0,
        ];
    }
}
