<?php

namespace App\Services\LMS;

use App\Events\AssessmentSubmitted;
use App\Models\lms\lmsOnlineExamAnswerModel;
use App\Models\lms\lmsOnlineExamModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AssessmentService
{
    /**
     * Process an assessment submission
     *
     * @param array $assessmentData
     * @return array
     */
    public function processAssessment(array $assessmentData): array
    {
        try {
            DB::beginTransaction();

            // Validate the assessment data
            $this->validateAssessment($assessmentData);

            // Process each question answer
            $processedResults = $this->processAnswers($assessmentData);
            
            // Calculate mastery level based on results
            $masteryLevel = $this->calculateMastery($processedResults);
            
            // Store the attempt
            $onlineExamId = $this->storeAttempt($assessmentData, $processedResults);
            
            // Update learner state
            $this->updateLearnerState($assessmentData['student_id'], $masteryLevel);
            
            // Generate feedback for the learner
            $feedback = $this->generateFeedback($processedResults, $masteryLevel);
            
            // Determine next learning steps
            $nextSteps = $this->determineNextSteps($processedResults, $masteryLevel);
            
            // 🎯 DISPATCH THE EVENT (This is where event is used)
            $this->dispatchAssessmentEvent($assessmentData, $processedResults, $masteryLevel);
            
            DB::commit();
            
            return [
                'success' => true,
                'online_exam_id' => $onlineExamId,
                'results' => $processedResults,
                'mastery_level' => $masteryLevel,
                'feedback' => $feedback,
                'next_steps' => $nextSteps,
                'message' => 'Assessment processed successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment processing failed: ' . $e->getMessage(), [
                'assessment_data' => $assessmentData,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to process assessment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate assessment data
     *
     * @param array $assessmentData
     * @throws \InvalidArgumentException
     */
    private function validateAssessment(array $assessmentData): void
    {
        $requiredFields = ['student_id', 'question_paper_id', 'answers'];
        
        foreach ($requiredFields as $field) {
            if (!isset($assessmentData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        // Validate answer structure
        if (!is_array($assessmentData['answers'])) {
            throw new \InvalidArgumentException('Answers must be an array');
        }
    }
    
    /**
     * Process answers and check correctness
     *
     * @param array $assessmentData
     * @return array
     */
    private function processAnswers(array $assessmentData): array
    {
        $results = [
            'total_right' => 0,
            'total_wrong' => 0,
            'obtain_marks' => 0,
            'question_results' => [],
            'total_questions' => 0,
            'percentage' => 0
        ];
        
        $answers = $assessmentData['answers'];
        
        // Process single choice answers
        if (isset($answers['single']) && is_array($answers['single'])) {
            foreach ($answers['single'] as $questionId => $answerData) {
                $result = $this->validateSingleAnswer($questionId, $answerData);
                $results['question_results'][] = $result;
                if ($result['is_correct']) {
                    $results['total_right']++;
                    $results['obtain_marks'] += ($result['marks'] ?? 1);
                } else {
                    $results['total_wrong']++;
                }
                $results['total_questions']++;
            }
        }
        
        // Process multiple choice answers
        if (isset($answers['multiple']) && is_array($answers['multiple'])) {
            foreach ($answers['multiple'] as $questionId => $answerData) {
                $result = $this->validateMultipleAnswer($questionId, $answerData);
                $results['question_results'][] = $result;
                if ($result['is_correct']) {
                    $results['total_right']++;
                    $results['obtain_marks'] += ($result['marks'] ?? 1);
                } else {
                    $results['total_wrong']++;
                }
                $results['total_questions']++;
            }
        }
        
        // Process narrative answers (auto-marked as correct for now)
        if (isset($answers['narrative']) && is_array($answers['narrative'])) {
            foreach ($answers['narrative'] as $questionId => $answerText) {
                $result = $this->validateNarrativeAnswer($questionId, $answerText);
                $results['question_results'][] = $result;
                if ($result['is_correct']) {
                    $results['total_right']++;
                    $results['obtain_marks'] += ($result['marks'] ?? 1);
                } else {
                    $results['total_wrong']++;
                }
                $results['total_questions']++;
            }
        }
        
        // Calculate percentage
        if ($results['total_questions'] > 0) {
            $results['percentage'] = ($results['total_right'] / $results['total_questions']) * 100;
        }
        
        return $results;
    }
    
    /**
     * Validate a single choice answer
     *
     * @param int $questionId
     * @param string $answerData
     * @return array
     */
    private function validateSingleAnswer(int $questionId, string $answerData): array
    {
        $answerArr = explode("##", $answerData);
        $answerId = $answerArr[0] ?? null;
        $isCorrect = ($answerArr[1] ?? 0) == 1;
        
        return [
            'question_id' => $questionId,
            'answer_id' => $answerId,
            'is_correct' => $isCorrect,
            'answer_type' => 'single',
            'marks' => $isCorrect ? 1 : 0,
            'feedback' => $isCorrect ? 'Correct answer!' : 'Incorrect answer. Review the material and try again.'
        ];
    }
    
    /**
     * Validate a multiple choice answer
     *
     * @param int $questionId
     * @param array $answerData
     * @return array
     */
    private function validateMultipleAnswer(int $questionId, array $answerData): array
    {
        $allCorrect = true;
        $answers = [];
        
        foreach ($answerData as $answerItem) {
            $answerArr = explode("##", $answerItem);
            $isCorrect = ($answerArr[1] ?? 0) == 1;
            $answers[] = [
                'answer_id' => $answerArr[0],
                'is_correct' => $isCorrect
            ];
            if (!$isCorrect) {
                $allCorrect = false;
            }
        }
        
        return [
            'question_id' => $questionId,
            'answers' => $answers,
            'is_correct' => $allCorrect,
            'answer_type' => 'multiple',
            'marks' => $allCorrect ? 1 : 0,
            'feedback' => $allCorrect ? 'All answers are correct!' : 'Some answers are incorrect.'
        ];
    }
    
    /**
     * Validate a narrative answer
     *
     * @param int $questionId
     * @param string $answerText
     * @return array
     */
    private function validateNarrativeAnswer(int $questionId, string $answerText): array
    {
        return [
            'question_id' => $questionId,
            'answer_text' => $answerText,
            'is_correct' => true,
            'answer_type' => 'narrative',
            'marks' => 1,
            'feedback' => 'Your answer has been recorded and will be reviewed.',
            'requires_review' => true
        ];
    }
    
    /**
     * Calculate mastery level based on results
     *
     * @param array $results
     * @return array
     */
    private function calculateMastery(array $results): array
    {
        $percentage = $results['percentage'];
        $totalQuestions = $results['total_questions'];
        $correctCount = $results['total_right'];
        
        // Calculate pKnow (probability of knowing)
        $pKnow = $correctCount / max($totalQuestions, 1);
        
        // Determine mastery level
        if ($percentage >= 90) {
            $level = 'expert';
            $description = 'Outstanding performance! You have mastered the concepts.';
        } elseif ($percentage >= 75) {
            $level = 'proficient';
            $description = 'Good performance! You understand most concepts well.';
        } elseif ($percentage >= 60) {
            $level = 'developing';
            $description = 'Satisfactory performance. Some concepts need reinforcement.';
        } elseif ($percentage >= 40) {
            $level = 'beginner';
            $description = 'Basic understanding. More practice needed.';
        } else {
            $level = 'novice';
            $description = 'Limited understanding. Significant improvement needed.';
        }
        
        return [
            'level' => $level,
            'p_know' => $pKnow,
            'percentage' => $percentage,
            'correct_count' => $correctCount,
            'total_questions' => $totalQuestions,
            'description' => $description
        ];
    }
    
    /**
     * Store the assessment attempt
     *
     * @param array $assessmentData
     * @param array $processedResults
     * @return int
     */
    private function storeAttempt(array $assessmentData, array $processedResults): int
    {
        // Store main exam record
        $onlineExam = [
            'student_id' => $assessmentData['student_id'],
            'question_paper_id' => $assessmentData['question_paper_id'],
            'total_right' => $processedResults['total_right'],
            'total_wrong' => $processedResults['total_wrong'],
            'obtain_marks' => $processedResults['obtain_marks'],
            'start_time' => $assessmentData['start_time'] ?? date('Y-m-d H:i:s'),
        ];
        
        lmsOnlineExamModel::insert($onlineExam);
        $onlineExamId = DB::getPDO()->lastInsertId();
        
        // Store individual answers
        foreach ($processedResults['question_results'] as $questionResult) {
            $answerRecord = [
                'question_paper_id' => $assessmentData['question_paper_id'],
                'online_exam_id' => $onlineExamId,
                'student_id' => $assessmentData['student_id'],
                'question_id' => $questionResult['question_id'],
                'ans_status' => $questionResult['is_correct'] ? 'right' : 'wrong',
            ];
            
            // Add answer-specific fields
            if (isset($questionResult['answer_id'])) {
                $answerRecord['answer_id'] = $questionResult['answer_id'];
            }
            
            if (isset($questionResult['answer_text'])) {
                $answerRecord['narrative_answer'] = $questionResult['answer_text'];
            }
            
            lmsOnlineExamAnswerModel::insert($answerRecord);
        }
        
        return $onlineExamId;
    }
    
    /**
     * Update learner state
     *
     * @param int $studentId
     * @param array $masteryLevel
     */
    private function updateLearnerState(int $studentId, array $masteryLevel): void
    {
        // Store in session for immediate access
        Session::put("learner_mastery_{$studentId}", $masteryLevel);
        
        // You can also store in cache for performance
        $cacheKey = "learner_mastery_{$studentId}";
        cache([$cacheKey => $masteryLevel], now()->addDays(7));
    }
    
    /**
     * Generate feedback for the learner
     *
     * @param array $results
     * @param array $masteryLevel
     * @return array
     */
    private function generateFeedback(array $results, array $masteryLevel): array
    {
        $feedback = [
            'summary' => $masteryLevel['description'],
            'details' => [],
            'recommendations' => []
        ];
        
        // Generate question-specific feedback
        foreach ($results['question_results'] as $index => $questionResult) {
            if (!$questionResult['is_correct']) {
                $feedback['details'][] = [
                    'question_id' => $questionResult['question_id'],
                    'message' => $questionResult['feedback'],
                    'type' => 'incorrect'
                ];
            }
        }
        
        // Add recommendations based on mastery level
        if ($masteryLevel['level'] === 'novice' || $masteryLevel['level'] === 'beginner') {
            $feedback['recommendations'][] = 'Review the fundamental concepts before attempting again';
            $feedback['recommendations'][] = 'Watch video tutorials on the topic';
        } elseif ($masteryLevel['level'] === 'developing') {
            $feedback['recommendations'][] = 'Practice more questions on weak areas';
            $feedback['recommendations'][] = 'Take supplementary quizzes';
        } elseif ($masteryLevel['level'] === 'proficient') {
            $feedback['recommendations'][] = 'Challenge yourself with advanced questions';
            $feedback['recommendations'][] = 'Help peers who are struggling';
        }
        
        return $feedback;
    }
    
    /**
     * Determine next learning steps
     *
     * @param array $results
     * @param array $masteryLevel
     * @return array
     */
    private function determineNextSteps(array $results, array $masteryLevel): array
    {
        $nextSteps = [
            'immediate_actions' => [],
            'recommended_resources' => [],
            'suggested_activities' => []
        ];
        
        // Based on mastery level, suggest next steps
        switch ($masteryLevel['level']) {
            case 'expert':
                $nextSteps['immediate_actions'][] = 'Proceed to next module';
                $nextSteps['suggested_activities'][] = 'Take advanced assessment';
                $nextSteps['suggested_activities'][] = 'Earn certification';
                break;
                
            case 'proficient':
                $nextSteps['immediate_actions'][] = 'Review incorrect answers';
                $nextSteps['suggested_activities'][] = 'Take a refresher quiz';
                $nextSteps['recommended_resources'][] = 'Advanced practice problems';
                break;
                
            case 'developing':
                $nextSteps['immediate_actions'][] = 'Review weak concepts';
                $nextSteps['suggested_activities'][] = 'Complete remedial exercises';
                $nextSteps['recommended_resources'][] = 'Interactive tutorials';
                $nextSteps['recommended_resources'][] = 'Video explanations';
                break;
                
            default:
                $nextSteps['immediate_actions'][] = 'Restudy the material';
                $nextSteps['suggested_activities'][] = 'Take foundational quiz';
                $nextSteps['recommended_resources'][] = 'Basic concept videos';
                $nextSteps['recommended_resources'][] = 'Step-by-step guides';
                break;
        }
        
        // Add specific questions to review
        $incorrectQuestions = array_filter($results['question_results'], function($q) {
            return !$q['is_correct'];
        });
        
        if (!empty($incorrectQuestions)) {
            $nextSteps['immediate_actions'][] = 'Review ' . count($incorrectQuestions) . ' incorrect answers';
        }
        
        return $nextSteps;
    }
    
    /**
     * 🎯 DISPATCH ASSESSMENT EVENT
     * This is where the event is created and dispatched
     *
     * @param array $assessmentData
     * @param array $processedResults
     * @param array $masteryLevel
     */
    private function dispatchAssessmentEvent(array $assessmentData, array $processedResults, array $masteryLevel): void
    {
        // Calculate pKnow (probability of knowing) - this matches your event's float type
        $pKnow = (float) $masteryLevel['p_know'];
        
        // Determine if the overall assessment is correct (based on passing score)
        $isCorrect = $processedResults['percentage'] >= 60;
        
        // 🎯 CREATE AND DISPATCH THE EVENT
        event(new AssessmentSubmitted(
            student_id: (string) $assessmentData['student_id'],
            sub_institute_id: (string) ($assessmentData['sub_institute_id'] ?? ''),
            assessmentId: (string) $assessmentData['question_paper_id'],
            conceptId: (string) ($assessmentData['concept_id'] ?? 'general'),
            isCorrect: $isCorrect,
            pKnow: $pKnow,
            timeTaken: (int) ($assessmentData['time_taken'] ?? 0),
            confidence: (float) ($assessmentData['confidence'] ?? 0.8),
            kasbaDimensions: (array) ($assessmentData['kasba_dimensions'] ?? []),
            metadata: (array) [
                'total_questions' => $processedResults['total_questions'],
                'correct_answers' => $processedResults['total_right'],
                'wrong_answers' => $processedResults['total_wrong'],
                'obtain_marks' => $processedResults['obtain_marks'],
                'mastery_level' => $masteryLevel['level'],
                'mastery_percentage' => $masteryLevel['percentage'],
                'sub_institute_id' => $assessmentData['sub_institute_id'] ?? '',
                'timestamp' => now()->toIso8601String()
            ]
        ));
        
        // Log event dispatch for debugging
        Log::info('AssessmentSubmitted event dispatched', [
            'student_id' => $assessmentData['student_id'],
            'assessment_id' => $assessmentData['question_paper_id'],
            'is_correct' => $isCorrect,
            'p_know' => $pKnow
        ]);
    }
}