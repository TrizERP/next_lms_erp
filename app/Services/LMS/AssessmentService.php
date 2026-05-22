<?php

namespace App\Services\LMS;

use App\Events\AssessmentSubmitted;
use App\Models\lms\lmsOnlineExamAnswerModel;
use App\Models\lms\lmsOnlineExamModel;
use App\Services\LMS\BKTService;
use App\Services\LMS\LearnerStateEngine;
use App\Services\LMS\Neo4jService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Jobs\ProcessAssessmentAftermath;

class AssessmentService
{
    protected Neo4jService $neo4jService;
    protected BKTService $bktService;
    protected LearnerStateEngine $learnerStateEngine;
    
    /**
     * Constructor - Inject required services
     */
    public function __construct(
        Neo4jService $neo4jService,
        BKTService $bktService,
        LearnerStateEngine $learnerStateEngine
    ) {
        $this->neo4jService = $neo4jService;
        $this->bktService = $bktService;
        $this->learnerStateEngine = $learnerStateEngine;
    }
    
    /**
     * Main function: processSubmission
     * This is the entry point for assessment processing
     * 
     * @param int $studentId
     * @param int $subInstituteId
     * @param int $assessmentId
     * @param mixed $response
     * @param int $timeTaken
     * @return array
     */
    public function processSubmission(
        int $studentId,
        int $subInstituteId,
        int $assessmentId,
        $response,
        int $timeTaken
    ): array {
        try {
            DB::beginTransaction();
            
            // STEP 1: Fetch Assessment details
            $assessment = $this->fetchAssessment($assessmentId);
            
            // STEP 2: Check Answer Correctness
            $isCorrect = $this->evaluateResponse($response, $assessment);
            
            // STEP 3: Extract Confidence from response
            $confidence = $this->extractConfidence($response);
            
            // STEP 4: Save Attempt to database
            $attemptId = $this->saveAttempt($studentId, $subInstituteId, $assessmentId, $response, $isCorrect, $timeTaken, $confidence);
            
            // STEP 5: Update Mastery using BKT (AI Knowledge Tracing)
            $pKnow = $this->bktService->update($isCorrect);
            
            // STEP 6: Generate Feedback
            $feedback = $this->generateFeedback($isCorrect, $assessment, $pKnow);
            
            // STEP 7: Decide Next Learning Step
            $nextSteps = $this->determineNextSteps($isCorrect, $pKnow, $assessment, $studentId, $subInstituteId);
            
            DB::commit();
            
            // Dispatch asynchronous tasks to reduce load on main request
            // These operations are moved to a queue job to improve response time
            ProcessAssessmentAftermath::dispatch(
                $studentId,
                $subInstituteId,
                $assessmentId,
                $isCorrect,
                $timeTaken,
                $confidence,
                $pKnow
            );
            
            // Return final output matching PAL specification
            return [
                'is_correct' => $isCorrect,
                'mastery_estimate' => [
                    'p_know' => $pKnow,
                    'confidence' => $confidence
                ],
                'feedback' => $feedback,
                'next_steps' => $nextSteps,
                'learner_state' => [
                    'engagement' => 'medium',
                    'fatigue' => 'low',
                    'velocity' => 'medium',
                    'frustration' => 'low',
                    'last_updated' => now()->toIso8601String()
                ],
                'attempt_id' => $attemptId
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment submission failed: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'assessment_id' => $assessmentId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'is_correct' => false,
                'error' => true,
                'message' => 'Failed to process assessment: ' . $e->getMessage(),
                'mastery_estimate' => ['p_know' => 0.0, 'confidence' => 0.0],
                'feedback' => ['type' => 'error', 'message' => 'System error occurred'],
                'next_steps' => ['recommendation' => 'retry', 'message' => 'Please try again'],
                'learner_state' => ['engagement' => 'unknown', 'fatigue' => 'unknown']
            ];
        }
    }
    
    /**
     * STEP 1: Fetch Assessment
     * Gets: correct answer, concept ID, explanation, metadata
     * 
     * @param int $assessmentId
     * @return array
     */
    private function fetchAssessment(int $assessmentId): array
    {
        // Fetch from your assessment/question paper table
        $assessment = DB::table('question_paper')
            ->where('id', $assessmentId)
            ->first();
        
        if (!$assessment) {
            throw new \InvalidArgumentException("Assessment not found: {$assessmentId}");
        }
        
        // Get questions with their correct answers
        $questions = DB::table('lms_question_master')
            ->where('id', $assessmentId)
            ->get();
        
        return [
            'id' => $assessment->id,
            'title' => $assessment->title ?? 'Assessment',
            'concept_id' => $assessment->concept_id ?? 'general',
            'questions' => $questions->toArray(),
            'metadata' => [
                'total_questions' => $questions->count(),
                'passing_score' => $assessment->passing_score ?? 60
            ]
        ];
    }
    
