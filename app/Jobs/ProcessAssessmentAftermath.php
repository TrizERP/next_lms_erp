<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\LMS\Neo4jService;
use App\Services\LMS\BKTService;
use App\Services\LMS\LearnerStateEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use App\Events\AssessmentSubmitted;

class ProcessAssessmentAftermath implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $studentId;
    protected $subInstituteId;
    protected $assessmentId;
    protected $isCorrect;
    protected $timeTaken;
    protected $confidence;
    protected $pKnow;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $studentId,
        int $subInstituteId,
        int $assessmentId,
        bool $isCorrect,
        int $timeTaken,
        float $confidence,
        float $pKnow
    ) {
        $this->studentId = $studentId;
        $this->subInstituteId = $subInstituteId;
        $this->assessmentId = $assessmentId;
        $this->isCorrect = $isCorrect;
        $this->timeTaken = $timeTaken;
        $this->confidence = $confidence;
        $this->pKnow = $pKnow;
    }

    /**
     * Execute the job.
     */
    public function handle(Neo4jService $neo4jService, BKTService $bktService, LearnerStateEngine $learnerStateEngine)
    {
        try {
            // STEP 1: Update Neo4j Graph Database
            $neo4jService->recordAttempt(
                $this->studentId,
                $this->subInstituteId,
                $this->assessmentId,
                $this->isCorrect,
                $this->timeTaken,
                $this->confidence
            );

            // STEP 2: Update Learner State
            $learnerState = $learnerStateEngine->update($this->studentId, [
                'last_assessment' => now(),
                'is_correct' => $this->isCorrect,
                'time_taken' => $this->timeTaken,
                'confidence' => $this->confidence
            ]);

            // STEP 3: Dispatch Event for async processing
            Event::dispatch(new AssessmentSubmitted(
                $this->studentId,
                $this->subInstituteId,
                $this->assessmentId,
                'general', // conceptId
                $this->isCorrect,
                $this->pKnow,
                $this->timeTaken,
                $this->confidence
            ));

            Log::info('Assessment aftermath processed successfully', [
                'student_id' => $this->studentId,
                'assessment_id' => $this->assessmentId,
                'is_correct' => $this->isCorrect
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process assessment aftermath: ' . $e->getMessage(), [
                'student_id' => $this->studentId,
                'assessment_id' => $this->assessmentId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}