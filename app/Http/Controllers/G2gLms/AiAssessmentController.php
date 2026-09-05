<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use App\Services\G2gLms\DeepSeekAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * AI-generated capability assessment.
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\Competency\AiAssessmentController`
 * - specifically its FIRST, simpler shape (`git show 513899d2` in hp_erp),
 * matching this package's approved three-table schema
 * (`competency_assessment_test` / `_question` / `_response`, no scope_type,
 * no attempt table, no rating-proposal table - see the migration's
 * docblock for why). The later evolved version (timers, narrower
 * generation scope, assign-to-non-jobrole via an attempt row, AI marking of
 * written answers, propose-then-confirm rating writes) depends on two
 * tables NOT in this package's approved list
 * (`competency_assessment_attempt`, `competency_assessment_rating_proposal`)
 * and is therefore NOT ported - see the final report for the exact
 * endpoint-by-endpoint scope line.
 *
 * PORTED, matching the frontend's `services/competency/ai-assessment.ts`
 * (confirmed by reading it before porting, per the package brief):
 *   jobroles, generate, publish, mine, submit, myResult.
 *
 * ALSO ADDED (not in the source's 3-endpoint file, but needed by the
 * frontend's separate `assessment-review.ts` admin surface and buildable
 * within the approved 3 tables alone, with no attempt/proposal table):
 *   tests (list, admin), show (one test incl. answer key, admin),
 *   scoreResponse (manual mark of one written answer, admin). `assign`,
 *   `attempts`, `answers(attemptId)` and the whole proposals/decide flow
 *   from assessment-review.ts are NOT implemented - they are attempt- and
 *   proposal-table-shaped by construction in the source and have no
 *   equivalent that fits the approved schema.
 *
 * THE EMPLOYEE ENDPOINTS (mine/submit/myResult) NAME NOBODY - same as the
 * source: the subject is always `lmsContext($request)['user_id']`, never a
 * request field, so there is no parameter through which one person's
 * answers could be pointed at another.
 *
 * mine()/submit()/myResult() resolve the caller's job role from
 * `tbluser.jobtitle_id` directly (the source's ORIGINAL, simpler
 * resolution - not the later `allocated_standards`-aware
 * `ResolvesEmployeeJobRole`, which does not exist in this codebase).
 */
class AiAssessmentController extends Controller
{
    use ResolvesLmsIdentity;

    private const FORMATS = ['mcq', 'short_answer'];

    private function guardAdmin(Request $request)
    {
        $context = $this->lmsContext($request);
        if ($this->isLmsStaffAdmin($context)) {
            return null;
        }

        return $this->lmsError('Your profile is not permitted to manage assessments.', 403);
    }

    /* ─── Admin: job roles ────────────────────────────────────────────────── */

    /** GET /ai-assessment/jobroles */
    public function jobroles(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $roles = DB::table('s_user_jobrole as j')
            ->leftJoin('jobrole_competency_map as m', function ($join) use ($sid) {
                $join->on('m.jobrole_id', '=', 'j.id')->where('m.sub_institute_id', '=', $sid);
            })
            ->where('j.sub_institute_id', $sid)
            ->whereNull('j.deleted_at')
            ->groupBy('j.id', 'j.jobrole', 'j.department')
            ->orderBy('j.jobrole')
            ->get(['j.id', 'j.jobrole as name', 'j.department', DB::raw('COUNT(m.id) as competency_count')]);

        $assessable = $roles->where('competency_count', '>', 0)->count();

        return response()->json([
            'status' => 1,
            'data'   => ['roles' => $roles, 'total' => $roles->count(), 'assessable' => $assessable],
            'empty_is_expected' => $assessable === 0,
            'empty_reason' => $assessable === 0
                ? 'None of your job roles has competencies mapped yet. Add them in Role Requirements before generating an assessment.'
                : null,
        ]);
    }

    /**
     * POST /ai-assessment/generate
     *
     * Body: jobrole_id, [formats[]], [questions_per_item], [title]
     */
    public function generate(Request $request, DeepSeekAssessmentService $ai)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), [
            'jobrole_id'         => 'required|integer',
            'formats'            => 'nullable|array',
            'formats.*'          => 'string|in:' . implode(',', self::FORMATS),
            'questions_per_item' => 'nullable|integer|min:1|max:5',
            'title'              => 'nullable|string|max:191',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $context   = $this->lmsContext($request);
        $sid       = $context['sub_institute_id'];
        $actor     = $context['user_id'] ?: null;
        $jobroleId = $request->integer('jobrole_id');
        $formats   = $request->input('formats') ?: self::FORMATS;
        $perItem   = (int) ($request->input('questions_per_item') ?: 1);

        if (!$ai->isConfigured()) {
            return response()->json([
                'status' => 0, 'reason' => 'not_configured',
                'message' => 'AI assessment generation is not configured. DEEPSEEK_API_KEY is not set.',
            ], 503);
        }

        $jobrole = DB::table('s_user_jobrole')->where('id', $jobroleId)->where('sub_institute_id', $sid)->first(['id', 'jobrole']);
        if (!$jobrole) {
            return response()->json(['status' => 0, 'message' => 'Job role not found.'], 404);
        }

        // THE GUARD, applied before the model is told anything: only real,
        // tenant-authored capability items for this job role are eligible.
        $items = DB::table('jobrole_competency_map as m')
            ->join('competency_kasba_item as k', 'k.competency_id', '=', 'm.competency_id')
            ->leftJoin('competency as c', 'c.id', '=', 'm.competency_id')
            ->where('m.sub_institute_id', $sid)->where('k.sub_institute_id', $sid)
            ->where('m.jobrole_id', $jobroleId)
            ->get(['k.id', 'k.kasba_type', 'k.item_label', 'c.name as competency_name']);

        if ($items->isEmpty()) {
            return response()->json([
                'status' => 0, 'reason' => 'no_items', 'empty_is_expected' => true,
                'message' => 'This job role has no competencies mapped to it, so there is nothing to assess. Add them in Role Requirements first.',
            ], 422);
        }

        try {
            $generated = $ai->chatJson([
                ['role' => 'system', 'content' => 'You write workplace capability assessments. You return only valid JSON.'],
                ['role' => 'user',   'content' => $this->prompt($jobrole->jobrole, $items, $formats, $perItem)],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'The assessment service did not return a usable result.', 'detail' => $e->getMessage()], 502);
        }

        $rows = $this->acceptable($generated, $items, $formats);
        if (!$rows) {
            return response()->json(['status' => 0, 'reason' => 'no_valid_questions', 'message' => 'No question in the generated result referenced a real capability item, so nothing was saved.'], 422);
        }

        $testId = null;
        DB::transaction(function () use (&$testId, $sid, $jobroleId, $jobrole, $actor, $ai, $request, $rows) {
            $testId = DB::table('competency_assessment_test')->insertGetId([
                'sub_institute_id' => $sid,
                'jobrole_id'       => $jobroleId,
                'title'            => $request->input('title') ?: ('Capability assessment — ' . $jobrole->jobrole),
                'model'            => $ai->model(),
                'status'           => 'draft',
                'generated_by'     => $actor,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($rows as $i => $r) {
                DB::table('competency_assessment_question')->insert([
                    'sub_institute_id' => $sid,
                    'test_id'          => $testId,
                    'kasba_item_id'    => $r['kasba_item_id'],
                    'format'           => $r['format'],
                    'question_text'    => $r['question_text'],
                    'options'          => $r['options'] !== null ? json_encode($r['options']) : null,
                    'correct_option'   => $r['correct_option'],
                    'model_answer'     => $r['model_answer'],
                    'max_score'        => 1,
                    'sort_order'       => $i,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        });

        return response()->json([
            'status' => 1,
            'data'   => [
                'test_id' => $testId, 'jobrole_id' => $jobroleId, 'model' => $ai->model(),
                'items_available' => $items->count(), 'questions_saved' => count($rows), 'status_is' => 'draft',
            ],
            'questions_requested' => $items->count() * $perItem,
            'questions_dropped'   => max(0, ($items->count() * $perItem) - count($rows)),
            'message' => 'Test generated as a draft. Publish it to make it visible to employees in this job role.',
        ], 201);
    }

    /**
     * POST /ai-assessment/publish
     *
     * One published test per job role: publishing supersedes any other
     * published test for that role.
     */
    public function publish(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), ['test_id' => 'required|integer', 'publish' => 'nullable|boolean']);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $sid    = $this->lmsContext($request)['sub_institute_id'];
        $testId = $request->integer('test_id');
        $wants  = $request->boolean('publish', true);

        $test = DB::table('competency_assessment_test')->where('id', $testId)->where('sub_institute_id', $sid)->whereNull('deleted_at')->first(['id', 'jobrole_id', 'status', 'title']);
        if (!$test) {
            return response()->json(['status' => 0, 'message' => 'Assessment not found.'], 404);
        }

        $questions = DB::table('competency_assessment_question')->where('test_id', $testId)->count();
        if ($wants && $questions === 0) {
            return response()->json(['status' => 0, 'reason' => 'no_questions', 'message' => 'This assessment has no questions, so it cannot be published.'], 422);
        }

        $superseded = 0;
        DB::transaction(function () use (&$superseded, $sid, $test, $wants) {
            if ($wants) {
                $superseded = DB::table('competency_assessment_test')
                    ->where('sub_institute_id', $sid)->where('jobrole_id', $test->jobrole_id)
                    ->where('id', '!=', $test->id)->where('status', 'published')
                    ->update(['status' => 'superseded', 'updated_at' => now()]);
            }

            DB::table('competency_assessment_test')->where('id', $test->id)->update([
                'status'       => $wants ? 'published' : 'draft',
                'published_at' => $wants ? now() : null,
                'updated_at'   => now(),
            ]);
        });

        return response()->json([
            'status' => 1,
            'data'   => ['test_id' => $test->id, 'jobrole_id' => (int) $test->jobrole_id, 'status_is' => $wants ? 'published' : 'draft', 'questions' => $questions, 'superseded' => $superseded],
            'message' => $wants
                ? ($superseded > 0
                    ? "Published. {$superseded} previously published assessment(s) for this job role were superseded and are no longer shown to employees. Their recorded answers are untouched."
                    : 'Published. Employees in this job role can now see it.')
                : 'Unpublished. Employees can no longer see it. Answers already recorded are untouched.',
        ]);
    }

    /* ─── Employee: mine / submit / myResult - take no subject ─────────────── */

    /** GET /ai-assessment/mine */
    public function mine(Request $request)
    {
        $context = $this->lmsContext($request);
        $sid = $context['sub_institute_id'];
        $me  = (int) $context['user_id'];

        $user = DB::table('tbluser')->where('id', $me)->where('sub_institute_id', $sid)->first(['id', 'jobtitle_id']);
        if (!$user || !$user->jobtitle_id) {
            return response()->json([
                'status' => 1, 'data' => ['test' => null, 'questions' => []],
                'empty_is_expected' => true, 'empty_reason' => 'You do not have a job role yet, so no assessment has been prepared for you.', 'scope' => 'self',
            ]);
        }

        $test = DB::table('competency_assessment_test')
            ->where('sub_institute_id', $sid)->where('jobrole_id', $user->jobtitle_id)
            ->where('status', 'published')->whereNull('deleted_at')
            ->orderByDesc('published_at')->first(['id', 'title', 'instructions', 'published_at']);

        if (!$test) {
            return response()->json([
                'status' => 1, 'data' => ['test' => null, 'questions' => []],
                'empty_is_expected' => true, 'empty_reason' => 'No assessment has been published for your job role yet.', 'scope' => 'self',
            ]);
        }

        $questions = DB::table('competency_assessment_question as q')
            ->leftJoin('competency_assessment_response as r', function ($j) use ($me) {
                $j->on('r.question_id', '=', 'q.id')->where('r.user_id', '=', $me);
            })
            ->where('q.test_id', $test->id)->orderBy('q.sort_order')
            // correct_option and model_answer are NOT selected - never sent to the taker.
            ->get(['q.id', 'q.format', 'q.question_text', 'q.options', 'q.max_score', 'r.answer_text', 'r.selected_option', 'r.answered_at']);

        $answered = $questions->whereNotNull('answered_at')->count();

        return response()->json([
            'status' => 1,
            'data'   => [
                'test'      => $test,
                'questions' => $questions->map(function ($q) {
                    $q->options = $q->options ? json_decode($q->options, true) : null;
                    return $q;
                })->values(),
                'total'      => $questions->count(),
                'answered'   => $answered,
                'unanswered' => $questions->count() - $answered,
                'submitted'  => $questions->count() > 0 && $answered === $questions->count(),
            ],
            'empty_is_expected' => $questions->isEmpty(),
            'scope' => 'self',
        ]);
    }

    /**
     * POST /ai-assessment/submit
     *
     * Records answers and auto-scores MCQs. Does NOT move anyone's
     * proficiency - a submitted rating is not a confirmed one.
     */
    public function submit(Request $request)
    {
        $context = $this->lmsContext($request);
        $sid = $context['sub_institute_id'];
        $me  = (int) $context['user_id'];

        $validator = Validator::make($request->all(), [
            'answers'                   => 'required|array|min:1',
            'answers.*.question_id'     => 'required|integer',
            'answers.*.selected_option' => 'nullable|string|max:50',
            'answers.*.answer_text'     => 'nullable|string|max:5000',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $ids = collect($request->input('answers'))->pluck('question_id')->map('intval')->all();

        $allowed = DB::table('competency_assessment_question as q')
            ->join('competency_assessment_test as t', 't.id', '=', 'q.test_id')
            ->join('tbluser as u', 'u.jobtitle_id', '=', 't.jobrole_id')
            ->where('u.id', $me)->where('u.sub_institute_id', $sid)
            ->where('q.sub_institute_id', $sid)->where('t.status', 'published')
            ->whereIn('q.id', $ids)
            ->get(['q.id', 'q.test_id', 'q.format', 'q.correct_option', 'q.max_score'])
            ->keyBy('id');

        $written = 0; $scored = 0;
        foreach ($request->input('answers') as $a) {
            $q = $allowed->get((int) ($a['question_id'] ?? 0));
            if (!$q) {
                continue;
            }

            $score = null; $by = null;
            if ($q->format === 'mcq' && $q->correct_option !== null) {
                $score = ((string) ($a['selected_option'] ?? '') === (string) $q->correct_option) ? $q->max_score : 0;
                $by = 'auto';
                $scored++;
            }

            DB::table('competency_assessment_response')->updateOrInsert(
                ['question_id' => $q->id, 'user_id' => $me],
                [
                    'sub_institute_id' => $sid,
                    'test_id'          => $q->test_id,
                    'answer_text'      => $a['answer_text'] ?? null,
                    'selected_option'  => $a['selected_option'] ?? null,
                    'score'            => $score,
                    'scored_by'        => $by,
                    'answered_at'      => now(),
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ]
            );
            $written++;
        }

        return response()->json([
            'status' => 1,
            'data'   => ['answers_written' => $written, 'auto_scored' => $scored, 'awaiting_review' => $written - $scored, 'dropped' => count($ids) - $written],
            'proficiency_unchanged' => true,
            'message' => 'Answers recorded. Multiple-choice answers are scored automatically; written answers await review. Your proficiency is not changed by submitting.',
        ]);
    }

    /**
     * GET /ai-assessment/my-result - TAKES NO SUBJECT.
     *
     * What the caller scored on the published test for their own job role.
     * Correct answers are still not sent - only the caller's own score and
     * the maximum, per question.
     *
     * No `attempt_id`/`passed`/`bands`/`proposals` here (unlike the source):
     * this package's schema has no attempt table (no timer, no per-sitting
     * id) and no rating-proposal table - see the class docblock.
     */
    public function myResult(Request $request)
    {
        $context = $this->lmsContext($request);
        $sid = $context['sub_institute_id'];
        $me  = (int) $context['user_id'];

        $user = DB::table('tbluser')->where('id', $me)->where('sub_institute_id', $sid)->first(['id', 'jobtitle_id']);
        if (!$user || !$user->jobtitle_id) {
            return response()->json(['status' => 1, 'data' => null, 'empty_is_expected' => true, 'empty_reason' => 'You do not have a job role yet.']);
        }

        $test = DB::table('competency_assessment_test')
            ->where('sub_institute_id', $sid)->where('jobrole_id', $user->jobtitle_id)
            ->when($request->filled('test_id'), fn ($q) => $q->where('id', $request->integer('test_id')))
            ->whereNull('deleted_at')
            ->orderByDesc('published_at')->first(['id', 'title']);

        if (!$test) {
            return response()->json(['status' => 1, 'data' => null, 'empty_is_expected' => true, 'empty_reason' => 'No assessment has been prepared for your job role.']);
        }

        $questions = DB::table('competency_assessment_question as q')
            ->leftJoin('competency_assessment_response as r', function ($j) use ($me) {
                $j->on('r.question_id', '=', 'q.id')->where('r.user_id', '=', $me);
            })
            ->where('q.test_id', $test->id)->orderBy('q.sort_order')
            // correct_option and model_answer remain unselected.
            ->get(['q.id', 'q.question_text', 'q.format', 'q.max_score', 'r.score', 'r.scored_by', 'r.answered_at']);

        $answered = $questions->whereNotNull('answered_at')->count();
        if ($answered === 0) {
            return response()->json(['status' => 1, 'data' => null, 'empty_is_expected' => true, 'empty_reason' => 'You have not submitted an assessment yet.']);
        }

        $scored = $questions->whereNotNull('score');
        $total  = (float) $scored->sum('score');
        $max    = (float) $scored->sum('max_score');

        return response()->json([
            'status' => 1,
            'data'   => [
                'test'      => $test,
                'questions' => $questions->values(),
                'total'     => $questions->count(),
                'answered'  => $answered,
                'awaiting_review' => $answered - $scored->count(),
                'score'     => $scored->count() > 0 ? $total : null,
                'max_score' => $scored->count() > 0 ? $max : null,
                'percent'   => $max > 0 ? round(($total / $max) * 100, 2) : null,
            ],
            'proficiency_unchanged' => true,
        ]);
    }

    /* ─── Admin: review (no attempt/proposal table - see class docblock) ───── */

    /**
     * GET /ai-assessment/tests
     *
     * Every generated test for the tenant, with question/answered counts.
     * "assigned"/"submitted" here mean "questions this test has" / "distinct
     * people who have answered at least one of them" - there is no separate
     * assignment concept without an attempt table.
     */
    public function tests(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $tests = DB::table('competency_assessment_test as t')
            ->leftJoin('s_user_jobrole as j', 'j.id', '=', 't.jobrole_id')
            ->where('t.sub_institute_id', $sid)->whereNull('t.deleted_at')
            ->when($request->filled('status'), fn ($q) => $q->where('t.status', $request->input('status')))
            ->orderByDesc('t.id')
            ->get(['t.id', 't.title', 't.status', 't.model', 't.published_at', 't.jobrole_id', 'j.jobrole']);

        $testIds = $tests->pluck('id')->all();

        $questionCounts = DB::table('competency_assessment_question')
            ->whereIn('test_id', $testIds ?: [0])
            ->select('test_id', DB::raw('COUNT(*) as total'))->groupBy('test_id')->pluck('total', 'test_id');

        $responseStats = DB::table('competency_assessment_response')
            ->whereIn('test_id', $testIds ?: [0])
            ->select('test_id', DB::raw('COUNT(DISTINCT user_id) as submitted'), DB::raw('SUM(CASE WHEN score IS NULL THEN 1 ELSE 0 END) as awaiting_review'))
            ->groupBy('test_id')->get()->keyBy('test_id');

        $data = $tests->map(function ($t) use ($questionCounts, $responseStats) {
            $stats = $responseStats[$t->id] ?? null;
            return [
                'id'              => (int) $t->id,
                'title'           => $t->title,
                'status'          => $t->status,
                'model'           => $t->model,
                'published_at'    => $t->published_at,
                'jobrole'         => $t->jobrole,
                'questions'       => (int) ($questionCounts[$t->id] ?? 0),
                'submitted'       => (int) ($stats->submitted ?? 0),
                'awaiting_review' => (int) ($stats->awaiting_review ?? 0),
            ];
        });

        return response()->json(['status' => 1, 'data' => $data]);
    }

    /**
     * GET /ai-assessment/tests/{id}
     *
     * ⚠ Returns correct answers. Admin-gated only - never exposed to mine().
     */
    public function show(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $test = DB::table('competency_assessment_test')->where('id', $id)->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();
        if (!$test) {
            return response()->json(['status' => 0, 'message' => 'Assessment not found.'], 404);
        }

        $questions = DB::table('competency_assessment_question')
            ->where('test_id', $id)->orderBy('sort_order')
            ->get(['id', 'format', 'question_text', 'options', 'correct_option', 'model_answer', 'max_score', 'sort_order', 'kasba_item_id'])
            ->map(function ($q) {
                $q->options = $q->options ? json_decode($q->options, true) : null;
                return $q;
            });

        return response()->json(['status' => 1, 'data' => ['test' => $test, 'questions' => $questions]]);
    }

    /**
     * POST /ai-assessment/responses/{id}/score
     *
     * Manually marks one written answer. Writes `scored_by = 'manual'`,
     * overriding any earlier auto/blank score.
     */
    public function scoreResponse(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $validator = Validator::make($request->all(), ['score' => 'required|numeric|min:0']);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $response = DB::table('competency_assessment_response')->where('id', $id)->where('sub_institute_id', $sid)->first();
        if (!$response) {
            return response()->json(['status' => 0, 'message' => 'Response not found.'], 404);
        }

        $maxScore = DB::table('competency_assessment_question')->where('id', $response->question_id)->value('max_score');
        $score = min((float) $request->input('score'), (float) ($maxScore ?? $request->input('score')));

        DB::table('competency_assessment_response')->where('id', $id)->update([
            'score'      => $score,
            'scored_by'  => 'manual',
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 1, 'message' => 'Answer scored.']);
    }

    /* ─── Prompt building / acceptance ───────────────────────────────────── */

    private function prompt(string $jobrole, $items, array $formats, int $perItem): string
    {
        $formatList = implode(' and ', $formats);
        $lines = $items->map(fn ($i) => "- id={$i->id} | type={$i->kasba_type} | item={$i->item_label} | competency=" . ($i->competency_name ?? 'unnamed'))->implode("\n");

        return <<<TXT
        You are writing a workplace capability assessment for the job role "{$jobrole}".

        Write {$perItem} question(s) for EACH capability item listed below.
        Permitted formats: {$formatList}.

        RULES
        - Every question MUST carry the kasba_item_id of the item it assesses.
        - Use ONLY the ids listed. Do not invent an id.
        - mcq questions need "options" (3-5 strings) and "correct_option" (the exact option text).
        - short_answer questions need "model_answer" and no options.
        - Assess the item, not general knowledge.

        ITEMS
        {$lines}

        Return JSON: {"questions":[{"kasba_item_id":123,"format":"mcq","question_text":"...","options":["..."],"correct_option":"...","model_answer":null}]}
        TXT;
    }

    private function acceptable($generated, $items, array $formats): array
    {
        $valid = $items->pluck('id')->map('intval')->flip();
        $out = [];

        foreach ((array) ($generated['questions'] ?? []) as $q) {
            $id = (int) ($q['kasba_item_id'] ?? 0);
            $fmt = (string) ($q['format'] ?? '');
            $text = trim((string) ($q['question_text'] ?? ''));

            if (!$valid->has($id) || !in_array($fmt, $formats, true) || $text === '') {
                continue;
            }

            $options = is_array($q['options'] ?? null) ? array_values($q['options']) : null;
            $correct = isset($q['correct_option']) ? (string) $q['correct_option'] : null;

            if ($fmt === 'mcq' && (!$options || $correct === null || !in_array($correct, $options, true))) {
                continue;
            }

            $out[] = [
                'kasba_item_id'  => $id,
                'format'         => $fmt,
                'question_text'  => $text,
                'options'        => $fmt === 'mcq' ? $options : null,
                'correct_option' => $fmt === 'mcq' ? $correct : null,
                'model_answer'   => $fmt === 'short_answer' ? (string) ($q['model_answer'] ?? '') : null,
            ];
        }

        return $out;
    }
}
