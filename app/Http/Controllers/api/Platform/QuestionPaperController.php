<?php

namespace App\Http\Controllers\api\Platform;

use App\Models\Documents\DocumentMaster;
use App\Services\Documents\DocumentAuditService;
use App\Services\Documents\DocumentStorageService;
use App\Services\Platform\PlatformRegistry;
use App\Services\QuestionPapers\PdfFileCheck;
use App\Services\QuestionPapers\QuestionPaperJobService;
use App\Services\QuestionPapers\QuestionPaperMetadataResolver;
use App\Services\QuestionPapers\QuestionPaperReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Question Paper uploads and their processing status.
 *
 * Identity and school come ONLY from the verified token (PlatformController); nothing the caller
 * sends can name another user or institute. A job is visible to its uploader and to administrators
 * of the same school; every lookup is scoped by the token's institute first, so another school's
 * job is a 404, not a 403 that would confirm it exists.
 *
 * Upload returns as soon as the file is stored and the job row exists. The extraction itself runs
 * on the queue worker (QuestionPaperExtractionJob).
 */
class QuestionPaperController extends PlatformController
{
    public function __construct(
        PlatformRegistry $registry,
        private QuestionPaperJobService $jobs,
        private QuestionPaperMetadataResolver $resolver,
        private QuestionPaperReviewService $review,
        private \App\Services\QuestionPapers\QuestionPaperRemovalService $removal,
        private DocumentStorageService $storage,
    ) {
        parent::__construct($registry);
    }

