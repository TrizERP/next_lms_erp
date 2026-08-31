<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\AuditLog;
use App\Models\TalentManagement\PerformanceAttachment;
use App\Models\TalentManagement\PerformanceNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceActivityController`.
 *
 * The five tabs below the table: Activity Feed, Comments, Notes, Attachments and
 * Audit Trail.
 *
 * Activity Feed and Audit Trail read the same s_performance_activity_log; they
 * differ in what they show - the feed is the narrative, the audit trail is the
 * subset of rows that carry a field-level `changes` payload (that IS the version
 * history). The "Module" label and the action verb are DERIVED from subject_type
 * and action rather than stored, so they can never drift from the data.
 */
class PerformanceActivityController extends Controller
{
    use ResolvesPerformanceContext;

    /** subject_type => the label the Audit Trail's Module column shows. */
    private const SUBJECTS = [
        'cycle'        => 'Review Cycles',
        'review'       => 'Employee Reviews',
        'goal'         => 'Goals',
        'appraisal'    => 'Appraisals',
        'compensation' => 'Compensation',
        'bonus'        => 'Bonus',
        'calibration'  => 'Calibration',
        'note'         => 'Notes & Comments',
        'attachment'   => 'Attachments',
        'saved_view'   => 'Saved Views',
    ];

    /**
     * GET /api/performance/activity
     *
     * tab = feed | audit. `review_id` scopes it to one employee's review, which
     * is what the screen's Activity Feed panel does when a row is selected.
     */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        ['page' => $page, 'per_page' => $perPage] = $this->performancePaging($request, 20);

        $tab = strtolower((string) ($request->input('tab') ?: 'feed'));

        $query = DB::table('s_performance_activity_log as l')
            ->where('l.sub_institute_id', $tenant);

        if ($reviewId = $this->activePerfFilter($request->input('review_id'))) {
            $query->where('l.review_id', $reviewId);
        }

        if ($cycleId = $this->activePerfFilter($request->input('cycle_id'))) {
            $query->where('l.cycle_id', $cycleId);
        }

        if ($subjectType = $this->activePerfFilter($request->input('subject_type'))) {
            $query->where('l.subject_type', $subjectType);
        }

        if ($actorId = $this->activePerfFilter($request->input('actor_id'))) {
            $query->where('l.user_id', $actorId);
        }

        // The Audit Trail tab is the rows that recorded a field-level diff.
        if ($tab === 'audit') {
            $query->whereNotNull('l.changes');
        }

        if ($search = $this->activePerfFilter($request->input('search'))) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('l.description', 'like', $like)
                    ->orWhere('l.subject_name', 'like', $like)
                    ->orWhere('l.actor_name', 'like', $like)
                    ->orWhere('l.action', 'like', $like);
            });
        }

        if ($from = $this->activePerfFilter($request->input('date_from'))) {
            $query->whereDate('l.created_at', '>=', $from);
        }

        if ($to = $this->activePerfFilter($request->input('date_to'))) {
            $query->whereDate('l.created_at', '<=', $to);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('l.created_at')
            ->orderByDesc('l.id')
            ->forPage($page, $perPage)
            ->get();

        return $this->performanceResponse(
            $rows->map(fn ($row) => $this->presentEntry($row))->all(),
            'Success',
            200,
            [
                'pagination' => [
                    'page'      => $page,
                    'per_page'  => $perPage,
                    'total'     => $total,
                    'last_page' => (int) max(1, ceil($total / $perPage)),
                ],
            ]
        );
    }

    /**
     * GET /api/performance/activity/filters
     * Actor and module options for the feed's own filters.
     */
    public function filters(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $actors = DB::table('s_performance_activity_log')
            ->where('sub_institute_id', $tenant)
            ->whereNotNull('user_id')
            ->select('user_id', 'actor_name')
            ->distinct()
            ->orderBy('actor_name')
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->user_id,
                'label' => $row->actor_name ?: ('User #' . $row->user_id),
            ])
            ->unique('value')
            ->values()
            ->all();

        // Only modules that actually have entries, each mapped to ONE label.
        $modules = DB::table('s_performance_activity_log')
            ->where('sub_institute_id', $tenant)
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn ($type) => [
                'value' => $type,
                'label' => self::SUBJECTS[$type] ?? ucwords(str_replace('_', ' ', (string) $type)),
            ])
            ->all();

        return $this->performanceResponse([
            'actors'  => $actors,
            'modules' => $modules,
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Comments and Notes (one table, discriminated by note_type)
     * ------------------------------------------------------------------ */

    /**
     * GET /api/performance/reviews/{reviewId}/notes
     * `note_type` = comment | note; omit it to get both.
     */
    public function notes(Request $request, $reviewId)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $noteType = $this->activePerfFilter($request->input('note_type'));

        $rows = DB::table('s_performance_notes as n')
            ->leftJoin('tbluser as author', 'author.id', '=', 'n.author_id')
            ->where('n.sub_institute_id', $tenant)
            ->whereNull('n.deleted_at')
            ->where('n.review_id', $reviewId)
            ->when($noteType, fn ($q) => $q->where('n.note_type', $noteType))
            ->orderByDesc('n.created_at')
            ->orderByDesc('n.id')
            ->get([
                'n.*',
                'author.first_name as author_first_name',
                'author.last_name as author_last_name',
                'author.user_name as author_user_name',
            ]);

        $counts = DB::table('s_performance_notes')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->where('review_id', $reviewId)
            ->select('note_type', DB::raw('COUNT(*) as total'))
            ->groupBy('note_type')
            ->pluck('total', 'note_type');

        return $this->performanceResponse(
            $rows->map(fn ($row) => $this->presentNote($row))->all(),
            'Success',
            200,
            [
                'counts' => [
                    'comment' => (int) ($counts['comment'] ?? 0),
                    'note'    => (int) ($counts['note'] ?? 0),
                ],
            ]
        );
    }

    /** POST /api/performance/reviews/{reviewId}/notes */
    public function storeNote(Request $request, $reviewId)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $review = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->find($reviewId);

        if (!$review) {
            return $this->performanceError('Review not found', 404);
        }

        $validated = $request->validate([
            'body'       => 'required|string',
            'note_type'  => 'nullable|in:comment,note',
            'visibility' => 'nullable|in:all,manager,hr,private',
        ]);

        $noteType = $validated['note_type'] ?? 'comment';

        $note = PerformanceNote::create([
            'sub_institute_id' => $tenant,
            'review_id'        => (int) $reviewId,
            'cycle_id'         => $review->cycle_id ? (int) $review->cycle_id : null,
            // The employee the note is ABOUT comes from the review, never from
            // the request's user_id (which is the actor).
            'user_id'          => (int) $review->user_id,
            'note_type'        => $noteType,
            'visibility'       => $validated['visibility'] ?? ($noteType === 'note' ? 'hr' : 'all'),
            'body'             => $validated['body'],
            'author_id'        => $context['user_id'],
            'author_name'      => $this->resolveActorName($context['user_id']),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]);

        $employeeName = $this->resolveActorName((int) $review->user_id) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            $noteType === 'note' ? 'added_note' : 'commented',
            ($noteType === 'note' ? 'added a note on ' : 'commented on ') . $employeeName . "'s review",
            'note',
            $note->id,
            $employeeName,
            null,
            (int) $reviewId,
            $review->cycle_id ? (int) $review->cycle_id : null
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => $noteType === 'note' ? 'note_created' : 'comment_created',
            'entity_type' => 'performance_note',
            'entity_id'   => $note->id,
            'new_values'  => $validated + ['review_id' => (int) $reviewId, 'note_type' => $noteType],
        ]);

        return $this->performanceResponse($this->presentNoteModel($note), ucfirst($noteType) . ' added', 201);
    }

    /** PUT /api/performance/notes/{id} */
    public function updateNote(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $note = PerformanceNote::where('sub_institute_id', $tenant)->find($id);

        if (!$note) {
            return $this->performanceError('Note not found', 404);
        }

        $validated = $request->validate([
            'body'       => 'sometimes|required|string',
            'visibility' => 'nullable|in:all,manager,hr,private',
        ]);

        $changes = $this->diffPerformanceChanges(
            $note->getOriginal(),
            $validated,
            ['body' => 'Body', 'visibility' => 'Visibility']
        );

        $note->fill($validated);
        $note->updated_by = $context['user_id'];
        $note->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => $note->note_type === 'note' ? 'note_updated' : 'comment_updated',
            'entity_type' => 'performance_note',
            'entity_id'   => $note->id,
            'new_values'  => $validated,
        ]);

        if (!empty($changes)) {
            $employeeName = $this->resolveActorName((int) $note->user_id) ?? 'an employee';

            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                $note->note_type === 'note' ? 'updated_note' : 'updated_comment',
                'edited a ' . $note->note_type . " on " . $employeeName . "'s review",
                'note',
                $note->id,
                $employeeName,
                $changes,
                (int) $note->review_id,
                $note->cycle_id ? (int) $note->cycle_id : null
            );
        }

        return $this->performanceResponse($this->presentNoteModel($note), 'Note updated');
    }

    /** DELETE /api/performance/notes/{id} */
    public function destroyNote(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $note = PerformanceNote::where('sub_institute_id', $tenant)->find($id);

        if (!$note) {
            return $this->performanceError('Note not found', 404);
        }

        $noteType = $note->note_type;
        $reviewId = $note->review_id;
        $cycleId = $note->cycle_id;
        $employeeName = $this->resolveActorName((int) $note->user_id) ?? 'an employee';

        $note->deleted_by = $context['user_id'];
        $note->save();
        $note->delete();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            $noteType === 'note' ? 'deleted_note' : 'deleted_comment',
            'deleted a ' . $noteType . " on " . $employeeName . "'s review",
            'note',
            (int) $id,
            $employeeName,
            null,
            (int) $reviewId,
            $cycleId ? (int) $cycleId : null
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => $noteType === 'note' ? 'note_deleted' : 'comment_deleted',
            'entity_type' => 'performance_note',
            'entity_id'   => (int) $id,
            'new_values'  => ['review_id' => $reviewId ? (int) $reviewId : null],
        ]);

        return $this->performanceResponse(['id' => (int) $id], ucfirst((string) $noteType) . ' deleted');
    }

    /* ------------------------------------------------------------------ *
     * Attachments
     * ------------------------------------------------------------------ */

    /** GET /api/performance/reviews/{reviewId}/attachments */
    public function attachments(Request $request, $reviewId)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $rows = DB::table('s_performance_attachments')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->where('review_id', $reviewId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->performanceResponse(
            $rows->map(fn ($row) => $this->presentAttachment($row))->all(),
            'Success',
            200,
            ['counts' => ['attachments' => $rows->count()]]
        );
    }

    /**
     * POST /api/performance/reviews/{reviewId}/attachments
     *
     * Accepts a real upload (`file`) or, when no file is sent, a reference to an
     * already-hosted document (`file_name` + `file_path`).
     */
    public function storeAttachment(Request $request, $reviewId)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $review = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->find($reviewId);

        if (!$review) {
            return $this->performanceError('Review not found', 404);
        }

        $validated = $request->validate([
            'file'          => 'nullable|file|max:20480',
            'title'         => 'nullable|string|max:191',
            'file_name'     => 'nullable|string|max:191',
            'file_path'     => 'nullable|string|max:500',
            'document_type' => 'nullable|in:goal_evidence,review_document,appraisal_letter,other',
        ]);

        $fileName = $validated['file_name'] ?? null;
        $filePath = $validated['file_path'] ?? null;
        $mimeType = null;
        $fileSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $fileSize = $file->getSize();

            $stored = 'performance_attachments/' . $tenant . '/' . uniqid('perf_', true) . '.' . $file->getClientOriginalExtension();

            // 'public' is the disk every other upload in this app writes to.
            Storage::disk('public')->putFileAs(dirname($stored), $file, basename($stored));

            $filePath = $stored;
        }

        if (!$fileName) {
            return $this->performanceError('Provide a file upload, or file_name + file_path', 422);
        }

        $attachment = PerformanceAttachment::create([
            'sub_institute_id' => $tenant,
            'review_id'        => (int) $reviewId,
            'cycle_id'         => $review->cycle_id ? (int) $review->cycle_id : null,
            // The document belongs to the employee under review, not the uploader.
            'user_id'          => (int) $review->user_id,
            'title'            => $validated['title'] ?? $fileName,
            'file_name'        => $fileName,
            'file_path'        => $filePath,
            'mime_type'        => $mimeType,
            'file_size'        => $fileSize,
            'document_type'    => $validated['document_type'] ?? 'other',
            'uploaded_by'      => $context['user_id'],
            'uploaded_by_name' => $this->resolveActorName($context['user_id']),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]);

        $employeeName = $this->resolveActorName((int) $review->user_id) ?? 'an employee';

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'uploaded_attachment',
            'attached "' . $fileName . '" to ' . $employeeName . "'s review",
            'attachment',
            $attachment->id,
            $fileName,
            null,
            (int) $reviewId,
            $review->cycle_id ? (int) $review->cycle_id : null
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'attachment_uploaded',
            'entity_type' => 'performance_attachment',
            'entity_id'   => $attachment->id,
            'new_values'  => [
                'review_id'     => (int) $reviewId,
                'file_name'     => $fileName,
                'document_type' => $validated['document_type'] ?? 'other',
            ],
        ]);

        $row = DB::table('s_performance_attachments')->where('id', $attachment->id)->first();

        return $this->performanceResponse($this->presentAttachment($row), 'Attachment uploaded', 201);
    }

    /** DELETE /api/performance/attachments/{id} */
    public function destroyAttachment(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $attachment = PerformanceAttachment::where('sub_institute_id', $tenant)->find($id);

        if (!$attachment) {
            return $this->performanceError('Attachment not found', 404);
        }

        $fileName = $attachment->file_name;
        $reviewId = $attachment->review_id;
        $cycleId = $attachment->cycle_id;

        $attachment->deleted_by = $context['user_id'];
        $attachment->save();
        $attachment->delete();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_attachment',
            'removed attachment "' . $fileName . '"',
            'attachment',
            (int) $id,
            $fileName,
            null,
            $reviewId ? (int) $reviewId : null,
            $cycleId ? (int) $cycleId : null
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'attachment_deleted',
            'entity_type' => 'performance_attachment',
            'entity_id'   => (int) $id,
            'new_values'  => ['file_name' => $fileName, 'review_id' => $reviewId ? (int) $reviewId : null],
        ]);

        return $this->performanceResponse(['id' => (int) $id], 'Attachment deleted');
    }

    /* ------------------------------------------------------------------ */

    private function presentEntry($row): array
    {
        $changes = $row->changes ? json_decode($row->changes, true) : null;

        return [
            'id'           => (int) $row->id,
            'actor_id'     => $row->user_id ? (int) $row->user_id : null,
            'actor_name'   => $row->actor_name ?: 'System',
            'actor_initials' => $this->initialsOf($row->actor_name ?: 'System'),
            'action'       => $row->action,
            'action_label' => ucfirst(str_replace('_', ' ', (string) $row->action)),
            'description'  => $row->description,
            'subject_type' => $row->subject_type,
            'module'       => self::SUBJECTS[$row->subject_type] ?? ucwords(str_replace('_', ' ', (string) $row->subject_type)),
            'subject_id'   => $row->subject_id ? (int) $row->subject_id : null,
            'subject_name' => $row->subject_name,
            'review_id'    => $row->review_id ? (int) $row->review_id : null,
            'cycle_id'     => $row->cycle_id ? (int) $row->cycle_id : null,
            'changes'      => is_array($changes) ? $changes : null,
            'has_changes'  => is_array($changes) && !empty($changes),
            'created_at'   => $row->created_at,
            'created_label'=> $this->dateTimeLabel($row->created_at),
            // Drives the feed's dot colour, the way the design groups entries.
            'entry_type'   => $this->entryType((string) $row->action),
        ];
    }

    /** Feed presentation class, derived from the action verb - never stored. */
    private function entryType(string $action): string
    {
        if (str_contains($action, 'reminder')) {
            return 'reminder';
        }

        if (str_contains($action, 'advanced') || str_contains($action, 'stage') || str_contains($action, 'launched')) {
            return 'status_change';
        }

        if (str_contains($action, 'approve') || str_contains($action, 'reject') || str_contains($action, 'calibrat')) {
            return 'approval';
        }

        if (str_contains($action, 'comment') || str_contains($action, 'note')) {
            return 'comment';
        }

        return 'system';
    }

    private function presentNote($row): array
    {
        $authorName = trim(($row->author_first_name ?? '') . ' ' . ($row->author_last_name ?? ''));
        $authorName = $authorName !== '' ? $authorName : ($row->author_user_name ?? $row->author_name);

        return [
            'id'              => (int) $row->id,
            'review_id'       => (int) $row->review_id,
            'note_type'       => $row->note_type,
            'visibility'      => $row->visibility,
            'body'            => $row->body,
            'author_id'       => $row->author_id ? (int) $row->author_id : null,
            'author_name'     => $authorName ?: 'System',
            'author_initials' => $this->initialsOf($authorName ?: 'System'),
            'created_at'      => $row->created_at,
            'created_label'   => $this->dateTimeLabel($row->created_at),
            'updated_label'   => $this->dateTimeLabel($row->updated_at),
        ];
    }

    private function presentNoteModel(PerformanceNote $note): array
    {
        $row = DB::table('s_performance_notes as n')
            ->leftJoin('tbluser as author', 'author.id', '=', 'n.author_id')
            ->where('n.id', $note->id)
            ->first([
                'n.*',
                'author.first_name as author_first_name',
                'author.last_name as author_last_name',
                'author.user_name as author_user_name',
            ]);

        return $this->presentNote($row);
    }

    private function presentAttachment($row): array
    {
        return [
            'id'                  => (int) $row->id,
            'review_id'           => (int) $row->review_id,
            'title'               => $row->title,
            'file_name'           => $row->file_name,
            'file_path'           => $row->file_path,
            'url'                 => $row->file_path ? Storage::disk('public')->url($row->file_path) : null,
            'mime_type'           => $row->mime_type,
            'file_size'           => $row->file_size ? (int) $row->file_size : null,
            'file_size_label'     => $this->fileSizeLabel($row->file_size),
            'document_type'       => $row->document_type,
            'document_type_label' => ucwords(str_replace('_', ' ', (string) $row->document_type)),
            'uploaded_by'         => $row->uploaded_by_name,
            'uploaded_by_initials'=> $row->uploaded_by_name ? $this->initialsOf($row->uploaded_by_name) : null,
            'created_at'          => $row->created_at,
            'created_label'       => $this->dateTimeLabel($row->created_at),
        ];
    }

    private function fileSizeLabel($bytes): ?string
    {
        if (!$bytes) {
            return null;
        }

        $bytes = (int) $bytes;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
