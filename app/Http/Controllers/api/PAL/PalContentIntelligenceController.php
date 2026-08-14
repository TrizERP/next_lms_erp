<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Models\PAL\ContentMetadata;
use App\Models\PAL\MisconceptionCorrective;
use App\Models\PAL\MisconceptionLibrary;
use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\Content\BloomLadderService;
use App\Services\PAL\Content\ContentMetadataService;
use App\Services\PAL\Content\MisconceptionLibraryService;
use App\Services\PAL\Content\PalVocabulary;
use App\Services\PAL\Content\VariantRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PAL V4 Content Intelligence Layer API.
 *
 * Mounted under the same `pal.auth` middleware as the rest of /api/pal/*, so
 * learner-scoped routes are already ownership-checked before they arrive here.
 * Tenancy for CONTENT-scoped routes cannot come from the middleware (content has
 * no learner to resolve through), so every method resolves it from the caller's
 * own token — see tenantFor(). A caller can never read or write another
 * institute's authored content by passing an id.
 *
 * Response envelope matches PALAPIController: {success, data} / {success, message}.
 */
class PalContentIntelligenceController extends Controller
{
    public function __construct(
        protected ContentMetadataService $metadata,
        protected BloomLadderService $ladder,
        protected MisconceptionLibraryService $misconceptions,
        protected VariantRouterService $router
    ) {}

    // ══════════════════════════════════════════════════════════════════
    // Vocabulary — powers every dropdown in the authoring UI (spec §9.1)
    // ══════════════════════════════════════════════════════════════════

    /** GET /api/pal/content/vocabulary */
    public function vocabulary(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PalVocabulary::all()]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Metadata read / write
    // ══════════════════════════════════════════════════════════════════

