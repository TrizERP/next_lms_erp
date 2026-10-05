<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\PAL\Coherence\CurriculumGraphBuilder;
use App\Services\PAL\Coherence\RelationWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The Coherence Map's API: the curriculum graph, and edits to it.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NOT A METHOD ON CoherenceMapController
 * ---------------------------------------------------------------------------
 * `/api/pal/coherence/*` serves the PAL learner experience: it reads Neo4j, is
 * gated by `pal.auth`, answers in `{success, data}`, and its node shape is fixed by
 * six shipped call sites which its own docblock says are "not mine to choose".
 *
 * This endpoint serves the Next.js curriculum authoring screen. It reads MariaDB
 * (Neo4j carries no Unit and no Topic), is gated by `lms.auth` - the same JWT the
 * frontend already sends - and answers in `{status, message, data}`, the convention
 * of every other Next-facing LMS route. Bolting a fourth shape onto a controller
 * whose contract is frozen would make both harder to reason about.
 *
 * ---------------------------------------------------------------------------
 * TENANCY COMES FROM THE TOKEN
 * ---------------------------------------------------------------------------
 * Never from `$request->input('sub_institute_id')`, which is what the older LMS
 * controllers do and what G-SEC-29 forbids: a caller who names their own institute
 * can read any institute's curriculum. The identity here is the `lms_auth`
 * attribute set by the LmsApiAuth middleware.
 *
 * That middleware is warn-only today, so an unauthenticated request reaches this
 * class with a null identity. This controller refuses those with a 401 rather than
 * guessing a tenant. That is stricter than the middleware on purpose - these are
 * new routes with no legacy anonymous traffic to preserve.
 */
class CoherenceMapApiController extends Controller
{
    /**
     * How long a built graph stays cached. Short, because a curator approving edges
     * expects the map to reflect it; the generation counter below makes their own
     * writes appear instantly regardless.
     */
    private const CACHE_TTL = 300;

    public function __construct(
        private CurriculumGraphBuilder $graph,
        private RelationWriter $writer
    ) {
    }

