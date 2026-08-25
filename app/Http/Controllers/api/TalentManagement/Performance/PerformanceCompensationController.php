<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\AuditLog;
use App\Models\TalentManagement\PerformanceCompensationRevision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceCompensationController`.
 *
 * The Compensation tab: proposed and approved CTC revisions.
 *
 * Current CTC is READ from payroll (employee_salary_structures) when the caller
 * does not supply it - that table is payroll's salary-structure store and has no
 * proposed/approved workflow of its own, so nothing here writes to it.
 *
 * `user_id` is the CONTEXT ACTOR; the employee travels as `user_id_target`.
 */
class PerformanceCompensationController extends Controller
{
    use ResolvesPerformanceContext;
    use RequiresTalentAdmin;

    private const SORTABLE = ['current_ctc', 'proposed_ctc', 'increment_amount', 'increment_pct', 'effective_date', 'status', 'created_at'];

    private const AUDIT_LABELS = [
        'current_ctc'      => 'Current CTC',
        'proposed_ctc'     => 'Proposed CTC',
        'increment_amount' => 'Increment Amount',
        'increment_pct'    => 'Increment %',
        'revision_type'    => 'Revision Type',
        'effective_date'   => 'Effective Date',
        'status'           => 'Status',
        'remarks'          => 'Remarks',
        'currency'         => 'Currency',
    ];

    /** GET /api/performance/compensation */
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
        $total = (clone $query)->count('cr.id');

        $rows = $query
            ->orderBy('cr.' . $sortBy, $sortDir)
            ->orderByDesc('cr.id')
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
                    'draft'            => (clone $query)->where('cr.status', 'draft')->count('cr.id'),
                    'pending_approval' => (clone $query)->where('cr.status', 'pending_approval')->count('cr.id'),
                    'approved'         => (clone $query)->where('cr.status', 'approved')->count('cr.id'),
                    'rejected'         => (clone $query)->where('cr.status', 'rejected')->count('cr.id'),
                    'total_increment'  => round((float) (clone $query)->sum('cr.increment_amount'), 2),
                    'avg_increment_pct'=> round((float) (clone $query)->avg('cr.increment_pct'), 2),
                ],
            ]
        );
    }

    /** POST /api/performance/compensation */
    public function store(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'user_id_target'   => 'required|integer',
            'review_id'        => 'nullable|integer',
            'appraisal_id'     => 'nullable|integer',
            'cycle_id'         => 'nullable|integer',
            'currency'         => 'nullable|string|max:10',
            'current_ctc'      => 'nullable|numeric|min:0',
            'proposed_ctc'     => 'nullable|numeric|min:0',
            'increment_amount' => 'nullable|numeric',
            'increment_pct'    => 'nullable|numeric',
            'revision_type'    => 'nullable|in:merit,promotion,market_correction,retention,probation_confirmation',
            'effective_date'   => 'nullable|date',
            'status'           => 'nullable|in:draft,pending_approval,approved,rejected',
            'remarks'          => 'nullable|string',
        ]);

        $employeeId = (int) $validated['user_id_target'];
        unset($validated['user_id_target']);

        $review = !empty($validated['review_id'])
            ? DB::table('s_performance_reviews')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->find($validated['review_id'])
            : null;

        $currentCtc = $validated['current_ctc'] ?? $this->payrollCtc($tenant, $employeeId);

        $revision = PerformanceCompensationRevision::create(array_merge(
            $validated,
            $this->deriveAmounts($currentCtc, $validated['proposed_ctc'] ?? null, $validated),
            [
                'sub_institute_id' => $tenant,
                'user_id'          => $employeeId,
                'cycle_id'         => $validated['cycle_id'] ?? ($review->cycle_id ?? null),
                'department_id'    => $review->department_id ?? null,
                'currency'         => $validated['currency'] ?? 'INR',
                'revision_type'    => $validated['revision_type'] ?? 'merit',
                'status'           => $validated['status'] ?? 'draft',
                'created_by'       => $context['user_id'],
                'updated_by'       => $context['user_id'],
            ]
        ));

        $employeeName = $this->resolveActorName($employeeId) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'created_compensation',
            'proposed a compensation revision for ' . $employeeName,
            'compensation',
            $revision->id,
            $employeeName,
            null,
            $revision->review_id ? (int) $revision->review_id : null,
            $revision->cycle_id ? (int) $revision->cycle_id : null
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'compensation_created',
            'entity_type' => 'performance_compensation_revision',
            'entity_id'   => $revision->id,
            'new_values'  => array_merge($validated, ['user_id' => $employeeId]),
        ]);

        return $this->performanceResponse($this->presentModel($revision), 'Compensation revision created', 201);
    }

    /** PUT /api/performance/compensation/{id} */
    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $revision = PerformanceCompensationRevision::where('sub_institute_id', $tenant)->find($id);

        if (!$revision) {
            return $this->performanceError('Compensation revision not found', 404);
        }

        if ($revision->status === 'approved' && !$request->boolean('force')) {
            return $this->performanceError('An approved revision cannot be edited', 422);
        }

        $validated = $request->validate([
            'currency'         => 'nullable|string|max:10',
            'current_ctc'      => 'nullable|numeric|min:0',
            'proposed_ctc'     => 'nullable|numeric|min:0',
            'increment_amount' => 'nullable|numeric',
            'increment_pct'    => 'nullable|numeric',
            'revision_type'    => 'nullable|in:merit,promotion,market_correction,retention,probation_confirmation',
            'effective_date'   => 'nullable|date',
            'status'           => 'nullable|in:draft,pending_approval,approved,rejected',
            'remarks'          => 'nullable|string',
        ]);

        // No user column is writable here (see the trait's `user_id` note).
        $current = $validated['current_ctc'] ?? $revision->current_ctc;
        $proposed = $validated['proposed_ctc'] ?? $revision->proposed_ctc;
        $validated = array_merge($validated, $this->deriveAmounts($current, $proposed, $validated));

        $changes = $this->diffPerformanceChanges($revision->getOriginal(), $validated, self::AUDIT_LABELS);

        $revision->fill($validated);
        $revision->updated_by = $context['user_id'];
        $revision->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'compensation_updated',
            'entity_type' => 'performance_compensation_revision',
            'entity_id'   => $revision->id,
            'new_values'  => $validated,
        ]);

        if (!empty($changes)) {
            $employeeName = $this->resolveActorName((int) $revision->user_id) ?? 'an employee';

            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'updated_compensation',
                'updated the compensation revision for ' . $employeeName,
                'compensation',
                $revision->id,
                $employeeName,
                $changes,
                $revision->review_id ? (int) $revision->review_id : null,
                $revision->cycle_id ? (int) $revision->cycle_id : null
            );
        }

        return $this->performanceResponse($this->presentModel($revision), 'Compensation revision updated');
    }

    /**
     * PUT /api/performance/compensation/{id}/decision
     * action = submit | approve | reject
     */
    public function decision(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $revision = PerformanceCompensationRevision::where('sub_institute_id', $tenant)->find($id);

        if (!$revision) {
            return $this->performanceError('Compensation revision not found', 404);
        }

        $validated = $request->validate([
            'action'  => 'required|in:submit,approve,reject',
            'remarks' => 'nullable|string',
        ]);

        $from = $revision->status;
        $target = match ($validated['action']) {
            'submit'  => 'pending_approval',
            'approve' => 'approved',
            'reject'  => 'rejected',
        };

        if ($from === $target) {
            return $this->performanceError('This revision is already ' . $this->statusLabel($target), 422);
        }

        $revision->status = $target;

        if (in_array($target, ['approved', 'rejected'], true)) {
            $revision->approver_id = $context['user_id'];
            $revision->approved_at = now();
        }

        if (!empty($validated['remarks'])) {
            $revision->remarks = $validated['remarks'];
        }

        $revision->updated_by = $context['user_id'];
        $revision->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'compensation_decision',
            'entity_type' => 'performance_compensation_revision',
            'entity_id'   => $revision->id,
            'new_values'  => ['action' => $validated['action'], 'status' => $target, 'remarks' => $validated['remarks'] ?? null],
        ]);

        $employeeName = $this->resolveActorName((int) $revision->user_id) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            $validated['action'] . 'ed_compensation',
            $this->statusLabel($target) . ' the compensation revision for ' . $employeeName,
            'compensation',
            $revision->id,
            $employeeName,
            [['field' => 'status', 'label' => 'Status', 'old' => $this->statusLabel($from), 'new' => $this->statusLabel($target)]],
            $revision->review_id ? (int) $revision->review_id : null,
            $revision->cycle_id ? (int) $revision->cycle_id : null
        );

        return $this->performanceResponse($this->presentModel($revision), 'Compensation revision ' . $this->statusLabel($target));
    }

    /** POST /api/performance/compensation/bulk */
    public function bulk(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

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

        $revisions = PerformanceCompensationRevision::where('sub_institute_id', $tenant)
            ->whereIn('id', $validated['ids'])
            ->where('status', '!=', $target)
            ->get();

        foreach ($revisions as $revision) {
            $revision->status = $target;

            if (in_array($target, ['approved', 'rejected'], true)) {
                $revision->approver_id = $context['user_id'];
                $revision->approved_at = now();
            }

            $revision->updated_by = $context['user_id'];
            $revision->save();

            AuditLog::record([
                'module'      => 'talent_management',
                'action'      => 'compensation_decision',
                'entity_type' => 'performance_compensation_revision',
                'entity_id'   => $revision->id,
                'new_values'  => ['action' => $validated['action'], 'status' => $target, 'bulk' => true],
            ]);
        }

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'bulk_' . $validated['action'] . '_compensation',
            $this->statusLabel($target) . ' ' . $revisions->count() . ' compensation revision(s)',
            'compensation',
            null,
            $revisions->count() . ' revision(s)'
        );

        return $this->performanceResponse(
            ['affected' => $revisions->count()],
            $revisions->count() . ' revision(s) ' . strtolower((string) $this->statusLabel($target))
        );
    }

    /** DELETE /api/performance/compensation/{id} */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $revision = PerformanceCompensationRevision::where('sub_institute_id', $tenant)->find($id);

        if (!$revision) {
            return $this->performanceError('Compensation revision not found', 404);
        }

        if ($revision->status === 'approved') {
            return $this->performanceError('An approved revision cannot be deleted', 422);
        }

        $employeeName = $this->resolveActorName((int) $revision->user_id) ?? 'an employee';
        $reviewId = $revision->review_id;
        $cycleId = $revision->cycle_id;

        $revision->deleted_by = $context['user_id'];
        $revision->save();
        $revision->delete();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'compensation_deleted',
            'entity_type' => 'performance_compensation_revision',
            'entity_id'   => (int) $id,
            'new_values'  => ['user_id' => (int) $revision->user_id],
        ]);

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_compensation',
            'deleted the compensation revision for ' . $employeeName,
            'compensation',
            (int) $id,
            $employeeName,
            null,
            $reviewId ? (int) $reviewId : null,
            $cycleId ? (int) $cycleId : null
        );

        return $this->performanceResponse(['id' => (int) $id], 'Compensation revision deleted');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Keep amount and percentage consistent: whichever of the two the caller
     * supplied wins, and the other is derived, so the table can never show an
     * increment % that disagrees with its own amount.
     *
     * @return array<string, mixed>
     */
    private function deriveAmounts($currentCtc, $proposedCtc, array $payload): array
    {
        $derived = ['current_ctc' => $currentCtc];

        $current = $currentCtc !== null ? (float) $currentCtc : null;

        // Percentage given, no proposed CTC -> compute both from the percentage.
        if ($proposedCtc === null && isset($payload['increment_pct']) && $current) {
            $amount = round($current * ((float) $payload['increment_pct'] / 100), 2);

            return array_merge($derived, [
                'increment_amount' => $amount,
                'proposed_ctc'     => round($current + $amount, 2),
            ]);
        }

        // Amount given, no proposed CTC -> compute the rest from the amount.
        if ($proposedCtc === null && isset($payload['increment_amount']) && $current) {
            $amount = (float) $payload['increment_amount'];

            return array_merge($derived, [
                'proposed_ctc'  => round($current + $amount, 2),
                'increment_pct' => $current > 0 ? round($amount / $current * 100, 2) : null,
            ]);
        }

        if ($proposedCtc === null || $current === null) {
            return array_merge($derived, ['proposed_ctc' => $proposedCtc]);
        }

        $proposed = (float) $proposedCtc;
        $amount = round($proposed - $current, 2);

        return array_merge($derived, [
            'proposed_ctc'     => $proposed,
            'increment_amount' => $amount,
            'increment_pct'    => $current > 0 ? round($amount / $current * 100, 2) : null,
        ]);
    }

    /**
     * How many pay periods a salary structure covers in a year.
     *
     * The payroll module builds MONTHLY payslips from a structure
     * (employee_monthly_salary_data), so the amounts stored on a structure are
     * per month and are annualised here because this column is a CTC.
     */
    private const PAY_PERIODS_PER_YEAR = 12;

    /**
     * Current CTC from payroll's salary structure for the latest year on record.
     *
     * The shape of employee_salary_structures.employee_salary_data is confirmed
     * from its writer (PayrollController::store, which json_encodes
     * [$payroll_type_id => $amount]) and its reader
     * (leaveEncashmentController, which maps each key back through
     * payroll_types.payroll_name):
     *
     *     {"<payroll_types.id>": <amount>, ...}   e.g. {"1":1000,"5":1500,"6":1000}
     *
     * CRITICAL: that map holds BOTH earnings and deductions - PayrollController
     * itself writes PF and PT into it. payroll_types.payroll_type is the
     * discriminator (1 = allowance, 2 = deduction; see PayrollController lines
     * 559-560, `->where('payroll_type', 1)` / `('payroll_type', 2)`), so only
     * allowances are summed. Summing every numeric leaf - as this method first
     * did - adds the employee's own PF and professional tax into their CTC.
     *
     * Pay heads are resolved by tenant, but NOT filtered on status/deleted_at: a
     * head that has since been retired still contributed to a historical
     * structure, and excluding it would silently under-report the CTC.
     */
    private function payrollCtc(int $tenant, int $userId): ?float
    {
        $structure = DB::table('employee_salary_structures')
            ->where('sub_institute_id', $tenant)
            ->where('employee_id', $userId)
            ->whereNull('deleted_at')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('employee_salary_data');

        if (!$structure) {
            return null;
        }

        $decoded = json_decode($structure, true);

        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        // Keys are payroll_types ids. Anything non-numeric is not this shape.
        $headIds = array_values(array_filter(array_keys($decoded), 'is_numeric'));

        if (empty($headIds)) {
            return null;
        }

        // Tenant scoped: a structure in this data can reference another tenant's
        // pay head, and reading that would leak a foreign salary component.
        $allowanceIds = DB::table('payroll_types')
            ->where('sub_institute_id', $tenant)
            ->whereIn('id', $headIds)
            ->where('payroll_type', 1)
            ->pluck('id')
            ->all();

        if (empty($allowanceIds)) {
            return null;
        }

        $monthly = 0.0;

        foreach ($allowanceIds as $headId) {
            $amount = $decoded[(string) $headId] ?? $decoded[$headId] ?? null;

            if (is_numeric($amount)) {
                $monthly += (float) $amount;
            }
        }

        if ($monthly <= 0) {
            return null;
        }

        return round($monthly * self::PAY_PERIODS_PER_YEAR, 2);
    }

    private function baseQuery(int $tenant, array $filters)
    {
        $query = DB::table('s_performance_compensation_revisions as cr')
            ->leftJoin('tbluser as emp', 'emp.id', '=', 'cr.user_id')
            ->leftJoin('tbluser as apr', 'apr.id', '=', 'cr.approver_id')
            ->leftJoin('hrms_departments as dept', 'dept.id', '=', 'cr.department_id')
            ->leftJoin('s_performance_cycles as c', 'c.id', '=', 'cr.cycle_id')
            ->where('cr.sub_institute_id', $tenant)
            ->whereNull('cr.deleted_at')
            ->select([
                'cr.*',
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
            $query->where('cr.cycle_id', $filters['cycle_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('cr.department_id', $filters['department_id']);
        }

        if (!empty($filters['revision_type'])) {
            $query->where('cr.revision_type', $filters['revision_type']);
        }

        if (!empty($filters['user_id_filter'])) {
            $query->where('cr.user_id', $filters['user_id_filter']);
        }

        if (!empty($filters['status'])
            && in_array($filters['status'], ['draft', 'pending_approval', 'approved', 'rejected'], true)) {
            $query->where('cr.status', $filters['status']);
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

    private function present($row): array
    {
        $employeeName = trim(($row->emp_first_name ?? '') . ' ' . ($row->emp_last_name ?? ''));
        $employeeName = $employeeName !== '' ? $employeeName : ($row->emp_user_name ?? 'Unknown');

        $approverName = trim(($row->apr_first_name ?? '') . ' ' . ($row->apr_last_name ?? ''));
        $approverName = $approverName !== '' ? $approverName : ($row->apr_user_name ?? null);

        return [
            'id'                 => (int) $row->id,
            'user_id'            => (int) $row->user_id,
            'employee_name'      => $employeeName,
            'employee_initials'  => $this->initialsOf($employeeName),
            'employee_no'        => $row->emp_employee_no,
            'department_id'      => $row->department_id ? (int) $row->department_id : null,
            'department'         => $row->department_name,
            'cycle_id'           => $row->cycle_id ? (int) $row->cycle_id : null,
            'cycle'              => $row->cycle_name,
            'review_id'          => $row->review_id ? (int) $row->review_id : null,
            'appraisal_id'       => $row->appraisal_id ? (int) $row->appraisal_id : null,
            'currency'           => $row->currency,
            'current_ctc'        => $row->current_ctc !== null ? (float) $row->current_ctc : null,
            'proposed_ctc'       => $row->proposed_ctc !== null ? (float) $row->proposed_ctc : null,
            'increment_amount'   => $row->increment_amount !== null ? (float) $row->increment_amount : null,
            'increment_pct'      => $row->increment_pct !== null ? (float) $row->increment_pct : null,
            'revision_type'      => $row->revision_type,
            'revision_type_label'=> ucwords(str_replace('_', ' ', (string) $row->revision_type)),
            'effective_date'     => $row->effective_date,
            'effective_label'    => $this->dateLabel($row->effective_date),
            'status'             => $row->status,
            'status_label'       => $this->statusLabel($row->status),
            'approver_id'        => $row->approver_id ? (int) $row->approver_id : null,
            'approver'           => $approverName,
            'approved_label'     => $this->dateTimeLabel($row->approved_at),
            'remarks'            => $row->remarks,
            'created_at'         => $row->created_at,
        ];
    }

    private function presentModel(PerformanceCompensationRevision $revision): array
    {
        $row = $this->baseQuery((int) $revision->sub_institute_id, [])->where('cr.id', $revision->id)->first();

        return $this->present($row);
    }
}
