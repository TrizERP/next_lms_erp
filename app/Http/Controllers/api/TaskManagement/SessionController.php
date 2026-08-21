<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\SessionController`.
 *
 * Who am I, for the task module: identity, profile and department resolved
 * from the hydrated session so the frontend never trusts localStorage for
 * role gating. Backs the Permissions Matrix and Integration status screens.
 */
class SessionController extends Controller
{
    use ResolvesTaskManagementContext;

    public function show(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $user = DB::table('tbluser')
            ->leftJoin('tbluserprofilemaster as profile', 'profile.id', '=', 'tbluser.user_profile_id')
            ->leftJoin('hrms_departments as department', 'department.id', '=', 'tbluser.department_id')
            ->where('tbluser.id', $context['user_id'])
            ->selectRaw("tbluser.id,
                TRIM(CONCAT_WS(' ', tbluser.first_name, tbluser.middle_name, tbluser.last_name)) as name,
                profile.name as profile_name, tbluser.department_id, department.department as department_name")
            ->first();

        if (!$user) {
            return $this->taskManagementError('User not found.', 404);
        }

        return $this->taskManagementResponse([
            'user_id' => (string) $user->id,
            'name' => (string) $user->name,
            'profile' => $user->profile_name ?: null,
            'department_id' => $user->department_id ? (string) $user->department_id : null,
            'department' => $user->department_name ?: null,
            'sub_institute_id' => (string) $context['sub_institute_id'],
            'syear' => $context['syear'],
        ], 'Session retrieved successfully.');
    }

    /**
     * The permission matrix as the server actually enforces it.
     *
     * Since this port does not carry over hp_erp's TaskPermissionMiddleware,
     * this rendering documents the SAME rule the source enforced (Employee
     * profile denied privileged abilities) as reference metadata for the
     * Administration > Permissions screen; enforcement itself is left to
     * this app's existing menu-rights system (see the stage-1 menu/rights
     * migrations), not re-implemented here.
     */
    public function permissions(Request $request)
    {
        $context = $this->taskManagementContext($request);

        $profiles = DB::table('tbluserprofilemaster')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->pluck('name')
            ->unique()
            ->values();

        $abilities = [
            ['key' => 'task.create', 'label' => 'Create tasks', 'privileged' => false],
            ['key' => 'task.update', 'label' => 'Edit tasks', 'privileged' => false],
            ['key' => 'task.status', 'label' => 'Update task status', 'privileged' => false],
            ['key' => 'task.comment', 'label' => 'Comment on tasks', 'privileged' => false],
            ['key' => 'task.delete', 'label' => 'Archive / delete tasks', 'privileged' => true],
            ['key' => 'task.approve', 'label' => 'Approve / reject completed work', 'privileged' => true],
            ['key' => 'project.create', 'label' => 'Create projects', 'privileged' => true],
            ['key' => 'project.manage', 'label' => 'Manage projects & teams', 'privileged' => true],
            ['key' => 'workstream.manage', 'label' => 'Manage workstreams', 'privileged' => true],
            ['key' => 'dependency.manage', 'label' => 'Manage dependencies', 'privileged' => true],
            ['key' => 'milestone.manage', 'label' => 'Manage milestones', 'privileged' => true],
            ['key' => 'notification.manage', 'label' => 'Manage notifications', 'privileged' => true],
        ];

        $matrix = collect($abilities)->map(fn (array $ability) => [
            'key' => $ability['key'],
            'label' => $ability['label'],
            'roles' => $profiles->mapWithKeys(fn ($profile) => [
                $profile => !($ability['privileged'] && strcasecmp(trim((string) $profile), 'Employee') === 0),
            ]),
        ]);

        return $this->taskManagementResponse([
            'profiles' => $profiles->all(),
            'abilities' => $matrix->all(),
            'note' => 'Reference matrix; the Employee profile is denied privileged abilities.',
        ], 'Permission matrix.');
    }

    /**
     * Which task-module integrations are configured, without exposing any
     * secret. Configuration itself happens in .env, deliberately not from
     * the browser.
     */
    public function integrations(Request $request)
    {
        $this->taskManagementContext($request);

        $n8n = (string) config('services.n8n.task_webhook', '');

        return $this->taskManagementResponse([
            'integrations' => [
                [
                    'key' => 'n8n',
                    'name' => 'n8n task webhook',
                    'description' => 'Notifies an n8n workflow whenever a task is created through the API.',
                    'configured' => $n8n !== '',
                    'env' => 'N8N_TASK_WEBHOOK_URL',
                ],
                [
                    'key' => 'gemini',
                    'name' => 'Gemini AI task generation',
                    'description' => 'Drafts task titles and descriptions in the create-task form.',
                    'configured' => (string) config('gemini.api_key', env('GEMINI_API_KEY', '')) !== '',
                    'env' => 'GEMINI_API_KEY',
                ],
                [
                    'key' => 'fcm',
                    'name' => 'Push notifications (FCM)',
                    'description' => 'Delivers task notifications to mobile devices.',
                    'configured' => (string) env('FCM_SERVER_KEY', '') !== '',
                    'env' => 'FCM_SERVER_KEY',
                ],
            ],
        ], 'Integration status.');
    }

    /**
     * "Sign out of the task module". The source revoked a Sanctum token;
     * this project is session-based, so this clears the task-scoped keys
     * from the hydrated session instead.
     */
    public function destroy(Request $request)
    {
        session()->forget(['sub_institute_id', 'user_id', 'syear', 'department_id']);

        return $this->taskManagementResponse(null, 'Session ended.');
    }
}
