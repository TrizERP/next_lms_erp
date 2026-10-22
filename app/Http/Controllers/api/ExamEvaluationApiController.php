<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamEvaluation\ConfirmIdentitiesRequest;
use App\Http\Requests\ExamEvaluation\CreateBatchRequest;
use App\Http\Requests\ExamEvaluation\ReviewSheetRequest;
use App\Http\Requests\ExamEvaluation\UploadSheetsRequest;
use App\Jobs\EvaluateAnswerSheetJob;
use App\Jobs\IdentifyAnswerSheetJob;
use App\Services\Evaluation\AnswerKeyService;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\ExamSheetMarkup;
use App\Services\Evaluation\QuestionLabel;
use App\Services\Evaluation\ReviewRejected;
use App\Services\Evaluation\SheetIdentity;
use App\Services\Evaluation\StudentMatcherService;
use App\Services\Homework\GeminiClient;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        private readonly ExamEvaluationStorage $storage,
        private readonly ExamSheetMarkup $markup
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

    public function store(CreateBatchRequest $request): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $paperId = (int) $request->input('question_paper_id');

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
            'created_by' => $this->actorId($request),
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

        try {
            $files = DB::transaction(function () use ($tenantId, $id) {
                // Locked and re-read: a publish that committed since the check above must
                // win, and a sheet uploaded a moment ago must be in the list of files to remove.
                $locked = DB::table('exam_evaluation_batch')
                    ->where('id', $id)
                    ->where('sub_institute_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new ReviewRejected('Evaluation batch not found.', 404);
                }

                if ($locked->status === 'Published') {
                    throw new ReviewRejected('This batch has already been published to the gradebook and cannot be removed.');
                }

                $sheets = DB::table('exam_evaluation_sheet')->where('batch_id', $id)->get(['file_name', 'annotated_file_name']);

                DB::table('exam_evaluation_answer')->where('batch_id', $id)->delete();
                DB::table('exam_evaluation_sheet')->where('batch_id', $id)->delete();
                DB::table('exam_evaluation_batch')->where('id', $id)->delete();

                return $sheets;
            }, 3);
        } catch (ReviewRejected $rejected) {
            return $this->failure($rejected->getMessage(), $rejected->status, $rejected->errors);
        }

        AuditTrail::record($tenantId, 'exam_evaluation', 'batch_delete', 'delete_batch', [
            'entity_type' => 'exam_evaluation_batch',
            'entity_id' => $id,
            'actor_user_id' => $this->actorId($request),
            'before' => ['status' => $batch->status, 'sheets' => $files->count()],
        ], $request);

        foreach ($files as $sheet) {
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
     *
     * An upload only starts the FIRST stage: the sheet is read for whose it is
     * and then waits for a teacher to confirm the student. Marking starts when
     * they do (see review() and confirmIdentities()), never from here.
     */
    public function uploadSheets(UploadSheetsRequest $request, int $id): JsonResponse
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
                    'status' => SheetIdentity::STATUS_IDENTIFYING,
                    'created_by' => $this->actorId($request),
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
            $this->dispatchIdentification($sheetId);
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
        $batch = $this->findBatch((int) $sheet->batch_id, $tenantId);

        try {
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

        // Sheets read before labels existed have none. Rather than fall back to bare positions,
        // number them from the paper's layout -- but only when NO answer has a real label, so an
        // inferred number is never shown beside one that was actually read.
        $inferred = $answers->contains(fn ($row) => ! empty($row->question_label))
            ? []
            : QuestionLabel::inferFromLayout($answers->mapWithKeys(fn ($row) => [(int) $row->question_no => (bool) $row->is_objective])->all());

        return $this->success([
            'sheet' => $this->presentSheet($this->decorateSheet($sheet)),
            'context' => $this->sheetContext($sheet, $batch),
            'answers' => $answers->map(fn ($row) => [
                'id' => (int) $row->id,
                // `question_no` is the question's POSITION in the paper: it ties the answer to the
                // marking key and is never the number to show. `question_label` is what the sheet
                // itself printed (Q.1(A), Q.2 ...), null when it could not be read reliably.
                'question_no' => (int) $row->question_no,
                'question_label' => ($row->question_label ?? null) !== null && $row->question_label !== '' ? (string) $row->question_label : null,
                'display_label' => QuestionLabel::display(($row->question_label ?? null) ?: ($inferred[(int) $row->question_no] ?? null), (int) $row->question_no),
                'label_from_sheet' => ($row->question_label ?? null) !== null && $row->question_label !== '',
                // Worked out from the paper's layout, not read from the sheet.
                'label_inferred' => isset($inferred[(int) $row->question_no]),
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
                // Absent until the review-fields migration has been applied.
                'teacher_feedback' => (string) ($row->teacher_feedback ?? ''),
                'needs_attention' => $this->needsAttention($row),
                // A teacher has put a number on it (typed, or accepted the AI's).
                'reviewed' => $row->teacher_marks !== null,
                'page' => (int) $row->page,
            ])->all(),
        ]);
    }

    /**
     * A teacher's pass over one sheet.
     *
     * Four things can happen here and any combination of them is valid: fixing
     * who the sheet belongs to, changing marks or feedback on individual
     * questions, approving the sheet, and re-opening an approved one.
     *
     * Marks are validated, not clamped: a mark outside 0..max is refused with the
     * question named, because quietly moving a teacher's number to a different
     * one is the kind of surprise that ends up on a report card.
     *
     * Approval is what turns proposals into marks, and it is deliberate. Every
     * question flagged for attention must carry a mark a teacher has put there
     * (typed, or accepted from the AI) before it goes through; the remaining
     * questions -- objective ones scored against the key, and blanks -- take the
     * AI's number at that moment, so an approved sheet has a complete,
     * teacher-owned set of marks and the total no longer moves if the model is
     * re-run. All of it happens in one transaction.
     */
    public function review(ReviewSheetRequest $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $found = $this->findSheet($id, $tenantId);

        if (! $found) {
            return $this->failure('Answer sheet not found.', 404);
        }

        try {
            // Three tries, because a lock wait that deadlocks is retried by Laravel and
            // everything inside is safe to repeat: it re-reads the locked rows each time.
            $result = DB::transaction(
                fn () => $this->applyReview($request, $tenantId, $id, (int) $found->batch_id),
                3
            );
        } catch (ReviewRejected $rejected) {
            return $this->failure($rejected->getMessage(), $rejected->status, $rejected->errors);
        }

        EvaluateAnswerSheetJob::refreshBatch((int) $found->batch_id);

        // The saved marked-up copy shows the marks that count, so it is redrawn whenever a save
        // changed them (a mark typed, the AI's accepted on approval, an approval undone). The
        // uploaded scan is only ever read. A failure here is logged and never fails the save.
        if ($result['edits'] !== [] || $result['approved_now'] || $result['reopened_now']) {
            $this->markup->regenerate($id);
        }

        // Marking starts the moment a teacher has said whose sheet this is --
        // and only then. Queued after the commit, so the job never runs ahead of
        // the confirmation it depends on.
        if ($result['begins_evaluation']) {
            $this->dispatchEvaluation($id);
        }

        $this->auditReview($request, $tenantId, $id, $result);

        return $this->sheet($request, $id);
    }

    /**
     * One review, decided and written entirely under row locks.
     *
     * The batch row is locked first and the sheet row second, here and everywhere else
     * in this module that takes both (publish, bulk confirmation, the identity job), so
     * two requests can never wait on each other in a circle. Locking the batch is also
     * what keeps a review and a publish from interleaving: publish holds the same lock
     * while it reads the approved sheets, so a sheet cannot be re-opened (or approved)
     * half-way through being published.
     *
     * EVERY rule below is judged on the sheet and answers read after the locks were
     * taken. That is the point of the shape: with the old order (validate on a copy, then
     * lock to write) two requests could both pass the checks on the same stale copy,
     * double-stamping an approval or letting an edit land on a sheet that a second
     * request had approved a moment before. Now whichever request gets the lock second
     * simply sees the sheet as the first one left it.
     *
     * @return array<string,mixed>  What was decided, for the audit trail and the caller.
     *
     * @throws ReviewRejected  A rule failed; the transaction is rolled back, nothing is written.
     */
    private function applyReview(ReviewSheetRequest $request, int $tenantId, int $id, int $batchId): array
    {
        $batch = DB::table('exam_evaluation_batch')
            ->where('id', $batchId)
            ->where('sub_institute_id', $tenantId)
            ->lockForUpdate()
            ->first();

        $sheet = DB::table('exam_evaluation_sheet')
            ->where('id', $id)
            ->where('sub_institute_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if (! $sheet) {
            throw new ReviewRejected('Answer sheet not found.', 404);
        }

        if ($batch && $batch->status === 'Published') {
            throw new ReviewRejected('This batch has been published; its marks can no longer be changed here.');
        }

        $actor = $this->actorId($request);
        $approving = $request->boolean('approve');
        $reopening = $request->boolean('unapprove') && ! $approving;
        $marksInput = is_array($request->input('marks')) ? $request->input('marks') : [];

        // Only a different student is a reassignment. Sending the one already on the
        // sheet back is a no-op, so it neither blocks an approved sheet nor rewrites
        // how the sheet was matched ("manual" would replace "GR number").
        $requestedStudent = $request->has('student_id') ? max(0, (int) $request->input('student_id')) : null;
        $studentChanging = $requestedStudent !== null && $requestedStudent !== (int) $sheet->student_id;

        // "These are the right students" -- the teacher agreeing with the student
        // already on the sheet, which is what turns a suggestion into an assignment.
        $confirming = $request->boolean('confirm_identity');

        // Only an APPROVED sheet can be re-opened. A sheet that is pending, being read,
        // failed or waiting for its student has no approval to take back, and moving it
        // to "Needs review" would pass it off as marked work it is not. A second,
        // simultaneous re-open lands here too, because the first has already done it.
        if ($reopening && $sheet->status !== 'Approved') {
            throw new ReviewRejected(sprintf('Only an approved sheet can be re-opened. This one is "%s".', $sheet->status));
        }

        if ($sheet->status === 'Approved' && ! $reopening && ($marksInput !== [] || $studentChanging)) {
            throw new ReviewRejected('This sheet is approved. Re-open it before changing its marks or student.');
        }

        // Before the student is confirmed there is nothing to mark: no answers
        // exist yet, because evaluation only starts once somebody has said whose
        // sheet this is.
        if (in_array($sheet->status, SheetIdentity::PRE_EVALUATION, true) && ($approving || $marksInput !== [])) {
            throw new ReviewRejected('This sheet is still waiting for its student to be confirmed. Confirm the student first; it is marked after that.');
        }

        if (in_array($sheet->status, ['Pending', 'Processing', 'Failed'], true) && ($approving || $marksInput !== [])) {
            throw new ReviewRejected(
                $sheet->status === 'Failed'
                    ? 'This sheet could not be evaluated, so there are no marks to review. Re-run it first.'
                    : 'This sheet has not finished evaluating yet.'
            );
        }

        $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $id)->get()->keyBy('question_no');
        $feedbackColumn = $this->hasFeedbackColumn();

        // -- Validate everything before writing anything ------------------------

        $edits = [];
        $problems = [];

        foreach ($marksInput as $row) {
            $questionNo = (int) ($row['question_no'] ?? 0);
            $answer = $answers->get($questionNo);

            if (! $answer) {
                $problems[] = "Question {$questionNo} is not on this sheet.";

                continue;
            }

            $edit = [];

            if (array_key_exists('teacher_marks', $row)) {
                $given = $row['teacher_marks'];

                if ($given === null || $given === '') {
                    $edit['teacher_marks'] = null;
                } else {
                    $value = round((float) $given, 2);

                    if ($value < 0 || $value > (float) $answer->max_marks) {
                        $problems[] = sprintf(
                            '%s: marks must be between 0 and %s.',
                            $this->questionName($answer, $questionNo),
                            $this->trimNumber((float) $answer->max_marks)
                        );
                    } else {
                        $edit['teacher_marks'] = $value;
                    }
                }
            }

            if (array_key_exists('teacher_feedback', $row)) {
                $text = trim((string) ($row['teacher_feedback'] ?? ''));

                if (! $feedbackColumn) {
                    // Do not pretend to save what cannot be stored.
                    if ($text !== '') {
                        $problems[] = 'Feedback cannot be saved yet: the database update that adds it has not been applied.';
                    }
                } else {
                    $edit['teacher_feedback'] = $text === '' ? null : $text;
                }
            }

            if ($edit !== []) {
                $edits[$questionNo] = $edit;
            }
        }

        $sheetUpdates = [];
        $wasConfirmed = SheetIdentity::isConfirmed($sheet);
        $currentStudent = $sheet->student_id ? (int) $sheet->student_id : 0;
        $confirmedNow = false;
        $reason = null;
        $roster = null;

        if ($studentChanging || $confirming) {
            $targetStudent = $studentChanging ? (int) $requestedStudent : $currentStudent;

            if ($targetStudent === 0 && ! $studentChanging) {
                throw new ReviewRejected('Choose the student this sheet belongs to before confirming it.');
            }

            if ($targetStudent > 0) {
                $belongs = DB::table('tblstudent')
                    ->where('id', $targetStudent)
                    ->where('sub_institute_id', $tenantId)
                    ->exists();

                if (! $belongs) {
                    throw new ReviewRejected('That student is not on this school\'s roll.');
                }

                // On this school's roll is not enough: the student must be one
                // who is actually eligible for THIS exam -- enrolled this year in
                // the class the paper was set for. Otherwise a sheet, and its
                // marks, could be filed under a child who never sat the paper.
                $roster = $this->rosterFor($batch, $tenantId);

                if ($this->matcher->findEligible($roster, $targetStudent) === null) {
                    throw new ReviewRejected('That student is not on the class list for this exam, so this sheet cannot be assigned to them.');
                }

                // One sheet per student per batch. Settled here, under the batch lock, so
                // two teachers (or two tabs) assigning the same student to two sheets
                // cannot both get through.
                $clash = DB::table('exam_evaluation_sheet')
                    ->where('batch_id', $sheet->batch_id)
                    ->where('student_id', $targetStudent)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($clash) {
                    throw new ReviewRejected('Another sheet in this batch is already assigned to that student.');
                }
            }

            if ($studentChanging) {
                // Moving a sheet off a student a teacher has already confirmed, or
                // one whose marks are already being worked on, is a CORRECTION,
                // not a pick: it has to say why, so the trail shows who moved
                // marks from one child to another and for what reason.
                if ($currentStudent > 0 && ($wasConfirmed || $this->hasTeacherWork($answers))) {
                    $reason = trim((string) $request->input('reason', ''));

                    if (mb_strlen($reason) < 3) {
                        throw new ReviewRejected(
                            'This sheet has already been assigned to a student and confirmed or marked. Changing it is a correction: please give a reason so it is on record.'
                        );
                    }

                    $reason = mb_substr($reason, 0, 500);
                }

                $sheetUpdates = $targetStudent > 0
                    ? ['student_id' => $targetStudent, 'identity_source' => 'manual', 'identity_confidence' => 100] + SheetIdentity::confirmationFields($actor)
                    : ['student_id' => null, 'identity_source' => 'unmatched', 'identity_confidence' => null] + SheetIdentity::clearedConfirmation();
            } elseif (! $wasConfirmed) {
                // Agreeing with the suggestion: the student stays, and so does HOW
                // they were matched; only the confirmation is added.
                $sheetUpdates = SheetIdentity::confirmationFields($actor);
                $confirmedNow = true;
            }
        }

        // Who the sheet will belong to once this request is saved, and whether a
        // teacher will have vouched for that. Choosing a student by hand is itself
        // the confirmation.
        $effectiveStudent = array_key_exists('student_id', $sheetUpdates) ? $sheetUpdates['student_id'] : ($currentStudent ?: null);
        $confirmedAfter = $effectiveStudent !== null
            && (array_key_exists('student_id', $sheetUpdates) || $wasConfirmed || $confirmedNow);
        $beginsEvaluation = $confirmedAfter
            && ($studentChanging || $confirming)
            && in_array($sheet->status, SheetIdentity::PRE_EVALUATION, true);

        if ($approving) {
            if (! $effectiveStudent) {
                $problems[] = 'Assign this sheet to a student before approving it.';
            } else {
                if (! $confirmedAfter) {
                    $problems[] = 'Confirm that this sheet belongs to the student shown before approving it.';
                }

                // Checked again here, not only when the student was chosen: the
                // class list can change after that (a student leaves), and an
                // approved sheet is what reaches the gradebook.
                $roster ??= $this->rosterFor($batch, $tenantId);

                if (! $batch) {
                    $problems[] = 'The evaluation this sheet belongs to no longer exists.';
                } elseif ($this->matcher->findEligible($roster, (int) $effectiveStudent) === null) {
                    $problems[] = 'This student is no longer on the class list for this exam. Choose the right student before approving.';
                }

                if (DB::table('exam_evaluation_sheet')
                    ->where('batch_id', $sheet->batch_id)
                    ->where('student_id', $effectiveStudent)
                    ->where('id', '!=', $id)
                    ->exists()) {
                    $problems[] = 'Another sheet in this batch is already assigned to that student.';
                }
            }

            $unresolved = [];

            foreach ($answers as $questionNo => $answer) {
                if (! $this->needsAttention($answer)) {
                    continue;
                }

                $teacherMarks = isset($edits[$questionNo]) && array_key_exists('teacher_marks', $edits[$questionNo])
                    ? $edits[$questionNo]['teacher_marks']
                    : $answer->teacher_marks;

                if ($teacherMarks === null) {
                    // Named as the sheet numbers it (Q.1(B)) when that was read, so the teacher can find it.
                    $unresolved[] = ! empty($answer->question_label) ? (string) $answer->question_label : (string) (int) $questionNo;
                }
            }

            if ($unresolved !== []) {
                $problems[] = sprintf(
                    'Question%s %s need%s your mark before this sheet can be approved. Enter a mark, or accept the AI mark.',
                    count($unresolved) === 1 ? '' : 's',
                    implode(', ', $unresolved),
                    count($unresolved) === 1 ? 's' : ''
                );
            }
        }

        if ($problems !== []) {
            $problems = array_values(array_unique($problems));

            throw new ReviewRejected(implode(' ', $problems), 422, $problems);
        }

        // -- Write it all, or none of it ---------------------------------------
        //
        // Nothing below can fail a rule: it was all decided above, on the locked rows.

        foreach ($edits as $questionNo => $edit) {
            DB::table('exam_evaluation_answer')
                ->where('sheet_id', $id)
                ->where('question_no', $questionNo)
                ->update($edit + ['updated_at' => now()]);
        }

        $updates = $sheetUpdates;
        $approvedNow = $approving && $sheet->status !== 'Approved';
        $reopenedNow = $reopening;

        if ($beginsEvaluation) {
            $updates['status'] = 'Pending';
            $updates['failure_reason'] = null;
        }

        if ($approving) {
            DB::table('exam_evaluation_answer')
                ->where('sheet_id', $id)
                ->whereNull('teacher_marks')
                ->update(['teacher_marks' => DB::raw('COALESCE(ai_marks, 0)'), 'updated_at' => now()]);

            // Approving an already-approved sheet changes nothing, including
            // who approved it and when. A second simultaneous approval arrives
            // here after the first has committed, sees "Approved", and stops.
            if ($approvedNow) {
                $updates['status'] = 'Approved';
                $updates['approved_by'] = $actor;
                $updates['approved_at'] = now();
            }
        } elseif ($reopenedNow) {
            $updates['status'] = 'Needs review';
            $updates['approved_by'] = null;
            $updates['approved_at'] = null;
        }

        $teacherTotal = null;
        $percentage = null;

        // A sheet still waiting for its student has no answers and so no total;
        // writing one would put a 0 on a sheet nobody has marked.
        if ($answers->isNotEmpty()) {
            $teacherTotal = round((float) (DB::table('exam_evaluation_answer')
                ->where('sheet_id', $id)
                ->selectRaw('SUM(COALESCE(teacher_marks, ai_marks, 0)) as total')
                ->value('total') ?? 0), 2);

            $maxMarks = (float) ($sheet->max_marks ?? DB::table('exam_evaluation_answer')->where('sheet_id', $id)->sum('max_marks'));

            // Recomputed with the total, so it can no longer drift from it.
            $percentage = $maxMarks > 0 ? round($teacherTotal / $maxMarks * 100, 2) : null;

            $updates['teacher_total'] = $teacherTotal;
            $updates['percentage'] = $percentage;
        }

        $updates['updated_at'] = now();

        DB::table('exam_evaluation_sheet')->where('id', $id)->update($updates);

        return [
            'sheet' => $sheet,
            'answers' => $answers,
            'edits' => $edits,
            'sheet_updates' => $sheetUpdates,
            'actor' => $actor,
            'reason' => $reason,
            'confirmed_now' => $confirmedNow,
            'approved_now' => $approvedNow,
            'reopened_now' => $reopenedNow,
            'begins_evaluation' => $beginsEvaluation,
            'teacher_total' => $teacherTotal,
            'percentage' => $percentage,
        ];
    }

    /**
     * The trail for a review: what a teacher changed, from what, to what.
     *
     * Built from what `applyReview()` actually did, on the locked rows, never from a
     * copy read beforehand: an approval is recorded exactly once, by the request that
     * made the change, and a request that found the sheet already approved (or already
     * re-opened) records nothing. Written after the transaction commits, and by an audit
     * helper that never throws, so a logging problem cannot undo or fail a teacher's save.
     *
     * Ids only, never names: the trail says WHICH student moved to WHICH, and why,
     * without copying a child's details into a log table.
     *
     * @param  array<string,mixed>  $result  From applyReview().
     */
    private function auditReview(Request $request, int $tenantId, int $sheetId, array $result): void
    {
        /** @var object $sheet */
        $sheet = $result['sheet'];
        $answers = $result['answers'];
        $edits = $result['edits'];
        $sheetUpdates = $result['sheet_updates'];
        $actor = $result['actor'];
        $base = ['entity_type' => 'exam_evaluation_sheet', 'entity_id' => $sheetId, 'actor_user_id' => $actor];

        if ($edits !== []) {
            $before = [];
            $after = [];

            foreach ($edits as $questionNo => $edit) {
                $row = $answers->get($questionNo);

                foreach ($edit as $field => $value) {
                    $previous = $row->{$field} ?? null;

                    if ((string) $previous !== (string) $value) {
                        $before["q{$questionNo}"][$field] = $previous;
                        $after["q{$questionNo}"][$field] = $value;
                    }
                }
            }

            if ($after !== []) {
                AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'update_marks', $base + ['before' => $before, 'after' => $after], $request);
            }
        }

        if (array_key_exists('student_id', $sheetUpdates) && (int) $sheetUpdates['student_id'] !== (int) $sheet->student_id) {
            AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'assign_student', $base + [
                'before' => [
                    'student_id' => $sheet->student_id,
                    'identity_source' => $sheet->identity_source,
                    'confirmed' => SheetIdentity::isConfirmed($sheet),
                ],
                'after' => array_filter([
                    'student_id' => $sheetUpdates['student_id'],
                    'identity_source' => $sheetUpdates['identity_source'],
                    'confirmed' => $sheetUpdates['student_id'] !== null,
                    'reason' => $result['reason'],
                ], static fn ($value) => $value !== null),
            ], $request);
        } elseif ($result['confirmed_now']) {
            AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'confirm_identity', $base + [
                'before' => ['student_id' => $sheet->student_id, 'identity_source' => $sheet->identity_source, 'confirmed' => false],
                'after' => ['student_id' => $sheet->student_id, 'identity_source' => $sheetUpdates['identity_source'] ?? $sheet->identity_source, 'confirmed' => true],
            ], $request);
        }

        if ($result['approved_now']) {
            AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'approve', $base + [
                'before' => ['status' => $sheet->status, 'ai_total' => $sheet->ai_total],
                'after' => ['status' => 'Approved', 'teacher_total' => $result['teacher_total'], 'percentage' => $result['percentage']],
            ], $request);
        } elseif ($result['reopened_now']) {
            AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'reopen', $base + [
                'before' => ['status' => 'Approved', 'teacher_total' => $sheet->teacher_total],
                'after' => ['status' => 'Needs review'],
            ], $request);
        }
    }

    public function reprocess(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $found = $this->findSheet($id, $tenantId);

        if (! $found) {
            return $this->failure('Answer sheet not found.', 404);
        }

        try {
            // Decided under the batch-then-sheet locks, like a review: reading the sheet
            // first and writing after (the old shape) let a re-run overwrite an approval
            // that had committed in between, resetting an approved sheet to "Pending".
            $result = DB::transaction(function () use ($tenantId, $id, $found) {
                $batch = DB::table('exam_evaluation_batch')
                    ->where('id', $found->batch_id)
                    ->where('sub_institute_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                $sheet = DB::table('exam_evaluation_sheet')
                    ->where('id', $id)
                    ->where('sub_institute_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if (! $sheet) {
                    throw new ReviewRejected('Answer sheet not found.', 404);
                }

                if ($batch && $batch->status === 'Published') {
                    throw new ReviewRejected('This batch has been published; its sheets can no longer be re-run.');
                }

                if ($sheet->status === 'Approved') {
                    throw new ReviewRejected('This sheet has been approved. Re-open it for review before re-running the evaluation.');
                }

                // A sheet that is being worked on is left alone -- unless nothing has touched it
                // for long enough that its job is presumed lost (a worker that died, or never
                // started). Then it may be put back on the queue; if the original is somehow
                // still running, the job's own overlap lock drops the second copy.
                $stalled = SheetIdentity::isStalled($sheet);

                if ($sheet->status === 'Processing' && ! $stalled) {
                    throw new ReviewRejected('This sheet is already being evaluated. Wait for it to finish before re-running it.');
                }

                if ($sheet->status === SheetIdentity::STATUS_IDENTIFYING && ! $stalled) {
                    throw new ReviewRejected('This sheet is already being read. Wait for it to finish before reading it again.');
                }

                // A sheet whose student a teacher has confirmed is re-MARKED, and keeps
                // that student. One that has only a suggestion goes back to the start --
                // read for its identity again -- so marking never runs on a student
                // nobody has agreed to. (Marks and feedback a teacher already saved are
                // kept either way.)
                $confirmed = SheetIdentity::isConfirmed($sheet);
                $next = $confirmed ? 'Pending' : SheetIdentity::STATUS_IDENTIFYING;

                DB::table('exam_evaluation_sheet')->where('id', $id)->update([
                    'status' => $next,
                    'failure_reason' => null,
                    'updated_at' => now(),
                ]);

                return ['before' => (string) $sheet->status, 'next' => $next, 'confirmed' => $confirmed, 'stalled' => $stalled];
            }, 3);
        } catch (ReviewRejected $rejected) {
            return $this->failure($rejected->getMessage(), $rejected->status, $rejected->errors);
        }

        AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'reprocess', [
            'entity_type' => 'exam_evaluation_sheet',
            'entity_id' => $id,
            'actor_user_id' => $this->actorId($request),
            'before' => array_filter(['status' => $result['before'], 'stalled' => $result['stalled'] ?: null], static fn ($value) => $value !== null),
            'after' => ['status' => $result['next']],
        ], $request);

        if ($result['confirmed']) {
            $this->dispatchEvaluation($id);
        } else {
            $this->dispatchIdentification($id);
        }

        // Counters recomputed: the sheet has just left a settled state.
        EvaluateAnswerSheetJob::refreshBatch((int) $found->batch_id);

        return $this->sheet($request, $id);
    }

    /**
     * Confirms several suggested students at once, for the sheets where the
     * suggestion is as strong as it gets: a clean match on a GR or roll number,
     * with nothing flagged against it.
     *
     * Only that kind. A name-only match, a conflict, a duplicate or an unmatched
     * sheet is never confirmed in bulk, whatever ids are sent: those need
     * someone to look at the scan, one at a time. Each sheet is checked again on
     * its own (still eligible, still not claimed by another sheet), so one bad
     * sheet is reported and skipped rather than holding up the rest.
     *
     * Confirming is what starts marking, so every confirmed sheet is queued for
     * evaluation.
     */
    public function confirmIdentities(ConfirmIdentitiesRequest $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $batch = $this->findBatch($id, $tenantId);

        if (! $batch) {
            return $this->failure('Evaluation batch not found.', 404);
        }

        if ($batch->status === 'Published') {
            return $this->failure('This batch has been published; its sheets can no longer be changed here.', 422);
        }

        $actor = $this->actorId($request);
        $roster = $this->rosterFor($batch, $tenantId);
        $confirmed = [];
        $skipped = [];

        foreach (array_values(array_unique(array_map('intval', (array) $request->input('sheet_ids', [])))) as $sheetId) {
            // Scoped to this batch and school: an id from elsewhere is "not found", not a clue.
            $sheet = DB::table('exam_evaluation_sheet')
                ->where('id', $sheetId)
                ->where('batch_id', $id)
                ->where('sub_institute_id', $tenantId)
                ->first();

            if (! $sheet) {
                $skipped[] = ['sheet_id' => $sheetId, 'reason' => 'not_found'];

                continue;
            }

            if (! SheetIdentity::isBulkConfirmable($sheet)) {
                $skipped[] = ['sheet_id' => $sheetId, 'reason' => 'needs_individual_review'];

                continue;
            }

            if ($this->matcher->findEligible($roster, (int) $sheet->student_id) === null) {
                $skipped[] = ['sheet_id' => $sheetId, 'reason' => 'not_eligible'];

                continue;
            }

            $outcome = DB::transaction(function () use ($sheet, $actor) {
                DB::table('exam_evaluation_batch')->where('id', $sheet->batch_id)->lockForUpdate()->first();

                $locked = DB::table('exam_evaluation_sheet')->where('id', $sheet->id)->lockForUpdate()->first();

                // Re-read under the lock: a teacher in another tab may have just
                // acted on this very sheet.
                if (! $locked || ! SheetIdentity::isBulkConfirmable($locked) || (int) $locked->student_id !== (int) $sheet->student_id) {
                    return 'changed';
                }

                if (DB::table('exam_evaluation_sheet')
                    ->where('batch_id', $sheet->batch_id)
                    ->where('student_id', $locked->student_id)
                    ->where('id', '!=', $sheet->id)
                    ->exists()) {
                    return 'duplicate';
                }

                DB::table('exam_evaluation_sheet')->where('id', $sheet->id)->update([
                    'status' => 'Pending',
                    'failure_reason' => null,
                    'updated_at' => now(),
                ] + SheetIdentity::confirmationFields($actor));

                return 'confirmed';
            });

            if ($outcome !== 'confirmed') {
                $skipped[] = ['sheet_id' => $sheetId, 'reason' => $outcome];

                continue;
            }

            $confirmed[] = $sheetId;

            AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'confirm_identity', [
                'entity_type' => 'exam_evaluation_sheet',
                'entity_id' => $sheetId,
                'actor_user_id' => $actor,
                'before' => ['student_id' => $sheet->student_id, 'identity_source' => $sheet->identity_source, 'confirmed' => false],
                'after' => ['student_id' => $sheet->student_id, 'confirmed' => true, 'bulk' => true],
            ], $request);

            $this->dispatchEvaluation($sheetId);
        }

        EvaluateAnswerSheetJob::refreshBatch($id);

        $response = $this->show($request, $id);
        $payload = $response->getData(true);
        $payload['data']['confirmed'] = $confirmed;
        $payload['data']['skipped'] = $skipped;

        return $response->setData($payload);
    }

    public function destroySheet(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $sheet = $this->findSheet($id, $tenantId);

        if (! $sheet) {
            return $this->failure('Answer sheet not found.', 404);
        }

        try {
            $removed = DB::transaction(function () use ($tenantId, $id, $sheet) {
                // Same lock order as every other writer: batch, then sheet.
                $batch = DB::table('exam_evaluation_batch')
                    ->where('id', $sheet->batch_id)
                    ->where('sub_institute_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                $current = DB::table('exam_evaluation_sheet')
                    ->where('id', $id)
                    ->where('sub_institute_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if (! $current) {
                    throw new ReviewRejected('Answer sheet not found.', 404);
                }

                // A published batch is a record of what reached the gradebook; its sheets
                // are not removed out from under it.
                if ($batch && $batch->status === 'Published') {
                    throw new ReviewRejected('This batch has been published; its sheets can no longer be removed.');
                }

                DB::table('exam_evaluation_answer')->where('sheet_id', $id)->delete();
                DB::table('exam_evaluation_sheet')->where('id', $id)->delete();

                return $current;
            }, 3);
        } catch (ReviewRejected $rejected) {
            return $this->failure($rejected->getMessage(), $rejected->status, $rejected->errors);
        }

        // Removing a sheet removes its marks, so it is on the trail: ids and the state it
        // was in, never the scan's name or the student's.
        AuditTrail::record($tenantId, 'exam_evaluation', 'sheet_review', 'delete_sheet', [
            'entity_type' => 'exam_evaluation_sheet',
            'entity_id' => $id,
            'actor_user_id' => $this->actorId($request),
            'before' => ['status' => $removed->status, 'student_id' => $removed->student_id, 'batch_id' => (int) $removed->batch_id],
        ], $request);

        $this->storage->delete($sheet->file_name, $sheet->annotated_file_name);
        EvaluateAnswerSheetJob::refreshBatch((int) $sheet->batch_id);

        return $this->success([], 'Answer sheet removed.');
    }

    /**
     * The marked-up PDF as it WOULD look with the marks a teacher has typed but not saved.
     *
     * Draws the stored answers with the given teacher marks laid over them and streams the
     * result. Nothing is written: not the marks, not a file, not an audit row. Saving is
     * still the review endpoint. The marks are held to the same rule as a save (0 to the
     * question's maximum, refused rather than clamped), so a preview never shows a mark the
     * save would reject.
     */
    public function preview(Request $request, int $id): \Symfony\Component\HttpFoundation\Response
    {
        $tenantId = (int) $request->input('sub_institute_id');
        $sheet = $this->findSheet($id, $tenantId);

        if (! $sheet) {
            return $this->failure('Answer sheet not found.', 404);
        }

        $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $id)->orderBy('question_no')->get();

        if ($answers->isEmpty()) {
            return $this->failure('This sheet has no marks to draw yet.', 422);
        }

        $byQuestion = $answers->keyBy('question_no');
        $overrides = [];
        $problems = [];
        $marksInput = $request->input('marks');

        foreach (is_array($marksInput) ? $marksInput : [] as $row) {
            $questionNo = (int) (is_array($row) ? ($row['question_no'] ?? 0) : 0);
            $answer = $byQuestion->get($questionNo);

            if (! $answer) {
                $problems[] = "Question {$questionNo} is not on this sheet.";

                continue;
            }

            $given = $row['teacher_marks'] ?? null;
            if ($given === null || $given === '') {
                $overrides[$questionNo] = null;
            } elseif (! is_numeric($given) || (float) $given < 0 || round((float) $given, 2) > (float) $answer->max_marks) {
                $problems[] = sprintf('%s: marks must be between 0 and %s.', $this->questionName($answer, $questionNo), $this->trimNumber((float) $answer->max_marks));
            } else {
                $overrides[$questionNo] = round((float) $given, 2);
            }
        }

        if ($problems !== []) {
            return $this->failure(implode(' ', $problems), 422, $problems);
        }

        if ($this->storage->sheetPath((string) $sheet->file_name) === null) {
            return $this->failure('The scan is no longer on the server.', 404);
        }

        $pdf = $this->markup->render($sheet, $answers, $overrides);

        if ($pdf === null) {
            return $this->failure('There is nothing to mark up on this sheet yet.', 422);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="marked-up-preview.pdf"',
            'Cache-Control' => 'no-store',
        ]);
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

        // Who may be assigned a sheet for this exam, said before any scanning: how many
        // students are eligible, and whether the exam is tied to a class at all. A paper that
        // names no class has no class list to hold a sheet to -- everyone enrolled in the school
        // that year would be "eligible" -- so a teacher is told to set its class first.
        // A count only: no student is named here.
        $eligible = $this->matcher->roster(
            $tenantId,
            (int) ($request->input('syear') ?: $key['paper']['syear']),
            (int) $key['paper']['standard_id'],
            (int) $key['paper']['grade_id']
        );

        return $this->success([
            'paper' => $key['paper'],
            'applies_to_class' => (int) $key['paper']['standard_id'] > 0,
            'eligible_students' => count($eligible),
            // A problem with the AI key that its FORMAT already shows (empty, or a Vertex/OAuth
            // credential where an AI Studio key is needed), said before any sheet is uploaded
            // and sent to the provider for nothing. No request is made, and the key is never echoed.
            'ai_warning' => app(GeminiClient::class)->forInstitute($tenantId)->credentialProblem(),
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

        $userId = $this->actorId($request);
        $published = 0;
        $alreadyPublished = false;
        $noneApproved = false;

        $blocked = [];
        $roster = $this->rosterFor($batch, $tenantId);

        DB::transaction(function () use ($batch, $id, $userId, $roster, &$published, &$alreadyPublished, &$noneApproved, &$blocked) {
            // Re-checked under a lock: two clicks on Publish (or two tabs) both
            // pass the check above, and only the first may write.
            $fresh = DB::table('exam_evaluation_batch')->where('id', $id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status === 'Published') {
                $alreadyPublished = true;

                return;
            }

            // Read again now the batch is locked: a sheet re-opened since the
            // list above was built must not be published as if still approved.
            $approvedNow = DB::table('exam_evaluation_sheet')
                ->where('batch_id', $id)
                ->where('status', 'Approved')
                ->whereNotNull('student_id')
                ->get();

            if ($approvedNow->isEmpty()) {
                $noneApproved = true;

                return;
            }

            // The last look before marks reach the gradebook: every approved sheet
            // must still belong to this school and exam, to a student who is still
            // eligible for it, and to a student no other approved sheet claims. The
            // class list can have changed since approval (a student left), and a
            // student published twice would have the second sheet silently replace
            // the first. Nothing is published unless all of it is clean, so no
            // student's result is dropped without anyone being told.
            $claimed = [];

            foreach ($approvedNow as $sheet) {
                $studentId = (int) $sheet->student_id;
                $label = (string) ($sheet->original_name ?: 'Sheet ' . $sheet->id);

                if ((int) $sheet->sub_institute_id !== (int) $batch->sub_institute_id) {
                    $blocked[] = "{$label}: this sheet does not belong to this school's evaluation.";
                } elseif ($this->matcher->findEligible($roster, $studentId) === null) {
                    $blocked[] = "{$label}: the student is no longer on the class list for this exam.";
                }

                if (isset($claimed[$studentId])) {
                    $blocked[] = "{$label}: another approved sheet in this batch is already assigned to the same student.";
                }

                $claimed[$studentId] = true;
            }

            if ($blocked !== []) {
                return;
            }

            foreach ($approvedNow as $sheet) {
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

        if ($alreadyPublished) {
            return $this->failure('This batch has already been published.', 422);
        }

        if ($noneApproved) {
            return $this->failure('No sheets in this batch have been approved yet.', 422);
        }

        if ($blocked !== []) {
            return $this->failure(
                'Nothing was published, because of the following. Fix them and publish again. ' . implode(' ', $blocked),
                422,
                $blocked
            );
        }

        AuditTrail::record($tenantId, 'exam_evaluation', 'batch_publish', 'publish', [
            'entity_type' => 'exam_evaluation_batch',
            'entity_id' => $id,
            'actor_user_id' => $userId,
            'after' => ['published_sheets' => $published, 'skipped_sheets' => $pending],
        ], $request);

        return $this->success([
            'published' => $published,
            'skipped' => $pending,
        ], $pending > 0
            ? "{$published} sheet(s) published. {$pending} sheet(s) were not approved and have been left out."
            : "{$published} sheet(s) published to the gradebook.");
    }

    // -- Plumbing -----------------------------------------------------------

    /**
     * Who is acting.
     *
     * The verified user from the token (set by RequireApiJwt) wins; the
     * client-sent `user_id` is only a fallback for a request that did not pass
     * through the guard. It is what `created_by` / `approved_by` record, so it
     * must not be something a caller can simply type.
     */
    private function actorId(Request $request): ?int
    {
        $verified = (int) $request->attributes->get('verified_user_id', 0);

        return $verified > 0 ? $verified : ((int) $request->input('user_id') ?: null);
    }

    /**
     * Whether a teacher has to look at this answer before the sheet is approved.
     *
     * The same rule the scorer applies when it decides a sheet needs review:
     * a written answer that was attempted and that the AI was not sure about
     * (or did not mark at all). Objective answers are scored against the key.
     */
    private function needsAttention(object $answer): bool
    {
        return ! $answer->is_objective
            && $answer->status !== 'unattempted'
            && (float) $answer->ai_confidence < AnswerSheetScoringService::CONFIDENCE_REVIEW_THRESHOLD;
    }

    /**
     * Whether a teacher has already put their own work on this sheet -- a mark or
     * a note -- as opposed to it holding only the AI's proposals.
     *
     * @param  \Illuminate\Support\Collection<int,object>  $answers
     */
    private function hasTeacherWork($answers): bool
    {
        return $answers->contains(
            static fn (object $answer) => $answer->teacher_marks !== null
                || trim((string) ($answer->teacher_feedback ?? '')) !== ''
        );
    }

    /**
     * The students eligible to sit this batch's exam: enrolled this year in the
     * class its paper was set for. A batch that no longer exists has none.
     *
     * @return array<int,array<string,mixed>>
     */
    private function rosterFor(?object $batch, int $tenantId): array
    {
        return $batch
            ? $this->matcher->rosterForPaper($tenantId, (int) $batch->syear, (int) $batch->question_paper_id)
            : [];
    }

    /**
     * `teacher_feedback` arrives with a migration. Until it has been applied the
     * review screen still has to work, so the controller asks rather than assumes.
     */
    private function hasFeedbackColumn(): bool
    {
        static $present = null;

        return $present ??= Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback');
    }

    /**
     * How a message names a question: the number the sheet printed when it was read, else its
     * position in the paper ("Question 2"), which is all there is to go on.
     */
    private function questionName(object $answer, int $questionNo): string
    {
        return ! empty($answer->question_label) ? (string) $answer->question_label : "Question {$questionNo}";
    }

    /** 5.00 -> "5", 2.50 -> "2.5". */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * What the review page header needs beyond the sheet itself: the exam, the
     * batch, the class, and the section the matched student is actually in.
     *
     * Section comes from the student's own enrollment (a paper belongs to a
     * class, not a division), so an unmatched sheet has none.
     *
     * @return array<string,mixed>
     */
    private function sheetContext(object $sheet, ?object $batch): array
    {
        $paper = $batch
            ? DB::table('question_paper as p')
                ->leftJoin('standard as st', 'st.id', '=', 'p.standard_id')
                ->leftJoin('academic_section as sec', 'sec.id', '=', 'p.grade_id')
                ->leftJoin('subject as sub', 'sub.id', '=', 'p.subject_id')
                ->where('p.id', $batch->question_paper_id)
                ->first(['p.paper_name', 'p.exam_type', 'st.name as standard_name', 'sec.title as grade_name', 'sub.subject_name'])
            : null;

        $section = null;

        if ($sheet->student_id) {
            $sectionQuery = DB::table('tblstudent_enrollment as e')
                ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
                ->where('e.student_id', $sheet->student_id)
                ->where('e.sub_institute_id', $sheet->sub_institute_id)
                ->where('e.syear', $sheet->syear);

            $section = StudentMatcherService::stillEnrolled($sectionQuery)
                ->orderBy('e.id')
                ->value('d.name');
        }

        return [
            'batch_id' => $batch ? (int) $batch->id : (int) $sheet->batch_id,
            'batch_name' => (string) ($batch->name ?? ''),
            'batch_status' => (string) ($batch->status ?? ''),
            'paper_id' => $batch ? (int) $batch->question_paper_id : null,
            'paper_name' => (string) ($paper->paper_name ?? ''),
            'exam_type' => (string) ($paper->exam_type ?? ''),
            'subject_name' => (string) ($paper->subject_name ?? ''),
            'class_name' => (string) ($paper->standard_name ?? ''),
            'grade_name' => (string) ($paper->grade_name ?? ''),
            'section_name' => (string) ($section ?? ''),
            'syear' => (int) $sheet->syear,
            'total_marks' => $batch ? (float) $batch->total_marks : null,
        ];
    }

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

    /** Queues the identity read for one sheet; the same care as dispatchEvaluation(). */
    private function dispatchIdentification(int $sheetId): void
    {
        try {
            IdentifyAnswerSheetJob::dispatch($sheetId);
        } catch (Throwable $exception) {
            Log::error('Answer sheet identification could not be dispatched', [
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
        $identity = SheetIdentity::block(isset($row->ai_result_json) ? (string) $row->ai_result_json : null);

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
            // Whether a teacher has vouched for the student on this sheet, as
            // against the matcher merely having suggested one.
            'identity_confirmed' => SheetIdentity::isConfirmed($row),
            'identity_confirmed_at' => ! empty($row->identity_confirmed_at) ? (string) $row->identity_confirmed_at : null,
            'identity_issue' => $identity['issue'] ?? null,
            'identity_candidates' => array_map('intval', (array) ($identity['candidates'] ?? [])),
            'identity_page' => isset($identity['page']) ? (int) $identity['page'] : null,
            'duplicate_of_sheet_id' => isset($identity['duplicate_of_sheet_id']) ? (int) $identity['duplicate_of_sheet_id'] : null,
            'can_bulk_confirm' => SheetIdentity::isBulkConfirmable($row),
            // In flight for so long that its job is presumed lost: it can be re-queued.
            'stalled' => SheetIdentity::isStalled($row),
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
