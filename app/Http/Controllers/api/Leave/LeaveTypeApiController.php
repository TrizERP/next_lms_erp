<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Leave type configuration.
 *
 * The annual quota does not live on hrms_leave_types - it lives in
 * hrms_leave_allocation, keyed by (department, employee, leave_type, year).
 * This controller surfaces the department level allocation for the current
 * leave year as "annual_quota" and writes it back to that table, so the two
 * screens (Configuration and Leave Allocation) stay consistent.
 */
class LeaveTypeApiController extends Controller
{
    use ResolvesLeaveContext;

    /**
     * GET /api/leave/leave-types
     */
    public function index(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $leaveTypes = DB::table('hrms_leave_types')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        $allocations = DB::table('hrms_leave_allocation as hla')
            ->leftJoin('hrms_departments as hd', 'hd.id', '=', 'hla.department_id')
            ->selectRaw('hla.leave_type_id, hla.department_id, hla.value, COALESCE(hd.department, "") AS department')
            ->where('hla.sub_institute_id', $context['sub_institute_id'])
            ->where('hla.year', $context['year'])
            ->whereNull('hla.deleted_at')
            ->where(function ($q) {
                $q->whereNull('hla.employee_id')->orWhere('hla.employee_id', 0);
            })
            ->get()
            ->groupBy('leave_type_id');

        $data = $leaveTypes->map(function ($type) use ($allocations) {
            $rows = $allocations->get($type->id, collect());
            $values = $rows->pluck('value')->map(fn ($value) => (float) $value)->unique()->values();

            return [
                'id'                  => (int) $type->id,
                'leave_type_id'       => $type->leave_type_id,
                'leave_type'          => $type->leave_type,
                'sort_order'          => (int) ($type->sort_order ?? 0),
                'carry_forward'       => (bool) ($type->carry_forward ?? false),
                'status'              => (int) ($type->status ?? 0),
                // Null when departments disagree - the UI then shows the per-department breakdown.
                'annual_quota'        => $values->count() === 1 ? $values->first() : null,
                'annual_quota_varies' => $values->count() > 1,
                'allocations'         => $rows->map(fn ($row) => [
                    'department_id' => (int) $row->department_id,
                    'department'    => $row->department,
                    'value'         => (float) $row->value,
                ])->values(),
            ];
        });

        return response()->json([
            'status'  => 1,
            'message' => 'Leave types fetched successfully',
            'year'    => $context['year'],
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/leave/leave-types      (create)
     * PUT  /api/leave/leave-types/{id} (update)
     */
    public function store(Request $request, $id = null)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $id = $id ?: $request->input('id');

        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'leave_type'    => 'required|string|max:191',
            'sort_order'    => 'nullable|integer|min:0',
            'status'        => 'nullable|boolean',
            'carry_forward' => 'nullable|boolean',
            'annual_quota'  => 'nullable|numeric|min:0|max:365',
            'department_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $name = trim($request->input('leave_type'));

        $duplicate = DB::table('hrms_leave_types')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('leave_type', $name)
            ->whereNull('deleted_at')
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status'  => 0,
                'message' => 'A leave type with this name already exists',
                'errors'  => ['leave_type' => ['A leave type with this name already exists']],
            ], 422);
        }

        $payload = [
            'leave_type'       => $name,
            'sort_order'       => (int) $request->input('sort_order', 0),
            'status'           => $request->boolean('status', true) ? 1 : 0,
            'carry_forward'    => $request->boolean('carry_forward') ? 1 : 0,
            'sub_institute_id' => $context['sub_institute_id'],
            'deleted_at'       => null,
        ];

        if ($id && DB::table('hrms_leave_types')->where('id', $id)->where('sub_institute_id', $context['sub_institute_id'])->exists()) {
            $payload['updated_at'] = now();
            $payload['updated_by'] = $context['user_id'];
            DB::table('hrms_leave_types')->where('id', $id)->update($payload);
            $leaveTypeId = (int) $id;
            $message = 'Leave type updated successfully';
            $code = 200;
        } else {
            $payload['leave_type_id'] = $request->input('leave_type_id') ?: $this->nextLeaveTypeCode($context['sub_institute_id']);
            $payload['created_at']    = now();
            $payload['created_by']    = $context['user_id'];
            $leaveTypeId = (int) DB::table('hrms_leave_types')->insertGetId($payload);
            $message = 'Leave type added successfully';
            $code = 201;
        }

