<?php

namespace App\Jobs;

use App\Models\lms\assignment\lms_assignmentModel;
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
 * Queued AI evaluation pipeline for the LMS Assignment module, keeping the
 * ENTIRE lifecycle on the existing `lms_assignment` row — no separate
 * submission/evaluation table. Reuses the same Gemini/OCR/annotation
 * services as EvaluateHomeworkSubmissionJob (App\Services\Homework\*):
 * those services only take file paths/mime types/plain text in and
 * structured results out, so they are not actually homework-specific and
 * are shared across both the Homework and LMS Assignment modules.
 *
 * The output is the student's OWN uploaded pages with verdict marks drawn
 * directly on them (see HomeworkAnnotatedPdfService) — never a separate
 * typed report.
 *
 * Column reuse on `lms_assignment` (see the 2026_09_04_130000 migration for
 * the handful of genuinely new columns):
 *   - exam_pdf          -> assignment questions PDF (exam_paper assignments)
 *   - homework_file     -> reference/worksheet PDF (uploaded_homework assignments,
 *                          stands in for exam_pdf — see handle())
 *   - submission_image  -> student's uploaded answer file
 *   - json_annotation   -> full Gemini evaluation JSON
 *   - teacher_remarks   -> auto-filled with the AI summary (left alone once
 *                          a teacher has manually reviewed the assignment)
 */
class EvaluateAssignmentSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        private readonly int $assignmentId,
        private readonly int $subInstituteId,
        private readonly int $syear
    ) {
    }

    public function handle(
        HomeworkDocumentExtractionService $extractor,
        HomeworkAnswerLocatorService $locator,
        HomeworkEvaluationService $evaluationService,
        HomeworkAnnotatedPdfService $annotatedPdfService
    ): void {
        $assignment = lms_assignmentModel::where([
            'id' => $this->assignmentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
        ])->first();

        if (!$assignment || empty($assignment->submission_image)) {
            return;
        }

        // Uploaded-homework assignments have no question paper — the
        // teacher's homework_file (the worksheet/instructions the student
        // completed) stands in for exam_pdf as the "reference" document,
        // same as EvaluateHomeworkSubmissionJob does for the older Homework
        // module. This keeps AI auto-evaluation (score, reviewed PDF,
        // AI-drafted remarks) running identically for both assignment types.
        $isHomework = $assignment->assignment_source_type === 'uploaded_homework';
        $referenceFile = $isHomework ? $assignment->homework_file : $assignment->exam_pdf;

        if (empty($referenceFile)) {
            $this->markFailed($assignment, 'Evaluation Failed', $isHomework
                ? 'No homework file is attached to this assignment.'
                : 'No assignment question paper is attached to this assignment.');
            return;
        }

        $assignmentPath = $this->localPath('public/' . ltrim($referenceFile, '/'));
        $submissionPath = $this->localPath('public/lms_assignment_submission/' . $assignment->submission_image);

        if (!$assignmentPath || !$submissionPath) {
            $this->markFailed($assignment, 'OCR Failed', 'Assignment paper or submission file could not be located on disk.');
            return;
        }

        $questionsText = '';
        $submissionMime = $this->detectMime($submissionPath, $assignment->submission_image);
        $referenceMime = $isHomework ? $this->detectMime($assignmentPath, $referenceFile) : 'application/pdf';
        $located = null;

        // A Word submission has no page images for the locator's spatial
        // box_2d lookup to make sense of, so its own text layer (already
        // clean, never scanned) is read directly and there is nothing to
        // annotate — see the annotation step below.
        $submissionIsWord = in_array($submissionMime, HomeworkDocumentExtractionService::WORD_MIME_TYPES, true);

        try {
            $questionsText = $extractor->extractText(
                $assignmentPath,
                $referenceMime,
                'assignment questions'
            );
            $located = $submissionIsWord
                ? ['answers' => [], 'combined_text' => $extractor->extractText($submissionPath, $submissionMime, 'student answers')]
                : $locator->locateAnswers($submissionPath, $submissionMime);
        } catch (DocumentExtractionException $exception) {
            Log::warning('Assignment OCR/extraction failed', [
                'assignment_id' => $this->assignmentId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($assignment, null, "OCR failed: {$exception->getMessage()}");
            $this->markFailed($assignment, 'OCR Failed', $exception->getMessage());
            return;
        }

        try {
            $evaluation = $evaluationService->evaluate($questionsText, $located['combined_text'], null);
        } catch (EvaluationException $exception) {
            Log::warning('Assignment Gemini evaluation failed', [
                'assignment_id' => $this->assignmentId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($assignment, null, "Evaluation failed: {$exception->getMessage()}");
            $this->markFailed($assignment, 'Evaluation Failed', $exception->getMessage());
            return;
        }

        $evaluatedSubmissionUrl = null;
        if ($submissionIsWord) {
            Log::info('Skipping annotated-submission generation for a Word document submission (no page images to mark up)', [
                'assignment_id' => $this->assignmentId,
            ]);
        } else {
            try {
                $annotations = $this->mergeAnnotations($evaluation['results'], $located['answers']);
                $pdfBinary = $annotatedPdfService->annotate($submissionPath, $submissionMime, $annotations);
                $filePath = 'public/assignment_evaluated_submissions/evaluated-' . $this->assignmentId . '-' . now()->format('YmdHis') . '.pdf';
                Storage::disk('digitalocean')->put($filePath, $pdfBinary, 'public');
                $evaluatedSubmissionUrl = Storage::disk('digitalocean')->url($filePath);
            } catch (Throwable $exception) {
                Log::warning('Assignment annotated-submission generation/storage failed', [
                    'assignment_id' => $this->assignmentId,
                    'message' => $exception->getMessage(),
                ]);
                // Non-fatal: the structured result is still saved even if the annotated PDF could not be produced.
            }
        }

        $summary = $this->buildTeacherRemarks($evaluation);

        $updateData = [
            'reviewed_pdf_path' => $evaluatedSubmissionUrl,
            'json_annotation' => json_encode($evaluation),
            'ai_score' => $evaluation['overall_score'],
            'ai_total_questions' => $evaluation['total_questions'],
            'ai_percentage' => $evaluation['percentage'],
            'ai_status' => 'Evaluated',
            'ai_failure_reason' => null,
            'evaluated_at' => now(),
        ];

        // Never clobber a teacher's own manual review with the AI draft.
        if ($assignment->teacher_submission_status !== 'Y') {
            $updateData['teacher_remarks'] = $summary;
        }

        $assignment->update($updateData);

        $this->logAiInteraction($assignment, $evaluation, null);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('EvaluateAssignmentSubmissionJob failed permanently', [
            'assignment_id' => $this->assignmentId,
            'message' => $exception->getMessage(),
        ]);

        $assignment = lms_assignmentModel::where([
            'id' => $this->assignmentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
        ])->first();

        if ($assignment) {
            $this->markFailed($assignment, 'Failed', $exception->getMessage());
        }
    }

    /** @see EvaluateHomeworkSubmissionJob::mergeAnnotations() — identical join, different model. */
    private function mergeAnnotations(array $results, array $located): array
    {
        $byQuestion = [];
        foreach ($located as $answer) {
            $byQuestion[$answer['question_no']] = $answer;
        }

        $annotations = [];
        foreach ($results as $result) {
            $location = $byQuestion[$result['question_no']] ?? null;
            $annotations[] = [
                'question_no' => $result['question_no'],
                'status' => $result['status'],
                'expected_answer' => $result['expected_answer'],
                'remarks' => $result['remarks'],
                'page' => $location['page'] ?? 1,
                'box_2d' => $location['box_2d'] ?? null,
            ];
        }

        return $annotations;
    }

    private function markFailed(lms_assignmentModel $assignment, string $status, string $reason): void
    {
        $assignment->update([
            'ai_status' => $status,
            'ai_failure_reason' => mb_substr($reason, 0, 250),
            'evaluated_at' => now(),
        ]);
    }

    private function buildTeacherRemarks(array $evaluation): string
    {
        $score = $evaluation['overall_score'];
        $total = $evaluation['total_questions'];
        $percentage = $evaluation['percentage'];

        $weak = array_values(array_filter($evaluation['results'], fn ($row) => $row['status'] !== 'correct'));
        $weakNumbers = array_map(fn ($row) => (string) $row['question_no'], $weak);

        $summaryLine = empty($weakNumbers)
            ? 'Student answered all questions correctly.'
            : 'Student understands most concepts but needs improvement in Question ' . implode(' and Question ', $weakNumbers) . '.';

        return "AI Score: {$score}/{$total}\nPercentage: {$percentage}%\n\nSummary:\n{$summaryLine}";
    }

    private function logAiInteraction(lms_assignmentModel $assignment, ?array $evaluation, ?string $errorMessage): void
    {
        DB::table('ai_interaction_logs')->insert([
            'menu_type' => 'lms_assignment_gemini_evaluation',
            'student_level' => null,
            'student_id' => $assignment->student_id,
            'prompt_by_user' => null,
            'response_ai' => $evaluation ? json_encode($evaluation) : $errorMessage,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
            'created_by' => $assignment->teacher_id ?? $assignment->created_by,
            'created_at' => now(),
        ]);
    }

    private function localPath(string $relativePath): ?string
    {
        $path = storage_path('app/' . $relativePath);

        return is_file($path) ? $path : null;
    }

    /**
     * Trusts the file's actual bytes over its extension: Gemini's "inline_data"
     * call declares a mime type, and a mislabeled file (e.g. an image saved
     * with a .pdf name, or an extension-less/renamed upload) sent as
     * "application/pdf" is exactly what produces its "document has no pages"
     * rejection. Falls back to the old extension-based guess only if the file
     * can't be inspected on disk.
     *
     * .docx is itself a zip archive, and fileinfo commonly reports it as the
     * generic "application/zip"/"application/octet-stream" rather than
     * recognising the Word-specific content types inside — so a recognised
     * .docx extension wins over that generic sniff result.
     */
    private function detectMime(?string $absolutePath, ?string $fileName): string
    {
        $extension = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));

        if ($absolutePath && is_file($absolutePath)) {
            $detected = @mime_content_type($absolutePath);
            if (in_array($detected, self::RECOGNISED_MIME_TYPES, true)) {
                return $detected;
            }
            if ($extension === 'docx' && in_array($detected, ['application/zip', 'application/octet-stream'], true)) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
        }

        return $this->mimeFromExtension($fileName);
    }

    private const RECOGNISED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private function mimeFromExtension(?string $fileName): string
    {
        $extension = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/pdf',
        };
    }

    private function studentName(?int $studentId): string
    {
        if (!$studentId) {
            return '';
        }

        $student = DB::table('tblstudent')->where('id', $studentId)->first(['first_name', 'middle_name', 'last_name']);

        return $student ? trim("{$student->first_name} {$student->middle_name} {$student->last_name}") : '';
    }

    private function subjectName(?int $subjectId): string
    {
        if (!$subjectId) {
            return '';
        }

        return (string) (DB::table('subject')->where('id', $subjectId)->value('subject_name') ?? '');
    }

    private function standardName(?int $standardId): string
    {
        if (!$standardId) {
            return '';
        }

        return (string) (DB::table('standard')->where('id', $standardId)->value('name') ?? '');
    }
}
