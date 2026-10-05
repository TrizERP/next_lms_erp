<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Documents\DocumentMaster;
use App\Models\Documents\DocumentHistory;
use App\Http\Resources\Documents\DocumentResource;
use App\Services\Documents\DocumentStorageService;
use App\Services\Documents\DocumentAuditService;
use App\Services\Documents\Search\DocumentSearchService;
use App\Services\Documents\Search\NaturalLanguageSearchParser;
use App\Services\Documents\Browse\DocumentBrowseService;
use App\Jobs\ProcessDocumentPipelineJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentController extends Controller
{
    protected DocumentStorageService $storage;
    protected DocumentSearchService $searchService;
    protected NaturalLanguageSearchParser $searchParser;
    protected DocumentBrowseService $browseService;

    public function __construct(
        DocumentStorageService $storage,
        DocumentSearchService $searchService,
        NaturalLanguageSearchParser $searchParser,
        DocumentBrowseService $browseService
    ) {
        $this->storage = $storage;
        $this->searchService = $searchService;
        $this->searchParser = $searchParser;
        $this->browseService = $browseService;
    }

    /**
     * Resolve authenticated user & tenant context from session/token
     */
    protected function getContext(Request $request): array
    {
        $userId = $request->header('X-User-Id') ?: $request->input('user_id') ?: session()->get('user_id') ?: 1;
        $subInstituteId = $request->header('X-Sub-Institute-Id') ?: $request->input('sub_institute_id') ?: session()->get('sub_institute_id') ?: 1;

        $user = DB::table('tbluser as u')
            ->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->select('u.id', 'u.first_name', 'u.last_name', 'u.user_profile_id', 'u.department_id', 'u.sub_institute_id', 'u.is_admin', 'p.name as profile_name')
            ->where('u.id', $userId)
            ->first();

        return [
            'user' => $user ?: (object)['id' => (int)$userId, 'is_admin' => 0, 'user_profile_id' => 0, 'department_id' => null, 'sub_institute_id' => (int)$subInstituteId],
            'sub_institute_id' => (int)$subInstituteId,
        ];
    }

    /**
     * GET /api/v1/documents - List & search documents
     */
    public function index(Request $request)
    {
        $ctx = $this->getContext($request);
        $paginator = $this->searchService->search($request->all(), $ctx['user'], $ctx['sub_institute_id']);

        return response()->json([
            'status' => 1,
            'data' => DocumentResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/documents - Upload document (returns immediately with document_id and pending status)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:' . config('idms.max_upload_size_kb', 51200),
            'visibility' => 'nullable|in:private,department,organization',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $ctx = $this->getContext($request);
        $file = $request->file('file');

        // Store file in DigitalOcean Spaces
        $stored = $this->storage->storeUpload($file);

        $title = pathinfo($stored['original_file_name'], PATHINFO_FILENAME);

        DB::beginTransaction();
        try {
            $document = DocumentMaster::create([
                'sub_institute_id' => $ctx['sub_institute_id'],
                'title' => $title,
                'original_file_name' => $stored['original_file_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'checksum_sha256' => $stored['checksum_sha256'],
                'storage_path' => $stored['storage_path'],
                'current_version' => 1,
                'owner_id' => $ctx['user']->id,
                'visibility' => $request->input('visibility', 'organization'),
                'processing_status' => 'pending',
                'created_by' => $ctx['user']->id,
            ]);

            $document->recomputeViewPrincipals();
            $document->save();

            // Record version and audit in document_history
            DocumentAuditService::logVersion(
                $document,
                1,
                $stored['storage_path'],
                $stored['checksum_sha256'],
                $stored['size'],
                'Initial upload',
                $ctx['user']->id
            );

            DocumentAuditService::log($document, 'upload', $ctx['user']->id, [
                'original_file_name' => $stored['original_file_name'],
                'size' => $stored['size'],
            ]);

            DB::commit();

            // Dispatch pipeline job (runs synchronously if queue is sync, or queued in database/redis)
            ProcessDocumentPipelineJob::dispatch($document->id, $ctx['user']->id);

            return response()->json([
                'status' => 1,
                'message' => 'Document uploaded and processing pipeline started.',
                'data' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'processing_status' => $document->processing_status,
                ],
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => 'Failed to store document: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/documents/{id} - View document details
     */
    public function show($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found or access denied.'], 404);
        }

        DocumentAuditService::log($document, 'view', $ctx['user']->id);

        return response()->json([
            'status' => 1,
            'data' => new DocumentResource($document),
        ]);
    }

    /**
     * PATCH /api/v1/documents/{id} - Edit metadata and permissions
     */
    public function update($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found or access denied.'], 404);
        }

        // Policy check
        if (!app(\App\Policies\DocumentPolicy::class)->update($ctx['user'], $document)) {
            return response()->json(['status' => 0, 'message' => 'Unauthorized to edit this document.'], 403);
        }

        $fields = $request->only([
            'title', 'document_type', 'category', 'department_id',
            'subject', 'document_date', 'academic_year', 'organization',
            'project', 'lifecycle_status', 'summary', 'visibility', 'permissions'
        ]);

        $before = $document->only(array_keys($fields));

        DB::beginTransaction();
        try {
            $document->fill($fields);

            if ($request->has('visibility') || $request->has('permissions') || $request->has('department_id')) {
                $document->recomputeViewPrincipals();
            }

            $document->save();

            DocumentAuditService::log($document, 'edit_metadata', $ctx['user']->id, [
                'before' => $before,
                'after' => $fields,
            ]);

            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Document updated successfully.',
                'data' => new DocumentResource($document),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/documents/{id}/confirm - Confirm review and approve publication
     */
    public function confirm($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        // Apply any reviewed edits sent along
        if ($request->has('title')) $document->title = $request->input('title');
        if ($request->has('document_type')) $document->document_type = $request->input('document_type');
        if ($request->has('department_id')) $document->department_id = $request->input('department_id');
        if ($request->has('academic_year')) $document->academic_year = $request->input('academic_year');
        if ($request->has('summary')) $document->summary = $request->input('summary');
        if ($request->has('tags') && is_array($request->input('tags'))) {
            $document->syncTags($request->input('tags'));
        }

        $document->processing_status = 'done';
        $document->recomputeViewPrincipals();
        $document->save();

        DocumentAuditService::log($document, 'review_confirmed', $ctx['user']->id);

        return response()->json([
            'status' => 1,
            'message' => 'Document confirmed and published to search.',
            'data' => new DocumentResource($document),
        ]);
    }

    /**
     * POST /api/v1/documents/{id}/tags - Add or edit tags
     */
    public function updateTags($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        $tags = $request->input('tags', []);
        DB::beginTransaction();
        try {
            $document->syncTags($tags);
            $document->save();

            DocumentAuditService::log($document, 'tags_updated', $ctx['user']->id, ['tags' => $tags]);
            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Tags updated successfully.',
                'tags' => $document->tags,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/documents/{id}/preview - Generate signed temporary URL for preview
     */
    public function preview($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        // Preview target: preview_path if generated, otherwise storage_path
        $targetPath = $document->preview_path ?: $document->storage_path;
        $url = $this->storage->getTemporaryUrl($targetPath, 20);

        DocumentAuditService::log($document, 'preview', $ctx['user']->id);

        return response()->json([
            'status' => 1,
            'preview_url' => $url,
            'mime_type' => $document->mime_type,
        ]);
    }

    /**
     * GET /api/v1/documents/{id}/download - Download file securely
     */
    public function download($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        if (!app(\App\Policies\DocumentPolicy::class)->download($ctx['user'], $document)) {
            return response()->json(['status' => 0, 'message' => 'Unauthorized to download this document.'], 403);
        }

        DocumentAuditService::log($document, 'download', $ctx['user']->id);

        $url = $this->storage->getTemporaryUrl($document->storage_path, 15);
        return response()->json([
            'status' => 1,
            'download_url' => $url,
            'file_name' => $document->original_file_name,
        ]);
    }

    /**
     * POST /api/v1/documents/{id}/versions - Upload a new version
     */
    public function addVersion($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:' . config('idms.max_upload_size_kb', 51200),
            'change_note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        $stored = $this->storage->storeUpload($request->file('file'));
        $newVersionNumber = $document->current_version + 1;

        DB::beginTransaction();
        try {
            $document->current_version = $newVersionNumber;
            $document->storage_path = $stored['storage_path'];
            $document->checksum_sha256 = $stored['checksum_sha256'];
            $document->size = $stored['size'];
            $document->mime_type = $stored['mime_type'];
            $document->original_file_name = $stored['original_file_name'];
            $document->processing_status = 'pending';
            $document->save();

            DocumentAuditService::logVersion(
                $document,
                $newVersionNumber,
                $stored['storage_path'],
                $stored['checksum_sha256'],
                $stored['size'],
                $request->input('change_note', 'Version ' . $newVersionNumber),
                $ctx['user']->id
            );

            DB::commit();

            ProcessDocumentPipelineJob::dispatch($document->id, $ctx['user']->id);

            return response()->json([
                'status' => 1,
                'message' => 'Version uploaded successfully.',
                'current_version' => $newVersionNumber,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/documents/{id}/versions - List all versions
     */
    public function getVersions($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        $versions = DocumentHistory::where('document_id', $document->id)
            ->where('entry_type', 'version')
            ->orderBy('version_number', 'desc')
            ->get();

        return response()->json([
            'status' => 1,
            'versions' => $versions,
        ]);
    }

    /**
     * POST /api/v1/documents/{id}/versions/{versionNumber}/restore - Restore a version
     */
    public function restoreVersion($id, $versionNumber, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        $targetVersion = DocumentHistory::where('document_id', $document->id)
            ->where('entry_type', 'version')
            ->where('version_number', $versionNumber)
            ->first();

        if (!$targetVersion) {
            return response()->json(['status' => 0, 'message' => 'Version not found.'], 404);
        }

        $restoredVersionNumber = $document->current_version + 1;

        DB::beginTransaction();
        try {
            $document->current_version = $restoredVersionNumber;
            $document->storage_path = $targetVersion->storage_path;
            $document->checksum_sha256 = $targetVersion->checksum_sha256;
            $document->size = $targetVersion->size;
            $document->save();

            DocumentAuditService::logVersion(
                $document,
                $restoredVersionNumber,
                $targetVersion->storage_path,
                $targetVersion->checksum_sha256,
                $targetVersion->size,
                "Restored from version {$versionNumber}",
                $ctx['user']->id
            );

            DocumentAuditService::log($document, 'version_restored', $ctx['user']->id, [
                'restored_from' => $versionNumber,
                'new_version' => $restoredVersionNumber,
            ]);

            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => "Version {$versionNumber} restored as Version {$restoredVersionNumber}.",
                'current_version' => $restoredVersionNumber,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/documents/{id}/related - Find related documents by shared tags & subject
     */
    public function related($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        $query = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])
            ->where('id', '!=', $document->id)
            ->where('processing_status', 'done');

        if (!empty($document->tag_names)) {
            $jsonTags = json_encode($document->tag_names);
            $query->whereRaw('JSON_OVERLAPS(tag_names, ?)', [$jsonTags]);
        } elseif ($document->department_id) {
            $query->where('department_id', $document->department_id);
        }

        $related = $query->limit(6)->get();

        return response()->json([
            'status' => 1,
            'related' => DocumentResource::collection($related),
        ]);
    }

    /**
     * DELETE /api/v1/documents/{id} - Soft delete document
     */
    public function destroy($id, Request $request)
    {
        $ctx = $this->getContext($request);
        $document = DocumentMaster::visibleTo($ctx['user'], $ctx['sub_institute_id'])->find($id);

        if (!$document) {
            return response()->json(['status' => 0, 'message' => 'Document not found.'], 404);
        }

        if (!app(\App\Policies\DocumentPolicy::class)->delete($ctx['user'], $document)) {
            return response()->json(['status' => 0, 'message' => 'Unauthorized to delete this document.'], 403);
        }

        DocumentAuditService::log($document, 'delete', $ctx['user']->id);
        $document->delete();

        return response()->json([
            'status' => 1,
            'message' => 'Document moved to trash.',
        ]);
    }

    /**
     * POST /api/v1/search/parse - Natural Language Query parser
     */
    public function parseSearch(Request $request)
    {
        $ctx = $this->getContext($request);
        $q = (string) $request->input('query', '');

        $result = $this->searchParser->parse($q, $ctx['sub_institute_id']);

        return response()->json([
            'status' => 1,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/v1/browse/tree - Virtual directory tree
     */
    public function tree(Request $request)
    {
        $ctx = $this->getContext($request);
        $tree = $this->browseService->getTree($ctx['user'], $ctx['sub_institute_id']);

        return response()->json([
            'status' => 1,
            'data' => $tree,
        ]);
    }

    /**
     * GET /api/v1/tags - Tag cloud
     */
    public function tags(Request $request)
    {
        $ctx = $this->getContext($request);
        $tags = $this->browseService->getTagCloud($ctx['user'], $ctx['sub_institute_id']);

        return response()->json([
            'status' => 1,
            'data' => $tags,
        ]);
    }

    /**
     * GET /api/v1/audit - Global or document audit trail
     */
    public function audit(Request $request)
    {
        $ctx = $this->getContext($request);

        $query = DocumentHistory::with(['document', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->has('document_id')) {
            $query->where('document_id', $request->input('document_id'));
        }
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        $logs = $query->paginate(30);

        return response()->json([
            'status' => 1,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
