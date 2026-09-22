<?php

namespace App\Services\PAL\Diagnostic;

use App\Models\PAL\DiagnosticAttempt;
use App\Services\PAL\Questions\ServableQuestions;
use Illuminate\Support\Facades\DB;

/**
 * The chapter diagnostic: draw a paper, score it, describe the result.
 *
 * Called from palController::diagnosticStart / diagnosticSubmit /
 * diagnosticResult. The array shapes returned here are a contract with those
 * call sites and with the three diagnostic blades - see each method.
 */
class DiagnosticService
{
    public function __construct(
        private DiagnosticQuestionSelector $selector = new DiagnosticQuestionSelector(),
        private DiagnosticScorer $scorer = new DiagnosticScorer(),
    ) {
    }

    /**
     * Draw and persist a paper.
     *
     * The attempt row AND one response row per question are written here, up
     * front, rather than at submit. That buys three things at once: an
     * unanswered question is already a row so nothing is reconstructed later;
     * submit can only ever UPDATE rows that exist, so a browser cannot score a
     * question it was never served; and the paper survives a refresh.
     *
     * Because those rows are written up front, an unfinished attempt is already
     * a complete record of the paper, so this RESUMES one rather than drawing
     * again - see resume(). Without that, every page load minted a fresh
     * attempt: the learner got a different paper, any answers they had already
     * given were stranded on the abandoned row, and the table filled with
     * orphaned in_progress attempts.
     *
     * @return array{attempt_id: ?int, questions: array, selection_report: array, reason: ?string, resumed?: bool, answered?: int}
     */
    public function start($studentId, int $chapterId, $subInstituteId, $syear = null, $subjectId = null, $standardId = null): array
    {
        $resumed = $this->resume($studentId, $chapterId, $subInstituteId, $syear);

        if ($resumed !== null) {
            return $resumed;
        }

        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first(['id', 'subject_id', 'standard_id']);

        $subjectId = $subjectId ?? ($chapter->subject_id ?? null);
        $standardId = $standardId ?? ($chapter->standard_id ?? null);

        // One seed per attempt, stored in the report. Re-running the selector
        // with it reproduces this exact paper, which is what makes a bad
        // attempt debuggable after the learner has gone home.
        $seed = random_int(1, 999999999);

        $selection = $this->selector->select($chapterId, $subInstituteId, $studentId, $seed);
        $questions = $selection['questions'];
        $report = $selection['report'];

        if ($questions === []) {
            return $this->failure('no_mcq_questions_available', $report);
        }

        // Below nine items a percentage is arithmetic, not a measurement, and
        // this one drives the whole adaptive path. Refuse rather than label a
        // learner off four questions.
        if (count($questions) < $this->selector->minViable()) {
            return $this->failure('insufficient_mcq_questions', $report);
        }

        $hydrated = [];
        $rows = [];
        $now = now();

        foreach ($questions as $q) {
            // hydrate() returns the options WITHOUT the answer key. Nothing in
            // this method may put correct_answer into the payload.
            $item = ServableQuestions::hydrate((int) $q['question_id']);

            if ($item === null) {
                continue;
            }

            $item['difficulty'] = $q['difficulty'];
            $hydrated[] = $item;

            $rows[] = [
                'question_id' => $q['question_id'],
                'answer_master_id' => null,
                'is_correct' => 0,
                'difficulty_served' => $q['difficulty'],
                'difficulty_source' => $q['difficulty_source'],
                'concept_id_snapshot' => $q['concept_id_snapshot'],
                'chapter_id_snapshot' => $q['chapter_id_snapshot'],
                'concept_exact' => $q['concept_exact'] ? 1 : 0,
                'sequence' => count($rows) + 1,
                'answered_at' => null,
            ];
        }

        if ($rows === []) {
            return $this->failure('no_mcq_questions_available', $report);
        }

        $report['total'] = count($rows);

        $attemptId = DB::transaction(function () use ($studentId, $subjectId, $chapterId, $standardId, $subInstituteId, $syear, $rows, $report, $now) {
            $attempt = DiagnosticAttempt::create([
                'student_id' => (int) $studentId,
                'subject_id' => (int) $subjectId,
                'chapter_id' => $chapterId,
                'standard_id' => $standardId !== null ? (int) $standardId : null,
                'sub_institute_id' => (int) $subInstituteId,
                'syear' => $syear !== null ? (int) $syear : null,
                'status' => DiagnosticAttempt::STATUS_IN_PROGRESS,
                'total_questions' => count($rows),
                'correct' => 0, 'incorrect' => 0, 'unanswered' => count($rows),
                'percentage' => 0,
                'level' => null,
                'difficulty_breakdown' => null,
                'concept_breakdown' => null,
                'selection_report' => $report,
                'started_at' => $now,
            ]);

            foreach ($rows as $i => $row) {
                $rows[$i]['attempt_id'] = $attempt->id;
            }

            DB::table('pal_diagnostic_response')->insert($rows);

            return $attempt->id;
        });

        return [
            'attempt_id' => $attemptId,
            'questions' => $hydrated,
            'selection_report' => $report,
            'reason' => null,
            'resumed' => false,
            'answered' => 0,
        ];
    }

