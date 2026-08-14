<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\H5P\H5PContentRepository;
use App\Services\PAL\H5P\H5PEngagementService;
use App\Services\PAL\H5P\H5PInsightService;
use App\Services\PAL\H5P\H5PIntelligenceService;
use App\Services\PAL\H5P\H5PModelRegistry;
use App\Services\PAL\H5P\H5PTaggingService;
use App\Services\PAL\H5P\H5PXapiPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PAL V4 — H5P Model API.
 *
 * The backend for LMS+PAL → Tech/Learn → Subject → Chapter → H5P Content.
 *
 * Everything served here is read from the database at request time: the
 * registry (`pal_vocabulary`), the H5P content itself (`h5p_scenarios`,
 * `h5p_interactive_video`, `h5p_flashcard`, the MCQ slice of
 * `lms_question_master`), the tags (`pal_h5p_node_metadata`) and the telemetry
 * (`pal_telemetry_events`). No catalog, mapping or metric in a response is
 * written into this class.
 *
 * Mounted under the same `pal.auth` middleware as the rest of /api/pal/*.
 * H5P content has no learner for the middleware to scope through, so tenancy
 * is resolved here from the caller's own token — the same rule
 * NewPalContentModelController and PalContentIntelligenceController use.
 *
 * Envelope: {success: true, data: …} / {success: false, message: …}.
 */
class PalH5PModelController extends Controller
{
    public function __construct(
        protected H5PModelRegistry $registry,
        protected H5PContentRepository $repository,
        protected H5PTaggingService $tagging,
        protected H5PEngagementService $engagement,
        protected H5PIntelligenceService $intelligence,
        protected H5PXapiPipeline $pipeline
    ) {
    }

