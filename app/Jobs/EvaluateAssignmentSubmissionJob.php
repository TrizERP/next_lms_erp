<?php

namespace App\Jobs;

use App\Models\lms\assignment\lms_assignmentModel;
use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\Exceptions\EvaluationException;
use App\Services\Homework\HomeworkDocumentExtractionService;
use App\Services\Homework\HomeworkEvaluationService;
use App\Services\Homework\HomeworkReviewPdfService;
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
 * submission/evaluation table. Reuses the same Gemini/OCR/PDF services as
 * EvaluateHomeworkSubmissionJob (App\Services\Homework\*): those services
 * only take file paths/mime types/plain text in and structured results out,
 * so they are not actually homework-specific and are shared across both
 * the Homework and LMS Assignment modules.
 *
 * Column reuse on `lms_assignment` (see the 2026_09_04_130000 migration for
 * the handful of genuinely new columns):
 *   - exam_pdf          -> assignment questions PDF
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
        HomeworkEvaluationService $evaluationService,
        HomeworkReviewPdfService $pdfService
    ): void {
        $assignment = lms_assignmentModel::where([
            'id' => $this->assignmentId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
        ])->first();

        if (!$assignment || empty($assignment->submission_image)) {
            return;
        }

        if (empty($assignment->exam_pdf)) {
            $this->markFailed($assignment, 'Evaluation Failed', 'No assignment question paper is attached to this assignment.');
            return;
        }

        $assignmentPath = $this->localPath('public/' . ltrim($assignment->exam_pdf, '/'));
        $submissionPath = $this->localPath('public/lms_assignment_submission/' . $assignment->submission_image);

        if (!$assignmentPath || !$submissionPath) {
            $this->markFailed($assignment, 'OCR Failed', 'Assignment paper or submission file could not be located on disk.');
            return;
        }

        $questionsText = '';
        $answersText = '';

        try {
            $questionsText = $extractor->extractText(
                $assignmentPath,
                'application/pdf',
                'assignment questions'
            );
            $answersText = $extractor->extractText(
                $submissionPath,
                $this->mimeFromExtension($assignment->submission_image),
                'student answers'
            );
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
            $evaluation = $evaluationService->evaluate($questionsText, $answersText, null);
        } catch (EvaluationException $exception) {
            Log::warning('Assignment Gemini evaluation failed', [
                'assignment_id' => $this->assignmentId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($assignment, null, "Evaluation failed: {$exception->getMessage()}");
            $this->markFailed($assignment, 'Evaluation Failed', $exception->getMessage());
            return;
        }

        $reviewedPdfUrl = null;
        try {
            $pdfBinary = $pdfService->generate($evaluation, [
                'title' => (string) $assignment->title,
                'studentName' => $this->studentName($assignment->student_id),
                'subjectName' => $this->subjectName($assignment->subject_id),
                'standardName' => $this->standardName($assignment->standard_id),
            ]);
            $filePath = 'public/assignment_evaluations/evaluation-' . $this->assignmentId . '-' . now()->format('YmdHis') . '.pdf';
            Storage::disk('digitalocean')->put($filePath, $pdfBinary, 'public');
            $reviewedPdfUrl = Storage::disk('digitalocean')->url($filePath);
        } catch (Throwable $exception) {
            Log::warning('Assignment reviewed-PDF generation/storage failed', [
                'assignment_id' => $this->assignmentId,
                'message' => $exception->getMessage(),
            ]);
            // Non-fatal: the structured result is still saved even if the PDF could not be produced.
        }

        $summary = $this->buildTeacherRemarks($evaluation);

        $updateData = [
            'reviewed_pdf_path' => $reviewedPdfUrl,
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

    private function mimeFromExtension(?string $fileName): string
    {
        $extension = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
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