    /** GET /api/pal/content/metadata/{entityType}/{entityId} */
    public function show(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        $data = match ($entityType) {
            'question' => $this->metadata->forQuestion($entityId, $tenant),
            'content' => $this->metadata->forContent($entityId, $tenant),
            'concept' => $this->metadata->forConcept($entityId, $tenant),
            default => null,
        };

        if ($data === null) {
            return $this->fail("Unknown entity type '{$entityType}'. Expected question, content or concept.", 422);
        }
        if (! ($data['found'] ?? false)) {
            return $this->fail('Not found.', 404);
        }

        // A caller may only read metadata for their own institute's content.
        if ($tenant !== null && ! $this->tenantAllows($request, (int) $data['sub_institute_id'])) {
            return $this->fail('This content belongs to another institute.', 403);
        }

        if (! empty($data['metadata'])) {
            $data['missing_mandatory'] = $this->metadata->missingMandatory($entityType, $data['metadata']);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /api/pal/content/metadata/{entityType}/{entityId} */
    public function upsert(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        if (! empty($auth['is_student'])) {
            return $this->fail('Students may not author content metadata.', 403);
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a tenant for this write. Undecidable tenancy is rejected (C3).', 422);
        }

        $payload = $request->except(['_token', 'sub_institute_id', 'scope', 'reviewed_by', 'reviewed_at']);

        try {
            $row = $this->metadata->upsert(
                $entityType,
                $entityId,
                $payload,
                $tenant,
                'human',
                (int) $auth['user_id']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'metadata' => $row,
                'missing_mandatory' => $this->metadata->missingMandatory($entityType, $row),
                'completeness' => $this->metadata->completeness($entityType, $row),
            ],
        ]);
    }

    /** POST /api/pal/content/review/{entityType}/{metadataId} */
    public function transition(Request $request, string $entityType, int $metadataId): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        if (! empty($auth['is_student'])) {
            return $this->fail('Students may not review content.', 403);
        }

        $validated = $request->validate([
            'to_status' => 'required|string',
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $row = $this->metadata->transition(
                $entityType,
                $metadataId,
                $validated['to_status'],
                (int) $auth['user_id'],
                'human',
                $validated['note'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->fail('Metadata row not found.', 404);
        }

        if (! $this->tenantAllows($request, (int) $row->sub_institute_id)) {
            return $this->fail('This content belongs to another institute.', 403);
        }

        return response()->json(['success' => true, 'data' => $row]);
    }

    /** POST /api/pal/content/review/{entityType}/bulk */
    public function bulkTransition(Request $request, string $entityType): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        if (! empty($auth['is_student'])) {
            return $this->fail('Students may not review content.', 403);
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer',
            'to_status' => 'required|string',
            'note' => 'nullable|string|max:2000',
        ]);

        $result = $this->metadata->bulkTransition(
            $entityType,
            $validated['ids'],
            $validated['to_status'],
            (int) $auth['user_id'],
            $validated['note'] ?? null
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET /api/pal/content/review-queue/{entityType}
     *
     * The authoring console's work list: draft proposals ordered so the
     * lowest-confidence ones surface first — those are where a reviewer's time
     * is actually worth spending (plan §8, R4).
     */
    public function reviewQueue(Request $request, string $entityType): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        if (! empty($auth['is_student'])) {
            return $this->fail('Students may not review content.', 403);
        }

        $tenant = $this->tenantFor($request);
        $status = $request->get('status', 'draft');
        $limit = min(200, max(1, (int) $request->get('limit', 50)));

        if (! PalVocabulary::isQualityStatus($status)) {
            return $this->fail("Unknown quality_status '{$status}'.", 422);
        }

        [$model, $key, $sourceTable, $titleCol] = match ($entityType) {
            'question' => [QuestionMetadata::class, 'question_id', 'lms_question_master', 'question_title'],
            'content' => [ContentMetadata::class, 'content_master_id', 'content_master', 'title'],
            default => [null, null, null, null],
        };

        if ($model === null) {
            return $this->fail("Review queue supports 'question' and 'content'.", 422);
        }

        $rows = $model::where('quality_status', $status)
            ->forTenant($tenant)
            ->when($request->filled('concept_id'), fn ($q) => $q->where('concept_ref_id', (int) $request->get('concept_id')))
            ->when($request->filled('tagged_by'), fn ($q) => $q->where('tagged_by', $request->get('tagged_by')))
            ->orderByRaw('confidence IS NULL, confidence ASC')
            ->limit($limit)
            ->get();

        $titles = $rows->isEmpty() ? collect() : DB::table($sourceTable)
            ->whereIn('id', $rows->pluck($key))
            ->pluck($titleCol, 'id');

        return response()->json([
            'success' => true,
            'data' => [
                'entity_type' => $entityType,
                'status' => $status,
                'count' => $rows->count(),
                'items' => $rows->map(fn ($r) => [
                    'metadata_id' => (int) $r->id,
                    'entity_id' => (int) $r->{$key},
                    'title' => $titles[$r->{$key}] ?? null,
                    'concept_id' => $r->concept_ref_id,
                    'bloom_level' => $r->bloom_level ?? $r->bloom_level_served,
                    'practice_level' => $r->practice_level,
                    'difficulty' => $r->difficulty_1_to_5,
                    'cultural_context' => $r->cultural_context,
                    'language' => $r->language,
                    'tagged_by' => $r->tagged_by,
                    'confidence' => $r->confidence,
                    'quality_status' => $r->quality_status,
                    'missing_mandatory' => $this->metadata->missingMandatory($entityType, $r),
                    'completeness' => $this->metadata->completeness($entityType, $r),
                    'ai_rationale' => $r->ai_rationale,
                ])->all(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Bloom ladder (spec §3)
    // ══════════════════════════════════════════════════════════════════

    /** GET /api/pal/content/ladder/{conceptId} */
    public function ladder(Request $request, int $conceptId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ladder->ladderStatus($conceptId, $this->tenantFor($request)),
        ]);
    }

    /** POST /api/pal/content/ladder/evaluate */
    public function evaluateLadder(Request $request): JsonResponse
    {
        $v = $request->validate([
            'learner_id' => 'required|integer',
            'concept_id' => 'required|integer',
            'current_level' => 'required|integer|min:1|max:5',
            'net_fluency' => 'nullable|numeric',
            'bkt_mastery' => 'nullable|numeric',
            'items_at_level' => 'nullable|integer',
            'hpc_level' => 'nullable|string',
        ]);

        try {
            $decision = $this->ladder->evaluate(
                (int) $v['learner_id'],
                (int) $v['concept_id'],
                (int) $v['current_level'],
                array_filter([
                    'net_fluency' => $v['net_fluency'] ?? null,
                    'bkt_mastery' => $v['bkt_mastery'] ?? null,
                    'items_at_level' => $v['items_at_level'] ?? null,
                    'hpc_level' => $v['hpc_level'] ?? null,
                ], fn ($x) => $x !== null),
                $this->tenantFor($request)
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return response()->json(['success' => true, 'data' => $decision]);
    }

    /** POST /api/pal/content/ladder/regression */
    public function checkRegression(Request $request): JsonResponse
    {
        $v = $request->validate([
            'learner_id' => 'required|integer',
            'concept_id' => 'required|integer',
            'current_level' => 'required|integer|min:1|max:5',
            'recent_outcomes' => 'required|array|min:1|max:50',
            'recent_outcomes.*' => 'boolean',
            'misconception_tag' => 'nullable|string',
        ]);

        $attempts = ! empty($v['misconception_tag'])
            ? $this->misconceptions->correctiveAttempts((int) $v['learner_id'], $v['misconception_tag'])
            : 0;

        return response()->json([
            'success' => true,
            'data' => $this->ladder->checkRegression(
                (int) $v['learner_id'],
                (int) $v['concept_id'],
                (int) $v['current_level'],
                $v['recent_outcomes'],
                $attempts
            ),
        ]);
    }

    /** GET /api/pal/content/practice/{learnerId}/{conceptId} */
    public function practiceItems(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $level = (int) $request->get('practice_level', 1);

        if (! PalVocabulary::isPracticeLevel($level)) {
            return $this->fail('practice_level must be 1-5.', 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'concept_id' => $conceptId,
                'practice_level' => $level,
                'bloom_level' => PalVocabulary::bloomForPracticeLevel($level),
                'items' => $this->ladder->itemsForLevel(
                    $conceptId,
                    $level,
                    $this->tenantFor($request),
                    $learnerId,
                    min(50, max(1, (int) $request->get('limit', 10)))
                ),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Variant routing (spec §2, CONTENT LAW C7)
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/content/variant-coverage/{conceptId}
     *
     * Named variant-coverage, not variants, because PALAPIController already owns
     * GET /content/variants/{conceptId} against the legacy pal_contents table.
     */
    public function variants(Request $request, int $conceptId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->router->variantsForConcept(
                $conceptId,
                $this->tenantFor($request),
                $request->filled('learner_id') ? (int) $request->get('learner_id') : null,
                $request->get('content_type', 'concept')
            ),
        ]);
    }

    /** POST /api/pal/content/next-variant */
    public function nextVariant(Request $request): JsonResponse
    {
        $v = $request->validate([
            'learner_id' => 'required|integer',
            'concept_id' => 'required|integer',
            'failed_format' => 'nullable|string',
            'learning_style' => 'nullable|string',
            'mother_tongue' => 'nullable|string',
            'content_type' => 'nullable|string',
            'bloom_level' => 'nullable|string',
            'session_id' => 'nullable|integer',
            'record' => 'nullable|boolean',
        ]);

        $tenant = $this->tenantFor($request);

        $result = $this->router->nextVariant(
            (int) $v['learner_id'],
            (int) $v['concept_id'],
            $tenant,
            array_filter([
                'failed_format' => $v['failed_format'] ?? null,
                'learning_style' => $v['learning_style'] ?? null,
                'mother_tongue' => $v['mother_tongue'] ?? null,
                'content_type' => $v['content_type'] ?? null,
                'bloom_level' => $v['bloom_level'] ?? null,
            ], fn ($x) => $x !== null)
        );

        // Recording is opt-in so a UI can preview a route without polluting the
        // shown-set — but once it actually serves the content it must record,
        // otherwise C7 degrades into re-teaching.
        if (($v['record'] ?? false) && $result['content'] !== null && $tenant !== null) {
            $this->router->recordServe(
                (int) $v['learner_id'],
                (int) $v['concept_id'],
                $tenant,
                $result['content'],
                $result['reason'],
                $v['session_id'] ?? null
            );
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Misconception library (spec §4)
    // ══════════════════════════════════════════════════════════════════

    /** GET /api/pal/content/misconceptions */
    public function listMisconceptions(Request $request): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        $rows = MisconceptionLibrary::forTenant($tenant)
            ->when($request->filled('concept_id'), fn ($q) => $q->where('concept_ref_id', (int) $request->get('concept_id')))
            ->when($request->filled('subject'), fn ($q) => $q->where('subject', $request->get('subject')))
            ->when($request->filled('status'), fn ($q) => $q->where('quality_status', $request->get('status')))
            ->withCount('correctives')
            ->orderBy('priority_level')
            ->limit(min(500, max(1, (int) $request->get('limit', 100))))
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $rows->count(),
                'items' => $rows->map(fn ($m) => [
                    'id' => (int) $m->id,
                    'tag' => $m->tag,
                    'subject' => $m->subject,
                    'grade_band' => $m->grade_band,
                    'concept_id' => $m->concept_ref_id,
                    'concept_code' => $m->concept_code,
                    'description' => $m->description,
                    'error_pattern' => $m->error_pattern,
                    'corrective_action' => $m->corrective_action,
                    'typical_wrong_answers' => $m->typical_wrong_answers,
                    'prevalence_rate' => $m->prevalence_rate,
                    'corrective_format' => $m->corrective_format,
                    'priority_level' => (int) $m->priority_level,
                    'quality_status' => $m->quality_status,
                    'teacher_confirmed' => (bool) $m->teacher_confirmed,
                    'detection_count' => (int) $m->detection_count,
                    'corrective_count' => (int) $m->correctives_count,
                    // CONTENT LAW C6 surfaced per row so the console can show it.
                    'c6_ok' => (int) $m->correctives_count > 0,
                ])->all(),
            ],
        ]);
    }

    /** GET /api/pal/content/misconceptions/{id} */
    public function showMisconception(Request $request, int $id): JsonResponse
    {
        $m = MisconceptionLibrary::with('correctives')->find($id);

        if (! $m) {
            return $this->fail('Misconception not found.', 404);
        }
        if (! $this->tenantAllows($request, (int) $m->sub_institute_id)) {
            return $this->fail('This misconception belongs to another institute.', 403);
        }

        return response()->json(['success' => true, 'data' => $m]);
    }

    /** POST /api/pal/content/misconceptions */
    public function storeMisconception(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        if (! empty($auth['is_student'])) {
            return $this->fail('Students may not author misconceptions.', 403);
        }

        $v = $request->validate([
            'tag' => 'required|string|max:96',
            'description' => 'required|string',
            'subject' => 'nullable|string|max:96',
            'grade_band' => 'nullable|string|max:16',
            'concept_ref_id' => 'nullable|integer',
            'concept_code' => 'nullable|string|max:128',
            'error_pattern' => 'nullable|string',
            'corrective_action' => 'nullable|string',
            'error_regex' => 'nullable|string|max:191',
            'typical_wrong_answers' => 'nullable|array',
            'prevalence_rate' => 'nullable|numeric',
            'corrective_format' => 'nullable|string',
            'priority_level' => 'nullable|integer',
        ]);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a tenant for this write (C3).', 422);
        }

        $payload = array_merge($v, [
            'sub_institute_id' => $tenant,
            'scope' => $tenant === 0 ? 'global' : 'tenant',
            'quality_status' => 'draft',
            'tagged_by' => 'human',
        ]);

        $errors = PalVocabulary::validate($payload);
        if ($errors !== []) {
            return $this->fail(implode(' | ', $errors), 422);
        }

        if (! empty($payload['error_regex'])
            && @preg_match('/' . str_replace('/', '\/', $payload['error_regex']) . '/i', 'probe') === false) {
            return $this->fail('error_regex does not compile.', 422);
        }

        $existing = MisconceptionLibrary::where('tag', $payload['tag'])
            ->where('sub_institute_id', $tenant)
            ->first();

        if ($existing) {
            return $this->fail("Tag '{$payload['tag']}' already exists for this institute. Tags are permanent — update it or deprecate it, do not re-create.", 409);
        }

        return response()->json(['success' => true, 'data' => MisconceptionLibrary::create($payload)], 201);
    }

    /** POST /api/pal/content/misconceptions/{id}/correctives */
    public function storeCorrective(Request $request, int $id): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        if (! empty($auth['is_student'])) {
            return $this->fail('Students may not author correctives.', 403);
        }

        $m = MisconceptionLibrary::find($id);
        if (! $m) {
            return $this->fail('Misconception not found.', 404);
        }
        if (! $this->tenantAllows($request, (int) $m->sub_institute_id)) {
            return $this->fail('This misconception belongs to another institute.', 403);
        }

        $v = $request->validate([
            'title' => 'required|string|max:191',
            'body' => 'nullable|string',
            'media_url' => 'nullable|string',
            'content_master_id' => 'nullable|integer',
            'format' => 'nullable|string',
            'h5p_type' => 'nullable|string',
            'language' => 'nullable|string|max:8',
            'estimated_duration_minutes' => 'nullable|integer',
            'priority_level' => 'nullable|integer',
        ]);

        // A corrective must actually contain something to show.
        if (empty($v['body']) && empty($v['media_url']) && empty($v['content_master_id'])) {
            return $this->fail('A corrective needs at least one of: body, media_url, content_master_id.', 422);
        }

        $payload = array_merge($v, [
            'misconception_id' => $m->id,
            'sub_institute_id' => $m->sub_institute_id,
            'scope' => $m->scope,
            'format' => $v['format'] ?? 'text_diagram',
            'language' => $v['language'] ?? config('pal_content.default_language'),
            'quality_status' => 'draft',
            'tagged_by' => 'human',
        ]);

        $errors = PalVocabulary::validate($payload);
        if ($errors !== []) {
            return $this->fail(implode(' | ', $errors), 422);
        }

        return response()->json(['success' => true, 'data' => MisconceptionCorrective::create($payload)], 201);
    }

    /**
     * POST /api/pal/content/misconception/detect
     *
     * The spec §4.4 pipeline: wrong answer in, detected misconception plus a
     * different-modality corrective out.
     */
    public function detect(Request $request): JsonResponse
    {
        $v = $request->validate([
            'learner_id' => 'required|integer',
            'question_id' => 'required|integer',
            'student_answer' => 'required',
            'session_id' => 'nullable|integer',
            'mother_tongue' => 'nullable|string|max:8',
            'class_student_ids' => 'nullable|array',
            'class_student_ids.*' => 'integer',
            'class_size' => 'nullable|integer',
        ]);

        $tenant = $this->tenantFor($request)
            ?? (int) DB::table('lms_question_master')->where('id', $v['question_id'])->value('sub_institute_id');

        $result = $this->misconceptions->detectAndRoute(
            (int) $v['learner_id'],
            (int) $v['question_id'],
            $v['student_answer'],
            (int) $tenant,
            array_filter([
                'session_id' => $v['session_id'] ?? null,
                'mother_tongue' => $v['mother_tongue'] ?? null,
                'class_student_ids' => $v['class_student_ids'] ?? null,
                'class_size' => $v['class_size'] ?? null,
            ], fn ($x) => $x !== null)
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** POST /api/pal/content/misconception/outcome */
    public function recordOutcome(Request $request): JsonResponse
    {
        $v = $request->validate([
            'learner_id' => 'required|integer',
            'misconception_tag' => 'required|string|max:96',
            'resolved' => 'required|boolean',
        ]);

        $this->misconceptions->recordOutcome(
            (int) $v['learner_id'],
            $v['misconception_tag'],
            (bool) $v['resolved']
        );

        return response()->json(['success' => true, 'data' => ['recorded' => true]]);
    }

    /** GET /api/pal/content/misconception/health */
    public function libraryHealth(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->misconceptions->libraryHealth(
                $this->tenantFor($request),
                $request->filled('concept_id') ? (int) $request->get('concept_id') : null
            ),
        ]);
    }

    /** POST /api/pal/content/misconception/class-prevalence */
    public function classPrevalence(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        // Class-wide data is a teacher view. C9 keeps peer comparison away from
        // learners, and this endpoint is exactly that kind of data.
        if (! empty($auth['is_student'])) {
            return $this->fail('Class-level analytics are not available to students.', 403);
        }

        $v = $request->validate([
            'class_student_ids' => 'required|array|min:1',
            'class_student_ids.*' => 'integer',
            'concept_id' => 'nullable|integer',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->misconceptions->classPrevalence(
                $v['class_student_ids'],
                $v['concept_id'] ?? null,
                (int) ($v['days'] ?? 30)
            ),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Monitoring (spec §7.3)
    // ══════════════════════════════════════════════════════════════════

    /** GET /api/pal/content/coverage */
    public function coverage(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        if (! empty($auth['is_student'])) {
            return $this->fail('Coverage reporting is not available to students.', 403);
        }

        $tenant = $this->tenantFor($request);

        $questions = DB::table('lms_question_master')
            ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))->count();
        $content = DB::table('content_master')
            ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))->count();

        $qTagged = QuestionMetadata::forTenant($tenant)->whereNotNull('bloom_level')->count();
        $qApproved = QuestionMetadata::forTenant($tenant)->servable()->count();
        $cTagged = ContentMetadata::forTenant($tenant)->whereNotNull('content_type')->count();
        $cApproved = ContentMetadata::forTenant($tenant)->servable()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'sub_institute_id' => $tenant,
                'questions' => [
                    'total' => $questions,
                    'tagged' => $qTagged,
                    'approved' => $qApproved,
                    'coverage_pct' => $questions ? round($qTagged / $questions * 100, 2) : 0,
                ],
                'content' => [
                    'total' => $content,
                    'tagged' => $cTagged,
                    'approved' => $cApproved,
                    'coverage_pct' => $content ? round($cTagged / $content * 100, 2) : 0,
                ],
                'misconceptions' => $this->misconceptions->libraryHealth($tenant),
            ],
        ]);
    }

