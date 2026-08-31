<?php

namespace App\Http\Controllers\lms\pal;

use App\Http\Controllers\Controller;
use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\Coherence\CoherenceRecommender;
use App\Services\PAL\Coherence\MasteryUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Set Coherence Map, rendered as an LMS page.
 *
 * Goes through the SAME services the JSON API uses - CoherenceMapRepository and
 * CoherenceRecommender - so the Blade view and /api/pal/coherence/* can never
 * disagree about a learner's readiness. That is the whole reason this is a thin
 * controller rather than a second implementation.
 *
 * TENANCY COMES FROM THE SESSION, NOT THE QUERY STRING. `routes/web.php` mounts
 * this outside the pal.auth middleware that guards the API, so there is no token
 * to read a scope from. `sub_institute_id` is taken from the logged-in session
 * and passed explicitly into every repository call; standard_id and subject_id
 * may come from the query string, but are validated against the scopes that
 * actually exist for that institute before use. A crafted ?standard_id= for
 * another school resolves to nothing rather than to their curriculum.
 */
class CoherenceMapWebController extends Controller
{
    public function __construct(
        protected CoherenceMapRepository $map,
        protected CoherenceRecommender $recommender,
        protected MasteryUpdater $mastery,
    ) {}

    /**
     * GET /lms/coherence-map
     *
     * Renders the shell with the first payload already embedded, so the graph
     * paints on first byte instead of after a round trip. Subsequent scope
     * changes come back through this same action as JSON (?format=json), which
     * keeps one code path for both.
     */
    public function index(Request $request)
    {
        $tenant = $this->tenant($request);

        if ($tenant === null) {
            return $this->bail($request, 'Your session does not name an institute, so no map can be scoped.');
        }

        // Only scopes that are actually projected into Neo4j. A picker that
        // offers a scope with no map produces a 404 the user cannot act on.
        $scopes = $this->map->scopes($tenant);

        if ($scopes === []) {
            return $this->bail(
                $request,
                'No coherence map has been projected for this institute yet. Run '
                . 'php artisan pal:coherence-sync --tenant=' . $tenant . ' --standard=<id> --subject=<id>.'
            );
        }

        $scope = $this->resolveScope($request, $scopes);

        $graph = $this->map->map(
            $scope['standard_id'],
            $scope['subject_id'],
            $scope['learner_id'],
            $tenant
        );

        $health = $this->map->health($scope['standard_id'], $scope['subject_id'], $tenant);

        $payload = [
            'scope'     => $scope,
            'scopes'    => $scopes,
            'graph'     => $graph,
            'health'    => $health + [
                'fit_to_use' => $health['concepts'] > 0 && $health['acyclic'] && $health['roots'] > 0,
            ],
            'readiness' => $scope['learner_id'] === null
                ? []
                : $this->map->readiness($scope['standard_id'], $scope['subject_id'], $scope['learner_id'], $tenant),
            'learners'  => $this->learnersFor($tenant, $scope['standard_id']),
        ];

        if ($request->get('format') === 'json') {
            return response()->json(['success' => true, 'data' => $payload]);
        }

        return view('lms.pal.coherence-map', ['payload' => $payload]);
    }

