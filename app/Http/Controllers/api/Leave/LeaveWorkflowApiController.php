<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use App\Http\Controllers\Controller;
use App\Models\HrmsLeaveRolePermission;
use App\Models\HrmsLeaveWorkflowSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Approval Workflow and Roles & Access configuration.
 *
 * Both were UI-only in the Next.js Configuration screen with no backing table
 * anywhere in Laravel. Each institute gets a row seeded from the documented
 * defaults on first read, so the screen is never empty.
 */
class LeaveWorkflowApiController extends Controller
{
    use ResolvesLeaveContext;

    /**
     * GET /api/leave/workflow
     */
    public function workflow(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $settings = $this->resolveWorkflow($context);

        return response()->json([
            'status'  => 1,
            'message' => 'Approval workflow fetched successfully',
            'data'    => $this->transformWorkflow($settings),
        ]);
    }

    /**
     * PUT /api/leave/workflow
     */
    public function saveWorkflow(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'reporting_manager_enabled' => 'required|boolean',
            'department_head_enabled'   => 'required|boolean',
            'hr_enabled'                => 'required|boolean',
            'multi_level_enabled'       => 'required|boolean',
            'multi_level_count'         => 'required_if:multi_level_enabled,1,true|nullable|integer|min:2|max:4',
            'escalation_enabled'        => 'required|boolean',
            'escalation_time'           => 'required_if:escalation_enabled,1,true|nullable|integer|min:1|max:8760',
            'escalation_unit'           => 'required_if:escalation_enabled,1,true|nullable|in:hours,days',
            'escalate_to'               => 'required_if:escalation_enabled,1,true|nullable|in:department-head,hr,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!$request->boolean('reporting_manager_enabled')
            && !$request->boolean('department_head_enabled')
            && !$request->boolean('hr_enabled')) {
            return response()->json([
                'status'  => 0,
                'message' => 'At least one approval stage must be enabled',
            ], 422);
        }

        $settings = $this->resolveWorkflow($context);

        $settings->fill([
            'reporting_manager_enabled' => $request->boolean('reporting_manager_enabled'),
            'department_head_enabled'   => $request->boolean('department_head_enabled'),
            'hr_enabled'                => $request->boolean('hr_enabled'),
            'multi_level_enabled'       => $request->boolean('multi_level_enabled'),
            'multi_level_count'         => (int) ($request->input('multi_level_count') ?: 2),
            'escalation_enabled'        => $request->boolean('escalation_enabled'),
            'escalation_time'           => (int) ($request->input('escalation_time') ?: 24),
            'escalation_unit'           => $request->input('escalation_unit') ?: 'hours',
            'escalate_to'               => $request->input('escalate_to') ?: 'hr',
            'updated_by'                => $context['user_id'],
        ])->save();

