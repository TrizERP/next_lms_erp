<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\LmsQuizAttemptModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questionpaperModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The teacher-authored, non-adaptive LMS "Quiz" feature.
 *
 * This is NOT the PAL Adaptive Quiz (palController/BktEngine) and does not
 * touch it. A quiz is a `question_paper` row with `exam_type = 'quiz'` — the
 * same discriminator column that already carries 'online'/'offline'/
 * 'practice'/'term' in this schema — so quiz content lives in the same
 * `question_paper` / `lms_question_master` / `answer_master` tables every
 * other paper-based feature uses (see ApiQuestionPaperController,
 * onlineExamController). The only new table is `lms_quiz_attempts`, which
 * records a student's attempt (see its migration for why no existing table
 * fit). Scoring is always computed here, server-side, from `answer_master`
 * (`correct_answer = 1`) — never from a client-supplied correctness flag.
 */
class QuizAttemptApiController extends Controller
{
    private const EXAM_TYPE = 'quiz';

    /**
     * Resolve the "multiple" (MCQ) question_type_master id for this school,
     * falling back to the global sub_institute_id=1 row, and finally to 1 -
     * the id both carry in every school sampled while building this feature.
     */
    private function mcqQuestionTypeId(int $subInstituteId): int
    {
        $id = DB::table('question_type_master')
            ->where('question_type', 'multiple')
            ->where('sub_institute_id', $subInstituteId)
            ->value('id');

        if (!$id) {
            $id = DB::table('question_type_master')
                ->where('question_type', 'multiple')
                ->where('sub_institute_id', 1)
                ->value('id');
        }

        return (int) ($id ?: 1);
    }