    /**
     * Hand back an unfinished attempt instead of starting a new one.
     *
     * Returns null when there is nothing to resume, which is the signal for
     * start() to draw a paper.
     *
     * The paper is rebuilt from pal_diagnostic_response in its stored
     * `sequence`, so the learner sees the same questions in the same order.
     * Hydration still goes through ServableQuestions::hydrate(), which is what
     * guarantees the answer key does not reach the payload - a resume must not
     * become the one path that leaks it.
     *
     * A question that has since been unpublished or deleted hydrates to null.
     * That is not a reason to refuse the resume: the response row survives and
     * scores as unanswered, exactly as it would have if the learner had skipped
     * it, so the attempt stays scorable.
     *
     * @return array{attempt_id: int, questions: array, selection_report: array, reason: null, resumed: bool, answered: int}|null
     */
    protected function resume($studentId, int $chapterId, $subInstituteId, $syear = null): ?array
    {
        $query = DiagnosticAttempt::query()
            ->where('student_id', (int) $studentId)
            ->where('chapter_id', $chapterId)
            ->where('sub_institute_id', (int) $subInstituteId)
            ->where('status', DiagnosticAttempt::STATUS_IN_PROGRESS);

        // Scope to the academic year only when the caller supplies one, so a
        // null syear does not silently match every year's attempts.
        if ($syear !== null) {
            $query->where('syear', (int) $syear);
        }

        // Newest first: if orphans already exist from before this fix, the
        // learner is put back on the most recent one rather than the oldest.
        $attempt = $query->orderByDesc('id')->first();

        if ($attempt === null) {
            return null;
        }

        $responses = DB::table('pal_diagnostic_response')
            ->where('attempt_id', $attempt->id)
            ->orderBy('sequence')
            ->get(['question_id', 'difficulty_served', 'answer_master_id']);

        // An attempt with no response rows cannot be resumed into a paper.
        // Treat it as absent and let start() draw a real one.
        if ($responses->isEmpty()) {
            return null;
        }

        $hydrated = [];
        $answered = 0;

        foreach ($responses as $response) {
            if ($response->answer_master_id !== null) {
                $answered++;
            }

            $item = ServableQuestions::hydrate((int) $response->question_id);

            if ($item === null) {
                continue;
            }

            $item['difficulty'] = $response->difficulty_served;
            // Their previous choice, so the form can come back filled in.
            $item['answer_master_id'] = $response->answer_master_id !== null
                ? (int) $response->answer_master_id
                : null;

            $hydrated[] = $item;
        }

        if ($hydrated === []) {
            return null;
        }

        $report = $attempt->selection_report;

        return [
            'attempt_id' => (int) $attempt->id,
            'questions' => $hydrated,
            'selection_report' => is_array($report) ? $report : [],
            'reason' => null,
            'resumed' => true,
            'answered' => $answered,
        ];
    }

    /**
     * Score a submitted paper.
     *
     * $answers is question_id => answer_master_id, straight off the form. It
     * is a CANDIDATE only: correctness is resolved here from answer_master,
     * never read from the request, and an option is honoured only when it
     * genuinely belongs to the question it was submitted against - otherwise
     * a crafted post could claim any question with any option id.
     *
     * @param  array<int|string,mixed>  $answers
     */
    public function submit(DiagnosticAttempt $attempt, array $answers): array
    {
        // Submit is a plain form POST; a double click or a back-and-resubmit
        // must not rescore an attempt that is already final.
        if ($attempt->isSubmitted()) {
            return $this->result($attempt);
        }

        $responses = $attempt->responses()->get();

        $submitted = [];
        foreach ($answers as $questionId => $answerId) {
            if ($answerId !== null && $answerId !== '') {
                $submitted[(int) $questionId] = (int) $answerId;
            }
        }

        // One query for every option the learner picked, keyed by option id.
        $options = [];
        if ($submitted !== []) {
            foreach (DB::table('answer_master')->whereIn('id', array_values($submitted))->get(['id', 'question_id', 'correct_answer']) as $opt) {
                $options[(int) $opt->id] = $opt;
            }
        }

        $now = now();

        DB::transaction(function () use ($responses, $submitted, $options, $now, $attempt) {
            foreach ($responses as $response) {
                $chosen = $submitted[$response->question_id] ?? null;
                $option = $chosen !== null ? ($options[$chosen] ?? null) : null;

                // The ownership test. A mismatched or unknown option id is
                // discarded and the row stays unanswered.
                if ($option === null || (int) $option->question_id !== (int) $response->question_id) {
                    continue;
                }

                DB::table('pal_diagnostic_response')
                    ->where('id', $response->id)
                    ->update([
                        'answer_master_id' => $chosen,
                        'is_correct' => (int) $option->correct_answer === 1 ? 1 : 0,
                        'answered_at' => $now,
                    ]);
            }

            $scored = $attempt->responses()->get();
            $totals = $this->scorer->totals($scored);
            $level = $this->scorer->level($totals['percentage']);

            $attempt->fill([
                'status' => DiagnosticAttempt::STATUS_SUBMITTED,
                'correct' => $totals['correct'],
                'incorrect' => $totals['incorrect'],
                'unanswered' => $totals['unanswered'],
                'percentage' => $totals['percentage'],
                'level' => $level,
                'difficulty_breakdown' => $this->scorer->difficultyBreakdown($scored),
                'concept_breakdown' => $this->conceptBreakdown($scored),
                'submitted_at' => $now,
            ])->save();
        });

        return $this->result($attempt->refresh());
    }