    /**
     * STEP 2: Evaluate Response
     * Compares Student Answer VS Correct Answer
     * 
     * @param mixed $response
     * @param array $assessment
     * @return bool
     */
    private function evaluateResponse($response, array $assessment): bool
    {
        // Handle different answer types
        $answers = is_array($response) ? ($response['answers'] ?? $response) : $response;
        
        $totalQuestions = 0;
        $correctCount = 0;
        
        // Process single choice answers
        if (isset($answers['single']) && is_array($answers['single'])) {
            foreach ($answers['single'] as $questionId => $answerData) {
                $answerArr = explode("##", $answerData);
                $isCorrect = ($answerArr[1] ?? 0) == 1;
                if ($isCorrect) {
                    $correctCount++;
                }
                $totalQuestions++;
            }
        }
        
        // Process multiple choice answers
        if (isset($answers['multiple']) && is_array($answers['multiple'])) {
            foreach ($answers['multiple'] as $questionId => $answerData) {
                $allCorrect = true;
                foreach ($answerData as $answerItem) {
                    $answerArr = explode("##", $answerItem);
                    if (($answerArr[1] ?? 0) != 1) {
                        $allCorrect = false;
                        break;
                    }
                }
                if ($allCorrect) {
                    $correctCount++;
                }
                $totalQuestions++;
            }
        }
        
        // Process narrative answers
        if (isset($answers['narrative']) && is_array($answers['narrative'])) {
            foreach ($answers['narrative'] as $questionId => $answerText) {
                // Narrative answers need manual review, auto-mark as correct for now
                $correctCount++;
                $totalQuestions++;
            }
        }
        
        // Calculate overall correctness (60% passing threshold)
        if ($totalQuestions > 0) {
            $percentage = ($correctCount / $totalQuestions) * 100;
            return $percentage >= 60;
        }
        
        return false;
    }
    
    /**
     * STEP 3: Extract Confidence from response
     * 
     * @param mixed $response
     * @return float
     */
    private function extractConfidence($response): float
    {
        if (is_array($response) && isset($response['confidence'])) {
            return (float) $response['confidence'];
        }
        
        // Default confidence if not provided
        return 0.8;
    }
    
    /**
     * STEP 4: Save Attempt into database
     * 
     * @param int $studentId
     * @param int $subInstituteId
     * @param int $assessmentId
     * @param mixed $response
     * @param bool $isCorrect
     * @param int $timeTaken
     * @param float $confidence
     * @return int
     */
    private function saveAttempt(
        int $studentId,
        int $subInstituteId,
        int $assessmentId,
        $response,
        bool $isCorrect,
        int $timeTaken,
        float $confidence
    ): int {
        $answers = is_array($response) ? ($response['answers'] ?? $response) : $response;
        
        // Calculate total right and wrong
        $totalRight = 0;
        $totalWrong = 0;
        $totalMarks = 0;
        
        if (isset($answers['single']) && is_array($answers['single'])) {
            foreach ($answers['single'] as $questionId => $answerData) {
                $answerArr = explode("##", $answerData);
                if (($answerArr[1] ?? 0) == 1) {
                    $totalRight++;
                    $totalMarks++;
                } else {
                    $totalWrong++;
                }
            }
        }
        
        if (isset($answers['multiple']) && is_array($answers['multiple'])) {
            foreach ($answers['multiple'] as $questionId => $answerData) {
                $allCorrect = true;
                foreach ($answerData as $answerItem) {
                    $answerArr = explode("##", $answerItem);
                    if (($answerArr[1] ?? 0) != 1) {
                        $allCorrect = false;
                        break;
                    }
                }
                if ($allCorrect) {
                    $totalRight++;
                    $totalMarks++;
                } else {
                    $totalWrong++;
                }
            }
        }
        
        if (isset($answers['narrative']) && is_array($answers['narrative'])) {
            foreach ($answers['narrative'] as $questionId => $answerText) {
                $totalRight++;
                $totalMarks++;
            }
        }
        
        // Store main exam record
        $onlineExam = [
            'student_id' => $studentId,
            'question_paper_id' => $assessmentId,
            'total_right' => $totalRight,
            'total_wrong' => $totalWrong,
            'start_time' => now(),
            'avg_time' => $timeTaken,
            'struggle_score' => $confidence
        ];
        
        lmsOnlineExamModel::insert($onlineExam);
        $onlineExamId = DB::getPDO()->lastInsertId();
        
        // Store individual answers (simplified - add your existing logic here)
        // ... (your existing answer storage code)
        
        return $onlineExamId;
    }
    
