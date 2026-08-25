<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\TaskManagement\ActivityController;
use App\Http\Controllers\api\TaskManagement\AuditLogController;
use App\Http\Controllers\api\TaskManagement\BulkTaskController;
use App\Http\Controllers\api\TaskManagement\CapacityController;
use App\Http\Controllers\api\TaskManagement\DeadlineExtensionController;
use App\Http\Controllers\api\TaskManagement\DependencyController;
use App\Http\Controllers\api\TaskManagement\GlobalSearchController;
use App\Http\Controllers\api\TaskManagement\IdempotentTaskController;
use App\Http\Controllers\api\TaskManagement\JobRoleTaskLibraryController;
use App\Http\Controllers\api\TaskManagement\JobroleTaskCompetencyMapController;
use App\Http\Controllers\api\TaskManagement\LegacyTaskController;
use App\Http\Controllers\api\TaskManagement\MyTasksController;
use App\Http\Controllers\api\TaskManagement\NotificationController;
use App\Http\Controllers\api\TaskManagement\ProjectController;
use App\Http\Controllers\api\TaskManagement\ReportController;
use App\Http\Controllers\api\TaskManagement\SessionController;
use App\Http\Controllers\api\TaskManagement\TaskAttachmentVersionController;
use App\Http\Controllers\api\TaskManagement\TaskListController;
use App\Http\Controllers\api\TaskManagement\TaskOptionController;
use App\Http\Controllers\api\TaskManagement\TaskRecurrenceController;
use App\Http\Controllers\api\TaskManagement\TaskScheduleController;
use App\Http\Controllers\api\TaskManagement\TaskSubtaskController;
use App\Http\Controllers\api\TaskManagement\TaskTemplateController;
use App\Http\Controllers\api\TaskManagement\TaskTimeTrackingController;
use App\Http\Controllers\api\TaskManagement\UserSkillController;
use App\Http\Controllers\api\TaskManagement\VersionedLegacyTaskController;
use App\Http\Controllers\api\TaskManagement\WorkspaceController;