    // ── Tenancy helpers ──────────────────────────────────────────────────────

    /**
     * The tenant a READ is scoped to.
     *
     * Null means unrestricted and is returned only for the super admin
     * (is_admin === 2), matching PalApiAuth's own rule. Every other caller is
     * pinned to their own institute — content routes have no learner for the
     * middleware to scope through, so the scoping has to happen here.
     */
    protected function tenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return $request->filled('sub_institute_id') ? (int) $request->get('sub_institute_id') : null;
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');

        // Staff may hold a CSV of institutes; the first is their primary.
        if (str_contains($sub, ',')) {
            $sub = trim(explode(',', $sub)[0]);
        }

        return $sub === '' ? null : (int) $sub;
    }

    /**
     * The tenant a WRITE lands in. Stricter than the read scope: a write must
     * name exactly one institute, so an ambiguous CSV or a missing value is
     * rejected rather than defaulted (C3).
     */
    protected function writeTenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2 && $request->filled('sub_institute_id')) {
            return (int) $request->get('sub_institute_id');
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');
        if ($sub === '' || str_contains($sub, ',')) {
            return null;
        }

        $id = (int) $sub;

        // sub_institute_id 0 is the shared global vocabulary — only the super
        // admin may author into it, otherwise one school's content becomes
        // every school's content (plan §8, R7).
        if ($id === 0 && (int) ($auth['is_admin'] ?? 0) !== 2) {
            return null;
        }

        return $id;
    }

    /** May this caller touch a row owned by $rowTenant? */
    protected function tenantAllows(Request $request, int $rowTenant): bool
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return true;
        }

        // The shared global vocabulary is readable by everyone by design.
        if ($rowTenant === 0) {
            return true;
        }

        $own = array_map('intval', array_filter(array_map('trim', explode(',', (string) ($auth['sub_institute_id'] ?? '')))));

        return in_array($rowTenant, $own, true);
    }

    protected function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
