<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Models\Eso\DecisionLog;
use App\Services\Eso\EsoPalRenderer;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Adaptive Learning Engine (Learning ESO) API.
 *
 * Thin HTTP layer over EsoPolicyService — every route here resolves a
 * decision or records evidence; none of them choose content, that is the
 * service's job. Auth + per-learner ownership is enforced upstream by
 * `pal.auth` (see routes/pal_eso_api.php), matching every other PAL V4
 * controller's convention: routes name the learner `{learnerId}` so
 * PalApiAuth::resolveTargetLearner() can scope it automatically.
 */
class EsoEngineController extends Controller
{
    public function __construct(
        protected EsoPolicyService $policy,
        protected EsoPalRenderer $renderer,
    ) {
    }

    private function ok($data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /**
     * The caller's own institute, from the JWT — for endpoints that list
     * data (not per-learner state) and so don't go through
     * PalApiAuth::resolveTargetLearner()'s per-learner ownership check.
     */
    private function callerInstitute(Request $request): ?int
    {
        $auth = $request->attributes->get('pal_auth');
        $value = is_array($auth) ? ($auth['sub_institute_id'] ?? null) : null;

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * GET /api/pal/eso/chapter-concepts/{chapterId}
     *
     * Navigation support for the "Start Adaptive Learning" entry point —
     * lists only the concepts in a chapter that actually have K/A/S nodes
     * (i.e. are ESO-ready), so the entry point can hide itself entirely for
     * chapters Phase 0 tagging hasn't reached yet. Read-only; makes no
     * decision and mutates no state.
     */
    public function chapterConcepts(Request $request, int $chapterId): JsonResponse
    {
        $tenant = $this->callerInstitute($request);

        $ready = $this->policy->esoReadyConceptsForChapters([$chapterId], $tenant)
            ->map(fn ($c) => (array) $c)
            ->values()
            ->all();

        return $this->ok(['chapter_id' => $chapterId, 'concepts' => $ready]);
    }

    /** The learner's tenant — every EsoPolicyService call needs it. */
    private function subInstituteId(int $learnerId): ?int
    {
        $value = DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * GET /api/pal/eso/diagnostic/{learnerId}/{conceptId}
     */
    public function diagnostic(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $items = $this->policy->diagnosticItems($conceptId, $subInstituteId);

        return $this->ok(['concept_id' => $conceptId, 'items' => $items]);
    }

    /**
     * GET /api/pal/eso/practice-item/{learnerId}/{nodeId}
     */
    public function practiceItem(Request $request, int $learnerId, int $nodeId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $item = $this->policy->practiceItem($nodeId, $subInstituteId);
        if ($item === null) {
            return $this->fail('No tagged practice item is available for this node yet.', 404);
        }

        return $this->ok($item);
    }

    /**
     * POST /api/pal/eso/diagnostic/{learnerId}/{conceptId}/submit
     * body: { responses: [{node_id, answer_master_id}] }
     */
    public function submitDiagnostic(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $validated = $request->validate([
            'responses' => 'required|array|min:1',
            'responses.*.node_id' => 'required|integer',
            'responses.*.answer_master_id' => 'required|integer',
        ]);

        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $results = $this->policy->scoreDiagnostic($learnerId, $conceptId, $subInstituteId, $validated['responses']);

        return $this->ok(['concept_id' => $conceptId, 'nodes' => $results]);
    }

    /**
     * GET /api/pal/eso/next-action/{learnerId}/{conceptId}
     *
     * The core resolver — "the next best learning action for this student,
     * on this concept, now." Writes one eso_decision_log row per call.
     */
    public function nextAction(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        return $this->ok($this->policy->nextAction($learnerId, $conceptId, $subInstituteId));
    }

    /**
     * GET /api/pal/eso/concept-mastery-details/{learnerId}/{conceptId}
     *
     * The "Mastery details" modal's data: status, confidence note, the 6
     * mastery signals with descriptions, guided-vs-independent support
     * split, misconception history, and recent individual responses. See
     * EsoPolicyService::conceptMasteryDetails().
     */
    public function conceptMasteryDetails(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $details = $this->policy->conceptMasteryDetails($learnerId, $conceptId, $subInstituteId);
        if ($details === null) {
            return $this->fail('Unknown concept.', 404);
        }

        return $this->ok($details);
    }

    /**
     * GET /api/pal/eso/knowledge-map/{learnerId}/{conceptId}
     *
     * The whole chapter's real concept-relationship graph, with this concept
     * marked as current — on the same ESO mastery pipeline as the rest of
     * this student's dashboard (not the separate BKT/Coherence-Map system).
     * See EsoPolicyService::chapterKnowledgeMap().
     */
    public function knowledgeMap(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $map = $this->policy->chapterKnowledgeMap($learnerId, $conceptId, $subInstituteId);
        if ($map === null) {
            return $this->fail('Unknown concept.', 404);
        }

        return $this->ok($map);
    }

    /**
     * POST /api/pal/eso/practice/{learnerId}/{nodeId}/attempt
     * body: { concept_id, answer_master_id, hint_used?, mode? }
     *
     * Correctness is never taken from the request — recordAttempt() resolves
     * it server-side from answer_master_id.
     */
    public function recordAttempt(Request $request, int $learnerId, int $nodeId): JsonResponse
    {
        $validated = $request->validate([
            'concept_id' => 'required|integer',
            'answer_master_id' => 'required|integer',
            'hint_used' => 'nullable|boolean',
            'mode' => 'nullable|in:guided,independent',
        ]);

        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $attempt = [
            'answer_master_id' => $validated['answer_master_id'],
            'hint_used' => $validated['hint_used'] ?? false,
            'mode' => $validated['mode'] ?? null,
        ];

        $result = $this->policy->recordAttempt($learnerId, $nodeId, (int) $validated['concept_id'], $subInstituteId, $attempt);

        return $this->ok($result);
    }

    /**
     * POST /api/pal/eso/retrieval/{learnerId}/{nodeId}/check
     * body: { concept_id, responses: [{answer_master_id}] }
     */
    public function retrievalCheck(Request $request, int $learnerId, int $nodeId): JsonResponse
    {
        $validated = $request->validate([
            'concept_id' => 'required|integer',
            'responses' => 'required|array|min:1',
            'responses.*.answer_master_id' => 'required|integer',
        ]);

        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $result = $this->policy->retrievalCheck($learnerId, $nodeId, (int) $validated['concept_id'], $subInstituteId, $validated['responses']);

        return $this->ok($result);
    }

    /**
     * GET /api/pal/eso/retrieval-items/{learnerId}/{nodeId}
     */
    public function retrievalItems(Request $request, int $learnerId, int $nodeId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        return $this->ok($this->policy->retrievalItems($nodeId, $subInstituteId));
    }

    /**
     * GET /api/pal/eso/due-for-retrieval/{learnerId}
     */
    public function dueForRetrieval(Request $request, int $learnerId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        return $this->ok($this->policy->dueForRetrieval($learnerId, $subInstituteId)->values());
    }

    /**
     * GET /api/pal/eso/student-dashboard/{learnerId}?syear=
     *
     * The main-dashboard variant of chapter-dashboard: no {chapterId} in the
     * URL — the chapter is auto-picked across the student's whole enrollment
     * for $syear. See EsoPolicyService::studentDashboard().
     */
    public function studentDashboard(Request $request, int $learnerId): JsonResponse
    {
        $syear = $request->input('syear');
        if ($syear === null || $syear === '') {
            return $this->fail('syear is required.');
        }

        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $dashboard = $this->policy->studentDashboard($learnerId, $subInstituteId, (string) $syear);
        if ($dashboard === null) {
            return $this->fail('No enrollment found for this student in that academic year.', 404);
        }

        return $this->ok($dashboard);
    }

    /**
     * GET /api/pal/eso/chapter-dashboard/{learnerId}/{chapterId}
     *
     * The "where am I" screen a student sees before drilling into a
     * concept: chapter-wide progress, the current concept's next step (via
     * the same resolver nextAction() uses, silently — no decision-log row
     * for a plain page view), and the mastery-signal panel.
     */
    public function chapterDashboard(Request $request, int $learnerId, int $chapterId): JsonResponse
    {
        $subInstituteId = $this->subInstituteId($learnerId);
        if ($subInstituteId === null) {
            return $this->fail('Unknown learner.', 404);
        }

        $dashboard = $this->policy->chapterDashboard($learnerId, $chapterId, $subInstituteId);
        if ($dashboard === null) {
            return $this->fail('Unknown chapter.', 404);
        }

        return $this->ok($dashboard);
    }

    /**
     * GET /api/pal/eso/decision-log/{learnerId}/{conceptId}
     *
     * The plain-language audit trace for parents/teachers — Phase 7's "never
     * 'the AI decided'" requirement.
     */
    public function decisionLog(Request $request, int $learnerId, int $conceptId): JsonResponse
    {
        $rows = DecisionLog::forStudent($learnerId)
            ->forConcept($conceptId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'node_id', 'rule_fired', 'action', 'llm_instruction', 'state_snapshot', 'created_at']);

        return $this->ok($rows);
    }

    /**
     * POST /api/pal/eso/render
     * body: { learner_id, instruction, context? }
     *
     * The only endpoint that calls an LLM. Renders an already-decided
     * instruction conversationally; never chooses content.
     */
    public function render(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'learner_id' => 'required|integer',
            'instruction' => 'required|string',
            'context' => 'nullable|array',
        ]);

        $rendered = $this->renderer->render($validated['instruction'], $validated['context'] ?? []);

        if ($rendered === null) {
            // LLM unavailable or failed — the caller falls back to the plain
            // instruction text rather than blocking the student.
            return $this->ok(['rendered' => null, 'fallback_text' => $validated['instruction']]);
        }

        return $this->ok(['rendered' => $rendered]);
    }
}