    /**
     * GET /api/lms/coherence-map
     *
     * The whole map for one subject + grade.
     */
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|integer|min:1',
            'standard_id' => 'required|integer|min:1',
            'syear' => 'nullable|integer',
            'include_suggested' => 'nullable|boolean',
            'include_cross_grade' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed.', 422, $validator->errors()->toArray());
        }

        $tenant = $this->tenantFor($request);

        if ($tenant === null) {
            return $this->fail('A verified sign-in is required to read the coherence map.', 401);
        }

        // (int) rather than the raw input so MySQL uses the composite indexes on
        // chapter_master instead of collating a string against an integer column.
        $subjectId = (int) $request->input('subject_id');
        $standardId = (int) $request->input('standard_id');
        $syear = $request->filled('syear') ? (int) $request->input('syear') : null;

        $options = [
            'include_suggested' => $request->boolean('include_suggested', true),
            'include_cross_grade' => $request->boolean('include_cross_grade', true),
        ];

        $key = $this->cacheKey($tenant, $subjectId, $standardId, $syear, $options);

        $data = Cache::remember($key, self::CACHE_TTL, fn () => $this->graph->build(
            $tenant, $subjectId, $standardId, $syear, $options
        ));

        if ($data['stats']['chapters'] === 0) {
            return response()->json([
                'status' => true,
                'message' => $this->emptyReason($data, $syear),
                'data' => $data,
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Coherence map fetched successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * GET /api/lms/coherence-map/concept/{conceptId}
     *
     * The same map, but located by a concept instead of by a subject and grade.
     *
     * This is what makes the map walkable across classes 6-10. A prerequisite drawn
     * from a lower grade arrives as an off-map node; to centre on it the client needs
     * that concept's OWN subject and grade, which is a scope it cannot name in
     * advance. Rather than make the client resolve a scope it has no business
     * knowing, it sends the concept id and this resolves the scope server-side - the
     * concept row already carries both columns.
     *
     * Deliberately the same payload as show(), cache key and all: re-centring must
     * not hand the client a second, thinner shape to special-case.
     */
    public function showForConcept(Request $request, int $conceptId): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        if ($tenant === null) {
            return $this->fail('A verified sign-in is required to read the coherence map.', 401);
        }

        // Tenant-scoped on the way in, not after the build: a concept id is guessable,
        // and resolving one belonging to another institute would leak that institute's
        // whole curriculum through the scope it resolves to (G-SEC-29).
        $concept = DB::table('lms_concept')
            ->whereIn('sub_institute_id', array_unique([$tenant, 0]))
            ->where('id', $conceptId)
            ->select('id', 'name', 'subject_id', 'standard_id', 'syear')
            ->first();

        if ($concept === null) {
            return $this->fail('That concept could not be found.', 404);
        }

        if (! $concept->subject_id || ! $concept->standard_id) {
            return $this->fail('That concept is not attached to a subject and grade, so it has no map.', 422);
        }

        $subjectId = (int) $concept->subject_id;
        $standardId = (int) $concept->standard_id;
        $syear = $concept->syear !== null ? (int) $concept->syear : null;

        $options = [
            'include_suggested' => $request->boolean('include_suggested', true),
            'include_cross_grade' => $request->boolean('include_cross_grade', true),
        ];

        $key = $this->cacheKey($tenant, $subjectId, $standardId, $syear, $options);

        $data = Cache::remember($key, self::CACHE_TTL, fn () => $this->graph->build(
            $tenant, $subjectId, $standardId, $syear, $options
        ));

        // Which concept to centre on. The client asked by entity id; the graph speaks
        // in typed refs, so hand back the ref rather than make it rebuild the string.
        $data['focus'] = [
            'ref' => 'concept:'.(int) $concept->id,
            'entity_id' => (int) $concept->id,
            'label' => (string) $concept->name,
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Coherence map fetched successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * POST /api/lms/coherence-map/relations
     *
     * Draw a prerequisite. `prerequisite_id` is learned first; `dependent_id` needs it.
     */
    public function storeRelation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prerequisite_id' => 'required|string|max:64',
            'dependent_id' => 'required|string|max:64',
            'relation_type' => 'nullable|string|in:requires,cross_curricular',
            'note' => 'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed.', 422, $validator->errors()->toArray());
        }

        $tenant = $this->tenantFor($request);

        if ($tenant === null) {
            return $this->fail('A verified sign-in is required to edit the coherence map.', 401);
        }

        try {
            $result = $this->writer->create(
                $tenant,
                $this->actorFor($request),
                (string) $request->input('prerequisite_id'),
                (string) $request->input('dependent_id'),
                (string) $request->input('relation_type', 'requires'),
                $request->input('note')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $this->bustCache($tenant);

        return response()->json([
            'status' => true,
            'message' => $result['created'] ? 'Prerequisite added.' : 'Prerequisite already existed and is now approved.',
            'data' => [
                'relation' => $result['relation'],
                // 'creates_cycle' is advisory. The graph is already cyclic, so this
                // reports rather than refuses - see RelationWriter's docblock.
                'warnings' => $result['warnings'],
            ],
        ], $result['created'] ? 201 : 200);
    }

    /**
     * PATCH /api/lms/coherence-map/relations/{source}/{id}
     *
     * Approve or reject one suggestion.
     */
    public function reviewRelation(Request $request, string $source, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:approved,rejected',
            'note' => 'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed.', 422, $validator->errors()->toArray());
        }

        $tenant = $this->tenantFor($request);

        if ($tenant === null) {
            return $this->fail('A verified sign-in is required to edit the coherence map.', 401);
        }

        try {
            $result = $this->writer->review(
                $tenant, $this->actorFor($request), $source, $id,
                (string) $request->input('status'), $request->input('note')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 404);
        }

        $this->bustCache($tenant);

        return response()->json([
            'status' => true,
            'message' => $request->input('status') === 'approved' ? 'Prerequisite approved.' : 'Suggestion dismissed.',
            'data' => $result,
        ], 200);
    }

    /**
     * DELETE /api/lms/coherence-map/relations/{source}/{id}
     */
    public function destroyRelation(Request $request, string $source, int $id): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        if ($tenant === null) {
            return $this->fail('A verified sign-in is required to edit the coherence map.', 401);
        }

        try {
            $this->writer->delete($tenant, $this->actorFor($request), $source, $id, $request->input('note'));
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 404);
        }

        $this->bustCache($tenant);

        return response()->json([
            'status' => true,
            'message' => 'Prerequisite removed.',
            'data' => ['source' => $source, 'relation_id' => $id],
        ], 200);
    }

    /**
     * POST /api/lms/coherence-map/relations/bulk
     *
     * Approve or dismiss a selection in one action. Partial success is reported, not
     * rolled back - see RelationWriter::bulkReview.
     */
    public function bulkReview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:approved,rejected',
            'relations' => 'required|array|min:1|max:500',
            'relations.*.source' => 'required|string|in:concept,learning',
            'relations.*.id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed.', 422, $validator->errors()->toArray());
        }

        $tenant = $this->tenantFor($request);

        if ($tenant === null) {
            return $this->fail('A verified sign-in is required to edit the coherence map.', 401);
        }

        $result = $this->writer->bulkReview(
            $tenant, $this->actorFor($request),
            (array) $request->input('relations'),
            (string) $request->input('status')
        );

        $this->bustCache($tenant);

        return response()->json([
            'status' => true,
            'message' => $result['updated'].' of '.count($request->input('relations')).' updated.',
            'data' => $result,
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════════
    // Identity + cache
    // ══════════════════════════════════════════════════════════════════

    /**
     * Why the map came back empty.
     *
     * Two very different situations reach this branch and a teacher cannot tell them
     * apart from the screen: the subject genuinely has no curriculum authored, or it
     * has one under a different academic year than the one the app is pointed at.
     * The second is not fixable from this screen - the academic-year selector lives
     * elsewhere - so saying which years do have curriculum is the difference between
     * a dead end and an obvious next step.
     */
    private function emptyReason(array $data, ?int $syear): string
    {
        $years = $data['meta']['available_syears'] ?? [];

        if ($years === []) {
            return 'No curriculum has been set up for this subject and grade yet.';
        }

        $asked = $syear !== null ? (string) $syear : 'the selected year';
        $list = implode(', ', $years);

        return count($years) === 1
            ? "This subject has curriculum for {$list}, not for {$asked}. Switch the academic year to {$list} to see its map."
            : "This subject has curriculum for {$list}, not for {$asked}. Switch the academic year to one of those to see its map.";
    }

    /**
     * The institute this caller acts as.
     *
     * Mirrors CoherenceMapController::tenantFor so the two behave identically:
     * the multi-client super admin (is_admin === 2) may name any institute, and a
     * staff member holding a CSV of institutes acts as the first (their primary).
     */
    private function tenantFor(Request $request): ?int
    {
        $auth = $request->attributes->get('lms_auth');

        if (! is_array($auth)) {
            return null;
        }

        if ((int) ($auth['is_admin'] ?? 0) === 2) {
            return $request->filled('sub_institute_id') ? (int) $request->input('sub_institute_id') : null;
        }

        $sub = (string) ($auth['sub_institute_id'] ?? '');

        if (str_contains($sub, ',')) {
            $sub = trim(explode(',', $sub)[0]);
        }

        return $sub === '' ? null : (int) $sub;
    }

    private function actorFor(Request $request): ?int
    {
        $auth = $request->attributes->get('lms_auth');

        return is_array($auth) ? ($auth['user_id'] ?? null) : null;
    }

    private function cacheKey(int $tenant, int $subjectId, int $standardId, ?int $syear, array $options): string
    {
        // The generation counter is what makes an edit visible immediately: writes
        // bump it, which changes every key for that tenant at once. Tag-based
        // flushing is not available on every cache driver this estate runs.
        $generation = Cache::get($this->generationKey($tenant), 1);

        return implode('_', [
            'coherence_map_v1',
            $generation,
            $tenant,
            $subjectId,
            $standardId,
            $syear ?? 'any',
            $options['include_suggested'] ? 's1' : 's0',
            $options['include_cross_grade'] ? 'x1' : 'x0',
        ]);
    }

    private function generationKey(int $tenant): string
    {
        return 'coherence_map_generation_'.$tenant;
    }

    private function bustCache(int $tenant): void
    {
        $key = $this->generationKey($tenant);

        // No atomic increment: the value may not exist yet, and on a cache driver
        // without increment support a missing key would silently stay missing.
        Cache::put($key, ((int) Cache::get($key, 1)) + 1, 86400);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function fail(string $message, int $code, array $errors = []): JsonResponse
    {
        return response()->json(array_filter([
            'status' => false,
            'message' => $message,
            'errors' => $errors ?: null,
        ], static fn ($v): bool => $v !== null), $code);
    }
}
