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
 * Queued homework evaluation pipeline: text-extraction of the teacher's
 * assignment file, spatial answer-location + grading of the student's
 * submission, and drawing the verdicts directly onto the STUDENT'S OWN
 * uploaded pages (never a separate typed report — see
 * HomeworkAnnotatedPdfService), then persistence back onto the `homework`
 * row.
 *
 * Dispatched from StudentHomeworkApiController::submissionStore() right
 * after the student's file is saved, so the upload request itself never
 * waits on Gemini/OCR/PDF work. On QUEUE_CONNECTION=sync (this app's
 * current default) it still runs inline within that request; switching
 * QUEUE_CONNECTION to database/redis and running a queue worker makes it
 * fully asynchronous with no code change.
 */
class EvaluateHomeworkSubmissionJob implements ShouldQueue
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
        $homework = studentHomeworkModel::where([
            'id' => $this->homeworkId,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
        ])->first();

        if (!$homework || empty($homework->submission_image)) {
            return;
        }

        $assignmentPath = $this->localPath($homework->image);
        $submissionPath = $this->localPath($homework->submission_image);

        if (!$assignmentPath || !$submissionPath) {
            $this->markFailed($homework, 'OCR Failed', 'Assignment or submission file could not be located on disk.');
            return;
        }

        $questionsText = '';
        $submissionMime = $this->mimeFromExtension($homework->submission_image_type);
        $located = null;

        try {
            $questionsText = $extractor->extractText(
                $assignmentPath,
                $this->mimeFromExtension($homework->image_type),
                'assignment questions'
            );
            $located = $locator->locateAnswers($submissionPath, $submissionMime);
        } catch (DocumentExtractionException $exception) {
            Log::warning('Homework OCR/extraction failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($homework, null, "OCR failed: {$exception->getMessage()}");
            $this->markFailed($homework, 'OCR Failed', $exception->getMessage());
            return;
        }

        try {
            $evaluation = $evaluationService->evaluate($questionsText, $located['combined_text'], $homework->student_level);
        } catch (EvaluationException $exception) {
            Log::warning('Homework Gemini evaluation failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            $this->logAiInteraction($homework, null, "Evaluation failed: {$exception->getMessage()}");
            $this->markFailed($homework, 'Evaluation Failed', $exception->getMessage());
            return;
        }

        $evaluatedSubmissionUrl = null;
        try {
            $annotations = $this->mergeAnnotations($evaluation['results'], $located['answers']);
            $pdfBinary = $annotatedPdfService->annotate($submissionPath, $submissionMime, $annotations);
            $filePath = 'public/homework_evaluated_submissions/evaluated-' . $this->homeworkId . '-' . now()->format('YmdHis') . '.pdf';
            Storage::disk('digitalocean')->put($filePath, $pdfBinary, 'public');
            $evaluatedSubmissionUrl = Storage::disk('digitalocean')->url($filePath);
        } catch (Throwable $exception) {
            Log::warning('Homework annotated-submission generation/storage failed', [
                'homework_id' => $this->homeworkId,
                'message' => $exception->getMessage(),
            ]);
            // Non-fatal: the structured result is still saved even if the annotated PDF could not be produced.
        }

        $summary = $this->buildTeacherRemarks($evaluation);

        $homework->update([
            'reviewed_pdf_path' => $evaluatedSubmissionUrl,
            'ai_result_json' => json_encode($evaluation),
            'ai_score' => $evaluation['overall_score'],
            'ai_total_questions' => $evaluation['total_questions'],
            'ai_percentage' => $evaluation['percentage'],
            'ai_status' => 'Evaluated',
            'ai_failure_reason' => null,
            'evaluated_at' => now(),
            'submission_remarks' => $summary,
        ]);

        $this->logAiInteraction($homework, $evaluation, null);
    }

    /**
     * Joins each graded question (status/remarks/expected_answer, from
     * HomeworkEvaluationService) with where that answer actually sits on
     * the student's page (page/box_2d, from HomeworkAnswerLocatorService),
     * matched by question_no, so the annotator knows both WHAT to draw and
     * WHERE.
     */
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

    private function mimeFromExtension(?string $extension): string
    {
        return match (strtolower((string) $extension)) {
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
