<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\TalentManagement\PerformanceBonusAward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceBonusController`.
 *
 * The Bonus tab: bonus awards (performance / retention / spot / ...) and their
 * approval workflow.
 *
 * `user_id` is the CONTEXT ACTOR; the employee travels as `user_id_target`.
 */
class PerformanceBonusController extends Controller
{
    use ResolvesPerformanceContext;

    private const SORTABLE = ['bonus_type', 'amount', 'pct_of_ctc', 'payout_month', 'status', 'created_at'];

    private const AUDIT_LABELS = [
        'bonus_type'   => 'Bonus Type',
        'amount'       => 'Amount',
        'pct_of_ctc'   => '% of CTC',
        'payout_month' => 'Payout Month',
        'payout_date'  => 'Payout Date',
        'status'       => 'Status',
        'remarks'      => 'Remarks',
        'currency'     => 'Currency',
    ];

    /** GET /api/performance/bonus */
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
        $total = (clone $query)->count('b.id');

        $rows = $query
            ->orderBy('b.' . $sortBy, $sortDir)
            ->orderByDesc('b.id')
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
                    'draft'            => (clone $query)->where('b.status', 'draft')->count('b.id'),
                    'pending_approval' => (clone $query)->where('b.status', 'pending_approval')->count('b.id'),
                    'approved'         => (clone $query)->where('b.status', 'approved')->count('b.id'),
                    'rejected'         => (clone $query)->where('b.status', 'rejected')->count('b.id'),
                    'paid'             => (clone $query)->where('b.status', 'paid')->count('b.id'),
                    'total_amount'     => round((float) (clone $query)->sum('b.amount'), 2),
                ],
                'payout_months' => $this->payoutMonths($tenant),
            ]
        );
    }

    /** POST /api/performance/bonus */
    public function store(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'user_id_target' => 'required|integer',
            'review_id'      => 'nullable|integer',
            'appraisal_id'   => 'nullable|integer',
            'cycle_id'       => 'nullable|integer',
            'bonus_type'     => 'nullable|in:performance,retention,spot,festival,referral,project',
            'currency'       => 'nullable|string|max:10',
            'amount'         => 'nullable|numeric|min:0',
            'pct_of_ctc'     => 'nullable|numeric|min:0',
            'payout_month'   => 'nullable|string|max:7',
            'payout_date'    => 'nullable|date',
            'status'         => 'nullable|in:draft,pending_approval,approved,rejected,paid',
            'remarks'        => 'nullable|string',
        ]);

        $employeeId = (int) $validated['user_id_target'];
        unset($validated['user_id_target']);

        $review = !empty($validated['review_id'])
            ? DB::table('s_performance_reviews')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->find($validated['review_id'])
            : null;

        $award = PerformanceBonusAward::create(array_merge($validated, [
            'sub_institute_id' => $tenant,
            'user_id'          => $employeeId,
            'cycle_id'         => $validated['cycle_id'] ?? ($review->cycle_id ?? null),
            'department_id'    => $review->department_id ?? null,
            'bonus_type'       => $validated['bonus_type'] ?? 'performance',
            'currency'         => $validated['currency'] ?? 'INR',
            // Keep payout_month and payout_date consistent when only one is sent.
            'payout_month'     => $validated['payout_month'] ?? $this->monthOf($validated['payout_date'] ?? null),
            'status'           => $validated['status'] ?? 'draft',
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]));

        $employeeName = $this->resolveActorName($employeeId) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'created_bonus',
            'proposed a ' . str_replace('_', ' ', (string) $award->bonus_type) . ' bonus for ' . $employeeName,
            'bonus',
            $award->id,
            $employeeName,
            null,
            $award->review_id ? (int) $award->review_id : null,
            $award->cycle_id ? (int) $award->cycle_id : null
        );

        return $this->performanceResponse($this->presentModel($award), 'Bonus award created', 201);
    }

    /** PUT /api/performance/bonus/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $award = PerformanceBonusAward::where('sub_institute_id', $tenant)->find($id);

        if (!$award) {
            return $this->performanceError('Bonus award not found', 404);
        }

        if (in_array($award->status, ['approved', 'paid'], true) && !$request->boolean('force')) {
            return $this->performanceError('An ' . $award->status . ' bonus cannot be edited', 422);
        }

        $validated = $request->validate([
            'bonus_type'   => 'nullable|in:performance,retention,spot,festival,referral,project',
            'currency'     => 'nullable|string|max:10',
            'amount'       => 'nullable|numeric|min:0',
            'pct_of_ctc'   => 'nullable|numeric|min:0',
            'payout_month' => 'nullable|string|max:7',
            'payout_date'  => 'nullable|date',
            'status'       => 'nullable|in:draft,pending_approval,approved,rejected,paid',
            'remarks'      => 'nullable|string',
        ]);

        // No user column is writable here (see the trait's `user_id` note).
        if (!array_key_exists('payout_month', $validated) && array_key_exists('payout_date', $validated)) {
            $validated['payout_month'] = $this->monthOf($validated['payout_date']);
        }

        $changes = $this->diffPerformanceChanges($award->getOriginal(), $validated, self::AUDIT_LABELS);

        $award->fill($validated);
        $award->updated_by = $context['user_id'];
        $award->save();

        if (!empty($changes)) {
            $employeeName = $this->resolveActorName((int) $award->user_id) ?? 'an employee';

            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'updated_bonus',
                'updated the bonus award for ' . $employeeName,
                'bonus',
                $award->id,
                $employeeName,
                $changes,
                $award->review_id ? (int) $award->review_id : null,
                $award->cycle_id ? (int) $award->cycle_id : null
            );
        }

        return $this->performanceResponse($this->presentModel($award), 'Bonus award updated');
    }

    /**
     * PUT /api/performance/bonus/{id}/decision
     * action = submit | approve | reject | mark_paid
     */
    public function decision(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $award = PerformanceBonusAward::where('sub_institute_id', $tenant)->find($id);

        if (!$award) {
            return $this->performanceError('Bonus award not found', 404);
        }

        $validated = $request->validate([
            'action'  => 'required|in:submit,approve,reject,mark_paid',
            'remarks' => 'nullable|string',
        ]);

        $from = $award->status;
        $target = match ($validated['action']) {
            'submit'    => 'pending_approval',
            'approve'   => 'approved',
            'reject'    => 'rejected',
            'mark_paid' => 'paid',
        };

        if ($from === $target) {
            return $this->performanceError('This bonus is already ' . $this->statusLabel($target), 422);
        }

        if ($target === 'paid' && $from !== 'approved') {
            return $this->performanceError('Only an approved bonus can be marked paid', 422);
        }

        $award->status = $target;

        if (in_array($target, ['approved', 'rejected'], true)) {
            $award->approver_id = $context['user_id'];
            $award->approved_at = now();
        }

        if ($target === 'paid' && !$award->payout_date) {
            $award->payout_date = now()->toDateString();
            $award->payout_month = $award->payout_month ?: now()->format('Y-m');
        }

        if (!empty($validated['remarks'])) {
            $award->remarks = $validated['remarks'];
        }

        $award->updated_by = $context['user_id'];
        $award->save();

        $employeeName = $this->resolveActorName((int) $award->user_id) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            $validated['action'] . '_bonus',
            $this->statusLabel($target) . ' the bonus award for ' . $employeeName,
            'bonus',
            $award->id,
            $employeeName,
            [['field' => 'status', 'label' => 'Status', 'old' => $this->statusLabel($from), 'new' => $this->statusLabel($target)]],
            $award->review_id ? (int) $award->review_id : null,
            $award->cycle_id ? (int) $award->cycle_id : null
        );

        return $this->performanceResponse($this->presentModel($award), 'Bonus award ' . $this->statusLabel($target));
    }

    /** POST /api/performance/bonus/bulk */
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
            'action' => 'required|in:submit,approve,reject,mark_paid',
        ]);

        $target = match ($validated['action']) {
            'submit'    => 'pending_approval',
            'approve'   => 'approved',
            'reject'    => 'rejected',
            'mark_paid' => 'paid',
        };

        $query = PerformanceBonusAward::where('sub_institute_id', $tenant)
            ->whereIn('id', $validated['ids'])
            ->where('status', '!=', $target);

        // Marking paid is only valid from approved - skip the rest silently
        // rather than pushing drafts straight to paid.
        if ($target === 'paid') {
            $query->where('status', 'approved');
        }

        $awards = $query->get();

        foreach ($awards as $award) {
            $award->status = $target;

            if (in_array($target, ['approved', 'rejected'], true)) {
                $award->approver_id = $context['user_id'];
                $award->approved_at = now();
            }

            if ($target === 'paid' && !$award->payout_date) {
                $award->payout_date = now()->toDateString();
                $award->payout_month = $award->payout_month ?: now()->format('Y-m');
            }

            $award->updated_by = $context['user_id'];
            $award->save();
        }

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'bulk_' . $validated['action'] . '_bonus',
            $this->statusLabel($target) . ' ' . $awards->count() . ' bonus award(s)',
            'bonus',
            null,
            $awards->count() . ' bonus award(s)'
        );

        return $this->performanceResponse(
            ['affected' => $awards->count()],
            $awards->count() . ' bonus award(s) ' . strtolower((string) $this->statusLabel($target))
        );
    }

    /** DELETE /api/performance/bonus/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $award = PerformanceBonusAward::where('sub_institute_id', $tenant)->find($id);

        if (!$award) {
            return $this->performanceError('Bonus award not found', 404);
        }

        if (in_array($award->status, ['approved', 'paid'], true)) {
            return $this->performanceError('An ' . $award->status . ' bonus cannot be deleted', 422);
        }

        $employeeName = $this->resolveActorName((int) $award->user_id) ?? 'an employee';
        $reviewId = $award->review_id;
        $cycleId = $award->cycle_id;

        $award->deleted_by = $context['user_id'];
        $award->save();
        $award->delete();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_bonus',
            'deleted the bonus award for ' . $employeeName,
            'bonus',
            (int) $id,
            $employeeName,
            null,
            $reviewId ? (int) $reviewId : null,
            $cycleId ? (int) $cycleId : null
        );

        return $this->performanceResponse(['id' => (int) $id], 'Bonus award deleted');
    }

    /* ------------------------------------------------------------------ */

    private function baseQuery(int $tenant, array $filters)
    {
        $query = DB::table('s_performance_bonus_awards as b')
            ->leftJoin('tbluser as emp', 'emp.id', '=', 'b.user_id')
            ->leftJoin('tbluser as apr', 'apr.id', '=', 'b.approver_id')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 'b.department_id')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 'b.cycle_id')
            ->where('b.sub_institute_id', $tenant)
            ->whereNull('b.deleted_at')
            ->select([
                'b.*',
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
            $query->where('b.cycle_id', $filters['cycle_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('b.department_id', $filters['department_id']);
        }

        if (!empty($filters['bonus_type'])) {
            $query->where('b.bonus_type', $filters['bonus_type']);
        }

        if (!empty($filters['payout_month'])) {
            $query->where('b.payout_month', $filters['payout_month']);
        }

        if (!empty($filters['user_id_filter'])) {
            $query->where('b.user_id', $filters['user_id_filter']);
        }

        if (!empty($filters['status'])
            && in_array($filters['status'], ['draft', 'pending_approval', 'approved', 'rejected', 'paid'], true)) {
            $query->where('b.status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('emp.first_name', 'like', $search)
                    ->orWhere('emp.last_name', 'like', $search)
                    ->orWhere('emp.employee_no', 'like', $search);
            });
        }

        return $query;
    }

    /** Distinct payout months on record - the tab's month filter options. */
    private function payoutMonths(int $tenant): array
    {
        return DB::table('s_performance_bonus_awards')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereNotNull('payout_month')
            ->distinct()
            ->orderByDesc('payout_month')
            ->pluck('payout_month')
            ->map(function ($month) {
                try {
                    return ['value' => $month, 'label' => \Carbon\Carbon::parse($month . '-01')->format('M Y')];
                } catch (\Throwable $e) {
                    return ['value' => $month, 'label' => $month];
                }
            })
            ->all();
    }

    private function monthOf($date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function present($row): array
    {
        $employeeName = trim(($row->emp_first_name ?? '') . ' ' . ($row->emp_last_name ?? ''));
        $employeeName = $employeeName !== '' ? $employeeName : ($row->emp_user_name ?? 'Unknown');

        $approverName = trim(($row->apr_first_name ?? '') . ' ' . ($row->apr_last_name ?? ''));
        $approverName = $approverName !== '' ? $approverName : ($row->apr_user_name ?? null);

        $payoutLabel = null;
        if ($row->payout_month) {
            try {
                $payoutLabel = \Carbon\Carbon::parse($row->payout_month . '-01')->format('M Y');
            } catch (\Throwable $e) {
                $payoutLabel = $row->payout_month;
            }
        }

        return [
            'id'                => (int) $row->id,
            'user_id'           => (int) $row->user_id,
            'employee_name'     => $employeeName,
            'employee_initials' => $this->initialsOf($employeeName),
            'employee_no'       => $row->emp_employee_no,
            'department_id'     => $row->department_id ? (int) $row->department_id : null,
            'department'        => $row->department_name,
            'cycle_id'          => $row->cycle_id ? (int) $row->cycle_id : null,
            'cycle'             => $row->cycle_name,
            'review_id'         => $row->review_id ? (int) $row->review_id : null,
            'appraisal_id'      => $row->appraisal_id ? (int) $row->appraisal_id : null,
            'bonus_type'        => $row->bonus_type,
            'bonus_type_label'  => ucwords(str_replace('_', ' ', (string) $row->bonus_type)),
            'currency'          => $row->currency,
            'amount'            => $row->amount !== null ? (float) $row->amount : null,
            'pct_of_ctc'        => $row->pct_of_ctc !== null ? (float) $row->pct_of_ctc : null,
            'payout_month'      => $row->payout_month,
            'payout_label'      => $payoutLabel,
            'payout_date'       => $row->payout_date,
            'payout_date_label' => $this->dateLabel($row->payout_date),
            'status'            => $row->status,
            'status_label'      => $this->statusLabel($row->status),
            'approver_id'       => $row->approver_id ? (int) $row->approver_id : null,
            'approver'          => $approverName,
            'approved_label'    => $this->dateTimeLabel($row->approved_at),
            'remarks'           => $row->remarks,
            'created_at'        => $row->created_at,
        ];
    }

    private function presentModel(PerformanceBonusAward $award): array
    {
        $row = $this->baseQuery((int) $award->sub_institute_id, [])->where('b.id', $award->id)->first();

        return $this->present($row);
    }
}
