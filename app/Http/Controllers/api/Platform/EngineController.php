<?php

namespace App\Http\Controllers\api\Platform;

use App\Models\DocumentTemplate;
use App\Models\Platform\PlatformScheduledTask;
use App\Services\Platform\AuditTrail;
use App\Services\Platform\NotificationSender;
use App\Services\Platform\SchedulerDispatcher;
use App\Services\Platform\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Runs the platform engines: approval runs, the notification delivery log, the
 * scheduler's run-now, template PDF output and file attachments.
 *
 * Tenancy and identity come from the verified token (PlatformController::auth),
 * never from the body. Writes are gated per service in routes/platform/engines.php.
 */
class EngineController extends PlatformController
{
    private const FILE_MAX_BYTES = 10 * 1024 * 1024;
    private const FILE_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];

    public function __construct(
        private WorkflowEngine $workflows,
        private NotificationSender $sender,
        private SchedulerDispatcher $dispatcher,
    ) {
    }

    // ── Approval runs ───────────────────────────────────────────────────────

    public function runs(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $user = $this->userId($request);

        $q = DB::table('platform_workflow_runs')->where('sub_institute_id', $tenant);
        if ($request->query('status')) {
            $q->where('status', (string) $request->query('status'));
        }
        if ($request->query('flow_key')) {
            $q->where('flow_key', (string) $request->query('flow_key'));
        }
        $scope = (string) $request->query('scope', 'all');
        if ($scope === 'mine' && $user) {
            $q->where('requested_by', $user);
        } elseif ($scope === 'pending') {
            $q->where('status', 'pending');
        }

        $ids = $q->orderByDesc('id')->limit(100)->pluck('id');
        $rows = $ids->map(fn ($id) => $this->workflows->find($tenant, (int) $id))->filter()->values();

        return $this->ok($rows->all());
    }

    public function run(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $run = $this->workflows->find($tenant, $id);

        return $run ? $this->ok($run) : $this->fail('That approval request was not found.', 404);
    }

    public function startRun(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $data = $request->validate([
            'flow_key' => 'required|string|max:191',
            'entity_type' => 'required|string|max:128',
            'entity_id' => 'required|string|max:128',
            'title' => 'nullable|string|max:255',
            'payload' => 'nullable|array',
        ]);

        try {
            $run = $this->workflows->start(
                $tenant, $data['flow_key'], $data['entity_type'], (string) $data['entity_id'],
                $this->userId($request), $this->userName($request), $data['title'] ?? null, $data['payload'] ?? []
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        AuditTrail::record($tenant, 'platform', 'workflow', 'run_started', [
            'entity_type' => $data['entity_type'], 'entity_id' => $data['entity_id'], 'after' => ['flow_key' => $data['flow_key']],
        ], $request);

        return $this->ok($run, [], 201);
    }

    public function actOnRun(Request $request, int $id, string $action): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $user = $this->userId($request);
        if ($tenant === null || $user === null) {
            return $this->unauthenticated($request);
        }
        $data = $request->validate(['comment' => 'nullable|string|max:2000', 'delegate_to' => 'nullable|integer']);

        try {
            $run = $this->workflows->act($tenant, $id, $action, $user, $this->userName($request), $data['comment'] ?? null, $data['delegate_to'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        AuditTrail::record($tenant, 'platform', 'workflow', 'run_'.$action, [
            'entity_type' => 'workflow_run', 'entity_id' => $id, 'after' => ['status' => $run['status'] ?? null],
        ], $request);

        return $this->ok($run);
    }

    // ── Notification delivery log ───────────────────────────────────────────

    public function notificationLog(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $q = DB::table('platform_notification_log')->where('sub_institute_id', $tenant);
        foreach (['status', 'channel', 'event_key'] as $f) {
            if ($request->query($f)) {
                $q->where($f, (string) $request->query($f));
            }
        }
        $page = $q->orderByDesc('id')->paginate(max(1, min(100, (int) $request->query('per_page', 25))));

        return $this->ok($page->items(), ['meta' => [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $data = $request->validate([
            'event_key' => 'required|string|max:191', 'recipient' => 'required|string|max:191',
            'subject' => 'required|string|max:255', 'body' => 'required|string|max:5000',
        ]);
        $rows = $this->sender->dispatch($tenant, $data['event_key'], [$data['recipient']], $data['subject'], $data['body']);
        AuditTrail::record($tenant, 'platform', 'notification', 'test_send', ['after' => ['event_key' => $data['event_key'], 'rows' => count($rows)]], $request);

        return $this->ok($rows, [], 201);
    }

    // ── Scheduler run-now ───────────────────────────────────────────────────

    public function runTaskNow(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $key = (string) $request->validate(['task_key' => 'required|string|max:191'])['task_key'];
        $definition = config('platform_services.tasks.'.$key);
        if (! is_array($definition)) {
            return $this->fail('That task is not in the platform catalogue.', 404);
        }

        // A task nobody has customised has no row yet; create it from the shipped
        // default so running it now and saving its schedule later are one record.
        $task = PlatformScheduledTask::forTenant($tenant)->where('task_key', $key)->first()
            ?? PlatformScheduledTask::create([
                'sub_institute_id' => $tenant, 'task_key' => $key,
                'module' => explode('.', $key)[0], 'component' => implode('.', array_slice(explode('.', $key), 0, 2)),
                'minute' => (string) ($definition['schedule']['minute'] ?? '0'), 'hour' => (string) ($definition['schedule']['hour'] ?? '0'),
                'day' => (string) ($definition['schedule']['day'] ?? '*'), 'month' => (string) ($definition['schedule']['month'] ?? '*'),
                'day_of_week' => (string) ($definition['schedule']['day_of_week'] ?? '*'), 'disabled' => false, 'fail_delay' => 0,
                'updated_by' => 'run-now',
            ]);
        $result = $this->dispatcher->run($task);
        $ok = $result['status'] === 'ok';
        AuditTrail::record($tenant, (string) $task->module, (string) $task->component, 'task_run_now', [
            'entity_type' => 'scheduled_task', 'entity_id' => $task->task_key, 'after' => $result,
        ], $request);

        return $ok ? $this->ok($result) : $this->fail($result['message'], 422, ['data' => $result]);
    }

    // ── Template PDF ────────────────────────────────────────────────────────

    /** POST /templates/{id}/pdf  body: values {token: text} — merge fields already resolved by the caller. */
    public function templatePdf(Request $request, int $id)
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $template = DocumentTemplate::query()->where('sub_institute_id', $tenant)->find($id);
        if (! $template) {
            return $this->fail('That template was not found.', 404);
        }

        $values = (array) $request->input('values', []);
        $html = (string) $template->content;
        $html = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($values) {
            return e((string) ($values[$m[1]] ?? ''));
        }, $html);

        $pdf = app('dompdf.wrapper')->loadHTML('<html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>')
            ->setPaper($request->input('paper', 'a4'), $request->input('orientation', 'portrait'));

        AuditTrail::record($tenant, 'platform', 'template', 'pdf_rendered', ['entity_type' => 'document_template', 'entity_id' => $id], $request);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $template->name).'.pdf"',
        ]);
    }

    // ── File attachments ────────────────────────────────────────────────────

    public function files(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $request->validate(['entity_type' => 'required|string|max:128', 'entity_id' => 'required|string|max:128']);

        $rows = DB::table('platform_file_attachments')
            ->where('sub_institute_id', $tenant)->where('entity_type', $request->query('entity_type'))
            ->where('entity_id', (string) $request->query('entity_id'))->whereNull('deleted_at')
            ->orderBy('original_name')->orderByDesc('version')->get();

        return $this->ok($rows->map(fn ($r) => $this->fileRow($r))->all());
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $request->validate([
            'entity_type' => 'required|string|max:128',
            'entity_id' => 'required|string|max:128',
            'file' => 'required|file|max:'.(self::FILE_MAX_BYTES / 1024),
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::FILE_EXT, true)) {
            return $this->fail('That file type is not allowed. Use: '.implode(', ', self::FILE_EXT).'.', 422);
        }

        $name = mb_substr($file->getClientOriginalName(), 0, 255);
        $type = (string) $request->input('entity_type');
        $entity = (string) $request->input('entity_id');
        $previous = DB::table('platform_file_attachments')
            ->where('sub_institute_id', $tenant)->where('entity_type', $type)->where('entity_id', $entity)
            ->where('original_name', $name)->whereNull('deleted_at')->orderByDesc('version')->first();

        $path = $file->storeAs("platform-files/{$tenant}", bin2hex(random_bytes(12)).'.'.$ext, 'local');
        $id = DB::table('platform_file_attachments')->insertGetId([
            'sub_institute_id' => $tenant, 'entity_type' => $type, 'entity_id' => $entity,
            'original_name' => $name, 'mime' => $file->getClientMimeType(), 'size' => $file->getSize(),
            'storage_path' => $path, 'version' => $previous ? $previous->version + 1 : 1,
            'uploaded_by' => $this->userId($request), 'uploaded_by_name' => $this->userName($request),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($previous) {
            DB::table('platform_file_attachments')->where('id', $previous->id)->update(['replaced_by' => $id]);
        }

        AuditTrail::record($tenant, 'platform', 'document', 'file_attached', [
            'entity_type' => $type, 'entity_id' => $entity, 'after' => ['name' => $name, 'version' => $previous ? $previous->version + 1 : 1],
        ], $request);

        return $this->ok($this->fileRow(DB::table('platform_file_attachments')->find($id)), [], 201);
    }

    public function downloadFile(Request $request, int $id)
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $row = DB::table('platform_file_attachments')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->find($id);
        if (! $row || ! Storage::disk('local')->exists($row->storage_path)) {
            return $this->fail('That file was not found.', 404);
        }

        return Storage::disk('local')->download($row->storage_path, $row->original_name);
    }

    public function deleteFile(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenantId($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $row = DB::table('platform_file_attachments')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->find($id);
        if (! $row) {
            return $this->fail('That file was not found.', 404);
        }
        // Soft delete: the bytes and the row stay, so history and audit still resolve.
        DB::table('platform_file_attachments')->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        AuditTrail::record($tenant, 'platform', 'document', 'file_removed', ['entity_type' => $row->entity_type, 'entity_id' => $row->entity_id, 'before' => ['name' => $row->original_name]], $request);

        return $this->ok(['deleted' => 1]);
    }

    private function fileRow(object $r): array
    {
        return [
            'id' => (int) $r->id, 'entity_type' => $r->entity_type, 'entity_id' => $r->entity_id,
            'name' => $r->original_name, 'mime' => $r->mime, 'size' => (int) $r->size, 'version' => (int) $r->version,
            'uploaded_by_name' => $r->uploaded_by_name, 'created_at' => $r->created_at, 'is_sample' => (bool) $r->is_sample,
        ];
    }

    private function userId(Request $request): ?int
    {
        $u = $this->auth($request)['user_id'] ?? null;

        return is_numeric($u) ? (int) $u : null;
    }

    private function userName(Request $request): ?string
    {
        $id = $this->userId($request);

        return $id ? (DB::table('tbluser')->where('id', $id)->selectRaw("CONCAT_WS(' ', first_name, last_name) as n")->value('n') ?: null) : null;
    }
}