    /**
     * STEP 8: Generate Feedback
     * 
     * @param bool $isCorrect
     * @param array $assessment
     * @param float $pKnow
     * @return array
     */
    private function generateFeedback(bool $isCorrect, array $assessment, float $pKnow): array
    {
        if ($isCorrect && $pKnow >= 0.8) {
            return [
                'type' => 'excellent',
                'message' => 'Excellent! You have mastered this concept!',
                'explanation' => $assessment['metadata']['explanation'] ?? 'Great understanding demonstrated.'
            ];
        } elseif ($isCorrect && $pKnow >= 0.6) {
            return [
                'type' => 'correct',
                'message' => 'Correct! Good understanding.',
                'explanation' => $assessment['metadata']['explanation'] ?? 'You are on the right track.'
            ];
        } elseif ($isCorrect && $pKnow < 0.6) {
            return [
                'type' => 'correct_with_warning',
                'message' => 'Correct, but review the material to strengthen understanding.',
                'explanation' => $assessment['metadata']['explanation'] ?? 'Consider revisiting this topic.'
            ];
        } elseif (!$isCorrect && $pKnow >= 0.6) {
            return [
                'type' => 'incorrect_unexpected',
                'message' => 'Incorrect. Review the explanation carefully.',
                'explanation' => $assessment['metadata']['explanation'] ?? 'This concept needs more practice.'
            ];
        } else {
            return [
                'type' => 'incorrect',
                'message' => 'Incorrect. Let\'s review this concept again.',
                'explanation' => $assessment['metadata']['explanation'] ?? 'More practice is recommended.'
            ];
        }
    }
    
    /**
     * STEP 9: Decide Next Learning Step
     * 
     * @param bool $isCorrect
     * @param float $pKnow
     * @param array $assessment
     * @param int $studentId
     * @param int $subInstituteId
     * @return array
     */
    private function determineNextSteps(bool $isCorrect, float $pKnow, array $assessment, int $studentId, int $subInstituteId): array
    {
        // Get recommendations from Neo4j
        $recommendations = $this->neo4jService->getRecommendedConcepts($studentId, $subInstituteId, 3);
        
        if ($pKnow >= 0.8) {
            return [
                'recommendation' => 'move_to_next_concept',
                'message' => 'Mastered! Move to next concept.',
                'suggested_concepts' => $recommendations,
                'action' => 'advance'
            ];
        } elseif ($pKnow >= 0.6) {
            return [
                'recommendation' => 'practice_more',
                'message' => 'Good progress. Practice more to strengthen understanding.',
                'suggested_concepts' => [$assessment['concept_id']],
                'action' => 'practice'
            ];
        } elseif ($pKnow >= 0.4) {
            return [
                'recommendation' => 'remediation_needed',
                'message' => 'Review prerequisites before continuing.',
                'suggested_concepts' => $this->neo4jService->getPrerequisites($assessment['concept_id']),
                'action' => 'review'
            ];
        } else {
            return [
                'recommendation' => 'intensive_remediation',
                'message' => 'Significant improvement needed. Start from basics.',
                'suggested_concepts' => $this->neo4jService->getPrerequisites($assessment['concept_id']),
                'action' => 'relearn'
            ];
        }
    }
    
    /**
     * Dispatch Assessment Event
     * 
     * @param int $studentId
     * @param int $subInstituteId
     * @param int $assessmentId
     * @param bool $isCorrect
     * @param float $pKnow
     * @param int $timeTaken
     * @param float $confidence
     */
    private function dispatchAssessmentEvent(
        int $studentId,
        int $subInstituteId,
        int $assessmentId,
        bool $isCorrect,
        float $pKnow,
        int $timeTaken,
        float $confidence
    ): void {
        event(new AssessmentSubmitted(
            student_id: (string) $studentId,
            sub_institute_id: (string) $subInstituteId,
            assessmentId: (string) $assessmentId,
            conceptId: (string) 'general',
            isCorrect: $isCorrect,
            pKnow: $pKnow,
            timeTaken: $timeTaken,
            confidence: $confidence,
            kasbaDimensions: [],
            metadata: [
                'timestamp' => now()->toIso8601String(),
                'service_version' => '1.0'
            ]
        ));
    }
}