<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Models\PAL\ConceptVideo;
use App\Services\Eso\Video\ConceptVideoRelevanceScorer;
use App\Services\Eso\Video\InstituteVideoRepository;
use App\Services\PAL\ContentModel\ConceptVideoLibraryService;
use App\Services\PAL\Integration\ExternalVideoSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Review and approve the videos PAL offers on a reteach.
 *
 * Everything the harvester files is a draft, and a draft is invisible to
 * students. This controller is where a human turns one into something a
 * student can actually see — so every write here records who decided.
 *
 * Mounted under the same `pal.auth` middleware as the rest of /api/pal/*, with
 * tenancy resolved from the caller's own token exactly as
 * NewPalContentModelController does, so the two cannot disagree about which
 * institute a reviewer is acting for.
 *
 * Envelope: {success: true, data: …} / {success: false, message: …}.
 */
class ConceptVideoController extends Controller
{
    public function __construct(
        protected ConceptVideoLibraryService $library,
        protected InstituteVideoRepository $institute,
        protected ExternalVideoSearchService $external,
        protected ConceptVideoRelevanceScorer $scorer,
    ) {}

    /**
     * GET /api/pal/new/content-model/concept-videos
     *
     * The review queue. Drafts by default, because that is what a reviewer
     * opens this screen to act on.
     */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);
        if ($tenant === null) {
            return $this->fail('A single institute must be selected to review videos.', 422);
        }

        $filters = array_filter([
            'status' => $request->get('status', 'draft'),
            'chapter_id' => $request->get('chapter_id'),
            'concept_id' => $request->get('concept_id'),
            'source' => $request->get('source'),
        ], static fn ($v) => $v !== null && $v !== '');

        return $this->ok([
            'videos' => $this->library->reviewQueue($tenant, $filters, (int) $request->get('limit', 100)),
            'counts' => $this->library->pipelineCounts($tenant, (int) $request->get('chapter_id') ?: null),
            'servable_statuses' => config('pal_content.servable_statuses', ['approved']),
        ]);
    }

    /** GET /api/pal/new/content-model/concept-videos/{conceptId} */
    public function show(Request $request, int $conceptId): JsonResponse
    {
        if ($denied = $this->denyStudents($request)) {
            return $denied;
        }

        $tenant = $this->tenantFor($request);
        if ($tenant === null) {
            return $this->fail('A single institute must be selected.', 422);
        }

        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'name', 'chapter_id']);
        if ($concept === null) {
            return $this->fail('Concept not found.', 404);
        }

        return $this->ok([
            'concept' => ['id' => (int) $concept->id, 'name' => $concept->name, 'chapter_id' => $concept->chapter_id],
            'videos' => $this->library->reviewQueue($tenant, ['status' => $request->get('status', 'draft'), 'concept_id' => $conceptId], 100),
            'approved' => $this->library->reviewQueue($tenant, ['status' => 'approved', 'concept_id' => $conceptId], 100),
        ]);
    }

    /**
     * POST /api/pal/new/content-model/concept-videos/{conceptId}/search
     *
     * Look for videos for one concept, on demand.
     *
     * This is the one place an external search happens outside the harvest
     * command, and it is staff-only and explicit: a person has decided this
     * concept needs coverage. Results are still filed as drafts — finding a
     * video and publishing one remain separate acts.
     */
    public function search(Request $request, int $conceptId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Searching for videos is not available to students.')) {
            return $denied;
        }

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('A single institute must be selected to file video candidates.', 422);
        }

        $concept = DB::table('lms_concept')
            ->where('id', $conceptId)
            ->first(['id', 'name', 'chapter_id', 'topic_id', 'subject_id', 'standard_id', 'syear']);

        if ($concept === null) {
            return $this->fail('Concept not found.', 404);
        }

        $auth = $request->attributes->get('pal_auth');
        $userId = (int) ($auth['user_id'] ?? 0);

        // The school's own library first — it is already curriculum-aligned
        // and already cleared for these students.
        $candidates = array_map(static fn (array $row) => [
            'media_url' => $row['media_url'],
            'title' => $row['title'] ?? null,
            'description' => $row['description'] ?? null,
            'content_master_id' => (int) ($row['id'] ?? 0) ?: null,
            'match_score' => $row['match_score'] ?? null,
            'match_reason' => $row['match_reason'] ?? null,
            'source' => 'institute',
            'provider' => 'upload',
        ], $this->institute->candidatesForConcept($concept, $tenant));

        $usedExternal = false;

        if ($candidates === [] && $this->external->available()) {
            $usedExternal = true;
            $candidates = $this->externalCandidates($concept);
        }

        if ($candidates === []) {
            return $this->ok([
                'candidates' => [],
                'reason' => $this->external->available()
                    ? 'no_relevant_video_found'
                    : 'external_search_unconfigured',
                'searched_externally' => $usedExternal,
            ]);
        }

        $result = $this->library->ingest(
            $conceptId,
            $concept->chapter_id === null ? null : (int) $concept->chapter_id,
            $tenant,
            $candidates,
            $userId
        );

        return $this->ok([
            'candidates' => $this->library->reviewQueue($tenant, ['status' => 'draft', 'concept_id' => $conceptId], 20),
            'ingest' => $result,
            'searched_externally' => $usedExternal,
            'reason' => null,
        ]);
    }

    /**
     * POST /api/pal/new/content-model/concept-videos/{videoId}/transition
     *
     * The gate. Moving a video to an approved status is what makes it
     * reachable by a student, so it is always attributed.
     */
    public function transition(Request $request, int $videoId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Approving videos is not available to students.')) {
            return $denied;
        }

        $validated = $request->validate([
            'to' => 'required|string|in:draft,approved,deprecated',
            'note' => 'nullable|string|max:1000',
        ]);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('A single institute must be selected.', 422);
        }

        $video = ConceptVideo::query()->where('id', $videoId)->where('sub_institute_id', $tenant)->first();
        if ($video === null) {
            return $this->fail('Video not found.', 404);
        }

        $auth = $request->attributes->get('pal_auth');

        $saved = $this->library->transition($video, $validated['to'], (int) ($auth['user_id'] ?? 0), $validated['note'] ?? null);

        return $this->ok(['video' => $saved->toArray()]);
    }

    /**
     * POST /api/pal/new/content-model/concept-videos/bulk-transition
     *
     * Approving a chapter's candidates in one action — the difference between
     * review being an afternoon and review being a backlog nobody starts.
     */
    public function bulkTransition(Request $request): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Approving videos is not available to students.')) {
            return $denied;
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'to' => 'required|string|in:draft,approved,deprecated',
            'note' => 'nullable|string|max:1000',
        ]);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('A single institute must be selected.', 422);
        }

        $auth = $request->attributes->get('pal_auth');

        $count = $this->library->bulkTransition(
            $validated['ids'],
            $tenant,
            $validated['to'],
            (int) ($auth['user_id'] ?? 0),
            $validated['note'] ?? null
        );

        return $this->ok(['transitioned' => $count, 'to' => $validated['to']]);
    }

    /**
     * POST /api/pal/new/content-model/concept-videos/{conceptId}
     *
     * A teacher attaching a specific video by hand — the highest-quality
     * source there is, because a person who teaches the concept chose it.
     */
    public function store(Request $request, int $conceptId): JsonResponse
    {
        if ($denied = $this->denyStudents($request, 'Adding videos is not available to students.')) {
            return $denied;
        }

        $validated = $request->validate([
            'media_url' => 'required|url|max:2000',
            'title' => 'nullable|string|max:512',
            'description' => 'nullable|string|max:5000',
            'attribution' => 'nullable|string|max:191',
            'approve' => 'nullable|boolean',
        ]);

        $tenant = $this->writeTenantFor($request);
        if ($tenant === null) {
            return $this->fail('A single institute must be selected.', 422);
        }

        $concept = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'chapter_id']);
        if ($concept === null) {
            return $this->fail('Concept not found.', 404);
        }

        $auth = $request->attributes->get('pal_auth');

        $video = $this->library->addManual(
            $conceptId,
            $concept->chapter_id === null ? null : (int) $concept->chapter_id,
            $tenant,
            $validated + ['provider' => $this->providerFor($validated['media_url'])],
            (int) ($auth['user_id'] ?? 0),
            (bool) ($validated['approve'] ?? false)
        );

        return $this->ok(['video' => $video->toArray()]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function externalCandidates(object $concept): array
    {
        $chapter = DB::table('chapter_master')
            ->where('id', (int) ($concept->chapter_id ?? 0))
            ->first(['chapter_name', 'subject_id', 'standard_id']);

        $subjectId = (int) ($concept->subject_id ?? ($chapter->subject_id ?? 0));
        $standardId = (int) ($concept->standard_id ?? ($chapter->standard_id ?? 0));

        $query = $this->external->queryFor(
            (string) $concept->name,
            $chapter->chapter_name ?? null,
            $subjectId > 0 ? (string) DB::table('subject')->where('id', $subjectId)->value('subject_name') : null,
            $standardId > 0 ? (string) DB::table('standard')->where('id', $standardId)->value('name') : null,
        );

        $results = $this->external->search($query);
        if ($results === []) {
            return [];
        }

        // 'search' mode: these results share the concept's vocabulary because
        // the search worked, not because they are generic. See the scorer.
        return $this->scorer->rank((string) $concept->name, $results, [
            'min_score' => (float) config('pal_content.video.min_match_score', 0.55),
            'pool_mode' => 'search',
        ]);
    }

    protected function providerFor(string $url): string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'youtu') => 'youtube',
            str_contains($host, 'vimeo') => 'vimeo',
            default => 'upload',
        };
    }

    // ══════════════════════════════════════════════════════════════════════
    // Tenancy + envelope — identical rules to NewPalContentModelController
    // ══════════════════════════════════════════════════════════════════════

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

        $id = (int) $sub;
        if ($id === 0 && (int) ($auth['is_admin'] ?? 0) !== 2) {
            return null;
        }

        return $id;
    }

    protected function denyStudents(Request $request, string $message = 'The video review queue is not available to students.'): ?JsonResponse
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