    // ══════════════════════════════════════════════════════════════════════
    // Registry
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/registry
     *
     * The whole H5P Model vocabulary in one call — every selector, chip and
     * matrix in the UI is rendered from this and nothing else.
     */
    public function registry(Request $request): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        return $this->ok([
            'source' => $this->registry->source($tenant),
            'h5p_types' => array_values($this->registry->types($tenant)),
            'pedagogies' => array_values($this->registry->pedagogies($tenant)),
            'frameworks' => array_map('array_values', $this->registry->frameworks($tenant)),
            'gardner_intelligences' => array_values($this->registry->domain('gardner_intelligences', $tenant)),
            'riasec_signals' => array_values($this->registry->domain('riasec_signals', $tenant)),
            'hpc_lenses' => array_values($this->registry->domain('hpc_lenses', $tenant)),
            'bloom_levels' => array_values($this->registry->domain('bloom_levels', $tenant)),
            'xapi_verbs' => array_values($this->registry->domain('xapi_verbs', $tenant)),
            'engagement_signals' => array_values($this->registry->domain('engagement_signals', $tenant)),
            'engagement_weights' => $this->registry->engagementWeights($tenant),
            'selection_rules' => array_values($this->registry->selectionRules($tenant)),
            'coverage_matrix' => $this->registry->coverageMatrix($tenant),
            'quality_statuses' => array_keys(config('pal_content.quality_statuses', [])),
            'cultural_contexts' => array_keys(config('pal_content.cultural_contexts', [])),
            'ai' => [
                'available' => $this->tagging->aiAvailable(),
                'unavailable_reason' => $this->tagging->aiUnavailableReason(),
            ],
        ]);
    }

    /**
     * GET /api/pal/h5p/coverage-matrix
     * §9 on its own, for the matrix view.
     */
    public function coverageMatrix(Request $request): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        return $this->ok([
            'matrix' => $this->registry->coverageMatrix($tenant),
            'pedagogies' => array_values($this->registry->pedagogies($tenant)),
            'frameworks' => array_map('array_values', $this->registry->frameworks($tenant)),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Hub + chapter model
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/hub
     *
     * One card per natively implemented H5P type, with the chapter's real node
     * counts, the pedagogies that use the type and its measured engagement.
     * This is the replacement for the hard-coded four-card list.
     */
    public function hub(Request $request): JsonResponse
    {
        $context = $this->contextFor($request);

        return $this->ok([
            'context' => $context + $this->repository->resolveContextNames($context),
            'modules' => $this->intelligence->hubModules($context),
            'telemetry' => $this->engagement->summary($context),
            'registry_source' => $this->registry->source($context['sub_institute_id']),
        ]);
    }

    /**
     * GET /api/pal/h5p/chapter-model
     *
     * The complete H5P Model for one chapter: nodes, tags, engagement,
     * §9 coverage, pedagogy distribution and tagging health.
     */
    public function chapterModel(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'subject_id' => 'nullable|integer',
            'standard_id' => 'nullable|integer',
            'type' => 'nullable|string|max:48',
            'limit' => 'nullable|integer|min:1|max:200',
            'window_days' => 'nullable|integer|min:1|max:730',
        ]);

        $context = $this->contextFor($request);

        return $this->ok($this->intelligence->chapterModel($context, [
            'type' => $request->input('type'),
            'limit' => (int) $request->input('limit', H5PContentRepository::DEFAULT_LIMIT),
            'window_days' => $request->filled('window_days') ? (int) $request->input('window_days') : null,
        ]));
    }

    /**
     * GET /api/pal/h5p/chapters
     * Chapters that hold at least one H5P node — the workspace's picker when
     * it is opened without a chapter in the query string.
     */
    public function chapters(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        return $this->ok(['chapters' => $this->repository->chaptersWithContent($this->contextFor($request))]);
    }

    /**
     * GET /api/pal/h5p/coverage
     * §9 read against the chapter's real content, with the pedagogy + H5P type
     * that would close each gap.
     */
    public function coverage(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate(['chapter_id' => 'required|integer|min:1']);
        $context = $this->contextFor($request);

        return $this->ok([
            'context' => $context + $this->repository->resolveContextNames($context),
            'coverage' => $this->intelligence->coverage($context),
            'available_pedagogies' => $this->intelligence->availablePedagogies($context),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Nodes + tagging
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/nodes/{h5pType}/{nodeId}
     * One node, its children, its tags and its engagement.
     */
    public function node(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $context = $this->contextFor($request);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $context['sub_institute_id']]);

        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $engagement = $this->engagement->forNodes([$node['node_key']], $context);

        return $this->ok([
            'node' => $node,
            'model' => $this->tagging->tagNode($node, $context),
            'engagement' => $engagement[$node['node_key']] ?? null,
            'pedagogies' => $this->registry->pedagogiesForH5pType($node['h5p_type'], $context['sub_institute_id']),
        ]);
    }

    /**
     * POST /api/pal/h5p/nodes/{h5pType}/{nodeId}/tags
     * Save a human tag set. Unregistered vocabulary is rejected.
     */
    public function saveTags(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Tagging H5P content is not available to students.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $context = $this->contextFor($request, $tenant);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $tenant]);
        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $auth = $request->attributes->get('pal_auth');
        $saved = $this->tagging->store($node, $context, $request->all(), [
            'user_id' => (int) ($auth['user_id'] ?? 0),
            'is_ai' => false,
        ]);

        return $this->ok(['node' => $node, 'model' => $saved]);
    }

    /**
     * GET /api/pal/h5p/nodes/{h5pType}/{nodeId}/preview
     *
     * What the node's framework tags would become under a different pedagogy.
     * Read-only — it lets the authoring UI show the consequence of a change
     * before the teacher commits to it.
     */
    public function previewTags(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate(['pedagogy' => 'required|string|max:48']);

        $context = $this->contextFor($request);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $context['sub_institute_id']]);
        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $pedagogy = $this->registry->normalize('pedagogy_tags', (string) $request->input('pedagogy'), $context['sub_institute_id']);
        if ($pedagogy === null) {
            return $this->fail('That pedagogy is not in the registry.', 422);
        }

        return $this->ok([
            'node' => $node,
            'current' => $this->tagging->tagNode($node, $context),
            'preview' => $this->tagging->previewWithPedagogy($node, $context, $pedagogy),
        ]);
    }

    /**
     * POST /api/pal/h5p/nodes/{h5pType}/{nodeId}/transition
     * Promote or reject a stored tag set (draft → in_review → approved).
     */
    public function transitionTags(Request $request, string $h5pType, int $nodeId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Reviewing H5P tags is not available to students.')) {
            return $denied;
        }

        $request->validate(['status' => 'required|string|max:24']);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('Cannot resolve a single institute for this write.', 422);
        }

        $context = $this->contextFor($request, $tenant);
        $node = $this->repository->node($h5pType, $nodeId, ['sub_institute_id' => $tenant]);
        if ($node === null) {
            return $this->fail("No H5P node matches {$h5pType}:{$nodeId} in your institute.", 404);
        }

        $auth = $request->attributes->get('pal_auth');
        $result = $this->tagging->transition($node, $context, (string) $request->input('status'), (int) ($auth['user_id'] ?? 0));

        if ($result === null) {
            return $this->fail('That status is not in the registry, or this node has no saved tags to transition.', 422);
        }

        return $this->ok(['node' => $node, 'model' => $result]);
    }

    /**
     * POST /api/pal/h5p/suggest-tags
     *
     * AI proposals for the fields derivation could not fill. Nothing is
     * written — the proposal comes back for review, and saving it is a
     * separate, human call to saveTags.
     */
    public function suggestTags(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'AI tagging is not available to students.')) {
            return $denied;
        }

        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'node_keys' => 'nullable|array',
            'node_keys.*' => 'string|max:64',
        ]);

        $context = $this->contextFor($request);
        $auth = $request->attributes->get('pal_auth');
        $context['user_id'] = (int) ($auth['user_id'] ?? 0);

        $nodes = $this->repository->nodesForContext($context, null, 200);

        $wanted = (array) $request->input('node_keys', []);
        if ($wanted !== []) {
            $nodes = array_values(array_filter($nodes, fn ($node) => in_array($node['node_key'], $wanted, true)));
        }

        if ($nodes === []) {
            return $this->fail('No H5P nodes matched this request.', 404);
        }

        $tagged = $this->tagging->tagNodes($nodes, $context);

        // Least complete first — AI budget goes where derivation left gaps.
        usort($nodes, fn ($a, $b) => ($tagged[$a['node_key']]['completeness'] ?? 0) <=> ($tagged[$b['node_key']]['completeness'] ?? 0));

        return $this->ok($this->tagging->proposeTags($nodes, $context, $tagged));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Engagement
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/engagement
     *
     * §8.3 engagement metadata per H5P type. Computed figures are null until
     * there is telemetry — they are never defaulted to a plausible number.
     */
    public function engagement(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $request->validate(['window_days' => 'nullable|integer|min:1|max:730']);
        $context = $this->contextFor($request);
        $window = $request->filled('window_days') ? (int) $request->input('window_days') : null;

        return $this->ok([
            'context' => $context,
            'summary' => $this->engagement->summary($context, $window),
            'by_type' => array_values($this->engagement->forTypes($context, $window)),
            'signal_weights' => $this->registry->engagementWeights($context['sub_institute_id']),
            'reference' => H5PEngagementService::REFERENCE,
        ]);
    }

    /**
     * GET /api/pal/h5p/insights
     *
     * The DeepSeek layer ON TOP of the xAPI event stream. Returns two things:
     *
     *   evidence  every figure, computed in SQL from the events. Always
     *             present, with or without AI.
     *   insight   DeepSeek reading that evidence. Absent — with an explicit
     *             status and reason — when there are no events to interpret,
     *             or when no provider key resolves.
     *
     * Read-only: nothing is written and no tag is changed.
     */
    public function insights(Request $request, H5PInsightService $insights): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'H5P insights are not available to students.')) {
            return $denied;
        }

        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'learner_id' => 'nullable|integer',
            'window_days' => 'nullable|integer|min:1|max:365',
            // The evidence pack alone, for a fast render before the model runs.
            'evidence_only' => 'nullable|boolean',
        ]);

        $context = $this->contextFor($request);
        $auth = $request->attributes->get('pal_auth');
        $learnerId = $request->filled('learner_id') ? (int) $request->input('learner_id') : null;

        $evidence = $insights->evidencePack(
            $context,
            $learnerId,
            $request->filled('window_days') ? (int) $request->input('window_days') : null
        );

        if ($request->boolean('evidence_only')) {
            return $this->ok([
                'evidence' => $evidence,
                'insight' => null,
                'ai' => [
                    'available' => $insights->available(),
                    'unavailable_reason' => $insights->unavailableReason(),
                ],
            ]);
        }

        return $this->ok([
            'evidence' => $evidence,
            'insight' => $insights->insight($evidence, $context, (int) ($auth['user_id'] ?? 0)),
            'ai' => [
                'available' => $insights->available(),
                'unavailable_reason' => $insights->unavailableReason(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pedagogy selection (§1.3)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/h5p/pedagogy/select
     *
     * Runs the registry's selection rules and returns the decision plus the
     * full trace of why each rule did or did not fire.
     */
    public function selectPedagogy(Request $request): JsonResponse
    {
        $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'learner_id' => 'nullable|integer',
            'session_type' => 'nullable|string|max:32',
            'engagement_trend' => 'nullable|string|max:32',
            'pedagogy_required' => 'nullable|string|max:48',
        ]);

        $context = $this->contextFor($request);
        $learnerId = $request->filled('learner_id') ? (int) $request->input('learner_id') : null;

        return $this->ok($this->intelligence->selectPedagogy($learnerId, $context, [
            'type' => $request->input('session_type'),
            'engagement_trend' => $request->input('engagement_trend'),
            'pedagogy_required' => $request->input('pedagogy_required'),
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════
    // xAPI ingest (§8.2)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/pal/h5p/xapi
     *
     * One statement. `learner_id` is required so PalApiAuth ownership-scopes
     * the ingest and the event is attributed to a numeric learner rather than
     * the client-supplied actor.
     */
    public function ingestXapi(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'session_id' => 'nullable|integer',
            'statement' => 'required|array',
        ]);

        $result = $this->pipeline->process(
            (array) $request->input('statement'),
            (int) $request->input('learner_id'),
            $request->filled('session_id') ? (int) $request->input('session_id') : null,
            $this->contextFor($request)
        );

        return $this->ok($result);
    }

    /** POST /api/pal/h5p/xapi/batch */
    public function ingestXapiBatch(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|integer',
            'session_id' => 'nullable|integer',
            'statements' => 'required|array|min:1|max:200',
        ]);

        return $this->ok($this->pipeline->processBatch(
            (array) $request->input('statements'),
            (int) $request->input('learner_id'),
            $request->filled('session_id') ? (int) $request->input('session_id') : null,
            $this->contextFor($request)
        ));
    }

    // ══════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The curriculum + tenant scope for a request. The institute always comes
     * from the caller's own token, never from the query string, so a caller
     * cannot read another tenant's H5P estate by passing its id.
     */
    protected function contextFor(Request $request, ?int $tenant = null): array
    {
        return [
            'chapter_id' => $request->filled('chapter_id') ? (int) $request->input('chapter_id') : null,
            'subject_id' => $request->filled('subject_id') ? (int) $request->input('subject_id') : null,
            'standard_id' => $request->filled('standard_id') ? (int) $request->input('standard_id') : null,
            'sub_institute_id' => $tenant ?? $this->tenantFor($request),
        ];
    }

    /** Read scope. The super admin may target an institute explicitly. */
    protected function tenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');

        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return $request->filled('sub_institute_id') ? (int) $request->get('sub_institute_id') : null;
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');
        if (str_contains($sub, ',')) {
            $sub = trim(explode(',', $sub)[0]);
        }

        return $sub === '' ? null : (int) $sub;
    }

    /** A write must name exactly one institute; an ambiguous CSV is rejected. */
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

        return (int) $sub;
    }

    protected function denyStudents(Request $request, string $message = 'The H5P Model workspace is not available to students.'): ?JsonResponse
    {
        $auth = $request->attributes->get('pal_auth');

        return ! empty($auth['is_student']) ? $this->fail($message, 403) : null;
    }

    protected function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    protected function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
