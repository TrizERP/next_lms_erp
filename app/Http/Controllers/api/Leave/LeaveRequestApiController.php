<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use App\Http\Controllers\Controller;
use App\Services\Leave\LeaveAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeaveRequestApiController extends Controller
{
    use ResolvesLeaveContext;

    /** Statuses an approver may move a request into. */
    private const DECISION_STATUSES = ['approved', 'rejected', 'sent_back', 'cancelled', 'approved_lwp'];

    private const SORTABLE = [
        'employee_name' => 'employee_name',
        'department'    => 'hd.department',
        'leaveType'     => 'hlt.leave_type',
        'fromDate'      => 'hel.from_date',
        'toDate'        => 'hel.to_date',
        'status'        => 'hel.status',
        'submittedDate' => 'hel.created_at',
        'id'            => 'hel.id',
    ];

    public function __construct(private LeaveAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/leave/requests
     *
     * Server side search + filter + sort + pagination for the Leave Requests table.
     */
    public function index(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $perPage = min(max((int) $request->input('per_page', 10), 1), 200);
        $page    = max((int) $request->input('page', 1), 1);

        $query = $this->applyFilters(
            $this->analytics->requestsQuery($context['sub_institute_id'], $context['year']),
            $request
        );

        $total = (clone $query)->count('hel.id');

        $sortBy  = self::SORTABLE[$request->input('sort_by')] ?? 'hel.from_date';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = $query
            ->selectRaw("
                hel.*,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS employee_name,
                u.employee_no,
                u.email,
                u.mobile,
                u.image,
                u.department_id AS user_department_id,
                COALESCE(hd.department, '') AS department,
                COALESCE(hjt.title, '') AS designation,
                COALESCE(hlt.leave_type, '') AS leave_type,
                hlt.leave_type_id AS leave_type_code
            ")
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('hel.id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($row) => $this->transform($row));

        return response()->json([
            'status'     => 1,
            'message'    => 'Leave requests fetched successfully',
            'year'       => $context['year'],
            'data'       => $rows,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    /**
     * GET /api/leave/requests/{id}
     * Single request with balance snapshot, comments and timeline.
     */
    public function show(Request $request, $id)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $row = $this->analytics
            ->requestsQuery($context['sub_institute_id'], $context['year'])
            ->selectRaw("
                hel.*,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS employee_name,
                u.employee_no, u.email, u.mobile, u.image,
                u.department_id AS user_department_id,
                COALESCE(hd.department, '') AS department,
                COALESCE(hjt.title, '') AS designation,
                COALESCE(hlt.leave_type, '') AS leave_type,
                hlt.leave_type_id AS leave_type_code
            ")
            ->where('hel.id', $id)
            ->first();

        if (!$row) {
            return response()->json(['status' => 0, 'message' => 'Leave request not found'], 404);
        }

        $balances = $this->analytics->balancesForEmployee(
            $context['sub_institute_id'],
            $context['year'],
            (int) $row->user_id
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Leave request fetched successfully',
            'data'    => array_merge($this->transform($row), [
                'balances' => $balances['leave_types'],
                'comments' => $this->comments($row),
                'timeline' => $this->timeline($row),
            ]),
        ]);
    }

    /**
     * POST /api/leave/requests
     * Apply for leave. Mirrors ApplyLeaveController::store but with the validation
     * rules that were commented out there, and with status actually set.
     */
    public function store(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|integer|exists:hrms_leave_types,id',
            'day_type'      => 'required|in:full,half',
            'from_date'     => 'required|date',
            'to_date'       => 'required_if:day_type,full|nullable|date|after_or_equal:from_date',
            'slot'          => 'required_if:day_type,half|nullable|in:first_half,second_half',
            'comment'       => 'required|string|max:255',
            'employee_id'   => 'nullable|integer|exists:tbluser,id',
            'department_id' => 'nullable|integer',
        ], [
            'comment.required' => 'A reason is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userId = (int) ($request->input('employee_id') ?: $context['user_id']);

        if (!$userId) {
            return response()->json(['status' => 0, 'message' => 'Unable to resolve the employee for this request'], 422);
        }

        $isHalfDay = $request->input('day_type') === 'half';
        $fromDate  = $request->input('from_date');
        $toDate    = $isHalfDay ? $fromDate : ($request->input('to_date') ?: $fromDate);

        $departmentId = $request->input('department_id')
            ?: DB::table('tbluser')->where('id', $userId)->value('department_id');

        $payload = [
            'sub_institute_id' => $context['sub_institute_id'],
            'department_id'    => $departmentId,
            'user_id'          => $userId,
            'leave_type_id'    => (int) $request->input('leave_type_id'),
            'day_type'         => $isHalfDay ? '0.5' : '1',
            'slot'             => $isHalfDay ? $request->input('slot') : null,
            'from_date'        => $fromDate,
            'to_date'          => $toDate,
            'comment'          => $request->input('comment'),
            'status'           => 'pending',
        ];

        // Preserve the legacy upsert: re-applying for the same start date edits the
        // employee's existing pending row rather than creating a duplicate.
        $existing = DB::table('hrms_emp_leaves')
            ->where('user_id', $userId)
            ->where('from_date', $fromDate)
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $payload['updated_at'] = now();
            $payload['updated_by'] = $context['user_id'];
            DB::table('hrms_emp_leaves')->where('id', $existing->id)->update($payload);
            $leaveId = (int) $existing->id;
            $message = 'Leave request updated successfully';
        } else {
            $payload['created_at'] = now();
            $payload['created_by'] = $context['user_id'];
            $leaveId = (int) DB::table('hrms_emp_leaves')->insertGetId($payload);
            $message = 'Leave applied successfully';
        }

        return response()->json([
            'status'  => 1,
            'message' => $message,
            'data'    => ['id' => $leaveId],
        ], $existing ? 200 : 201);
    }

    /**
     * POST /api/leave/requests/{id}/decision
     */
    public function decision(Request $request, $id)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'status'      => 'required|in:' . implode(',', self::DECISION_STATUSES),
            'hod_comment' => 'nullable|string|max:50',
            'hr_remarks'  => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $exists = DB::table('hrms_emp_leaves')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            return response()->json(['status' => 0, 'message' => 'Leave request not found'], 404);
        }

        $updated = $this->applyDecision(
            [$id],
            $request->input('status'),
            [$id => $request->input('hod_comment')],
            [$id => $request->input('hr_remarks')],
            $context
        );

        return response()->json([
            'status'        => $updated ? 1 : 0,
            'message'       => $updated ? 'Leave request updated successfully' : 'No leave request updated',
            'updated_count' => $updated,
        ], $updated ? 200 : 400);
    }

    /**
     * POST /api/leave/requests/bulk-decision
     * Fixes the legacy leaveAuthorisationStore, which returned from inside its own
     * loop and therefore only ever updated the first selected record.
     */
    public function bulkDecision(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'ids'           => 'required|array|min:1',
            'ids.*'         => 'integer',
            'status'        => 'required|in:' . implode(',', self::DECISION_STATUSES),
            'hod_comment'   => 'nullable|array',
            'hr_remarks'    => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ids = DB::table('hrms_emp_leaves')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereIn('id', $request->input('ids'))
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return response()->json(['status' => 0, 'message' => 'No matching leave requests found'], 404);
        }

        $updated = $this->applyDecision(
            $ids,
            $request->input('status'),
            (array) $request->input('hod_comment', []),
            (array) $request->input('hr_remarks', []),
            $context
        );

        return response()->json([
            'status'        => $updated ? 1 : 0,
            'message'       => $updated
                ? $updated . ' leave request(s) updated successfully'
                : 'No leave request updated',
            'updated_count' => $updated,
        ], $updated ? 200 : 400);
    }

    /**
     * DELETE /api/leave/requests/{id}
     * Withdrawal by the applicant - soft delete, never a hard delete, so the
     * audit trail survives.
     */
    public function destroy(Request $request, $id)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $leave = DB::table('hrms_emp_leaves')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$leave) {
            return response()->json(['status' => 0, 'message' => 'Leave request not found'], 404);
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'status'  => 0,
                'message' => 'Only a pending leave request can be withdrawn',
            ], 422);
        }

        DB::table('hrms_emp_leaves')->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        return response()->json(['status' => 1, 'message' => 'Leave request withdrawn successfully']);
    }

    /** Shared write path for single and bulk decisions. */
    private function applyDecision(array $ids, string $status, array $hodComments, array $hrRemarks, array $context): int
    {
        // Resolve by id alone: an approver acting on an institute's requests is not
        // necessarily a member of it (the legacy leaveAuthorisationStore filtered on
        // sub_institute_id here and silently wrote a null approved_by whenever the two
        // differed). Fall back to a label rather than storing nothing.
        $approverName = DB::table('tbluser')
            ->selectRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) AS employee_name")
            ->where('id', $context['user_id'])
            ->value('employee_name');

        $approverName = $approverName ?: 'User #' . $context['user_id'];

        $updated = 0;

        DB::transaction(function () use ($ids, $status, $hodComments, $hrRemarks, $approverName, $context, &$updated) {
            foreach ($ids as $id) {
                $payload = [
                    'status'      => $status,
                    'approved_by' => $approverName,
                    'updated_at'  => now(),
                    'updated_by'  => $context['user_id'],
                ];

                if (array_key_exists($id, $hodComments) && $hodComments[$id] !== null && $hodComments[$id] !== '') {
                    $payload['hod_comment']      = $hodComments[$id];
                    $payload['hod_comment_date'] = now();
                }

                if (array_key_exists($id, $hrRemarks) && $hrRemarks[$id] !== null && $hrRemarks[$id] !== '') {
                    $payload['hr_remarks']     = $hrRemarks[$id];
                    $payload['hr_remark_date'] = now();
                }

                $updated += DB::table('hrms_emp_leaves')
                    ->where('id', $id)
                    ->where('sub_institute_id', $context['sub_institute_id'])
                    ->update($payload);
            }
        });

        return $updated;
    }

    private function applyFilters($query, Request $request)
    {
        $search       = trim((string) $request->input('search', ''));
        $statuses     = $this->filterList($request->input('status'));
        $departments  = $this->filterList($request->input('department_id'));
        $leaveTypes   = $this->filterList($request->input('leave_type_id'));
        $employees    = $this->filterList($request->input('employee_id'));
        $fromDate     = $this->activeFilter($request->input('from_date'));
        $toDate       = $this->activeFilter($request->input('to_date'));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->whereRaw("CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) LIKE ?", [$like])
                  ->orWhere('u.employee_no', 'like', $like)
                  ->orWhere('u.email', 'like', $like)
                  ->orWhere('hel.id', 'like', $like)
                  ->orWhere('hel.comment', 'like', $like);
            });
        }

        if (!empty($statuses)) {
            $query->whereIn('hel.status', $statuses);
        }

        if (!empty($departments)) {
            $query->whereIn('u.department_id', $departments);
        }

        if (!empty($leaveTypes)) {
            $query->whereIn('hel.leave_type_id', $leaveTypes);
        }

        if (!empty($employees)) {
            $query->whereIn('hel.user_id', $employees);
        }

        // Overlap, not containment: a leave spanning the window edge still belongs in it.
        if ($fromDate) {
            $query->where('hel.to_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('hel.from_date', '<=', $toDate);
        }

        return $query;
    }

    /** Row shape consumed by the Next.js LeaveRequest type. */
    private function transform($row): array
    {
        $days = $this->analytics->requestDays($row->from_date, $row->to_date, $row->day_type);

        return [
            'id'             => (int) $row->id,
            'employee_id'    => (int) $row->user_id,
            'employee_no'    => $row->employee_no,
            'employee_name'  => trim(preg_replace('/\s+/', ' ', (string) $row->employee_name)),
            'designation'    => $row->designation ?: null,
            'email'          => $row->email,
            'mobile'         => $row->mobile,
            'avatar'         => $row->image ?: null,
            'department_id'  => (int) ($row->user_department_id ?? $row->department_id ?? 0),
            'department'     => $row->department ?: 'Unassigned',
            'leave_type_id'  => (int) $row->leave_type_id,
            'leave_type'     => $row->leave_type ?: 'Unassigned',
            'leave_type_code'=> $row->leave_type_code ?? null,
            'day_type'       => $row->day_type,
            'slot'           => $row->slot,
            'session'        => ((string) $row->day_type === '0.5') ? 'Half Day' : 'Full Day',
            'days'           => $days,
            'duration'       => $this->analytics->durationLabel($days),
            'from_date'      => $row->from_date,
            'to_date'        => $row->to_date,
            'status'         => $row->status,
            'reason'         => $row->comment,
            'hod_comment'    => $row->hod_comment,
            'hod_comment_date' => $row->hod_comment_date,
            'hr_remarks'     => $row->hr_remarks,
            'hr_remark_date' => $row->hr_remark_date,
            'approver'       => $row->approved_by,
            'submitted_date' => $row->created_at,
            'updated_date'   => $row->updated_at,
        ];
    }

    private function comments($row): array
    {
        $comments = [];

        if ($row->comment) {
            $comments[] = [
                'author'    => trim((string) $row->employee_name),
                'role'      => 'Employee',
                'body'      => $row->comment,
                'timestamp' => $row->created_at,
            ];
        }

        if ($row->hod_comment) {
            $comments[] = [
                'author'    => $row->approved_by ?: 'Department Head',
                'role'      => 'Department Head',
                'body'      => $row->hod_comment,
                'timestamp' => $row->hod_comment_date,
            ];
        }

        if ($row->hr_remarks) {
            $comments[] = [
                'author'    => $row->approved_by ?: 'HR',
                'role'      => 'HR',
                'body'      => $row->hr_remarks,
                'timestamp' => $row->hr_remark_date,
            ];
        }

        return $comments;
    }

    private function timeline($row): array
    {
        $timeline = [[
            'stage'     => 'Request submitted',
            'status'    => 'completed',
            'actor'     => trim((string) $row->employee_name),
            'timestamp' => $row->created_at,
        ]];

        if ($row->hod_comment_date) {
            $timeline[] = [
                'stage'     => 'Department head reviewed',
                'status'    => 'completed',
                'actor'     => $row->approved_by,
                'timestamp' => $row->hod_comment_date,
            ];
        }

        if ($row->hr_remark_date) {
            $timeline[] = [
                'stage'     => 'HR reviewed',
                'status'    => 'completed',
                'actor'     => $row->approved_by,
                'timestamp' => $row->hr_remark_date,
            ];
        }

        if ($row->status !== 'pending') {
            $decidedAt = $row->hr_remark_date ?: ($row->hod_comment_date ?: $row->updated_at);

            $timeline[] = [
                'stage'     => 'Request ' . str_replace('_', ' ', $row->status),
                'status'    => $row->status,
                'actor'     => $row->approved_by,
                'timestamp' => $decidedAt ? Carbon::parse($decidedAt)->toDateTimeString() : null,
            ];
        }

        return $timeline;
    }
}