        return response()->json([
            'status'  => 1,
            'message' => 'Approval workflow saved successfully',
            'data'    => $this->transformWorkflow($settings->fresh()),
        ]);
    }

    /**
     * GET /api/leave/roles
     */
    public function roles(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        return response()->json([
            'status'  => 1,
            'message' => 'Role permissions fetched successfully',
            'data'    => $this->resolveRoles($context)->map(fn ($role) => $this->transformRole($role))->values(),
        ]);
    }

    /**
     * PUT /api/leave/roles
     * Accepts the whole matrix in one call, matching how the tab saves.
     */
    public function saveRoles(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'roles'                      => 'required|array|min:1',
            'roles.*.id'                 => 'nullable|integer',
            'roles.*.role_name'          => 'required|string|max:100',
            'roles.*.scope'              => 'required|in:Self,Team,Department,Organization',
            'roles.*.approve_leave'      => 'required|boolean',
            'roles.*.view_reports'       => 'required|boolean',
            'roles.*.configure_settings' => 'required|boolean',
            'roles.*.bulk_operations'    => 'required|boolean',
            'roles.*.escalation_rights'  => 'required|boolean',
            'roles.*.user_management'    => 'required|boolean',
            'roles.*.status'             => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Ensure the institute always keeps at least one role that can configure settings,
        // otherwise nobody can ever reach this screen again.
        $hasConfigurer = collect($request->input('roles'))
            ->contains(fn ($role) => filter_var($role['configure_settings'] ?? false, FILTER_VALIDATE_BOOLEAN));

        if (!$hasConfigurer) {
            return response()->json([
                'status'  => 0,
                'message' => 'At least one role must retain the Configure Settings permission',
            ], 422);
        }

        $this->resolveRoles($context);

        DB::transaction(function () use ($request, $context) {
            foreach ($request->input('roles') as $index => $role) {
                HrmsLeaveRolePermission::updateOrCreate(
                    [
                        'sub_institute_id' => $context['sub_institute_id'],
                        'role_name'        => $role['role_name'],
                    ],
                    [
                        'scope'              => $role['scope'],
                        'approve_leave'      => filter_var($role['approve_leave'], FILTER_VALIDATE_BOOLEAN),
                        'view_reports'       => filter_var($role['view_reports'], FILTER_VALIDATE_BOOLEAN),
                        'configure_settings' => filter_var($role['configure_settings'], FILTER_VALIDATE_BOOLEAN),
                        'bulk_operations'    => filter_var($role['bulk_operations'], FILTER_VALIDATE_BOOLEAN),
                        'escalation_rights'  => filter_var($role['escalation_rights'], FILTER_VALIDATE_BOOLEAN),
                        'user_management'    => filter_var($role['user_management'], FILTER_VALIDATE_BOOLEAN),
                        'status'             => array_key_exists('status', $role)
                            ? filter_var($role['status'], FILTER_VALIDATE_BOOLEAN)
                            : true,
                        'sort_order'         => (int) ($role['sort_order'] ?? $index + 1),
                        'updated_by'         => $context['user_id'],
                    ]
                );
            }
        });

        return response()->json([
            'status'  => 1,
            'message' => 'Role permissions saved successfully',
            'data'    => $this->resolveRoles($context)->map(fn ($role) => $this->transformRole($role))->values(),
        ]);
    }

    private function resolveWorkflow(array $context): HrmsLeaveWorkflowSetting
    {
        return HrmsLeaveWorkflowSetting::firstOrCreate(
            ['sub_institute_id' => $context['sub_institute_id']],
            array_merge(HrmsLeaveWorkflowSetting::defaults(), ['created_by' => $context['user_id']])
        );
    }

    private function resolveRoles(array $context)
    {
        $roles = HrmsLeaveRolePermission::where('sub_institute_id', $context['sub_institute_id'])
            ->orderBy('sort_order')
            ->get();

        if ($roles->isNotEmpty()) {
            return $roles;
        }

        foreach (HrmsLeaveRolePermission::defaults() as $role) {
            HrmsLeaveRolePermission::create(array_merge($role, [
                'sub_institute_id' => $context['sub_institute_id'],
                'status'           => true,
                'created_by'       => $context['user_id'],
            ]));
        }

        return HrmsLeaveRolePermission::where('sub_institute_id', $context['sub_institute_id'])
            ->orderBy('sort_order')
            ->get();
    }

    private function transformWorkflow(HrmsLeaveWorkflowSetting $settings): array
    {
        return [
            'id'                        => (int) $settings->id,
            'reporting_manager_enabled' => (bool) $settings->reporting_manager_enabled,
            'department_head_enabled'   => (bool) $settings->department_head_enabled,
            'hr_enabled'                => (bool) $settings->hr_enabled,
            'multi_level_enabled'       => (bool) $settings->multi_level_enabled,
            'multi_level_count'         => (int) $settings->multi_level_count,
            'escalation_enabled'        => (bool) $settings->escalation_enabled,
            'escalation_time'           => (int) $settings->escalation_time,
            'escalation_unit'           => $settings->escalation_unit,
            'escalate_to'               => $settings->escalate_to,
        ];
    }

    private function transformRole(HrmsLeaveRolePermission $role): array
    {
        return [
            'id'                 => (int) $role->id,
            'role_name'          => $role->role_name,
            'scope'              => $role->scope,
            'approve_leave'      => (bool) $role->approve_leave,
            'view_reports'       => (bool) $role->view_reports,
            'configure_settings' => (bool) $role->configure_settings,
            'bulk_operations'    => (bool) $role->bulk_operations,
            'escalation_rights'  => (bool) $role->escalation_rights,
            'user_management'    => (bool) $role->user_management,
            'status'             => (bool) $role->status,
            'sort_order'         => (int) $role->sort_order,
        ];
    }
}