    public function store(Request $request): JsonResponse
    {
        [$tenant, $userId] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:' . (int) config('idms.max_upload_size_kb', 51200),
            'standard_id' => 'nullable|integer|min:1',
            'subject_id' => 'nullable|integer|min:1',
            'chapter_id' => 'nullable|integer|min:1',
            'academic_year' => 'nullable|string|max:20',
            'scheduled_at' => ['nullable', 'date_format:Y-m-d\TH:i:s.v\Z'],
        ]);
        $validationFailed = $validator->fails();
        if (!$validationFailed && $request->filled('scheduled_at')) {
            $requestedSchedule = Carbon::createFromFormat('Y-m-d\TH:i:s.v\Z', $request->input('scheduled_at'), 'UTC');
            if (!$requestedSchedule || !$requestedSchedule->isAfter(now())) {
                $validator->errors()->add('scheduled_at', 'The scheduled extraction time must be in the future.');
                $validationFailed = true;
            }
        }
        if ($validationFailed) {
            return $this->fail($validator->errors()->first(), 422);
        }
        if ($request->filled('scheduled_at') && !Schema::hasColumn('question_paper_jobs', 'scheduled_at')) {
            return $this->fail(
                'Scheduled extraction is not enabled yet. Apply the scheduled question paper migration, then try again.',
                503
            );
        }
        $scheduledAt = $request->filled('scheduled_at')
            ? Carbon::createFromFormat('Y-m-d\TH:i:s.v\Z', $request->input('scheduled_at'), 'UTC')->utc()
            : null;

        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            return $this->fail('Question papers must be PDF files.', 422);
        }
        // The content decides, not the name or the browser's MIME type.
        if ($problem = PdfFileCheck::problem($file->getRealPath())) {
            return $this->fail($problem, 422);
        }

        $chosen = $this->resolver->validateChosen(
            $tenant,
            $request->integer('standard_id') ?: null,
            $request->integer('subject_id') ?: null,
            $request->integer('chapter_id') ?: null
        );
        // A choice that does not belong to this school is an error, not something to drop silently.
        foreach (['standard_id', 'subject_id', 'chapter_id'] as $key) {
            if ($request->filled($key) && $chosen[$key] === null) {
                return $this->fail("The selected {$key} does not belong to your school (or does not match the other selections).", 422);
            }
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $duplicate = DocumentMaster::withTrashed()
            ->where('sub_institute_id', $tenant)
            ->where('checksum_sha256', $checksum)
            ->where(function ($query) {
                $query->where('document_type', 'Question Paper');
                if (Schema::hasColumn('document_master', 'declared_type')) {
                    $query->orWhere('declared_type', 'question_paper');
                }
            })
            ->exists();
        if ($duplicate) {
            return $this->fail('This question paper has already been uploaded. Duplicate uploads are not allowed.', 409);
        }

        $stored = $this->storage->storeUpload($file);

        try {
            [$document, $job] = DB::transaction(function () use ($stored, $tenant, $userId, $chosen, $request, $scheduledAt) {
                $document = DocumentMaster::create([
                    'sub_institute_id' => $tenant,
                    'title' => pathinfo($stored['original_file_name'], PATHINFO_FILENAME),
                    'original_file_name' => $stored['original_file_name'],
                    'mime_type' => 'application/pdf',
                    'size' => $stored['size'],
                    'checksum_sha256' => $stored['checksum_sha256'],
                    'storage_path' => $stored['storage_path'],
                    'current_version' => 1,
                    'document_type' => 'Question Paper',
                    'owner_id' => $userId,
                    'visibility' => 'private',
                    // The library's own pipeline does not run for question papers; their status is the job's.
                    'processing_status' => 'done',
                    'created_by' => $userId,
                ]);
                $document->recomputeViewPrincipals();
                $document->save();

                if (Schema::hasColumn('document_master', 'declared_type')) {
                    DB::table('document_master')->where('id', $document->id)->update(['declared_type' => 'question_paper']);
                }

                DocumentAuditService::logVersion($document, 1, $stored['storage_path'], $stored['checksum_sha256'], $stored['size'], 'Initial upload', $userId);
                DocumentAuditService::log($document, 'upload', $userId, ['original_file_name' => $stored['original_file_name'], 'declared_type' => 'question_paper']);

                $job = $this->jobs->create($document->id, $tenant, $userId, $chosen + [
                    'academic_year' => $request->input('academic_year'),
                    'scheduled_at' => $scheduledAt?->toDateTimeString(),
                ]);

                return [$document, $job];
            });
        } catch (Throwable $e) {
            // The bytes are stored but no record points at them; do not leave them orphaned.
            try {
                Storage::disk(config('idms.disk', 'digitalocean'))->delete($stored['storage_path']);
            } catch (Throwable) {
            }
            report($e);

            return $this->fail('The upload could not be saved. Please try again.', 500);
        }

        $isScheduled = $scheduledAt !== null && $scheduledAt->isFuture();
        $queued = $isScheduled ? false : $this->jobs->dispatch($job);

        return response()->json([
            'status_code' => 1,
            'message' => $isScheduled
                ? 'Question paper uploaded and scheduled. It will start automatically at the selected time.'
                : ($queued
                    ? 'Question paper uploaded. It is being processed in the background.'
                    : 'Question paper uploaded. Processing will start when the background worker is available.'),
            'data' => $this->jobs->present($job, $document),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        [$tenant, $userId, $isAdmin] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }

        $query = DB::table('question_paper_jobs as j')
            ->leftJoin('document_master as d', 'd.id', '=', 'j.document_id')
            ->where('j.sub_institute_id', $tenant)
            ->when($isAdmin < 1, fn ($q) => $q->where('j.user_id', $userId))
            ->orderByDesc('j.id');

        $total = (clone $query)->count();
        $rows = $query->limit(50)->get(['j.*', 'd.title as doc_title', 'd.original_file_name as doc_file']);
        $breakdowns = $this->jobs->breakdowns($rows->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $this->ok([
            'total' => $total,
            'jobs' => $rows->map(fn ($r) => $this->jobs->present($r, (object) ['title' => $r->doc_title, 'original_file_name' => $r->doc_file], $breakdowns[(int) $r->id] ?? []))->all(),
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        [$tenant, $userId, $isAdmin] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }

        $job = $this->visibleJob($uuid, $tenant, $userId, $isAdmin);
        if (!$job) {
            return $this->fail('Question paper job not found.', 404);
        }

        $document = DB::table('document_master')->where('id', $job->document_id)->first(['title', 'original_file_name']);
        $attention = DB::table('question_paper_items')
            ->where('job_id', $job->id)->whereIn('state', ['skipped', 'duplicate', 'needs_review', 'unresolved'])
            ->orderBy('ordinal')->limit(200)
            ->get(['item_number', 'source_page', 'state', 'reason', 'question_id'])
            ->map(fn ($i) => [
                'item_number' => $i->item_number,
                'page' => $i->source_page !== null ? (int) $i->source_page : null,
                'state' => $i->state,
                'reason' => $i->reason,
                'question_id' => $i->question_id !== null ? (int) $i->question_id : null,
            ])->all();

        $states = $this->jobs->breakdowns([(int) $job->id])[(int) $job->id] ?? [];

        return $this->ok($this->jobs->present($job, $document, $states) + [
            'attention_items' => $attention,
            'academic' => $this->jobs->academicContext($job),
        ]);
    }

    /** The extracted questions with a preview of each, for review before anything is activated. */
    public function items(Request $request, string $uuid): JsonResponse
    {
        [$tenant, $userId, $isAdmin] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $job = $this->visibleJob($uuid, $tenant, $userId, $isAdmin);
        if (!$job) {
            return $this->fail('Question paper job not found.', 404);
        }
        $state = $request->query('state');
        $state = is_string($state) && in_array($state, ['parsed', 'saved', 'needs_review', 'unresolved', 'duplicate', 'skipped'], true) ? $state : null;

        return $this->ok(['items' => $this->review->items($job, $state)]);
    }

    /** Activate reviewed inactive questions. Owner or school admin only; every rule is enforced server-side. */
    public function activate(Request $request, string $uuid): JsonResponse
    {
        [$tenant, $userId, $isAdmin] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $job = $this->visibleJob($uuid, $tenant, $userId, $isAdmin);
        if (!$job) {
            return $this->fail('Question paper job not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1|max:200',
            'items.*.id' => 'required|integer|min:1',
            'items.*.correct_option' => 'nullable|string|max:2',
            'items.*.chapter_id' => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $results = $this->review->activate($job, $request->input('items'));
        $job = $this->jobs->find((int) $job->id);

        return $this->ok([
            'results' => $results,
            'job' => $this->jobs->present($job, null, $this->jobs->breakdowns([(int) $job->id])[(int) $job->id] ?? []),
        ]);
    }

    /**
     * The school's own standards and subjects, and the chapters of one standard+subject, for the
     * "choose academic context" pickers. Scoped by the token's institute; bounded lists.
     */
    public function options(Request $request): JsonResponse
    {
        [$tenant] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }

        $chapters = [];
        if ($request->filled('standard_id') && $request->filled('subject_id')) {
            $chapters = DB::table('chapter_master')
                ->where('sub_institute_id', $tenant)
                ->where('standard_id', $request->integer('standard_id'))
                ->where('subject_id', $request->integer('subject_id'))
                ->orderBy('sort_order')->orderBy('id')->limit(300)
                ->get(['id', 'chapter_name as name'])->all();
        }

        return $this->ok([
            'standards' => DB::table('standard')->where('sub_institute_id', $tenant)->orderBy('sort_order')->orderBy('name')->limit(200)->get(['id', 'name'])->all(),
            // `mapped` marks the subjects this school maps to the chosen standard, so same-named subjects can be told apart.
            'subjects' => $this->resolver->subjectsFor($tenant, $request->filled('standard_id') ? $request->integer('standard_id') : null),
            'chapters' => $chapters,
        ]);
    }

    /**
     * Remove one paper (job, items, unpicked queue messages, bell notice; the document goes to the Trash). Uploader or
     * school administrator of the same school only; another school's paper is a 404.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        [$tenant, $userId, $isAdmin] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }
        $job = $this->visibleJob($uuid, $tenant, $userId, $isAdmin);
        if (!$job) {
            return $this->fail('Question paper job not found.', 404);
        }

        try {
            return $this->ok($this->removal->remove($job, $userId));
        } catch (\App\Services\QuestionPapers\RemovalRefused $e) {
            return $this->fail($e->getMessage(), 409);
        }
    }

    public function retry(Request $request, string $uuid): JsonResponse
    {
        [$tenant, $userId, $isAdmin] = $this->identity($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }

        $job = $this->visibleJob($uuid, $tenant, $userId, $isAdmin);
        if (!$job) {
            return $this->fail('Question paper job not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'standard_id' => 'nullable|integer|min:1', 'subject_id' => 'nullable|integer|min:1',
            'chapter_id' => 'nullable|integer|min:1', 'item_chapters' => 'nullable|array|max:500',
            'item_chapters.*' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $chosen = $this->resolver->validateChosen(
            $tenant,
            $request->integer('standard_id') ?: null,
            $request->integer('subject_id') ?: null,
            $request->integer('chapter_id') ?: null
        );
        foreach (['standard_id', 'subject_id', 'chapter_id'] as $key) {
            if ($request->filled($key) && $chosen[$key] === null) {
                return $this->fail("The selected {$key} does not belong to your school (or does not match the other selections).", 422);
            }
        }
        $chosen['item_chapters'] = [];
        foreach ((array) $request->input('item_chapters', []) as $itemId => $chapterId) {
            $itemExists = DB::table('question_paper_items')->where('id', (int) $itemId)->where('job_id', $job->id)->exists();
            if (!$itemExists || !$chosen['standard_id'] || !$chosen['subject_id'] || !$this->resolver->validChapter($tenant, $chosen['standard_id'], $chosen['subject_id'], (int) $chapterId)) {
                return $this->fail('A selected chapter does not belong to this paper question, Standard, and Subject.', 422);
            }
            $chosen['item_chapters'][(int) $itemId] = (int) $chapterId;
        }

        if (!$this->jobs->prepareManualRetry($job, $chosen)) {
            return $this->fail('This job cannot be retried in its current state.', 409);
        }

        $job = $this->jobs->find((int) $job->id);
        $this->jobs->dispatch($job);

        return $this->ok($this->jobs->present($job));
    }

    private function visibleJob(string $uuid, int $tenant, int $userId, int $isAdmin): ?object
    {
        $job = $this->jobs->findForTenant($uuid, $tenant);

        return $job && $this->jobs->canView($job, $userId, $isAdmin) ? $job : null;
    }

    /** @return array{0: ?int, 1: int, 2: int} tenant, user, is_admin; tenant null when unauthenticated */
    private function identity(Request $request): array
    {
        $auth = $this->auth($request);
        $userId = (int) ($auth['user_id'] ?? 0);
        $tenant = $this->tenantId($request);

        // No identity means no answer: there is no honest user or school to fall back to.
        if ($tenant === null || $userId <= 0) {
            return [null, 0, 0];
        }

        return [$tenant, $userId, (int) ($auth['is_admin'] ?? 0)];
    }

}