    /**
     * GET /api/quiz — quizzes the caller may see.
     *
     * Student: quizzes published for their enrolled standard this school/year.
     * Teacher: the quizzes they authored, with live attempt stats.
     * Anyone else (admin/staff browsing): every quiz for the school/year.
     */
    public function index(Request $request): JsonResponse
    {
        $subInstituteId = (int) $request->input('sub_institute_id');
        $syear = (int) $request->input('syear');
        $userId = (int) $request->input('user_id');
        $profileName = strtoupper((string) $request->input('user_profile_name', ''));

        if (!$subInstituteId || !$syear) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required parameters: sub_institute_id and syear are required',
            ], 422);
        }

        if ($profileName === 'STUDENT') {
            $enrollment = tblstudentEnrollmentModel::select('standard_id')
                ->where([
                    'student_id' => $userId,
                    'syear' => $syear,
                    'sub_institute_id' => $subInstituteId,
                ])->first();

            if (!$enrollment) {
                return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => []], 200);
            }

            $quizzes = questionpaperModel::select(
                'question_paper.*',
                'standard.name as standard_name',
                'ssm.display_name as subject_name',
                DB::raw('(SELECT a.status FROM lms_quiz_attempts a WHERE a.question_paper_id = question_paper.id AND a.student_id = ' . $userId . ' ORDER BY a.id DESC LIMIT 1) as my_attempt_status'),
                DB::raw('(SELECT a.id FROM lms_quiz_attempts a WHERE a.question_paper_id = question_paper.id AND a.student_id = ' . $userId . ' ORDER BY a.id DESC LIMIT 1) as my_attempt_id'),
                DB::raw('(SELECT a.score_marks FROM lms_quiz_attempts a WHERE a.question_paper_id = question_paper.id AND a.student_id = ' . $userId . ' AND a.status = "completed" ORDER BY a.id DESC LIMIT 1) as my_score'),
                DB::raw('(SELECT a.max_marks FROM lms_quiz_attempts a WHERE a.question_paper_id = question_paper.id AND a.student_id = ' . $userId . ' AND a.status = "completed" ORDER BY a.id DESC LIMIT 1) as my_max_marks')
            )
                ->join('standard', 'standard.id', '=', 'question_paper.standard_id')
                ->leftJoin('sub_std_map as ssm', function ($join) {
                    $join->on('ssm.subject_id', '=', 'question_paper.subject_id')
                        ->on('ssm.standard_id', '=', 'question_paper.standard_id');
                })
                ->where('question_paper.exam_type', self::EXAM_TYPE)
                ->where('question_paper.sub_institute_id', $subInstituteId)
                ->where('question_paper.syear', $syear)
                ->where('standard.id', $enrollment->standard_id)
                ->orderByDesc('question_paper.id')
                ->get();
        } else {
            $query = questionpaperModel::select(
                'question_paper.*',
                'standard.name as standard_name',
                'ssm.display_name as subject_name',
                DB::raw('(SELECT COUNT(*) FROM lms_quiz_attempts a WHERE a.question_paper_id = question_paper.id AND a.status = "completed") as attempt_count'),
                DB::raw('(SELECT AVG(a.score_marks) FROM lms_quiz_attempts a WHERE a.question_paper_id = question_paper.id AND a.status = "completed") as avg_score')
            )
                ->join('standard', 'standard.id', '=', 'question_paper.standard_id')
                ->leftJoin('sub_std_map as ssm', function ($join) {
                    $join->on('ssm.subject_id', '=', 'question_paper.subject_id')
                        ->on('ssm.standard_id', '=', 'question_paper.standard_id');
                })
                ->where('question_paper.exam_type', self::EXAM_TYPE)
                ->where('question_paper.sub_institute_id', $subInstituteId)
                ->where('question_paper.syear', $syear);

            if ($profileName === 'TEACHER') {
                $query->where('question_paper.created_by', $userId);
            }

            $quizzes = $query->orderByDesc('question_paper.id')->get();
        }

        return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => $quizzes], 200);
    }

    /**
     * POST /api/quiz — a teacher publishes a quiz.
     *
     * Each submitted question is persisted as a REAL `lms_question_master` row
     * (so it carries the same chapter_id/standard_id/subject_id every other
     * question in the bank does, and is reachable by every feature that reads
     * that table), with one `answer_master` row per option and exactly one
     * `correct_answer = 1`. The resulting question_ids become a `question_paper`
     * row with exam_type='quiz'. All-or-nothing: wrapped in a transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $subInstituteId = (int) $request->input('sub_institute_id');
        $syear = (int) $request->input('syear');
        $userId = (int) $request->input('user_id');
        $standardId = $request->input('standard_id');
        $subjectId = $request->input('subject_id');
        $gradeId = $request->input('grade_id');
        $chapterId = $request->input('chapter_id');
        $title = trim((string) $request->input('title', ''));
        $timeAllowed = $request->input('time_allowed');
        $questions = $request->input('questions', []);

        if (!$subInstituteId || !$syear || !$userId || !$standardId || !$subjectId) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required parameters: sub_institute_id, syear, user_id, standard_id and subject_id are required',
            ], 422);
        }

        if ($title === '') {
            return response()->json(['status_code' => 0, 'message' => 'A quiz title is required'], 422);
        }

        if (!is_array($questions) || count($questions) === 0) {
            return response()->json(['status_code' => 0, 'message' => 'At least one question is required'], 422);
        }

        $clean = [];
        foreach ($questions as $index => $q) {
            $text = trim((string) ($q['text'] ?? ''));
            $options = array_values(array_filter(array_map(
                fn ($o) => trim((string) $o),
                is_array($q['options'] ?? null) ? $q['options'] : []
            ), fn ($o) => $o !== ''));
            $correctIndex = (int) ($q['correctOptionIndex'] ?? -1);

            if ($text === '') {
                return response()->json(['status_code' => 0, 'message' => 'Question ' . ($index + 1) . ' has no text'], 422);
            }
            if (count($options) < 2) {
                return response()->json(['status_code' => 0, 'message' => 'Question ' . ($index + 1) . ' needs at least two options'], 422);
            }
            if ($correctIndex < 0 || $correctIndex >= count($options)) {
                return response()->json(['status_code' => 0, 'message' => 'Question ' . ($index + 1) . ' has no valid correct answer selected'], 422);
            }

            $clean[] = ['text' => $text, 'options' => $options, 'correctIndex' => $correctIndex];
        }

        $questionTypeId = $this->mcqQuestionTypeId($subInstituteId);

        try {
            $questionPaperId = DB::transaction(function () use (
                $clean, $subInstituteId, $syear, $userId, $standardId, $subjectId,
                $gradeId, $chapterId, $title, $timeAllowed, $questionTypeId, $request
            ) {
                $questionIds = [];
                $totalMarks = 0;

                foreach ($clean as $q) {
                    $qId = lmsQuestionMasterModel::insertGetId([
                        'question_type_id' => $questionTypeId,
                        'grade_id' => $gradeId,
                        'standard_id' => $standardId,
                        'subject_id' => $subjectId,
                        'chapter_id' => $chapterId,
                        'question_title' => $q['text'],
                        'points' => 1,
                        'multiple_answer' => 0,
                        'sub_institute_id' => $subInstituteId,
                        'status' => 1,
                        'created_by' => $userId,
                        'created_on' => now(),
                    ]);

                    $rows = [];
                    foreach ($q['options'] as $i => $optionText) {
                        $rows[] = [
                            'question_id' => $qId,
                            'answer' => $optionText,
                            'correct_answer' => $i === $q['correctIndex'] ? 1 : 0,
                            'sub_institute_id' => $subInstituteId,
                            'created_by' => $userId,
                            'created_on' => now(),
                        ];
                    }
                    answermasterModel::insert($rows);

                    $questionIds[] = $qId;
                    $totalMarks += 1;
                }

                return questionpaperModel::insertGetId([
                    'grade_id' => $gradeId,
                    'standard_id' => $standardId,
                    'subject_id' => $subjectId,
                    'paper_name' => $title,
                    'paper_desc' => $request->input('description'),
                    'time_allowed' => $timeAllowed,
                    'timelimit_enable' => $timeAllowed ? 1 : 0,
                    'total_ques' => count($questionIds),
                    'total_marks' => $totalMarks,
                    'question_ids' => implode(',', $questionIds),
                    'shuffle_question' => 0,
                    'attempt_allowed' => 1,
                    'show_feedback' => 1,
                    'show_hide' => 0,
                    'result_show_ans' => 1,
                    'created_by' => $userId,
                    'sub_institute_id' => $subInstituteId,
                    'syear' => $syear,
                    'exam_type' => self::EXAM_TYPE,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('QuizAttemptApiController::store failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['status_code' => 0, 'message' => 'Failed to publish quiz: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Quiz published successfully',
            'data' => ['id' => $questionPaperId],
        ], 201);
    }

    /**
     * GET /api/quiz/{id} — quiz meta + questions, WITHOUT which option is
     * correct. Used for a teacher's preview and is the shape `startAttempt`
     * also returns to a student.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $quiz = questionpaperModel::where('exam_type', self::EXAM_TYPE)->find($id);

        if (!$quiz) {
            return response()->json(['status_code' => 0, 'message' => 'Quiz not found'], 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'id' => $quiz->id,
                'title' => $quiz->paper_name,
                'description' => $quiz->paper_desc,
                'time_allowed' => $quiz->time_allowed,
                'total_marks' => $quiz->total_marks,
                'total_ques' => $quiz->total_ques,
                'subject_id' => $quiz->subject_id,
                'standard_id' => $quiz->standard_id,
                'questions' => $this->questionsForTaking($quiz->question_ids),
            ],
        ], 200);
    }

    /** Question + option text only — no `correct_answer` leaked. */
    private function questionsForTaking(?string $questionIdsCsv): array
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $questionIdsCsv))));

        if (empty($ids)) {
            return [];
        }

        $questions = lmsQuestionMasterModel::whereIn('id', $ids)->get(['id', 'question_title', 'points']);
        $optionsByQuestion = answermasterModel::whereIn('question_id', $ids)
            ->orderBy('id')
            ->get(['id', 'question_id', 'answer'])
            ->groupBy('question_id');

        // Preserve the paper's own question order, not whatever order the
        // whereIn() happened to come back in.
        $byId = $questions->keyBy('id');

        $out = [];
        foreach ($ids as $qid) {
            $question = $byId->get($qid);
            if (!$question) {
                continue;
            }

            $out[] = [
                'id' => $question->id,
                'text' => $question->question_title,
                'points' => $question->points,
                'options' => $optionsByQuestion->get($qid, collect())->map(fn ($o) => [
                    'id' => $o->id,
                    'text' => $o->answer,
                ])->values(),
            ];
        }

        return $out;
    }

    /**
     * POST /api/quiz/{id}/attempts — a student starts (or resumes) an
     * attempt. Resuming an existing in_progress attempt instead of minting a
     * new one keeps a page refresh from creating duplicate rows.
     */
    public function startAttempt(Request $request, $id): JsonResponse
    {
        $subInstituteId = (int) $request->input('sub_institute_id');
        $syear = (int) $request->input('syear');
        $studentId = (int) $request->input('user_id');

        if (!$subInstituteId || !$studentId) {
            return response()->json(['status_code' => 0, 'message' => 'Missing required parameters: sub_institute_id and user_id are required'], 422);
        }

        $quiz = questionpaperModel::where('exam_type', self::EXAM_TYPE)
            ->where('sub_institute_id', $subInstituteId)
            ->find($id);

        if (!$quiz) {
            return response()->json(['status_code' => 0, 'message' => 'Quiz not found'], 404);
        }

        $attempt = LmsQuizAttemptModel::where([
            'question_paper_id' => $id,
            'student_id' => $studentId,
            'status' => 'in_progress',
        ])->orderByDesc('id')->first();

        if (!$attempt) {
            $totalQuestions = count(array_filter(explode(',', (string) $quiz->question_ids)));

            $attempt = LmsQuizAttemptModel::create([
                'question_paper_id' => $id,
                'student_id' => $studentId,
                'sub_institute_id' => $subInstituteId,
                'syear' => $syear ?: $quiz->syear,
                'status' => 'in_progress',
                'total_questions' => $totalQuestions,
                'max_marks' => $quiz->total_marks,
                'started_at' => now(),
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'attempt_id' => $attempt->id,
                'quiz' => [
                    'id' => $quiz->id,
                    'title' => $quiz->paper_name,
                    'time_allowed' => $quiz->time_allowed,
                    'total_marks' => $quiz->total_marks,
                    'total_ques' => $quiz->total_ques,
                ],
                'questions' => $this->questionsForTaking($quiz->question_ids),
            ],
        ], 200);
    }

    /**
     * POST /api/quiz/attempts/{attemptId}/submit — score a submitted attempt.
     *
     * `answers` is `{ "<question_id>": <answer_master id> }` (or an array of
     * ids for a future multi-select question). Correctness is always derived
     * here from `answer_master.correct_answer = 1`, never from anything the
     * client sent about which option is right. Idempotent: resubmitting an
     * already-completed attempt returns its existing result rather than
     * rescoring it.
     */
    public function submitAttempt(Request $request, $attemptId): JsonResponse
    {
        $studentId = (int) $request->input('user_id');

        $attempt = LmsQuizAttemptModel::find($attemptId);

        if (!$attempt || (int) $attempt->student_id !== $studentId) {
            return response()->json(['status_code' => 0, 'message' => 'Attempt not found'], 404);
        }

        $quiz = questionpaperModel::find($attempt->question_paper_id);
        if (!$quiz) {
            return response()->json(['status_code' => 0, 'message' => 'The quiz for this attempt no longer exists'], 404);
        }

        if ($attempt->status === 'completed') {
            return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => $this->attemptResult($attempt, $quiz)], 200);
        }

        $answers = $request->input('answers', []);
        if (!is_array($answers)) {
            $answers = [];
        }

        $questionIds = array_values(array_filter(array_map('intval', explode(',', (string) $quiz->question_ids))));
        $points = lmsQuestionMasterModel::whereIn('id', $questionIds)->pluck('points', 'id');
        $correctByQuestion = answermasterModel::whereIn('question_id', $questionIds)
            ->where('correct_answer', 1)
            ->get()
            ->groupBy('question_id');

        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $scoreMarks = 0;
        $maxMarks = 0;
        $detail = [];

        foreach ($questionIds as $qid) {
            $pointsForQ = (float) ($points[$qid] ?? 1);
            $maxMarks += $pointsForQ;

            $correctIds = $correctByQuestion->get($qid, collect())
                ->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();

            $given = $answers[$qid] ?? $answers[(string) $qid] ?? null;

            if ($given === null || $given === '') {
                $unanswered++;
                $detail[$qid] = ['given' => [], 'correct' => $correctIds, 'is_correct' => false];
                continue;
            }

            $givenIds = is_array($given) ? array_map('intval', $given) : [(int) $given];
            sort($givenIds);

            $isCorrect = !empty($correctIds) && $givenIds === $correctIds;

            if ($isCorrect) {
                $correct++;
                $scoreMarks += $pointsForQ;
            } else {
                $wrong++;
            }

            $detail[$qid] = ['given' => $givenIds, 'correct' => $correctIds, 'is_correct' => $isCorrect];
        }

        $attempt->update([
            'status' => 'completed',
            'total_questions' => count($questionIds),
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'unanswered_count' => $unanswered,
            'score_marks' => $scoreMarks,
            'max_marks' => $maxMarks,
            'answers_json' => $detail,
            'completed_at' => now(),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Quiz submitted successfully',
            'data' => $this->attemptResult($attempt->refresh(), $quiz),
        ], 200);
    }

    /**
     * GET /api/quiz/attempts — the caller's own attempt history.
     */
    public function history(Request $request): JsonResponse
    {
        $studentId = (int) $request->input('user_id');
        $subInstituteId = (int) $request->input('sub_institute_id');
        $syear = $request->input('syear');

        if (!$studentId || !$subInstituteId) {
            return response()->json(['status_code' => 0, 'message' => 'Missing required parameters: sub_institute_id and user_id are required'], 422);
        }

        $query = LmsQuizAttemptModel::select(
            'lms_quiz_attempts.*',
            'question_paper.paper_name',
            'question_paper.subject_id'
        )
            ->join('question_paper', 'question_paper.id', '=', 'lms_quiz_attempts.question_paper_id')
            ->where('lms_quiz_attempts.student_id', $studentId)
            ->where('lms_quiz_attempts.sub_institute_id', $subInstituteId);

        if ($syear) {
            $query->where('lms_quiz_attempts.syear', (int) $syear);
        }

        $attempts = $query->orderByDesc('lms_quiz_attempts.id')->get();

        return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => $attempts], 200);
    }

    /**
     * GET /api/quiz/attempts/{attemptId} — one attempt's full result,
     * including the per-question breakdown (safe to reveal: the attempt is
     * already completed).
     */
    public function attemptShow(Request $request, $attemptId): JsonResponse
    {
        $studentId = (int) $request->input('user_id');

        $attempt = LmsQuizAttemptModel::find($attemptId);
        if (!$attempt || (int) $attempt->student_id !== $studentId) {
            return response()->json(['status_code' => 0, 'message' => 'Attempt not found'], 404);
        }

        $quiz = questionpaperModel::find($attempt->question_paper_id);

        return response()->json(['status_code' => 1, 'message' => 'SUCCESS', 'data' => $this->attemptResult($attempt, $quiz)], 200);
    }

    /** Full result payload shared by submitAttempt/attemptShow. */
    private function attemptResult(LmsQuizAttemptModel $attempt, ?questionpaperModel $quiz): array
    {
        $detail = is_array($attempt->answers_json) ? $attempt->answers_json : [];
        $questionIds = array_values(array_filter(array_map('intval', explode(',', (string) ($quiz->question_ids ?? '')))));

        $questions = lmsQuestionMasterModel::whereIn('id', $questionIds)->get(['id', 'question_title'])->keyBy('id');
        $optionsByQuestion = answermasterModel::whereIn('question_id', $questionIds)
            ->orderBy('id')
            ->get(['id', 'question_id', 'answer'])
            ->groupBy('question_id');

        $breakdown = [];
        foreach ($questionIds as $qid) {
            $question = $questions->get($qid);
            if (!$question) {
                continue;
            }

            $qDetail = $detail[$qid] ?? $detail[(string) $qid] ?? ['given' => [], 'correct' => [], 'is_correct' => false];

            $breakdown[] = [
                'id' => $qid,
                'text' => $question->question_title,
                'options' => $optionsByQuestion->get($qid, collect())->map(fn ($o) => [
                    'id' => $o->id,
                    'text' => $o->answer,
                    'is_correct' => in_array((int) $o->id, $qDetail['correct'] ?? [], true),
                ])->values(),
                'given' => $qDetail['given'] ?? [],
                'is_correct' => (bool) ($qDetail['is_correct'] ?? false),
            ];
        }

        $maxMarks = (float) ($attempt->max_marks ?? 0);
        $scoreMarks = (float) ($attempt->score_marks ?? 0);

        return [
            'attempt_id' => $attempt->id,
            'quiz_id' => $attempt->question_paper_id,
            'title' => $quiz->paper_name ?? null,
            'status' => $attempt->status,
            'total_questions' => $attempt->total_questions,
            'correct_count' => $attempt->correct_count,
            'wrong_count' => $attempt->wrong_count,
            'unanswered_count' => $attempt->unanswered_count,
            'score_marks' => $scoreMarks,
            'max_marks' => $maxMarks,
            'percentage' => $maxMarks > 0 ? round(($scoreMarks / $maxMarks) * 100, 2) : null,
            'started_at' => optional($attempt->started_at)->toDateTimeString(),
            'completed_at' => optional($attempt->completed_at)->toDateTimeString(),
            'questions' => $breakdown,
        ];
    }
}