    /**
     * Describe a finished attempt. A pure read - it serves a GET.
     *
     * Everything here comes off the persisted JSON. The one exception is a
     * legacy row (submitted before this service existed, so scored but with no
     * breakdown); those are recomputed for display and still not written back,
     * because a GET has no business mutating an attempt.
     */
    public function result(DiagnosticAttempt $attempt): array
    {
        $difficulty = $attempt->difficulty_breakdown;
        $concepts = $attempt->concept_breakdown;

        if (empty($difficulty) || empty($concepts)) {
            $scored = $attempt->responses()->get();
            $difficulty = $difficulty ?: $this->scorer->difficultyBreakdown($scored);
            $concepts = $concepts ?: $this->conceptBreakdown($scored);
        }

        $level = $attempt->level ?: $this->scorer->level((float) $attempt->percentage);
        $baseline = $this->scorer->baselineDifficulty($level, $difficulty);

        $strengths = array_values(array_filter($concepts, fn ($c) => ($c['band'] ?? null) === DiagnosticScorer::BAND_STRONG));
        $weaknesses = array_values(array_filter($concepts, fn ($c) => in_array($c['band'] ?? null, [DiagnosticScorer::BAND_WEAK, DiagnosticScorer::BAND_MODERATE], true)));

        // conceptBreakdown() is sorted weakest first, so strengths need
        // reversing to lead with the strongest.
        $strengths = array_reverse($strengths);

        return [
            'attempt_id' => $attempt->id,
            'chapter_id' => $attempt->chapter_id,
            'subject_id' => $attempt->subject_id,
            'status' => $attempt->status,
            'total_questions' => (int) $attempt->total_questions,
            'correct' => (int) $attempt->correct,
            'incorrect' => (int) $attempt->incorrect,
            'unanswered' => (int) $attempt->unanswered,
            'percentage' => (float) $attempt->percentage,
            'level' => $level,
            'difficulty_breakdown' => $difficulty,
            'concept_breakdown' => $concepts,
            'strengths' => array_slice($strengths, 0, 5),
            'weaknesses' => array_slice($weaknesses, 0, 5),
            'recommended_difficulty' => $baseline['difficulty'],
            'recommended_reason' => $baseline['reason'],
            'selection_report' => $attempt->selection_report ?: [],
            'submitted_at' => $attempt->submitted_at,
        ];
    }

    /** Concept breakdown with display names resolved in two batched lookups. */
    private function conceptBreakdown($responses): array
    {
        $conceptIds = [];
        $chapterIds = [];

        foreach ($responses as $row) {
            if ($row->concept_id_snapshot !== null) {
                $conceptIds[] = (int) $row->concept_id_snapshot;
            }
            if ($row->chapter_id_snapshot !== null) {
                $chapterIds[] = (int) $row->chapter_id_snapshot;
            }
        }

        $conceptNames = $conceptIds === [] ? [] : DB::table('lms_concept')
            ->whereIn('id', array_unique($conceptIds))
            ->pluck('name', 'id')
            ->all();

        $chapterNames = $chapterIds === [] ? [] : DB::table('chapter_master')
            ->whereIn('id', array_unique($chapterIds))
            ->pluck('chapter_name', 'id')
            ->all();

        return $this->scorer->conceptBreakdown($responses, $conceptNames, $chapterNames);
    }

    /** @return array{attempt_id: null, questions: array, selection_report: array, reason: string} */
    private function failure(string $reason, array $report): array
    {
        return [
            'attempt_id' => null,
            'questions' => [],
            'selection_report' => $report,
            'reason' => $reason,
        ];
    }
}
