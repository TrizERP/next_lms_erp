<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ported from hp_erp's `App\Http\Controllers\Api\TaskManagement\TaskAttachmentVersionController`.
 *
 * Versioned attachments for a task. Each upload becomes a new numbered
 * version; the latest is mirrored onto the legacy task.TASK_ATTACHMENT
 * columns so the old screens keep showing the current file. Restore copies
 * an old version forward as a new one rather than rewriting history.
 */
class TaskAttachmentVersionController extends Controller
{
    use ResolvesTaskManagementContext;

    private const DISK = 'local';
    private const DIR = 'task-attachments';

    public function index(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $rows = DB::table('task_management_attachment_versions as v')
            ->leftJoin('tbluser as u', 'u.id', '=', 'v.uploaded_by')
            ->where('v.task_id', $id)
            ->where('v.sub_institute_id', $context['sub_institute_id'])
            ->orderByDesc('v.version')
            ->selectRaw("v.version, v.file_name, v.file_size, v.file_type, v.restored_from, v.created_at,
                TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) as uploaded_by_name")
            ->get()
            ->map(fn ($row) => [
                'version' => (int) $row->version,
                'file_name' => (string) $row->file_name,
                'file_size' => $row->file_size !== null ? (int) $row->file_size : null,
                'file_type' => $row->file_type,
                'uploaded_by' => $row->uploaded_by_name ?: null,
                'restored_from' => $row->restored_from !== null ? (int) $row->restored_from : null,
                'created_at' => $row->created_at,
            ]);

        return $this->taskManagementResponse(['versions' => $rows->all()], 'Attachment versions retrieved successfully.');
    }

    public function store(Request $request, int $id)
    {
        $context = $this->taskManagementContext($request);

        $request->validate(['file' => 'required|file|max:20480']);

        $task = DB::table('task')->where('ID', $id)->where('sub_institute_id', $context['sub_institute_id'])->whereNull('deleted_at')->first();
        if (!$task) {
            return $this->taskManagementError('Task not found.', 404);
        }

        $file = $request->file('file');
        $version = (int) DB::table('task_management_attachment_versions')->where('task_id', $id)->max('version') + 1;

        $path = $file->storeAs(
            self::DIR . "/{$id}",
            "v{$version}_" . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()),
            self::DISK
        );

        DB::table('task_management_attachment_versions')->insert([
            'sub_institute_id' => $context['sub_institute_id'],
            'task_id' => $id,
            'version' => $version,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getClientMimeType(),
            'uploaded_by' => $context['user_id'],
            'created_at' => now(),
        ]);

        $this->mirrorLatest($id, $file->getClientOriginalName(), (string) $file->getSize(), $file->getClientMimeType());

        return $this->taskManagementResponse(['version' => $version], 'Attachment uploaded.', 201);
    }

    public function download(Request $request, int $id, int $version)
    {
        $context = $this->taskManagementContext($request);

        $row = $this->findVersion($context, $id, $version);
        if (!$row) {
            return $this->taskManagementError('Attachment version not found.', 404);
        }

        if (!Storage::disk(self::DISK)->exists($row->file_path)) {
            return $this->taskManagementError('The stored file is missing from disk.', 410);
        }

        return Storage::disk(self::DISK)->download($row->file_path, $row->file_name);
    }

    public function restore(Request $request, int $id, int $version)
    {
        $context = $this->taskManagementContext($request);

        $source = $this->findVersion($context, $id, $version);
        if (!$source) {
            return $this->taskManagementError('Attachment version not found.', 404);
        }

        $next = (int) DB::table('task_management_attachment_versions')->where('task_id', $id)->max('version') + 1;

        DB::table('task_management_attachment_versions')->insert([
            'sub_institute_id' => $context['sub_institute_id'],
            'task_id' => $id,
            'version' => $next,
            'file_name' => $source->file_name,
            'file_path' => $source->file_path,
            'file_size' => $source->file_size,
            'file_type' => $source->file_type,
            'uploaded_by' => $context['user_id'],
            'restored_from' => $version,
            'created_at' => now(),
        ]);

        $this->mirrorLatest($id, $source->file_name, (string) $source->file_size, (string) $source->file_type);

        return $this->taskManagementResponse(['version' => $next], "Version {$version} restored as version {$next}.", 201);
    }

    private function findVersion(array $context, int $taskId, int $version): ?object
    {
        return DB::table('task_management_attachment_versions')
            ->where('task_id', $taskId)
            ->where('version', $version)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->first();
    }

    /** The legacy columns always describe the newest version. */
    private function mirrorLatest(int $taskId, string $name, ?string $size, ?string $type): void
    {
        DB::table('task')->where('ID', $taskId)->update([
            'TASK_ATTACHMENT' => $name,
            'FILE_SIZE' => $size,
            'FILE_TYPE' => $type,
            'updated_at' => now(),
        ]);
    }
}