/*
|--------------------------------------------------------------------------
| Task Management
|--------------------------------------------------------------------------
|
| Registered stateless (mapApiRoutes-style: prefix('api')->middleware('api'))
| in RouteServiceProvider::mapTaskManagementRoutes(). Every group below
| additionally requires `api.session`, matching the established convention
| for this generation of Next.js-facing modules (see routes/talent_management.php).
|
| An as-is migration of G2G's (hp_erp) Task Management module. URLs, HTTP
| methods and path params are kept identical to the source's
| routes/api.php `task-management` prefix group (lines ~918-1014) plus the
| standalone legacy `/task`, `/deadline-extension` and `/tasks/counts|
| daily|weekly|monthly` routes, so the ported frontend needs no URL changes
| beyond the shared `/api` base.
|
| Source middleware NOT ported 1:1:
|   - `task.sanitize` (input sanitisation) - no equivalent middleware alias
|     exists in this target; request validation is enforced per-endpoint by
|     each controller instead, as this target's other ported modules do.
|   - `task.permission:<ability>` (per-ability authorization) - this target
|     has no task-module permission gate yet. Routes are grouped under
|     `api.session` only, matching every other ported module here
|     (TalentManagement, OrganizationManagement); adding a `task.permission`
|     middleware is a follow-up, not part of this as-is port.
|
| Deliberately NOT added here (see stage-2 report for detail):
|   - `/tasks/counts`, `/tasks/daily`, `/tasks/weekly`, `/tasks/monthly` -
|     the source wires these to `App\Http\Controllers\Api\TaskController`
|     (`getTaskCounts`/`getDailyTasks`/`getWeeklyTasks`/`getMonthlyTasks`),
|     a legacy front_desk-style controller distinct from every controller in
|     this port's assigned list and not present anywhere in this target.
|     Faking them onto `TaskListController::index` (a generic paginated
|     list) would silently change their response shape and business logic,
|     so they are left unported rather than wired to the wrong behaviour.
|   - `/user-rejected-tasks-courses` - the source wires this to
|     `SkillMatchingController::getCoursesForUserRejectedTasksSkills`, part
|     of the unrelated Skill Matching module, not Task Management, and not
|     in this port's controller list. Not present anywhere in this target.
|   - `/get-employee-tasks`, `/jobrole-task-description` - `/get-employee-tasks`
|     already exists in the source as `AJAXController::getUsersMappings`, an
|     unrelated general-purpose endpoint outside Task Management; grepped
|     this target's route files and it is not present here either, but
|     adding it under TaskManagement would misattribute it to this module.
|     `/jobrole-task-description` does not exist anywhere in the source
|     repository (`routes/api.php`) at all - nothing to port.
|
*/
Route::middleware(['api.session', 'staff.only'])->group(function () {

    // ---------------------------------------------------------------
    // Legacy task routes (outside the task-management prefix, matching
    // the source's routes/api.php layout)
    // ---------------------------------------------------------------
    Route::post('deadline-extension', [DeadlineExtensionController::class, 'store']);

    Route::post('bulk-task/import', [BulkTaskController::class, 'import']);

    Route::get('user-skills/{userId}', [UserSkillController::class, 'index'])->whereNumber('userId');

    // Create Task modal's "Also save to the Job Role Task library" checkbox.
    // Matches the source's `/api/competency/library/jobrole-tasks` URL
    // (see `my-tasks-api.ts`'s `saveJobRoleTaskToLibrary`), which
    // `taskApiPost` reaches through the shared `/api` base.
    Route::post('competency/library/jobrole-tasks', [JobRoleTaskLibraryController::class, 'store']);

    // Create Task modal's "What this task builds" section
    // (`TaskCompetencyInlinePanel`). Matches the source's
    // `/api/competency/task-map/for-task` and `/api/competency/task-map`
    // URLs (see `_lib/competency-api.ts`), same top-level-under-api.session
    // placement as `competency/library/jobrole-tasks` above.
    Route::get('competency/task-map/for-task', [JobroleTaskCompetencyMapController::class, 'forTask']);
    Route::post('competency/task-map', [JobroleTaskCompetencyMapController::class, 'store']);

    // ---------------------------------------------------------------
    // Task Management module
    // ---------------------------------------------------------------
    Route::prefix('task-management')->group(function () {
        Route::get('session', [SessionController::class, 'show']);
        Route::get('permissions', [SessionController::class, 'permissions']);

        Route::post('bulk-tasks/import', [BulkTaskController::class, 'import']);

        Route::get('statuses', [TaskOptionController::class, 'statuses']);
        Route::post('statuses', [TaskOptionController::class, 'storeStatus']);
        Route::put('statuses/{id}', [TaskOptionController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('statuses/{id}', [TaskOptionController::class, 'destroyStatus'])->whereNumber('id');

        Route::get('priorities', [TaskOptionController::class, 'priorities']);
        Route::post('priorities', [TaskOptionController::class, 'storePriority']);
        Route::put('priorities/{id}', [TaskOptionController::class, 'updatePriority'])->whereNumber('id');
        Route::delete('priorities/{id}', [TaskOptionController::class, 'destroyPriority'])->whereNumber('id');

        Route::get('integrations', [SessionController::class, 'integrations']);
        Route::delete('session', [SessionController::class, 'destroy']);

        Route::post('assignment-capacity', [CapacityController::class, 'check']);

        Route::post('legacy-tasks', [LegacyTaskController::class, 'store']);
        Route::post('legacy-tasks/idempotent', [IdempotentTaskController::class, 'store']);
        Route::put('legacy-tasks/{id}', [LegacyTaskController::class, 'update'])->whereNumber('id');
        Route::put('legacy-tasks/{id}/versioned', [VersionedLegacyTaskController::class, 'update'])->whereNumber('id');
        Route::delete('legacy-tasks/{id}', [LegacyTaskController::class, 'destroy'])->whereNumber('id');

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');

        Route::get('tasks', [TaskListController::class, 'index']);
        Route::get('search', [GlobalSearchController::class, 'index']);

        Route::get('templates', [TaskTemplateController::class, 'index']);
        Route::post('templates', [TaskTemplateController::class, 'store']);
        Route::delete('templates/{id}', [TaskTemplateController::class, 'destroy'])->whereNumber('id');

        Route::get('reports/productivity', [ReportController::class, 'productivity']);
        Route::get('reports/delays', [ReportController::class, 'delays']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::get('audit-logs/export', [AuditLogController::class, 'export']);

        Route::get('workspace', [WorkspaceController::class, 'index']);
        Route::get('workspace/workload', [WorkspaceController::class, 'workload']);
        Route::get('workspace/{id}', [WorkspaceController::class, 'show'])->whereNumber('id');
        Route::get('workspace/{id}/activity', [ActivityController::class, 'index'])->whereNumber('id');
        Route::put('workspace/{id}', [WorkspaceController::class, 'update'])->whereNumber('id');
        Route::delete('workspace/{id}', [WorkspaceController::class, 'destroy'])->whereNumber('id');
        Route::patch('workspace/{id}/approval', [WorkspaceController::class, 'approve'])->whereNumber('id');
        Route::post('workspace/{id}/comments', [WorkspaceController::class, 'comment'])->whereNumber('id');

        Route::get('workspace/{id}/time-entries', [TaskTimeTrackingController::class, 'index'])->whereNumber('id');
        Route::post('workspace/{id}/time-entries/start', [TaskTimeTrackingController::class, 'start'])->whereNumber('id');
        Route::post('workspace/{id}/time-entries/stop', [TaskTimeTrackingController::class, 'stop'])->whereNumber('id');

        Route::get('workspace/{id}/subtasks', [TaskSubtaskController::class, 'index'])->whereNumber('id');
        Route::post('workspace/{id}/subtasks', [TaskSubtaskController::class, 'store'])->whereNumber('id');
        Route::patch('workspace/{id}/subtasks/{subtask}', [TaskSubtaskController::class, 'toggle'])->whereNumber(['id', 'subtask']);
        Route::delete('workspace/{id}/subtasks/{subtask}', [TaskSubtaskController::class, 'destroy'])->whereNumber(['id', 'subtask']);

        Route::get('workspace/{id}/recurrence', [TaskRecurrenceController::class, 'show'])->whereNumber('id');
        Route::put('workspace/{id}/recurrence', [TaskRecurrenceController::class, 'upsert'])->whereNumber('id');
        Route::delete('workspace/{id}/recurrence', [TaskRecurrenceController::class, 'destroy'])->whereNumber('id');

        Route::get('workspace/{id}/schedule', [TaskScheduleController::class, 'show'])->whereNumber('id');
        Route::put('workspace/{id}/schedule', [TaskScheduleController::class, 'update'])->whereNumber('id');

        Route::get('workspace/{id}/attachments', [TaskAttachmentVersionController::class, 'index'])->whereNumber('id');
        Route::post('workspace/{id}/attachments', [TaskAttachmentVersionController::class, 'store'])->whereNumber('id');
        Route::get('workspace/{id}/attachments/{version}', [TaskAttachmentVersionController::class, 'download'])->whereNumber(['id', 'version']);
        Route::post('workspace/{id}/attachments/{version}/restore', [TaskAttachmentVersionController::class, 'restore'])->whereNumber(['id', 'version']);

        Route::get('deadline-extensions', [DeadlineExtensionController::class, 'index']);
        Route::post('deadline-extensions', [DeadlineExtensionController::class, 'store']);
        Route::patch('deadline-extensions/{id}/decision', [DeadlineExtensionController::class, 'decide'])->whereNumber('id');

        Route::get('dependencies', [DependencyController::class, 'index']);
        Route::post('dependencies', [DependencyController::class, 'store']);
        Route::put('dependencies/{id}', [DependencyController::class, 'update'])->whereNumber('id');
        Route::delete('dependencies/{id}', [DependencyController::class, 'destroy'])->whereNumber('id');

        Route::post('milestones', [DependencyController::class, 'storeMilestone']);
        Route::put('milestones/{id}', [DependencyController::class, 'updateMilestone'])->whereNumber('id');
        Route::delete('milestones/{id}', [DependencyController::class, 'destroyMilestone'])->whereNumber('id');

        Route::get('my-tasks', [MyTasksController::class, 'index']);
        Route::get('my-tasks/{id}', [MyTasksController::class, 'show'])->whereNumber('id');
        Route::patch('my-tasks/{id}/status', [MyTasksController::class, 'updateStatus'])->whereNumber('id');

        Route::get('projects/options', [ProjectController::class, 'options']);
        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::get('projects/{id}', [ProjectController::class, 'show'])->whereNumber('id');
        Route::put('projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
        Route::patch('projects/{id}/archive', [ProjectController::class, 'archive'])->whereNumber('id');
        Route::put('projects/{id}/members', [ProjectController::class, 'syncProjectMembers'])->whereNumber('id');
        Route::put('projects/{id}/tasks', [ProjectController::class, 'syncTasks'])->whereNumber('id');
        Route::post('projects/{id}/tasks', [ProjectController::class, 'attachTask'])->whereNumber('id');
        Route::post('projects/{id}/workstreams', [ProjectController::class, 'storeWorkstream'])->whereNumber('id');
        Route::put('projects/{projectId}/workstreams/{workstreamId}', [ProjectController::class, 'updateWorkstream'])->whereNumber('projectId')->whereNumber('workstreamId');
        Route::delete('projects/{projectId}/workstreams/{workstreamId}', [ProjectController::class, 'destroyWorkstream'])->whereNumber('projectId')->whereNumber('workstreamId');
    });
});