        if ($request->filled('annual_quota')) {
            $this->syncAllocation(
                $context,
                $leaveTypeId,
                (float) $request->input('annual_quota'),
                $this->activeFilter($request->input('department_id'))
            );
        }

        return response()->json([
            'status'  => 1,
            'message' => $message,
            'data'    => ['id' => $leaveTypeId],
        ], $code);
    }

    /**
     * PATCH /api/leave/leave-types/{id}/status
     * Enable / disable without touching any other field.
     */
    public function toggleStatus(Request $request, $id)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $leaveType = DB::table('hrms_leave_types')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$leaveType) {
            return response()->json(['status' => 0, 'message' => 'Leave type not found'], 404);
        }

        $next = $request->has('status') ? ($request->boolean('status') ? 1 : 0) : ((int) $leaveType->status === 1 ? 0 : 1);

        DB::table('hrms_leave_types')->where('id', $id)->update([
            'status'     => $next,
            'updated_at' => now(),
            'updated_by' => $context['user_id'],
        ]);

        return response()->json([
            'status'  => 1,
            'message' => $next ? 'Leave type enabled' : 'Leave type disabled',
            'data'    => ['id' => (int) $id, 'status' => $next],
        ]);
    }

    /**
     * DELETE /api/leave/leave-types/{id}
     */
    public function destroy(Request $request, $id)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $leaveType = DB::table('hrms_leave_types')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$leaveType) {
            return response()->json(['status' => 0, 'message' => 'Leave type not found'], 404);
        }

        $inUse = DB::table('hrms_emp_leaves')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('leave_type_id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($inUse && !$request->boolean('force')) {
            return response()->json([
                'status'  => 0,
                'message' => 'This leave type is used by existing leave requests. Disable it instead, or resend with force=1.',
            ], 409);
        }

        DB::table('hrms_leave_types')->where('id', $id)->update([
            'deleted_at' => now(),
            'deleted_by' => $context['user_id'],
        ]);

        return response()->json(['status' => 1, 'message' => 'Leave type deleted successfully']);
    }

    /** Next LTY sequence for the institute. */
    private function nextLeaveTypeCode(int $subInstituteId): string
    {
        $last = DB::table('hrms_leave_types')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('leave_type_id')
            ->orderByDesc('id')
            ->value('leave_type_id');

        $next = $last && preg_match('/(\d+)$/', $last, $matches) ? ((int) $matches[1]) + 1 : 1;

        return 'LTY' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Write the annual quota into hrms_leave_allocation for the leave year.
     * With no department_id it applies to every active department.
     */
    private function syncAllocation(array $context, int $leaveTypeId, float $value, ?string $departmentId): void
    {
        $departmentIds = $departmentId
            ? [(int) $departmentId]
            : DB::table('hrms_departments')
                ->where('sub_institute_id', $context['sub_institute_id'])
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all();

        foreach ($departmentIds as $department) {
            $existing = DB::table('hrms_leave_allocation')
                ->where('sub_institute_id', $context['sub_institute_id'])
                ->where('department_id', $department)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $context['year'])
                ->where(function ($q) {
                    $q->whereNull('employee_id')->orWhere('employee_id', 0);
                })
                ->first();

            if ($existing) {
                DB::table('hrms_leave_allocation')->where('id', $existing->id)->update([
                    'value'      => $value,
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'updated_by' => $context['user_id'],
                ]);
            } else {
                DB::table('hrms_leave_allocation')->insert([
                    'department_id'    => $department,
                    'employee_id'      => null,
                    'leave_type_id'    => $leaveTypeId,
                    'year'             => $context['year'],
                    'value'            => $value,
                    'sub_institute_id' => $context['sub_institute_id'],
                    'created_at'       => now(),
                    'created_by'       => $context['user_id'],
                ]);
            }
        }
    }
}
