<?php

namespace App\Jobs;

use App\Models\student\studentHomeworkModel;
use App\Services\Evaluation\HomeworkMarkingService;
use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\Exceptions\EvaluationException;
use App\Services\Homework\HomeworkAnnotatedPdfService;
use App\Services\Homework\HomeworkAnswerLocatorService;
use App\Services\Homework\HomeworkDocumentExtractionService;
use App\Services\Homework\HomeworkEvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Queued homework evaluation: read the student's upload, mark it, draw the
 * verdicts onto their own pages, and leave the result for a teacher.
 *
 * TWO PATHS, one outcome. Homework arrives in two shapes and they cannot be
 * marked the same way:
 *
 *  - ANSWER KEY. The homework carries real questions (`homework.question_ids`,
 *    set when it was built from the question bank or taken from a homework
 *    paper). Then a true marking key exists — each question's own `points`, its
 *    correct options from `answer_master`, its model answer from
 *    `lms_question_master.answer` — and the submission is marked exactly as an
 *    exam answer sheet is, through the shared App\Services\Evaluation stack.
 *    Objective questions are compared to the key in PHP, so an MCQ in a
 *    homework book scores identically to the same MCQ on an exam paper.
 *
 *  - FREE FORM. The homework is an attachment with no questions on the row. The
 *    teacher's own file is read for the questions and the whole thing is judged
 *    by the model, one mark per question, which is what this job has always
 *    done.
 *
 * Either way the output is the same: one `homework_evaluation_answer` row per
 * question. That uniformity is the point — the teacher's review screen does not
 * need to know which path ran.
 *
 * What this job produces is a PROPOSAL. It writes `ai_marks` and never
 * `teacher_marks`, and it never sets a review status. A teacher approving the
 * submission is what turns proposals into marks.
 *
 * Dispatched right after the student's file is saved, so the upload request
 * never waits on model work. On QUEUE_CONNECTION=sync it still runs inline;
 * pointing that at database/redis with a worker makes it asynchronous with no
 * code change here.
 */
class EvaluateHomeworkSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        private readonly int $homeworkId,
        private readonly int $subInstituteId,
        private readonly int $syear
    ) {
    }

    public function handle(
        HomeworkDocumentExtractionService $extractor,
        HomeworkAnswerLocatorService $locator,
        HomeworkEvaluationService $evaluationService,
        HomeworkAnnotatedPdfService $annotatedPdfService,
        HomeworkMarkingService $marking
    ): void {
        $homework = studentHomeworkModel::where([
            'id' => $this->homeworkId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
        ])->first();

        if (!$homework || empty($homework->submission_image)) {
            return;
        }

        $submissionPath = $this->localPath($homework->submission_image);

        if (!$submissionPath) {
            $this->markFailed($homework, 'OCR Failed', 'The submitted file could not be located on disk.');
            return;
        }

        $submissionMime = $this->detectMime($submissionPath, $homework->submission_image_type);
        $questionIds = $this->questionIds($homework);

        try {
            $scored = $questionIds !== []
                ? $marking->markAgainstAnswerKey(
                    $questionIds,
                    (int) $homework->sub_institute_id,
                    [['path' => $submissionPath, 'mime' => $submissionMime]],
                    (string) $homework->title
                )
                : $this->markFreeForm($homework, $submissionPath, $submissionMime, $extractor, $locator, $evaluationService, $marking);
        } catch (DocumentExtractionException $exception) {
            Log::warning('Homework OCR/extraction failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($homework, null, "OCR failed: {$exception->getMessage()}");
            $this->markFailed($homework, 'OCR Failed', $exception->getMessage());
            return;
        } catch (EvaluationException $exception) {
            Log::warning('Homework evaluation failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($homework, null, "Evaluation failed: {$exception->getMessage()}");
            $this->markFailed($homework, 'Evaluation Failed', $exception->getMessage());
            return;
        } catch (Throwable $exception) {
            Log::warning('Homework evaluation failed unexpectedly', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            $this->markFailed($homework, 'Evaluation Failed', $exception->getMessage());
            return;
        }

        $annotatedUrl = $this->annotate(
            $annotatedPdfService,
            $submissionPath,
            $submissionMime,
            $marking->annotations($scored['answers'])
        );

        $this->persist($homework, $scored, $annotatedUrl, $marking);
        $this->logAiInteraction($homework, $scored, null);
    }

    // -- Path 2: no questions on the row --------------------------------------

    /**
     * The original path: read the teacher's attachment for the questions, read
     * the student's pages for the answers, and let the model judge both.
     *
     * Everything is worth one mark, because nothing here knows what any
     * question was worth -- there is no key, only two documents. The answers are
     * still written out per question so the review screen is identical.
     *
     * @return array{answers: array<int,array<string,mixed>>, ai_marks: float, max_marks: float, mode: string}
     */
    private function markFreeForm(
        studentHomeworkModel $homework,
        string $submissionPath,
        string $submissionMime,
        HomeworkDocumentExtractionService $extractor,
        HomeworkAnswerLocatorService $locator,
        HomeworkEvaluationService $evaluationService,
        HomeworkMarkingService $marking
    ): array {
        $assignmentPath = $this->localPath($homework->image);

        if (!$assignmentPath) {
            throw new DocumentExtractionException('The homework file this submission answers could not be found on disk.');
        }

        // A Word submission has no page images for the locator's spatial box_2d
        // lookup to make sense of, so its own text layer is read directly and
        // there is nothing to annotate.
        $submissionIsWord = in_array($submissionMime, HomeworkDocumentExtractionService::WORD_MIME_TYPES, true);

        $questionsText = $extractor->extractText(
            $assignmentPath,
            $this->detectMime($assignmentPath, $homework->image_type),
            'assignment questions'
        );

        $located = $submissionIsWord
            ? ['answers' => [], 'combined_text' => $extractor->extractText($submissionPath, $submissionMime, 'student answers')]
            : $locator->locateAnswers($submissionPath, $submissionMime);

        $evaluation = $evaluationService->evaluate($questionsText, $located['combined_text'], $homework->student_level);

        $scored = $marking->answersFromFreeForm($evaluation['results'], $located['answers']);

        if ($scored['answers'] === []) {
            throw new EvaluationException('The evaluation returned no questions to mark.');
        }

        return $scored;
    }

    // -- Shared ----------------------------------------------------------------

    /**
     * Draws each verdict next to the answer it belongs to on the student's own
     * page, which is what a teacher does with a red pen and what makes the
     * result recognisable to a parent.
     *
     * @param  array<int,array<string,mixed>>  $annotations  From HomeworkMarkingService::annotations().
     */
    private function annotate(
        HomeworkAnnotatedPdfService $annotatedPdfService,
        string $submissionPath,
        string $submissionMime,
        array $annotations
    ): ?string {
        if (in_array($submissionMime, HomeworkDocumentExtractionService::WORD_MIME_TYPES, true)) {
            Log::info('Skipping annotated-submission generation for a Word document submission (no page images to mark up)', [
                'homework_id' => $this->homeworkId,
            ]);

            return null;
        }

        if ($annotations === []) {
            return null;
        }

        try {
            $pdfBinary = $annotatedPdfService->annotate($submissionPath, $submissionMime, $annotations);
            $filePath = 'public/homework_evaluated_submissions/evaluated-' . $this->homeworkId . '-' . now()->format('YmdHis') . '.pdf';
            Storage::disk('digitalocean')->put($filePath, $pdfBinary, 'public');

            return Storage::disk('digitalocean')->url($filePath);
        } catch (Throwable $exception) {
            // Non-fatal on purpose: the marks are the result, the marked-up copy
            // is a convenience. Losing the PDF must not lose the grading.
            Log::warning('Homework annotated-submission generation/storage failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /** @param array{answers: array<int,array<string,mixed>>, ai_marks: float, max_marks: float, mode: string} $scored */
    private function persist(
        studentHomeworkModel $homework,
        array $scored,
        ?string $annotatedUrl,
        HomeworkMarkingService $marking
    ): void {
        $now = now();
        $answers = $scored['answers'];
        $maxMarks = round((float) $scored['max_marks'], 2);
        $aiMarks = round((float) $scored['ai_marks'], 2);
        $totals = $marking->totals($answers, $aiMarks, $maxMarks);

        $marking->persist((int) $homework->id, (int) $homework->sub_institute_id, $answers);

        $homework->update([
            'reviewed_pdf_path' => $annotatedUrl,
            'ai_result_json' => json_encode([
                'mode' => $scored['mode'],
                'ai_marks' => $aiMarks,
                'max_marks' => $maxMarks,
                'questions' => count($answers),
            ]),
            // The count-based fields keep the meaning they have always had, so
            // nothing already reading them changes behaviour.
            'ai_score' => $totals['correct'],
            'ai_total_questions' => $totals['questions'],
            // Marks where there are marks, counts where there are not. On the
            // free-form path every question is worth one, so the two coincide
            // and there is no ambiguity either way.
            'ai_percentage' => $totals['percentage'],
            'ai_marks' => $aiMarks,
            'max_marks' => $maxMarks,
            'evaluation_mode' => $scored['mode'],
            'ai_status' => 'Evaluated',
            'ai_failure_reason' => null,
            'evaluated_at' => $now,
            'submission_remarks' => $this->buildTeacherRemarks($answers, $aiMarks, $maxMarks),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('EvaluateHomeworkSubmissionJob failed permanently', [
            'homework_id' => $this->homeworkId,
            'message' => $exception->getMessage(),
        ]);

        $homework = studentHomeworkModel::where([
            'id' => $this->homeworkId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
        ])->first();

        if ($homework) {
            $this->markFailed($homework, 'Failed', $exception->getMessage());
        }
    }

    private function markFailed(studentHomeworkModel $homework, string $status, string $reason): void
    {
        $homework->update([
            'ai_status' => $status,
            'ai_failure_reason' => mb_substr($reason, 0, 250),
            'evaluated_at' => now(),
        ]);
    }

    /** @param array<int,array<string,mixed>> $answers */
    private function buildTeacherRemarks(array $answers, float $aiMarks, float $maxMarks): string
    {
        $weak = array_values(array_filter($answers, static fn ($answer) => $answer['status'] !== 'correct'));
        $weakNumbers = array_map(static fn ($answer) => (string) $answer['question_no'], $weak);

        $summaryLine = empty($weakNumbers)
            ? 'Student answered all questions correctly.'
            : 'Student understands most concepts but needs improvement in Question ' . implode(' and Question ', $weakNumbers) . '.';

        $percentage = $maxMarks > 0 ? round($aiMarks / $maxMarks * 100, 1) : 0;

        return "AI Score: {$this->trimNumber($aiMarks)}/{$this->trimNumber($maxMarks)}\n"
            . "Percentage: {$percentage}%\n\nSummary:\n{$summaryLine}";
    }

    /** @return array<int,int> */
    private function questionIds(studentHomeworkModel $homework): array
    {
        return collect(explode(',', (string) $homework->question_ids))
            ->map(static fn ($value) => (int) trim($value))
            ->filter()
            ->values()
            ->all();
    }

    private function logAiInteraction(studentHomeworkModel $homework, ?array $evaluation, ?string $errorMessage): void
    {
        DB::table('ai_interaction_logs')->insert([
            'menu_type' => 'homework_gemini_evaluation',
            'student_level' => $homework->student_level,
            'student_id' => $homework->student_id,
            'prompt_by_user' => $homework->prompt,
            'response_ai' => $evaluation ? json_encode($evaluation) : $errorMessage,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
            'created_by' => $homework->updated_by ?? $homework->created_by,
            'created_at' => now(),
        ]);
    }

    private function localPath(?string $fileName): ?string
    {
        if (!$fileName) {
            return null;
        }

        $path = storage_path('app/public/student/' . $fileName);

        return is_file($path) ? $path : null;
    }

    /** @see EvaluateAssignmentSubmissionJob::detectMime() — same reasoning, different model. */
    private const RECOGNISED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private function detectMime(?string $absolutePath, ?string $extension): string
    {
        if ($absolutePath && is_file($absolutePath)) {
            $detected = @mime_content_type($absolutePath);
            if (in_array($detected, self::RECOGNISED_MIME_TYPES, true)) {
                return $detected;
            }
            if (strtolower((string) $extension) === 'docx' && in_array($detected, ['application/zip', 'application/octet-stream'], true)) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
        }

        return $this->mimeFromExtension($extension);
    }

    private function mimeFromExtension(?string $extension): string
    {
        return match (strtolower((string) $extension)) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/pdf',
        };
    }

    /** 2.00 -> "2", 1.50 -> "1.5" — marks read badly with trailing zeros. */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
