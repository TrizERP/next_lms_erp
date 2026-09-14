<?php

namespace App\Jobs;

use App\Models\student\studentHomeworkModel;
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
 * Queued AI evaluation pipeline for the multi-file homework submission +
 * teacher-review workflow -- now operating directly on the `homework` row
 * (via studentHomeworkModel) instead of a separate homework_submissions
 * table. Sibling of EvaluateHomeworkSubmissionJob (legacy single-file
 * `homework` flow) and EvaluateAssignmentSubmissionJob (`lms_assignment`) --
 * reuses the same Gemini/OCR/annotation services (App\Services\Homework\*),
 * which only take file paths/mime types/plain text in and structured
 * results out.
 *
 * Unlike the legacy job, teacher_remarks is never auto-filled here: this
 * design keeps it strictly teacher-authored, so the AI summary lives only in
 * ai_result_json / ai_score / ai_percentage.
 *
 * Writes its annotated PDF to homework_evaluated_submissions_v2/... on the
 * digitalocean disk -- a distinct path from the OLD job's
 * homework_evaluated_submissions/... so the two never collide.
 */
class EvaluateHomeworkSubmissionV2Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

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
        HomeworkAnnotatedPdfService $annotatedPdfService
    ): void {
        $homework = studentHomeworkModel::find($this->homeworkId);

        if (!$homework || $homework->ai_status === 'Evaluated') {
            return;
        }

        $files = is_array($homework->submission_files) ? $homework->submission_files : [];
        if (empty($files)) {
            $this->markFailed($homework, 'OCR Failed', 'No files were uploaded with this submission.');
            return;
        }

        if (empty($homework->image)) {
            $this->markFailed($homework, 'OCR Failed', 'No reference homework file could be located for this submission.');
            return;
        }

        $referencePath = $this->localPath('student/' . $homework->image);
        if (!$referencePath) {
            $this->markFailed($homework, 'OCR Failed', 'Reference homework file could not be located on disk.');
            return;
        }

        $questionsText = '';
        $referenceMime = $this->detectMime($referencePath, $homework->image_type ?? null);

        try {
            $questionsText = $extractor->extractText($referencePath, $referenceMime, 'assignment questions');
        } catch (DocumentExtractionException $exception) {
            Log::warning('Homework submission (v2) reference OCR/extraction failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($homework, null, "OCR failed: {$exception->getMessage()}");
            $this->markFailed($homework, 'OCR Failed', $exception->getMessage());
            return;
        }

        $answerTexts = [];
        $firstLocated = null;
        $firstFilePath = null;
        $firstFileMime = null;

        foreach ($files as $index => $file) {
            $filePath = $this->localPath($file['path'] ?? null);
            if (!$filePath) {
                continue;
            }

            $mime = $this->detectMime($filePath, $file['mime_type'] ?? null);
            $isWord = in_array($mime, HomeworkDocumentExtractionService::WORD_MIME_TYPES, true);

            try {
                if ($isWord) {
                    $answerTexts[] = $extractor->extractText($filePath, $mime, 'student answers');
                } else {
                    $located = $locator->locateAnswers($filePath, $mime);
                    $answerTexts[] = $located['combined_text'];

                    if ($index === 0) {
                        $firstLocated = $located;
                        $firstFilePath = $filePath;
                        $firstFileMime = $mime;
                    }
                }
            } catch (DocumentExtractionException $exception) {
                Log::warning('Homework submission (v2) answer OCR/extraction failed for one file', [
                    'homework_id' => $this->homeworkId,
                    'file_index' => $index,
                    'message' => $exception->getMessage(),
                ]);
                // Keep going with whatever other files were readable; only
                // fail the whole submission if nothing could be extracted.
            }
        }

        if (empty($answerTexts)) {
            $this->logAiInteraction($homework, null, 'OCR failed: none of the submitted files could be read.');
            $this->markFailed($homework, 'OCR Failed', 'None of the submitted files could be read.');
            return;
        }

        $combinedAnswersText = implode("\n\n---\n\n", $answerTexts);
        $studentLevel = $homework->student_level ?? null;

        try {
            $evaluation = $evaluationService->evaluate($questionsText, $combinedAnswersText, $studentLevel);
        } catch (EvaluationException $exception) {
            Log::warning('Homework submission (v2) Gemini evaluation failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($homework, null, "Evaluation failed: {$exception->getMessage()}");
            $this->markFailed($homework, 'Evaluation Failed', $exception->getMessage());
            return;
        }

        $reviewedPdfPath = null;
        if ($firstLocated && $firstFilePath && $firstFileMime) {
            try {
                $annotations = $this->mergeAnnotations($evaluation['results'], $firstLocated['answers']);
                $pdfBinary = $annotatedPdfService->annotate($firstFilePath, $firstFileMime, $annotations);
                $filePath = 'homework_evaluated_submissions_v2/evaluated-' . $this->homeworkId . '-' . now()->format('YmdHis') . '.pdf';
                Storage::disk('digitalocean')->put($filePath, $pdfBinary, 'public');
                $reviewedPdfPath = Storage::disk('digitalocean')->url($filePath);
            } catch (Throwable $exception) {
                Log::warning('Homework submission (v2) annotated-PDF generation/storage failed', [
                    'homework_id' => $this->homeworkId,
                    'message' => $exception->getMessage(),
                ]);
                // Non-fatal: the structured result is still saved even if the annotated PDF could not be produced.
            }
        } else {
            Log::info('Skipping annotated-PDF generation for homework submission (v2) — no spatially-located answers available', [
                'homework_id' => $this->homeworkId,
            ]);
        }

        $updateData = [
            'ai_result_json' => json_encode($evaluation),
            'ai_score' => $evaluation['overall_score'],
            'ai_total_questions' => $evaluation['total_questions'],
            'ai_percentage' => $evaluation['percentage'],
            'ai_status' => 'Evaluated',
            'ai_failure_reason' => null,
            'evaluated_at' => now(),
        ];
        if ($reviewedPdfPath) {
            $updateData['reviewed_pdf_path'] = $reviewedPdfPath;
        }
        if ($homework->status === 'Submitted') {
            $updateData['status'] = 'Under Review';
        }

        $homework->update($updateData);

        $this->logAiInteraction($homework, $evaluation, null);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('EvaluateHomeworkSubmissionV2Job failed permanently', [
            'homework_id' => $this->homeworkId,
            'message' => $exception->getMessage(),
        ]);

        $homework = studentHomeworkModel::find($this->homeworkId);

        if ($homework) {
            $this->markFailed($homework, 'Failed', $exception->getMessage());
        }
    }

    /** @see EvaluateHomeworkSubmissionJob::mergeAnnotations() — identical join, same models. */
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

    private function markFailed(studentHomeworkModel $homework, string $status, string $reason): void
    {
        $homework->update([
            'ai_status' => $status,
            'ai_failure_reason' => mb_substr($reason, 0, 250),
            'evaluated_at' => now(),
        ]);
    }

    private function logAiInteraction(studentHomeworkModel $homework, ?array $evaluation, ?string $errorMessage): void
    {
        DB::table('ai_interaction_logs')->insert([
            'menu_type' => 'homework_submission_v2_gemini_evaluation',
            'student_level' => null,
            'student_id' => $homework->student_id,
            'prompt_by_user' => $homework->submission_remarks,
            'response_ai' => $evaluation ? json_encode($evaluation) : $errorMessage,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
            'created_by' => $homework->created_by,
            'created_at' => now(),
        ]);
    }

    private function localPath(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }

        $path = storage_path('app/public/' . ltrim($relativePath, '/'));

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

    private function detectMime(?string $absolutePath, ?string $extensionOrMime): string
    {
        if ($absolutePath && is_file($absolutePath)) {
            $detected = @mime_content_type($absolutePath);
            if (in_array($detected, self::RECOGNISED_MIME_TYPES, true)) {
                return $detected;
            }
            $extension = strtolower((string) pathinfo((string) $extensionOrMime, PATHINFO_EXTENSION));
            if ($extension === 'docx' && in_array($detected, ['application/zip', 'application/octet-stream'], true)) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
        }

        if (in_array($extensionOrMime, self::RECOGNISED_MIME_TYPES, true)) {
            return $extensionOrMime;
        }

        return $this->mimeFromExtension($extensionOrMime);
    }

    private function mimeFromExtension(?string $extensionOrMime): string
    {
        $extension = strtolower((string) pathinfo((string) $extensionOrMime, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) $extensionOrMime);
        }

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/pdf',
        };
    }
}
