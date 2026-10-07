<?php

namespace App\Http\Controllers\api\Platform;

use App\Services\Platform\AuditTrail;
use App\Services\Platform\DashboardWidgets;
use App\Services\Rbac\PermissionService;
use App\Services\Platform\ReportCatalog;
use App\Services\Platform\ReportScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reporting engine (catalogue, CSV export, scheduled reports) and the dashboard
 * engine (widget catalogue with data, per-user layout).
 *
 * Tenant and identity come from the verified token, never from the body.
 */
class ReportsDashboardController extends PlatformController
{
    public function __construct(
        private ReportCatalog $reports,
        private ReportScheduler $scheduler,
        private DashboardWidgets $widgets,
        private PermissionService $permissions,
    ) {
    }

    // ── Reports ─────────────────────────────────────────────────────────────

    public function catalog(Request $request): JsonResponse
    {
        if ($this->tenantId($request) === null) {
            return $this->unauthenticated($request);
        }

        return $this->ok($this->reports->catalog());
    }

    /** GET /reports/{key}/export?format=csv&from=&to=&status=&module= */
    public function export(Request $request, string $key)
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        if (! $this->reports->has($key)) {
            return $this->fail('That report does not exist.', 404);
        }
        $filters = $request->only(['from', 'to', 'status', 'module']);
        $result = $this->reports->run($key, $tenant, $filters);
        AuditTrail::record($tenant, 'platform', 'report', 'exported', ['entity_type' => 'report', 'entity_id' => $key, 'after' => ['rows' => count($result['rows'])]], $request);

        return response($this->reports->toCsv($result), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $key).'.csv"',
        ]);
    }

    public function schedules(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $rows = DB::table('platform_report_schedules')->where('sub_institute_id', $tenant)->orderByDesc('id')->get()
            ->map(fn ($r) => $this->scheduleRow($r))->all();

        return $this->ok($rows);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $data = $request->validate([
            'report_key' => 'required|string|max:191',
            'name' => 'required|string|max:191',
            'frequency' => 'required|in:daily,weekly,monthly',
            'recipients' => 'required|array|min:1|max:20',
            'recipients.*' => 'email',
            'filters' => 'nullable|array',
        ]);
        if (! $this->reports->has($data['report_key'])) {
            return $this->fail('That report does not exist.', 422);
        }

        $id = DB::table('platform_report_schedules')->insertGetId([
            'sub_institute_id' => $tenant, 'report_key' => $data['report_key'], 'name' => $data['name'], 'frequency' => $data['frequency'],
            'filters' => json_encode($data['filters'] ?? []), 'recipients' => json_encode(array_values($data['recipients'])),
            'enabled' => 1, 'created_by' => $this->auth($request)['user_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditTrail::record($tenant, 'platform', 'report', 'schedule_created', ['entity_type' => 'report_schedule', 'entity_id' => $id, 'after' => ['report' => $data['report_key'], 'frequency' => $data['frequency']]], $request);

        return $this->ok($this->scheduleRow(DB::table('platform_report_schedules')->find($id)), [], 201);
    }

    public function toggleSchedule(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $enabled = (bool) $request->validate(['enabled' => 'required|boolean'])['enabled'];
        $n = DB::table('platform_report_schedules')->where('sub_institute_id', $tenant)->where('id', $id)->update(['enabled' => $enabled ? 1 : 0, 'updated_at' => now()]);

        return $n ? $this->ok(['enabled' => $enabled]) : $this->fail('That schedule was not found.', 404);
    }

    public function deleteSchedule(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $n = DB::table('platform_report_schedules')->where('sub_institute_id', $tenant)->where('id', $id)->delete();
        if ($n) {
            AuditTrail::record($tenant, 'platform', 'report', 'schedule_deleted', ['entity_type' => 'report_schedule', 'entity_id' => $id], $request);
        }

        return $n ? $this->ok(['deleted' => 1]) : $this->fail('That schedule was not found.', 404);
    }

    public function runSchedule(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $result = $this->scheduler->run($id, $tenant);

        return $result['status'] === 'ok' ? $this->ok($result) : $this->fail($result['message'], 422, ['data' => $result]);
    }

    private function scheduleRow(object $r): array
    {
        return [
            'id' => (int) $r->id, 'report_key' => $r->report_key, 'name' => $r->name, 'frequency' => $r->frequency,
            'recipients' => json_decode((string) $r->recipients, true), 'enabled' => (bool) $r->enabled,
            'last_run_at' => $r->last_run_at, 'last_run_status' => $r->last_run_status, 'last_run_message' => $r->last_run_message,
            'last_file_id' => $r->last_file_id !== null ? (int) $r->last_file_id : null, 'is_sample' => (bool) $r->is_sample,
        ];
    }

    // ── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $auth = $this->auth($request);
        $user = is_numeric($auth['user_id'] ?? null) ? (int) $auth['user_id'] : null;
        if ($tenant === null || $user === null) {
            return $this->unauthenticated($request);
        }
        $profile = $auth['user_profile_id'] ?? null;
        $may = fn (string $right) => $this->permissions->check($user, $profile, $tenant, $right, 'view');

        return $this->ok($this->widgets->forUser($tenant, $user, $may));
    }

    public function saveDashboardLayout(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $auth = $this->auth($request);
        $user = is_numeric($auth['user_id'] ?? null) ? (int) $auth['user_id'] : null;
        if ($tenant === null || $user === null) {
            return $this->unauthenticated($request);
        }
        $data = $request->validate(['layout' => 'required|array|max:50']);
        try {
            $this->widgets->saveLayout($tenant, $user, $data['layout']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok(['saved' => true]);
    }
}
