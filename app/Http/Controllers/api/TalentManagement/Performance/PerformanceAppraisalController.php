<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\TalentManagement\PerformanceAppraisal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceAppraisalController`.
 *
 * The Appraisals tab: the final-rating decision plus the promotion
 * recommendation and its approval workflow.
 *
 * `user_id` is the CONTEXT ACTOR; the appraisee travels as `user_id_target`.
 */
class PerformanceAppraisalController extends Controller
{
    use ResolvesPerformanceContext;

    private const SORTABLE = ['final_rating', 'recommendation', 'effective_date', 'status', 'created_at'];

    private const AUDIT_LABELS = [
        'final_rating'         => 'Final Rating',
        'recommendation'       => 'Recommendation',
        'current_designation'  => 'Current Designation',
        'proposed_designation' => 'Proposed Designation',
        'current_grade'        => 'Current Grade',
        'proposed_grade'       => 'Proposed Grade',
        'effective_date'       => 'Effective Date',
        'status'               => 'Status',
        'remarks'              => 'Remarks',
    ];

    /** GET /api/performance/appraisals */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $filters = $this->performanceFilters($request);
        ['page' => $page, 'per_page' => $perPage] = $this->performancePaging($request);
        [$sortBy, $sortDir] = $this->performanceSort($request, self::SORTABLE, 'created_at');

        $query = $this->baseQuery($tenant, $filters);
        $total = (clone $query)->count('a.id');

        $rows = $query
            ->orderBy('a.' . $sortBy, $sortDir)
            ->orderByDesc('a.id')
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
                'summary' => [
                    'total'            => $total,
                    'draft'            => (clone $query)->where('a.status', 'draft')->count('a.id'),
                    'pending_approval' => (clone $query)->where('a.status', 'pending_approval')->count('a.id'),
                    'approved'         => (clone $query)->where('a.status', 'approved')->count('a.id'),
                    'rejected'         => (clone $query)->where('a.status', 'rejected')->count('a.id'),
                    'promotions'       => (clone $query)->where('a.recommendation', 'promote')->count('a.id'),
                ],
            ]
        );
    }

    /** POST /api/performance/appraisals */
    public function store(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'user_id_target'       => 'required|integer',
            'review_id'            => 'nullable|integer',
            'cycle_id'             => 'nullable|integer',
            'final_rating'         => 'nullable|numeric|min:0|max:10',
            'recommendation'       => 'nullable|in:promote,retain,pip,exit,lateral_move',
            'current_designation'  => 'nullable|string|max:191',
            'proposed_designation' => 'nullable|string|max:191',
            'current_grade'        => 'nullable|string|max:60',
            'proposed_grade'       => 'nullable|string|max:60',
            'effective_date'       => 'nullable|date',
            'status'               => 'nullable|in:draft,pending_approval,approved,rejected',
            'remarks'              => 'nullable|string',
        ]);

        $appraiseeId = (int) $validated['user_id_target'];
        unset($validated['user_id_target']);

        $review = !empty($validated['review_id'])
            ? DB::table('s_performance_reviews')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->find($validated['review_id'])
            : null;

        // An appraisal exists once per employee per cycle; guard the duplicate so
        // the tab cannot end up with two competing decisions for one cycle.
        $cycleId = $validated['cycle_id'] ?? ($review->cycle_id ?? null);

        if ($cycleId) {
            $exists = PerformanceAppraisal::where('sub_institute_id', $tenant)
                ->where('user_id', $appraiseeId)
                ->where('cycle_id', $cycleId)
                ->exists();

            if ($exists) {
                return $this->performanceError('This employee already has an appraisal in this cycle', 422);
            }
        }

        $appraisal = PerformanceAppraisal::create(array_merge($validated, [
            'sub_institute_id'    => $tenant,
            'user_id'             => $appraiseeId,
            'cycle_id'            => $cycleId,
            'department_id'       => $review->department_id ?? null,
            'jobrole'             => $review->jobrole ?? null,
            'current_designation' => $validated['current_designation'] ?? ($review->jobrole ?? null),
            'final_rating'        => $validated['final_rating'] ?? ($review->overall_rating ?? null),
            'final_rating_label'  => $this->ratingLabel(
                isset($validated['final_rating'])
                    ? (float) $validated['final_rating']
                    : (isset($review->overall_rating) ? (float) $review->overall_rating : null)
            ),
            'status'              => $validated['status'] ?? 'draft',
            'created_by'          => $context['user_id'],
            'updated_by'          => $context['user_id'],
        ]));

        $employeeName = $this->resolveActorName($appraiseeId) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'created_appraisal',
            'created an appraisal for ' . $employeeName,
            'appraisal',
            $appraisal->id,
            $employeeName,
            null,
            $appraisal->review_id ? (int) $appraisal->review_id : null,
            $appraisal->cycle_id ? (int) $appraisal->cycle_id : null
        );

        return $this->performanceResponse($this->presentModel($appraisal), 'Appraisal created', 201);
    }

    /** PUT /api/performance/appraisals/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $appraisal = PerformanceAppraisal::where('sub_institute_id', $tenant)->find($id);

        if (!$appraisal) {
            return $this->performanceError('Appraisal not found', 404);
        }

        if ($appraisal->status === 'approved' && !$request->boolean('force')) {
            return $this->performanceError('An approved appraisal cannot be edited', 422);
        }

        $validated = $request->validate([
            'final_rating'         => 'nullable|numeric|min:0|max:10',
            'recommendation'       => 'nullable|in:promote,retain,pip,exit,lateral_move',
            'current_designation'  => 'nullable|string|max:191',
            'proposed_designation' => 'nullable|string|max:191',
            'current_grade'        => 'nullable|string|max:60',
            'proposed_grade'       => 'nullable|string|max:60',
            'effective_date'       => 'nullable|date',
            'status'               => 'nullable|in:draft,pending_approval,approved,rejected',
            'remarks'              => 'nullable|string',
        ]);

        // No user column is writable here (see the trait's `user_id` note).
        if (array_key_exists('final_rating', $validated)) {
            $validated['final_rating_label'] = $this->ratingLabel(
                $validated['final_rating'] !== null ? (float) $validated['final_rating'] : null
            );
        }

        $changes = $this->diffPerformanceChanges($appraisal->getOriginal(), $validated, self::AUDIT_LABELS);

        $appraisal->fill($validated);
        $appraisal->updated_by = $context['user_id'];
        $appraisal->save();

        if (!empty($changes)) {
            $employeeName = $this->resolveActorName((int) $appraisal->user_id) ?? 'an employee';

            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'updated_appraisal',
                'updated the appraisal for ' . $employeeName,
                'appraisal',
                $appraisal->id,
                $employeeName,
                $changes,
                $appraisal->review_id ? (int) $appraisal->review_id : null,
                $appraisal->cycle_id ? (int) $appraisal->cycle_id : null
            );
        }

        return $this->performanceResponse($this->presentModel($appraisal), 'Appraisal updated');
    }

    /**
     * PUT /api/performance/appraisals/{id}/decision
     * action = submit | approve | reject
     */
    public function decision(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $appraisal = PerformanceAppraisal::where('sub_institute_id', $tenant)->find($id);

        if (!$appraisal) {
            return $this->performanceError('Appraisal not found', 404);
        }

        $validated = $request->validate([
            'action'  => 'required|in:submit,approve,reject',
            'remarks' => 'nullable|string',
        ]);

        $from = $appraisal->status;

        $target = match ($validated['action']) {
            'submit'  => 'pending_approval',
            'approve' => 'approved',
            'reject'  => 'rejected',
        };

        if ($from === $target) {
            return $this->performanceError('This appraisal is already ' . $this->statusLabel($target), 422);
        }

        $appraisal->status = $target;

        if (in_array($target, ['approved', 'rejected'], true)) {
            // approver_id is the actor here by definition - this is the one place
            // where the context user legitimately owns the column.
            $appraisal->approver_id = $context['user_id'];
            $appraisal->approved_at = now();
        }

        if (!empty($validated['remarks'])) {
            $appraisal->remarks = $validated['remarks'];
        }

        $appraisal->updated_by = $context['user_id'];
        $appraisal->save();

        $employeeName = $this->resolveActorName((int) $appraisal->user_id) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            $validated['action'] . 'ed_appraisal',
            $this->statusLabel($target) . ' the appraisal for ' . $employeeName,
            'appraisal',
            $appraisal->id,
            $employeeName,
            [['field' => 'status', 'label' => 'Status', 'old' => $this->statusLabel($from), 'new' => $this->statusLabel($target)]],
            $appraisal->review_id ? (int) $appraisal->review_id : null,
            $appraisal->cycle_id ? (int) $appraisal->cycle_id : null
        );

        return $this->performanceResponse($this->presentModel($appraisal), 'Appraisal ' . $this->statusLabel($target));
    }

    /**
     * POST /api/performance/appraisals/bulk
     * Bulk submit / approve / reject from the tab's selection.
     */
    public function bulk(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'action' => 'required|in:submit,approve,reject',
        ]);

        $target = match ($validated['action']) {
            'submit'  => 'pending_approval',
            'approve' => 'approved',
            'reject'  => 'rejected',
        };

        $appraisals = PerformanceAppraisal::where('sub_institute_id', $tenant)
            ->whereIn('id', $validated['ids'])
            ->where('status', '!=', $target)
            ->get();

        foreach ($appraisals as $appraisal) {
            $appraisal->status = $target;

            if (in_array($target, ['approved', 'rejected'], true)) {
                $appraisal->approver_id = $context['user_id'];
                $appraisal->approved_at = now();
            }

            $appraisal->updated_by = $context['user_id'];
            $appraisal->save();
        }

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'bulk_' . $validated['action'] . '_appraisal',
            $this->statusLabel($target) . ' ' . $appraisals->count() . ' appraisal(s)',
            'appraisal',
            null,
            $appraisals->count() . ' appraisal(s)'
        );

        return $this->performanceResponse(
            ['affected' => $appraisals->count()],
            $appraisals->count() . ' appraisal(s) ' . strtolower((string) $this->statusLabel($target))
        );
    }

    /** DELETE /api/performance/appraisals/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $appraisal = PerformanceAppraisal::where('sub_institute_id', $tenant)->find($id);

        if (!$appraisal) {
            return $this->performanceError('Appraisal not found', 404);
        }

        if ($appraisal->status === 'approved') {
            return $this->performanceError('An approved appraisal cannot be deleted', 422);
        }

        $employeeName = $this->resolveActorName((int) $appraisal->user_id) ?? 'an employee';
        $reviewId = $appraisal->review_id;
        $cycleId = $appraisal->cycle_id;

        $appraisal->deleted_by = $context['user_id'];
        $appraisal->save();
        $appraisal->delete();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_appraisal',
            'deleted the appraisal for ' . $employeeName,
            'appraisal',
            (int) $id,
            $employeeName,
            null,
            $reviewId ? (int) $reviewId : null,
            $cycleId ? (int) $cycleId : null
        );

        return $this->performanceResponse(['id' => (int) $id], 'Appraisal deleted');
    }

    /* ------------------------------------------------------------------ */

    private function baseQuery(int $tenant, array $filters)
    {
        $query = DB::table('s_performance_appraisals as a')
            ->leftJoin('tbluser as emp', 'emp.id', '=', 'a.user_id')
            ->leftJoin('tbluser as apr', 'apr.id', '=', 'a.approver_id')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 'a.department_id')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 'a.cycle_id')
            ->where('a.sub_institute_id', $tenant)
            ->whereNull('a.deleted_at')
            ->select([
                'a.*',
                'emp.first_name as emp_first_name',
                'emp.last_name as emp_last_name',
                'emp.user_name as emp_user_name',
                'emp.employee_no as emp_employee_no',
                'apr.first_name as apr_first_name',
                'apr.last_name as apr_last_name',
                'apr.user_name as apr_user_name',
                'dept.department as department_name',
                'c.name as cycle_name',
            ]);

        if (!empty($filters['cycle_id'])) {
            $query->where('a.cycle_id', $filters['cycle_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('a.department_id', $filters['department_id']);
        }

        if (!empty($filters['recommendation'])) {
            $query->where('a.recommendation', $filters['recommendation']);
        }

        if (!empty($filters['user_id_filter'])) {
            $query->where('a.user_id', $filters['user_id_filter']);
        }

        if (!empty($filters['status'])
            && in_array($filters['status'], ['draft', 'pending_approval', 'approved', 'rejected'], true)) {
            $query->where('a.status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('emp.first_name', 'like', $search)
                    ->orWhere('emp.last_name', 'like', $search)
                    ->orWhere('emp.employee_no', 'like', $search)
                    ->orWhere('a.proposed_designation', 'like', $search);
            });
        }

        return $query;
    }

    private function present($row): array
    {
        $employeeName = trim(($row->emp_first_name ?? '') . ' ' . ($row->emp_last_name ?? ''));
        $employeeName = $employeeName !== '' ? $employeeName : ($row->emp_user_name ?? 'Unknown');

        $approverName = trim(($row->apr_first_name ?? '') . ' ' . ($row->apr_last_name ?? ''));
        $approverName = $approverName !== '' ? $approverName : ($row->apr_user_name ?? null);

        return [
            'id'                   => (int) $row->id,
            'user_id'              => (int) $row->user_id,
            'employee_name'        => $employeeName,
            'employee_initials'    => $this->initialsOf($employeeName),
            'employee_no'          => $row->emp_employee_no,
            'department_id'        => $row->department_id ? (int) $row->department_id : null,
            'department'           => $row->department_name,
            'jobrole'              => $row->jobrole,
            'cycle_id'             => $row->cycle_id ? (int) $row->cycle_id : null,
            'cycle'                => $row->cycle_name,
            'review_id'            => $row->review_id ? (int) $row->review_id : null,
            'final_rating'         => $row->final_rating !== null ? (float) $row->final_rating : null,
            'final_rating_label'   => $row->final_rating_label,
            'recommendation'       => $row->recommendation,
            'recommendation_label' => $this->recommendationLabel($row->recommendation),
            'current_designation'  => $row->current_designation,
            'proposed_designation' => $row->proposed_designation,
            'current_grade'        => $row->current_grade,
            'proposed_grade'       => $row->proposed_grade,
            'is_promotion'         => $row->recommendation === 'promote',
            'effective_date'       => $row->effective_date,
            'effective_label'      => $this->dateLabel($row->effective_date),
            'status'               => $row->status,
            'status_label'         => $this->statusLabel($row->status),
            'approver_id'          => $row->approver_id ? (int) $row->approver_id : null,
            'approver'             => $approverName,
            'approved_label'       => $this->dateTimeLabel($row->approved_at),
            'remarks'              => $row->remarks,
            'created_at'           => $row->created_at,
        ];
    }

    private function presentModel(PerformanceAppraisal $appraisal): array
    {
        $row = $this->baseQuery((int) $appraisal->sub_institute_id, [])->where('a.id', $appraisal->id)->first();

        return $this->present($row);
    }

    private function recommendationLabel(?string $recommendation): ?string
    {
        return match ($recommendation) {
            'promote'      => 'Promote',
            'retain'       => 'Retain',
            'pip'          => 'Performance Improvement Plan',
            'exit'         => 'Exit',
            'lateral_move' => 'Lateral Move',
            default        => null,
        };
    }
}