    /**
     * POST /lms/coherence-map/answer
     *
     * One answered question in; new mastery and the next action out - the same
     * shape as POST /api/pal/coherence/evidence, because the Blade view drives
     * the identical loop.
     *
     * The concept is re-checked against the session's institute before anything
     * is written. Without that, a posted concept_id could move mastery on
     * another school's curriculum.
     */
    public function answer(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if ($tenant === null) {
            return $this->fail('Your session does not name an institute.', 422);
        }

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

        $conceptId = (int) $validated['concept_id'];
        $learnerId = (int) $validated['learner_id'];

        $concept = DB::table('lms_concept')
            ->where('id', $conceptId)
            ->where('sub_institute_id', $tenant)
            ->first(['standard_id', 'subject_id']);

        if ($concept === null) {
            return $this->fail('That concept does not belong to this institute.', 403);
        }

        // Same check on the learner: a staff user may only post evidence for a
        // student of their own institute.
        $learnerTenant = DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id');

        if ((int) $learnerTenant !== $tenant) {
            return $this->fail('That learner does not belong to this institute.', 403);
        }

        $state = $this->mastery->record($learnerId, $conceptId, $tenant, [
            'question_id'       => $validated['question_id'] ?? null,
            'content_id'        => $validated['content_id'] ?? null,
            'session_id'        => $validated['session_id'] ?? null,
            'correct'           => (bool) $validated['correct'],
            'misconception_tag' => $validated['misconception_tag'] ?? null,
            'duration_seconds'  => $validated['duration_seconds'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
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
                    'trajectory' => $state['trajectory'],
                ],
                // Re-read so the client can recolour the whole canvas from one
                // response rather than refetching the map.
                'readiness' => $this->map->readiness(
                    (int) $concept->standard_id,
                    (int) $concept->subject_id,
                    $learnerId,
                    $tenant
                ),
                'next' => $this->recommender->nextBestAction(
                    $learnerId,
                    (int) $concept->standard_id,
                    (int) $concept->subject_id
                ),
            ],
        ]);
    }

    /**
     * GET /lms/coherence-map/concept/{conceptId}
     *
     * The drawer payload for one concept: its own metadata, what it needs, what
     * it unlocks, what teaches it, what assesses it, and - when a learner is
     * named - where the chain actually breaks.
     *
     * Separate from index() because it is fetched on click, and pulling content
     * and questions for all 118 concepts up front would be most of the payload
     * for data the user will never look at.
     */
    public function concept(Request $request, int $conceptId): JsonResponse
    {
        $tenant = $this->tenant($request);

        if ($tenant === null) {
            return $this->fail('Your session does not name an institute.', 422);
        }

        $home = DB::table('lms_concept')
            ->where('id', $conceptId)
            ->where('sub_institute_id', $tenant)
            ->first(['id', 'name', 'description', 'standard_id', 'subject_id', 'chapter_id']);

        if ($home === null) {
            return $this->fail('No such concept in this institute.', 404);
        }

        $learnerId = $request->filled('learner_id') ? (int) $request->get('learner_id') : null;

        $graph = $this->map->map(
            (int) $home->standard_id,
            (int) $home->subject_id,
            $learnerId,
            $tenant
        );

        $self = null;
        $byId = [];

        foreach ($graph['nodes'] as $n) {
            $byId[(int) $n['id']] = $n;

            if ((int) $n['id'] === $conceptId) {
                $self = $n;
            }
        }

        $name = fn ($ids) => array_values(array_map(fn ($i) => [
            'id'      => (int) $i,
            'name'    => (string) ($byId[$i]['name'] ?? ''),
            'mastery' => $byId[$i]['mastery'] ?? null,
            'gate'    => $byId[$i]['gate'] ?? null,
        ], (array) $ids));

        return response()->json([
            'success' => true,
            'data'    => [
                'concept'        => $self,
                'prerequisites'  => $name($self['prereq_ids'] ?? []),
                'unlocks'        => $name($self['unlocks_ids'] ?? []),
                'content'        => $this->map->contentFor($conceptId, [], 8),
                'questions'      => $this->map->questionsFor($conceptId, 8),
                'root_blockers'  => $learnerId === null ? [] : $this->map->rootBlockers($conceptId, $learnerId),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════

    /**
     * Pick the scope to open on.
     *
     * A requested standard/subject is honoured only if it appears in the list of
     * scopes that exist for this institute - that is what stops a crafted query
     * string reaching another school's map. Otherwise the richest scope wins,
     * which is the one with the most prerequisite edges: a map with concepts but
     * no edges is a list, and opening on it makes the feature look broken.
     *
     * @param  array<int, array<string, mixed>>  $scopes
     * @return array{standard_id: int, subject_id: int, sub_institute_id: int, standard_name: string, subject_name: string, learner_id: int|null}
     */
    private function resolveScope(Request $request, array $scopes): array
    {
        $wantStandard = (int) $request->get('standard_id');
        $wantSubject = (int) $request->get('subject_id');

        $chosen = null;

        if ($wantStandard > 0 && $wantSubject > 0) {
            foreach ($scopes as $s) {
                if ($s['standard_id'] === $wantStandard && $s['subject_id'] === $wantSubject) {
                    $chosen = $s;
                    break;
                }
            }
        }

        $chosen ??= $scopes[0];

        return [
            'sub_institute_id' => (int) $chosen['sub_institute_id'],
            'standard_id'      => (int) $chosen['standard_id'],
            'subject_id'       => (int) $chosen['subject_id'],
            'standard_name'    => (string) $chosen['standard_name'],
            'subject_name'     => (string) $chosen['subject_name'],
            'learner_id'       => $request->filled('learner_id') ? (int) $request->get('learner_id') : null,
        ];
    }

    /**
     * Students of this institute enrolled in this class, for the overlay picker.
     *
     * Capped: a large school has thousands of students and this is a select
     * element, not a report. Only learners with mastery evidence are listed
     * first, because those are the only ones the overlay says anything about.
     *
     * @return array<int, array{id: int, name: string, evidence: int}>
     */
    private function learnersFor(int $tenant, int $standardId): array
    {
        $rows = DB::table('tblstudent_enrollment as e')
            ->join('tblstudent as s', 's.id', '=', 'e.student_id')
            ->leftJoin('pal_concept_mastery as m', 'm.learner_id', '=', 's.id')
            ->where('e.sub_institute_id', $tenant)
            ->where('e.standard_id', $standardId)
            ->groupBy('s.id', 's.first_name', 's.last_name')
            ->orderByRaw('COUNT(m.id) DESC')
            ->orderBy('s.first_name')
            ->limit(200)
            ->get([
                's.id',
                's.first_name',
                's.last_name',
                DB::raw('COUNT(m.id) AS evidence'),
            ]);

        return $rows->map(fn ($r) => [
            'id'       => (int) $r->id,
            'name'     => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ('Student ' . $r->id),
            'evidence' => (int) $r->evidence,
        ])->all();
    }

    /**
     * The institute from the session. Staff may hold a CSV of institutes; the
     * first is their primary, which mirrors how PalApiAuth and
     * PalContentIntelligenceController resolve it.
     */
    private function tenant(Request $request): ?int
    {
        $sub = (string) (session()->get('sub_institute_id') ?? $request->get('sub_institute_id') ?? '');

        if (str_contains($sub, ',')) {
            $sub = trim(explode(',', $sub)[0]);
        }

        return $sub === '' ? null : (int) $sub;
    }

    /**
     * A dead end that still renders. The view handles an empty payload and shows
     * the reason, because a white screen tells an administrator nothing about
     * which artisan command they have not run.
     */
    private function bail(Request $request, string $message)
    {
        if ($request->get('format') === 'json') {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return view('lms.pal.coherence-map', ['payload' => null, 'reason' => $message]);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
