<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\Coherence\CoherenceRecommender;
use App\Services\PAL\Coherence\MasteryUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Set Coherence Map - Recommendation API.
 *
 * Mounted under `pal.auth`, so every learner-scoped route below has already had
 * its ownership checked by PalApiAuth before it arrives here: a student can
 * only ever pass their own `learnerId`. Tenancy for the map-only routes (which
 * have no learner to resolve through) comes from the caller's own token.
 *
 * `learnerId` IS `tblstudent.id` throughout - the PERSON. That is what
 * PalApiAuth scopes on, and it is what :StuDetail is keyed on (sdId), so the id
 * the client holds is the id the graph is queried with. It is NOT
 * tblstudent_enrollment.id, which keys :Student and changes every year.
 *
 * Response envelope matches the rest of /api/pal/*: {success, data} on the way
 * out, {success, message} on a refusal.
 */
class CoherenceMapController extends Controller
{
    public function __construct(
        protected CoherenceMapRepository $map,
        protected CoherenceRecommender $recommender,
        protected MasteryUpdater $mastery,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    // The map
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/coherence/map?standard_id=&subject_id=&learner_id=
     *
     * The authored map, optionally overlaid with one learner's mastery. This is
     * what the graph view renders.
     */
    public function map(Request $request): JsonResponse
    {
        $scope = $this->scopeFrom($request);

        if (is_string($scope)) {
            return $this->fail($scope, 422);
        }

        $learnerId = $request->filled('learner_id') ? (int) $request->get('learner_id') : null;

        $data = $this->map->map($scope['standard_id'], $scope['subject_id'], $learnerId);

        if (! $data['available']) {
            return $this->fail(
                'No coherence map exists for this standard and subject. Concepts must be extracted into '
                . 'semantic_intelligence and projected with pal:coherence-sync before the map can be read.',
                404
            );
        }

        return $this->ok($data + ['scope' => $scope]);
    }

    /**
     * GET /api/pal/coherence/scopes
     *
     * The (standard, subject) pairs this caller may open a map on, richest
     * first. Exists because `/coherence/map` requires both ids and there is no
     * other way for a client to discover a valid pair — without it a front-end
     * hardcodes one and breaks the moment a different institute signs in.
     *
     * Tenancy: `tenantFor` returns null only for a super-admin who named no
     * institute, and the repository treats null as "every tenant" — which is
     * the correct answer for that caller and no one else.
     */
    public function scopes(Request $request): JsonResponse
    {
        return $this->ok(['scopes' => $this->map->scopes($this->tenantFor($request))]);
    }

    /**
     * GET /api/pal/coherence/learner/{learnerId}
     *
     * The learner's own view: their enrolled class resolved automatically, the
     * map overlaid with their mastery, and every concept classified
     * mastered / ready / blocked.
     *
     * This is the single call a student dashboard makes on load.
     */
    public function learner(Request $request, int $learnerId): JsonResponse
    {
        $scope = $this->scopeForLearner($request, $learnerId);

        if (is_string($scope)) {
            return $this->fail($scope, 422);
        }

        $readiness = $this->map->readiness($scope['standard_id'], $scope['subject_id'], $learnerId);

        if ($readiness === []) {
            return $this->fail('No coherence map exists for this learner\'s class and subject.', 404);
        }

        $graph = $this->map->map($scope['standard_id'], $scope['subject_id'], $learnerId);

        // The readiness state is merged onto the drawn node so the client can
        // colour the graph without joining two payloads and getting it wrong.
        $states = [];
        foreach ($readiness as $id => $row) {
            $states[$id] = ['state' => $row['state'], 'unmet' => $row['unmet'], 'unlocks' => $row['unlocks']];
        }

        $nodes = array_map(function ($node) use ($states) {
            $id = (int) ($node['id'] ?? 0);

            return $node + ($states[$id] ?? ['state' => 'blocked', 'unmet' => [], 'unlocks' => 0]);
        }, $graph['nodes']);

        return $this->ok([
            'scope'    => $scope,
            'learner'  => ['id' => $learnerId],
            'nodes'    => $nodes,
            'edges'    => $graph['edges'],
            'stats'    => $graph['stats'],
            'progress' => $this->progressOf($readiness),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Recommendation
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/coherence/next/{learnerId}
     *
     * The next best action: which concept, why that one, what to show, what to
     * ask. The `why` block is deliberately part of the contract - a
     * recommendation a teacher cannot interrogate is one they will not trust.
     */
    public function next(Request $request, int $learnerId): JsonResponse
    {
        $scope = $this->scopeForLearner($request, $learnerId);

        if (is_string($scope)) {
            return $this->fail($scope, 422);
        }

        $action = $this->recommender->nextBestAction(
            $learnerId,
            $scope['standard_id'],
            $scope['subject_id']
        );

        return $this->ok($action + ['scope' => $scope]);
    }

    /**
     * GET /api/pal/coherence/remediation/{learnerId}/{conceptId}
     *
     * Why a concept is out of reach, and where to start instead. Answers the
     * question a mark sheet never can: not "you got this wrong" but "you got
     * this wrong because this earlier thing is not in place".
     */
    public function remediation(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $roots = $this->map->rootBlockers($conceptId, $learnerId);

        if ($roots === []) {
            return $this->ok([
                'blocked'   => false,
                'roots'     => [],
                'message'   => 'Nothing beneath this concept is unmastered - it is reachable now.',
                'content'   => [],
            ]);
        }

        $root = $roots[0];

        return $this->ok([
            'blocked' => true,
            'roots'   => $roots,
            'start_at' => [
                'concept_id' => (int) $root['id'],
                'name'       => $root['name'] ?? null,
                'mastery'    => round((float) ($root['mastery'] ?? 0), 4),
                'gate'       => round((float) ($root['gate'] ?? 0.7), 4),
                'depth'      => (int) ($root['depth'] ?? 1),
            ],
            'content' => $this->map->contentFor((int) $root['id'], [], 5),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Evidence - the real-time write path
    // ══════════════════════════════════════════════════════════════════

    /**
     * POST /api/pal/coherence/evidence
     *
     * Body: learner_id, concept_id, correct, [question_id, content_id,
     *       session_id, misconception_tag, duration_seconds]
     *
     * Records one answered question, re-estimates mastery, writes it to the
     * graph, and returns the NEXT action in the same response - so a client can
     * submit and advance in one round-trip rather than two.
     *
     * `learner_id` in the body is what PalApiAuth ownership-checks, so a student
     * cannot post evidence against somebody else.
     */
    public function evidence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'learner_id'        => 'required|integer|min:1',
            'concept_id'        => 'required|integer|min:1',
            'correct'           => 'required|boolean',
            'question_id'       => 'nullable|integer|min:1',
            'content_id'        => 'nullable|integer|min:1',
            'session_id'        => 'nullable|integer|min:1',
            'misconception_tag' => 'nullable|string|max:96',
            'duration_seconds'  => 'nullable|integer|min:0|max:86400',
        ]);

        $learnerId = (int) $validated['learner_id'];
        $conceptId = (int) $validated['concept_id'];

        $scope = $this->scopeForLearner($request, $learnerId);

        if (is_string($scope)) {
            return $this->fail($scope, 422);
        }

        // The concept must belong to a scope this caller may write to. Without
        // this a learner could post evidence against any concept id in the
        // estate and move mastery on somebody else's curriculum.
        if (! $this->conceptInTenant($conceptId, $scope['sub_institute_id'])) {
            return $this->fail('That concept does not belong to this institute.', 403);
        }

        $state = $this->mastery->record($learnerId, $conceptId, $scope['sub_institute_id'], [
            'question_id'       => $validated['question_id'] ?? null,
            'content_id'        => $validated['content_id'] ?? null,
            'session_id'        => $validated['session_id'] ?? null,
            'correct'           => (bool) $validated['correct'],
            'misconception_tag' => $validated['misconception_tag'] ?? null,
            'duration_seconds'  => $validated['duration_seconds'] ?? null,
        ]);

        return $this->ok([
            'mastery' => [
                'concept_id' => $conceptId,
                'p'          => $state['mastery'],
                'delta'      => $state['delta'],
                'band'       => $state['band'],
                'gate'       => $state['gate'],
                'mastered'   => $state['mastered'],
                'attempts'   => $state['attempts'],
                'correct'    => $state['correct'],
                'streak'     => $state['streak'],
                // The BKT trajectory: what the posterior did on every answer so
                // far. A teacher asking "why is this 0.62" gets the whole curve.
                'trajectory' => $state['trajectory'],
            ],
            'next' => $this->recommender->nextBestAction(
                $learnerId,
                $scope['standard_id'],
                $scope['subject_id']
            ),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Health
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /api/pal/coherence/health?standard_id=&subject_id=
     *
     * The same structural gate `pal:coherence-sync --health` runs, exposed so an
     * authoring UI can show whether the map is fit to recommend from.
     */
    public function health(Request $request): JsonResponse
    {
        $scope = $this->scopeFrom($request);

        if (is_string($scope)) {
            return $this->fail($scope, 422);
        }

        $health = $this->map->health($scope['standard_id'], $scope['subject_id']);

        return $this->ok($health + [
            'scope'      => $scope,
            'fit_to_use' => $health['concepts'] > 0 && $health['acyclic'] && $health['roots'] > 0,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════

    /**
     * Explicit scope from the query string, for map-level routes.
     *
     * @return array{sub_institute_id: int, standard_id: int, subject_id: int}|string
     */
    private function scopeFrom(Request $request)
    {
        $tenant = $this->tenantFor($request);

        if ($tenant === null) {
            return 'Your token does not name a single institute, so the scope is ambiguous. '
                . 'Pass sub_institute_id explicitly.';
        }

        $standard = (int) $request->get('standard_id');
        $subject = (int) $request->get('subject_id');

        if ($standard <= 0 || $subject <= 0) {
            return 'standard_id and subject_id are both required.';
        }

        return ['sub_institute_id' => $tenant, 'standard_id' => $standard, 'subject_id' => $subject];
    }

    /**
     * Scope resolved FROM THE LEARNER - the "one student login" path.
     *
     * The client sends a learner id and nothing else; their current class comes
     * from their most recent enrollment. `subject_id` may still be passed to
     * choose between the subjects that class studies, and defaults to the one
     * with the most mapped concepts so a bare call returns something useful
     * rather than an error.
     *
     * @return array{sub_institute_id: int, standard_id: int, subject_id: int}|string
     */
    private function scopeForLearner(Request $request, int $learnerId)
    {
        $enrollment = DB::table('tblstudent_enrollment')
            ->where('student_id', $learnerId)
            ->orderByDesc('syear')
            ->orderByDesc('id')
            ->first(['standard_id', 'sub_institute_id', 'syear']);

        if ($enrollment === null) {
            return "Learner {$learnerId} has no enrollment, so their class cannot be resolved.";
        }

        $tenant = (int) $enrollment->sub_institute_id;
        $standard = (int) $enrollment->standard_id;

        if ($request->filled('subject_id')) {
            return [
                'sub_institute_id' => $tenant,
                'standard_id'      => $standard,
                'subject_id'       => (int) $request->get('subject_id'),
            ];
        }

        // No subject named: pick the one with the richest map for this class.
        $subject = DB::table('lms_concept')
            ->where('sub_institute_id', $tenant)
            ->where('standard_id', $standard)
            ->groupBy('subject_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('subject_id');

        if ($subject === null) {
            return "No subject in this learner's class (standard {$standard}) has any mapped concepts yet.";
        }

        return [
            'sub_institute_id' => $tenant,
            'standard_id'      => $standard,
            'subject_id'       => (int) $subject,
        ];
    }

    private function conceptInTenant(int $conceptId, int $tenant): bool
    {
        return DB::table('lms_concept')
            ->where('id', $conceptId)
            ->where('sub_institute_id', $tenant)
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $readiness
     * @return array<string, int|float>
     */
    private function progressOf(array $readiness): array
    {
        $counts = ['mastered' => 0, 'ready' => 0, 'blocked' => 0];

        foreach ($readiness as $c) {
            $counts[$c['state']] = ($counts[$c['state']] ?? 0) + 1;
        }

        $total = count($readiness);

        return $counts + [
            'total'   => $total,
            'percent' => $total === 0 ? 0.0 : round(($counts['mastered'] / $total) * 100, 1),
        ];
    }

    /**
     * Read scope from the caller's own token. Mirrors
     * PalContentIntelligenceController::tenantFor so the two behave identically.
     */
    private function tenantFor(Request $request): ?int
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

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
