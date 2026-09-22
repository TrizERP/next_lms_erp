<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateAnswerSheetJob;
use App\Services\Evaluation\AnswerKeyService;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\StudentMatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Exam Evaluation -- scanned answer sheets, read and scored against a paper.
 *
 * The shape of the work is: pick a question paper, upload the scans for that
 * class, let each sheet be read and scored, then go through them. A sheet is
 * only a result once a teacher has approved it, and a batch only reaches the
 * gradebook when it is published -- which reads `teacher_marks`, never the
 * AI's proposal.
 *
 * Every endpoint is scoped by `sub_institute_id` from the caller's session and
 * re-checks it on the row it is about to touch, because sheet and batch ids are
 * sequential and guessable.
 */
class ExamEvaluationApiController extends Controller
{
    public function __construct(
        private readonly AnswerKeyService $answerKeys,
        private readonly StudentMatcherService $matcher,
        private readonly ExamEvaluationStorage $storage
    ) {
    }

    // -- Batches ------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        $query = DB::table('exam_evaluation_batch as b')
            ->leftJoin('question_paper as p', 'p.id', '=', 'b.question_paper_id')
            ->leftJoin('standard as st', 'st.id', '=', 'p.standard_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'p.subject_id')
            ->where('b.sub_institute_id', $tenantId);

        if ($syear = (int) $request->input('syear')) {
            $query->where('b.syear', $syear);
        }

        $rows = $query
            ->orderByDesc('b.id')
            ->limit(200)
            ->get([
                'b.*',
                'p.paper_name',
                'p.exam_type',
                'st.name as standard_name',
                'sub.subject_name',
            ]);

        return $this->success([
            'batches' => $rows->map(fn ($row) => $this->presentBatch($row))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $paperId = (int) $request->input('question_paper_id');

        if (! $tenantId || ! $paperId) {
            return $this->failure('sub_institute_id and question_paper_id are required.', 422);
        }

        try {
            $key = $this->answerKeys->forPaper($paperId, [$tenantId]);
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage(), 422);
        }

        $syear = (int) ($request->input('syear') ?: $key['paper']['syear']);
        $name = trim((string) $request->input('name'));

        if ($name === '') {
            $name = trim(implode(' — ', array_filter([
                $key['paper']['paper_name'],
                $key['paper']['standard_name'],
                $key['paper']['grade_name'],
            ]))) ?: 'Answer sheet evaluation';
        }

        $id = DB::table('exam_evaluation_batch')->insertGetId([
            'sub_institute_id' => $tenantId,
            'syear' => $syear,
            'question_paper_id' => $paperId,
            'name' => mb_substr($name, 0, 191),
            'status' => 'Draft',
            'total_marks' => $key['total_marks'],
            'created_by' => (int) $request->input('user_id') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->show($request, $id);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $batch = $this->findBatch($id, $tenantId);

        if (! $batch) {
            return $this->failure('Evaluation batch not found.', 404);
        }

        $sheets = DB::table('exam_evaluation_sheet as sh')
            ->leftJoin('tblstudent as s', 's.id', '=', 'sh.student_id')
            ->where('sh.batch_id', $id)
            ->orderBy('sh.id')
            ->get([
                'sh.*',
                's.first_name',
                's.middle_name',
                's.last_name',
                's.enrollment_no as student_enrollment_no',
            ]);

        $paperRow = DB::table('question_paper as p')
            ->leftJoin('standard as st', 'st.id', '=', 'p.standard_id')
            ->leftJoin('academic_section as sec', 'sec.id', '=', 'p.grade_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'p.subject_id')
            ->where('p.id', $batch->question_paper_id)
            ->first(['p.standard_id', 'p.grade_id', 'st.name as standard_name', 'sec.title as grade_name', 'sub.subject_name', 'p.paper_name', 'p.exam_type']);

        $roster = $paperRow
            ? $this->matcher->roster(
                $tenantId,
                (int) $batch->syear,
                (int) $paperRow->standard_id,
                (int) $paperRow->grade_id
            )
            : [];

        // Roll numbers come off the roster rather than a join, because a
        // student can hold more than one enrollment row for a year (one per
        // term) and joining on that would duplicate the sheet rows.
        $rollNumbers = [];

        foreach ($roster as $student) {
            $rollNumbers[(int) $student['student_id']] = (string) $student['roll_no'];
        }

        return $this->success([
            'batch' => $this->presentBatch((object) array_merge((array) $batch, [
                'paper_name' => $paperRow->paper_name ?? '',
                'exam_type' => $paperRow->exam_type ?? '',
                'standard_name' => $paperRow->standard_name ?? '',
                'subject_name' => $paperRow->subject_name ?? '',
            ])),
            'sheets' => $sheets->map(function ($row) use ($rollNumbers) {
                $row->student_roll_no = $rollNumbers[(int) $row->student_id] ?? '';

                return $this->presentSheet($row);
            })->all(),
            'roster' => $roster,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $batch = $this->findBatch($id, $tenantId);

        if (! $batch) {
            return $this->failure('Evaluation batch not found.', 404);
        }

        if ($batch->status === 'Published') {
            return $this->failure('This batch has already been published to the gradebook and cannot be removed.', 422);
        }

        $sheets = DB::table('exam_evaluation_sheet')->where('batch_id', $id)->get(['file_name', 'annotated_file_name']);

        DB::transaction(function () use ($id) {
            DB::table('exam_evaluation_answer')->where('batch_id', $id)->delete();
            DB::table('exam_evaluation_sheet')->where('batch_id', $id)->delete();
            DB::table('exam_evaluation_batch')->where('id', $id)->delete();
        });

        foreach ($sheets as $sheet) {
            $this->storage->delete($sheet->file_name, $sheet->annotated_file_name);
        }

        return $this->success([], 'Evaluation batch removed.');
    }

    // -- Sheets -------------------------------------------------------------

    /**
     * Takes the scans for one batch.
     *
     * Each file is saved and queued on its own, and a file that cannot be
     * stored is reported back by name rather than failing the whole upload: a
     * teacher dropping in 40 scans should not lose 39 of them because one was a
     * .heic from somebody's phone.
     */
    public function uploadSheets(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $batch = $this->findBatch($id, $tenantId);

        if (! $batch) {
            return $this->failure('Evaluation batch not found.', 404);
        }

        if ($batch->status === 'Published') {
            return $this->failure('This batch has been published. Create a new batch to evaluate more sheets.', 422);
        }

        $files = $request->file('sheets');
        $files = is_array($files) ? $files : array_filter([$request->file('sheet')]);

        if ($files === []) {
            return $this->failure('Please choose at least one scanned answer sheet.', 422);
        }

        $accepted = [];
        $rejected = [];

        foreach ($files as $file) {
            $name = $file ? $file->getClientOriginalName() : 'unknown file';

            try {
                if (! $file || ! $file->isValid()) {
                    throw new \RuntimeException('The upload was incomplete or corrupted.');
                }

                if ($file->getSize() > ExamEvaluationStorage::MAX_BYTES) {
                    throw new \RuntimeException('The file is larger than 20 MB.');
                }

                $stored = $this->storage->putSheet($id, $file);

                $sheetId = DB::table('exam_evaluation_sheet')->insertGetId([
                    'batch_id' => $id,
                    'sub_institute_id' => $tenantId,
                    'syear' => (int) $batch->syear,
                    'original_name' => mb_substr($name, 0, 191),
                    'file_name' => $stored['file_name'],
                    'file_type' => $stored['file_type'],
                    'mime_type' => $stored['mime_type'],
                    'status' => 'Pending',
                    'created_by' => (int) $request->input('user_id') ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $accepted[] = $sheetId;
            } catch (Throwable $exception) {
                $rejected[] = ['file' => $name, 'reason' => $exception->getMessage()];
            }
        }

        EvaluateAnswerSheetJob::refreshBatch($id);

        foreach ($accepted as $sheetId) {
            $this->dispatchEvaluation($sheetId);
        }

        $response = $this->show($request, $id);
        $payload = $response->getData(true);
        $payload['data']['uploaded'] = count($accepted);
        $payload['data']['rejected'] = $rejected;

        if ($accepted === [] && $rejected !== []) {
            $payload['message'] = 'None of those files could be evaluated.';
        }

        return $response->setData($payload);
    }

    public function sheet(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $sheet = $this->findSheet($id, $tenantId);

        if (! $sheet) {
            return $this->failure('Answer sheet not found.', 404);
        }

        $answers = DB::table('exam_evaluation_answer')
            ->where('sheet_id', $id)
            ->orderBy('question_no')
            ->get();

        $questionTitles = [];

        try {
            $batch = $this->findBatch((int) $sheet->batch_id, $tenantId);

            if ($batch) {
                foreach ($this->answerKeys->forPaper((int) $batch->question_paper_id, [$tenantId])['questions'] as $question) {
                    $questionTitles[(int) $question['question_no']] = $question['question_title'];
                }
            }
        } catch (Throwable $exception) {
            // The question text is context for the reviewer, not the review
            // itself -- a paper that has since been edited must not stop a
            // teacher seeing the marks they still have to sign off.
            Log::info('Question titles unavailable for review screen', ['sheet_id' => $id, 'message' => $exception->getMessage()]);
        }

        return $this->success([
            'sheet' => $this->presentSheet($this->decorateSheet($sheet)),
            'answers' => $answers->map(fn ($row) => [
                'id' => (int) $row->id,
                'question_no' => (int) $row->question_no,
                'question_id' => $row->question_id ? (int) $row->question_id : null,
                'question_title' => $questionTitles[(int) $row->question_no] ?? '',
                'question_type' => (string) $row->question_type,
                'is_objective' => (bool) $row->is_objective,
                'detected_answer' => (string) $row->detected_answer,
                'selected_options' => array_values(array_filter(explode(',', (string) $row->selected_options))),
                'expected_answer' => (string) $row->expected_answer,
                'max_marks' => (float) $row->max_marks,
                'ai_marks' => $row->ai_marks === null ? null : (float) $row->ai_marks,
                'teacher_marks' => $row->teacher_marks === null ? null : (float) $row->teacher_marks,
                'status' => (string) $row->status,
                'ai_confidence' => $row->ai_confidence === null ? null : (float) $row->ai_confidence,
                'ai_remark' => (string) $row->ai_remark,
                'needs_attention' => ! $row->is_objective
                    && $row->status !== 'unattempted'
                    && (float) $row->ai_confidence < AnswerSheetScoringService::CONFIDENCE_REVIEW_THRESHOLD,
                'page' => (int) $row->page,
            ])->all(),
        ]);
    }

    /**
     * A teacher's pass over one sheet.
     *
     * Three things can happen here and any combination of them is valid: fixing
     * who the sheet belongs to, changing marks on individual questions, and
     * approving the sheet. Approval is what turns proposals into marks -- every
     * question without a teacher mark takes the AI's number at that moment, so
     * an approved sheet has a complete, teacher-owned set of marks and the
     * total no longer moves if the model is re-run.
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $sheet = $this->findSheet($id, $tenantId);

        if (! $sheet) {
            return $this->failure('Answer sheet not found.', 404);
        }

        $batch = $this->findBatch((int) $sheet->batch_id, $tenantId);

        if ($batch && $batch->status === 'Published') {
            return $this->failure('This batch has been published; its marks can no longer be changed here.', 422);
        }

        $updates = [];

        if ($request->has('student_id')) {
            $studentId = (int) $request->input('student_id');

            if ($studentId > 0) {
                $belongs = DB::table('tblstudent')
                    ->where('id', $studentId)
                    ->where('sub_institute_id', $tenantId)
                    ->exists();

                if (! $belongs) {
                    return $this->failure('That student is not on this school\'s roll.', 422);
                }

                $clash = DB::table('exam_evaluation_sheet')
                    ->where('batch_id', $sheet->batch_id)
                    ->where('student_id', $studentId)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($clash) {
                    return $this->failure('Another sheet in this batch is already assigned to that student.', 422);
                }
            }

            $updates['student_id'] = $studentId > 0 ? $studentId : null;
            $updates['identity_source'] = $studentId > 0 ? 'manual' : 'unmatched';
            $updates['identity_confidence'] = $studentId > 0 ? 100 : null;
        }

        $marks = $request->input('marks');

        if (is_array($marks)) {
            foreach ($marks as $row) {
                if (! is_array($row) || ! isset($row['question_no'])) {
                    continue;
                }

                $answer = DB::table('exam_evaluation_answer')
                    ->where('sheet_id', $id)
                    ->where('question_no', (int) $row['question_no'])
                    ->first(['id', 'max_marks']);

                if (! $answer) {
                    continue;
                }

                // Clamped, not rejected: a slip in a mark box should land on
                // the nearest legal mark rather than throw away the teacher's
                // whole pass over the sheet.
                $given = $row['teacher_marks'] ?? null;
                $value = ($given === null || $given === '')
                    ? null
                    : round(max(0.0, min((float) $answer->max_marks, (float) $given)), 2);

                DB::table('exam_evaluation_answer')->where('id', $answer->id)->update([
                    'teacher_marks' => $value,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($request->boolean('approve')) {
            $studentId = array_key_exists('student_id', $updates) ? $updates['student_id'] : $sheet->student_id;

            if (! $studentId) {
                return $this->failure('Assign this sheet to a student before approving it.', 422);
            }

            DB::table('exam_evaluation_answer')
                ->where('sheet_id', $id)
                ->whereNull('teacher_marks')
                ->update(['teacher_marks' => DB::raw('COALESCE(ai_marks, 0)'), 'updated_at' => now()]);

            $updates['status'] = 'Approved';
            $updates['approved_by'] = (int) $request->input('user_id') ?: null;
            $updates['approved_at'] = now();
        } elseif ($request->has('unapprove') && $request->boolean('unapprove')) {
            $updates['status'] = 'Needs review';
            $updates['approved_by'] = null;
            $updates['approved_at'] = null;
        }

        $teacherTotal = (float) (DB::table('exam_evaluation_answer')
            ->where('sheet_id', $id)
            ->selectRaw('SUM(COALESCE(teacher_marks, ai_marks, 0)) as total')
            ->value('total') ?? 0);

        $updates['teacher_total'] = round($teacherTotal, 2);
        $updates['updated_at'] = now();

        DB::table('exam_evaluation_sheet')->where('id', $id)->update($updates);
        EvaluateAnswerSheetJob::refreshBatch((int) $sheet->batch_id);

        return $this->sheet($request, $id);
    }

    public function reprocess(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $sheet = $this->findSheet($id, $tenantId);

        if (! $sheet) {
            return $this->failure('Answer sheet not found.', 404);
        }

        if ($sheet->status === 'Approved') {
            return $this->failure('This sheet has been approved. Re-open it for review before re-running the evaluation.', 422);
        }

        DB::table('exam_evaluation_sheet')->where('id', $id)->update([
            'status' => 'Pending',
            'failure_reason' => null,
            'updated_at' => now(),
        ]);

        $this->dispatchEvaluation($id);

        return $this->sheet($request, $id);
    }

    public function destroySheet(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $sheet = $this->findSheet($id, $tenantId);

        if (! $sheet) {
            return $this->failure('Answer sheet not found.', 404);
        }

        DB::transaction(function () use ($id) {
            DB::table('exam_evaluation_answer')->where('sheet_id', $id)->delete();
            DB::table('exam_evaluation_sheet')->where('id', $id)->delete();
        });

        $this->storage->delete($sheet->file_name, $sheet->annotated_file_name);
        EvaluateAnswerSheetJob::refreshBatch((int) $sheet->batch_id);

        return $this->success([], 'Answer sheet removed.');
    }

    /** The scan itself, and the marked-up copy, both behind the tenant check. */
    public function file(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $sheet = $this->findSheet($id, (int) $request->input('sub_institute_id'));

        if (! $sheet) {
            return $this->failure('Answer sheet not found.', 404);
        }

        $annotated = $request->boolean('annotated');
        $path = $annotated
            ? $this->storage->annotatedPath((string) $sheet->annotated_file_name)
            : $this->storage->sheetPath((string) $sheet->file_name);

        if ($path === null) {
            return $this->failure($annotated ? 'No marked-up copy was produced for this sheet.' : 'The scan is no longer on the server.', 404);
        }

        return response()->file($path, ['Content-Disposition' => 'inline']);
    }

    // -- Answer key readiness ----------------------------------------------

    /**
     * What the paper looks like as a marking key, before any scanning starts.
     *
     * Its real job is to say whether the paper can be marked at all: a written
     * question with no model answer saved on it cannot be graded against
     * anything, and a teacher deserves to find that out here rather than after
     * scanning a class set.
     */
    public function answerKey(Request $request, int $paperId): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');

        if (! $tenantId) {
            return $this->failure('sub_institute_id is required.', 422);
        }

        try {
            $key = $this->answerKeys->forPaper($paperId, [$tenantId]);
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage(), 422);
        }

        $missingModelAnswers = [];

        foreach ($key['questions'] as $question) {
            if ($question['kind'] === AnswerKeyService::KIND_SUBJECTIVE && $question['model_answer'] === '') {
                $missingModelAnswers[] = $question['question_no'];
            }
        }

        return $this->success([
            'paper' => $key['paper'],
            'total_marks' => $key['total_marks'],
            'objective_count' => $key['objective_count'],
            'subjective_count' => $key['subjective_count'],
            'question_count' => count($key['questions']),
            'missing_model_answers' => $missingModelAnswers,
            'questions' => array_map(static fn (array $question) => [
                'question_no' => $question['question_no'],
                'question_title' => $question['question_title'],
                'kind' => $question['kind'],
                'max_marks' => $question['max_marks'],
                'correct_letters' => $question['correct_letters'],
                'has_model_answer' => $question['model_answer'] !== '',
            ], $key['questions']),
        ]);
    }

    // -- Publish ------------------------------------------------------------

    /**
     * Writes the approved sheets into the offline-exam tables the rest of the
     * ERP already reports on.
     *
     * Only approved sheets, only `teacher_marks`, and a batch can only be
     * published once. Anything still unapproved is named in the response rather
     * than quietly left behind, so nobody discovers a missing student at
     * report-card time.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $batch = $this->findBatch($id, $tenantId);

        if (! $batch) {
            return $this->failure('Evaluation batch not found.', 404);
        }

        if ($batch->status === 'Published') {
            return $this->failure('This batch has already been published.', 422);
        }

        $pending = DB::table('exam_evaluation_sheet')
            ->where('batch_id', $id)
            ->where('status', '!=', 'Approved')
            ->count();

        $sheets = DB::table('exam_evaluation_sheet')
            ->where('batch_id', $id)
            ->where('status', 'Approved')
            ->whereNotNull('student_id')
            ->get();

        if ($sheets->isEmpty()) {
            return $this->failure('No sheets in this batch have been approved yet.', 422);
        }

        $userId = (int) $request->input('user_id') ?: null;
        $published = 0;

        DB::transaction(function () use ($sheets, $batch, $id, $userId, &$published) {
            foreach ($sheets as $sheet) {
                $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $sheet->id)->get();

                $right = 0;
                $wrong = 0;
                $obtained = 0.0;

                foreach ($answers as $answer) {
                    $marks = (float) ($answer->teacher_marks ?? $answer->ai_marks ?? 0);
                    $obtained += $marks;

                    if ((float) $answer->max_marks > 0 && $marks >= (float) $answer->max_marks) {
                        $right++;
                    } else {
                        $wrong++;
                    }
                }

                // Re-publishing the same student for the same paper would
                // double their marks in every downstream report, so an existing
                // row is replaced rather than added to.
                DB::table('lms_offline_exam')
                    ->where('question_paper_id', $batch->question_paper_id)
                    ->where('student_id', $sheet->student_id)
                    ->where('sub_institute_id', $batch->sub_institute_id)
                    ->where('syear', $batch->syear)
                    ->delete();

                $offlineExamId = DB::table('lms_offline_exam')->insertGetId([
                    'student_id' => (int) $sheet->student_id,
                    'question_paper_id' => (int) $batch->question_paper_id,
                    'total_right' => $right,
                    'total_wrong' => $wrong,
                    'obtain_marks' => (int) round($obtained),
                    'syear' => (int) $batch->syear,
                    'sub_institute_id' => (int) $batch->sub_institute_id,
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);

                DB::table('lms_offline_exam_answer')
                    ->where('question_paper_id', $batch->question_paper_id)
                    ->where('student_id', $sheet->student_id)
                    ->delete();

                $rows = [];

                foreach ($answers as $answer) {
                    if (! $answer->question_id) {
                        continue;
                    }

                    $marks = (float) ($answer->teacher_marks ?? $answer->ai_marks ?? 0);

                    $rows[] = [
                        'question_paper_id' => (int) $batch->question_paper_id,
                        'offline_exam_id' => $offlineExamId,
                        'student_id' => (int) $sheet->student_id,
                        'question_id' => (int) $answer->question_id,
                        'ans_status' => $marks >= (float) $answer->max_marks && (float) $answer->max_marks > 0
                            ? 'right'
                            : ($marks > 0 ? 'partial' : 'wrong'),
                        'created_by' => $userId,
                        'created_at' => now(),
                    ];
                }

                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('lms_offline_exam_answer')->insert($chunk);
                }

                $published++;
            }

            DB::table('exam_evaluation_batch')->where('id', $id)->update([
                'status' => 'Published',
                'published_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $this->success([
            'published' => $published,
            'skipped' => $pending,
        ], $pending > 0
            ? "{$published} sheet(s) published. {$pending} sheet(s) were not approved and have been left out."
            : "{$published} sheet(s) published to the gradebook.");
    }

    // -- Plumbing -----------------------------------------------------------

    /**
     * Queues one sheet.
     *
     * Wrapped because dispatch() re-throws a job's own exceptions synchronously
     * while QUEUE_CONNECTION=sync, and a model outage on sheet 7 must not throw
     * away the upload of sheets 8 to 40. The job records its own failure on the
     * row either way.
     */
    private function dispatchEvaluation(int $sheetId): void
    {
        try {
            EvaluateAnswerSheetJob::dispatch($sheetId);
        } catch (Throwable $exception) {
            Log::error('Answer sheet evaluation could not be dispatched', [
                'sheet_id' => $sheetId,
                'message' => $exception->getMessage(),
            ]);

            DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update([
                'status' => 'Failed',
                'failure_reason' => mb_substr($exception->getMessage(), 0, 250),
                'updated_at' => now(),
            ]);
        }
    }

    private function findBatch(int $id, int $tenantId): ?object
    {
        if (! $tenantId) {
            return null;
        }

        return DB::table('exam_evaluation_batch')
            ->where('id', $id)
            ->where('sub_institute_id', $tenantId)
            ->first();
    }

    private function findSheet(int $id, int $tenantId): ?object
    {
        if (! $tenantId) {
            return null;
        }

        return DB::table('exam_evaluation_sheet')
            ->where('id', $id)
            ->where('sub_institute_id', $tenantId)
            ->first();
    }

    /** Adds the matched student's names, which `findSheet()` does not join. */
    private function decorateSheet(object $sheet): object
    {
        if (! $sheet->student_id) {
            return $sheet;
        }

        $student = DB::table('tblstudent')
            ->where('id', $sheet->student_id)
            ->first(['first_name', 'middle_name', 'last_name', 'enrollment_no']);

        if ($student) {
            $sheet->first_name = $student->first_name;
            $sheet->middle_name = $student->middle_name;
            $sheet->last_name = $student->last_name;
            $sheet->student_enrollment_no = $student->enrollment_no;
        }

        // On the enrollment, not the student -- see StudentMatcherService.
        $sheet->student_roll_no = (string) (DB::table('tblstudent_enrollment')
            ->where('student_id', $sheet->student_id)
            ->where('sub_institute_id', $sheet->sub_institute_id)
            ->where('syear', $sheet->syear)
            ->orderBy('id')
            ->value('roll_no') ?? '');

        return $sheet;
    }

    private function presentBatch(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'question_paper_id' => (int) $row->question_paper_id,
            'name' => (string) $row->name,
            'status' => (string) $row->status,
            'syear' => (int) $row->syear,
            'total_sheets' => (int) $row->total_sheets,
            'evaluated_sheets' => (int) $row->evaluated_sheets,
            'approved_sheets' => (int) $row->approved_sheets,
            'total_marks' => (float) $row->total_marks,
            'paper_name' => (string) ($row->paper_name ?? ''),
            'exam_type' => (string) ($row->exam_type ?? ''),
            'standard_name' => (string) ($row->standard_name ?? ''),
            'subject_name' => (string) ($row->subject_name ?? ''),
            'published_at' => $row->published_at ? (string) $row->published_at : null,
            'created_at' => $row->created_at ? (string) $row->created_at : null,
        ];
    }

    private function presentSheet(object $row): array
    {
        $studentName = trim(implode(' ', array_filter([
            $row->first_name ?? null,
            $row->middle_name ?? null,
            $row->last_name ?? null,
        ])));

        return [
            'id' => (int) $row->id,
            'batch_id' => (int) $row->batch_id,
            'original_name' => (string) ($row->original_name ?? ''),
            'file_type' => (string) ($row->file_type ?? ''),
            'status' => (string) $row->status,
            'student_id' => $row->student_id ? (int) $row->student_id : null,
            'student_name' => $studentName,
            'student_roll_no' => (string) ($row->student_roll_no ?? ''),
            'student_enrollment_no' => (string) ($row->student_enrollment_no ?? ''),
            'detected_roll_no' => (string) ($row->detected_roll_no ?? ''),
            'detected_enrollment_no' => (string) ($row->detected_enrollment_no ?? ''),
            'detected_student_name' => (string) ($row->detected_student_name ?? ''),
            'identity_source' => (string) ($row->identity_source ?? ''),
            'identity_confidence' => $row->identity_confidence === null ? null : (float) $row->identity_confidence,
            'ai_total' => $row->ai_total === null ? null : (float) $row->ai_total,
            'teacher_total' => $row->teacher_total === null ? null : (float) $row->teacher_total,
            'max_marks' => $row->max_marks === null ? null : (float) $row->max_marks,
            'percentage' => $row->percentage === null ? null : (float) $row->percentage,
            'has_annotated' => ! empty($row->annotated_file_name),
            'failure_reason' => (string) ($row->failure_reason ?? ''),
            'evaluated_at' => $row->evaluated_at ? (string) $row->evaluated_at : null,
            'approved_at' => $row->approved_at ? (string) $row->approved_at : null,
        ];
    }

    private function success(array $data, string $message = 'SUCCESS'): JsonResponse
    {
        return response()->json([
            'status_code' => 1,
            'message' => $message,
            'data' => $data,
        ]);
    }

    private function failure(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
