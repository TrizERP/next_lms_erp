<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PedagogyEngineController extends Controller
{
    // ============================================================
    // 1. STUDENT INTELLIGENCE
    // ============================================================

    /**
     * Build Student Learning Profile
     */
    public function buildStudentProfile(Request $request)
    {
        $student_id = $request->get('student_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        if (!$student_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student ID is required'
            ]);
        }

        // Analyze learning patterns from engagement data
        $engagementData = $this->analyzeEngagement($student_id);
        
        // Analyze performance patterns
        $performanceData = $this->analyzePerformance($student_id);
        
        // Determine learning style
        $learningStyle = $this->determineLearningStyle($engagementData, $performanceData);
        
        // Determine learning pace
        $learningPace = $this->determineLearningPace($engagementData);
        
        // Identify strengths and weaknesses
        $strengths = $this->identifyStrengths($performanceData);
        $weaknesses = $this->identifyWeaknesses($performanceData);

        // Save profile
        $profile = DB::table('lms_student_profile')->updateOrInsert(
            ['student_id' => $student_id],
            [
                'learning_style' => $learningStyle,
                'learning_pace' => $learningPace,
                'motivation_level' => $this->determineMotivation($engagementData),
                'attention_span' => $this->calculateAttentionSpan($engagementData),
                'preferred_content_types' => json_encode($this->getPreferredContentTypes($engagementData)),
                'strengths' => json_encode($strengths),
                'weaknesses' => json_encode($weaknesses),
                'interests' => json_encode($this->getInterests($performanceData)),
                'updated_at' => now()
            ]
        );

        return response()->json([
            'status_code' => 1,
            'data' => [
                'learning_style' => $learningStyle,
                'learning_pace' => $learningPace,
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
                'preferred_content_types' => $this->getPreferredContentTypes($engagementData)
            ]
        ]);
    }

    /**
     * Analyze student engagement
     */
    private function analyzeEngagement($student_id)
    {
        return DB::table('lms_student_engagement')
            ->where('student_id', $student_id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();
    }

    /**
     * Analyze student performance
     */
    private function analyzePerformance($student_id)
    {
        return DB::table('lms_online_exam_answer as a')
            ->join('lms_question_master as q', 'q.id', '=', 'a.question_id')
            ->join('lms_concept as c', 'c.id', '=', 'q.concept_id')
            ->select(
                'c.id as concept_id',
                'c.name as concept_name',
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(CASE WHEN a.ans_status = 1 THEN 1 ELSE 0 END) as correct'),
                DB::raw('AVG(a.created_at) as avg_time')
            )
            ->where('a.student_id', $student_id)
            ->groupBy('c.id', 'c.name')
            ->get();
    }

    /**
     * Determine learning style
     */
    private function determineLearningStyle($engagementData, $performanceData)
    {
        $scores = [
            'visual' => 0,
            'auditory' => 0,
            'kinesthetic' => 0,
            'reading' => 0
        ];

        foreach ($engagementData as $activity) {
            $metadata = json_decode($activity->metadata, true) ?? [];
            
            if ($activity->activity_type === 'content_view') {
                $contentType = $metadata['content_type'] ?? '';
                if (in_array($contentType, ['video', 'image', 'infographic'])) {
                    $scores['visual'] += 2;
                } elseif (in_array($contentType, ['audio', 'podcast'])) {
                    $scores['auditory'] += 2;
                } elseif (in_array($contentType, ['interactive', 'simulation'])) {
                    $scores['kinesthetic'] += 2;
                } elseif (in_array($contentType, ['text', 'document'])) {
                    $scores['reading'] += 2;
                }
            }
            
            // Time spent also indicates preference
            if ($activity->time_spent_seconds > 60) {
                $contentType = $metadata['content_type'] ?? '';
                if ($contentType === 'video') $scores['visual'] += 1;
                if ($contentType === 'audio') $scores['auditory'] += 1;
                if ($contentType === 'interactive') $scores['kinesthetic'] += 1;
                if ($contentType === 'text') $scores['reading'] += 1;
            }
        }

        // Return the highest scoring learning style
        arsort($scores);
        return key($scores);
    }

    /**
     * Determine learning pace
     */
    private function determineLearningPace($engagementData)
    {
        $totalQuestions = 0;
        $avgTimePerQuestion = 0;
        
        foreach ($engagementData as $activity) {
            if ($activity->activity_type === 'quiz') {
                $totalQuestions++;
                $avgTimePerQuestion += $activity->time_spent_seconds / max(1, $activity->interaction_count);
            }
        }

        if ($totalQuestions === 0) return 'medium';
        
        $avgTime = $avgTimePerQuestion / $totalQuestions;
        
        if ($avgTime < 20) return 'fast';
        if ($avgTime < 45) return 'medium';
        return 'slow';
    }

    /**
     * Identify strengths
     */
    private function identifyStrengths($performanceData)
    {
        $strengths = [];
        foreach ($performanceData as $concept) {
            $accuracy = $concept->total_attempts > 0 
                ? ($concept->correct / $concept->total_attempts) * 100 
                : 0;
            
            if ($accuracy >= 70 && $concept->total_attempts >= 3) {
                $strengths[] = [
                    'concept_id' => $concept->concept_id,
                    'concept_name' => $concept->concept_name,
                    'accuracy' => round($accuracy, 1)
                ];
            }
        }
        return $strengths;
    }

    /**
     * Identify weaknesses
     */
    private function identifyWeaknesses($performanceData)
    {
        $weaknesses = [];
        foreach ($performanceData as $concept) {
            $accuracy = $concept->total_attempts > 0 
                ? ($concept->correct / $concept->total_attempts) * 100 
                : 0;
            
            if ($accuracy < 40 && $concept->total_attempts >= 2) {
                $weaknesses[] = [
                    'concept_id' => $concept->concept_id,
                    'concept_name' => $concept->concept_name,
                    'accuracy' => round($accuracy, 1)
                ];
            }
        }
        return $weaknesses;
    }

    /**
     * Determine motivation level
     */
    private function determineMotivation($engagementData)
    {
        $recentActivity = $engagementData->filter(function($item) {
            return $item->created_at >= now()->subDays(7);
        });

        $totalActivities = $recentActivity->count();
        $avgTimeSpent = $recentActivity->avg('time_spent_seconds');

        if ($totalActivities >= 20 && $avgTimeSpent >= 60) return 'high';
        if ($totalActivities >= 10 && $avgTimeSpent >= 30) return 'medium';
        return 'low';
    }

    /**
     * Calculate attention span
     */
    private function calculateAttentionSpan($engagementData)
    {
        $sessions = [];
        $currentSession = null;
        
        foreach ($engagementData as $activity) {
            $time = Carbon::parse($activity->created_at);
            
            if (!$currentSession || $time->diffInMinutes($currentSession['end']) > 15) {
                if ($currentSession) {
                    $sessions[] = $currentSession;
                }
                $currentSession = [
                    'start' => $time,
                    'end' => $time,
                    'activities' => 0
                ];
            }
            
            $currentSession['end'] = $time;
            $currentSession['activities']++;
        }
        
        if ($currentSession) {
            $sessions[] = $currentSession;
        }

        if (empty($sessions)) return 0;
        
        $avgDuration = collect($sessions)->avg(function($session) {
            return $session['start']->diffInMinutes($session['end']);
        });

        return min(100, round($avgDuration / 30 * 100));
    }

    /**
     * Get preferred content types
     */
    private function getPreferredContentTypes($engagementData)
    {
        $types = [];
        foreach ($engagementData as $activity) {
            $metadata = json_decode($activity->metadata, true) ?? [];
            if (isset($metadata['content_type'])) {
                $type = $metadata['content_type'];
                if (!isset($types[$type])) {
                    $types[$type] = 0;
                }
                $types[$type] += $activity->time_spent_seconds;
            }
        }
        
        arsort($types);
        return array_slice(array_keys($types), 0, 3);
    }

    /**
     * Get student interests from performance
     */
    private function getInterests($performanceData)
    {
        $interests = [];
        foreach ($performanceData as $concept) {
            if ($concept->total_attempts >= 5 && $concept->correct / $concept->total_attempts >= 0.6) {
                $interests[] = $concept->concept_name;
            }
        }
        return $interests;
    }

    /**
     * Get Forgetting Curve for a student
     */
    public function getForgettingCurve(Request $request)
    {
        $student_id = $request->get('student_id');
        $concept_id = $request->get('concept_id');

        if (!$student_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student ID is required'
            ]);
        }

        $query = DB::table('lms_forgetting_curve')
            ->where('student_id', $student_id);

        if ($concept_id) {
            $query->where('concept_id', $concept_id);
        }

        $curveData = $query->get();

        // Calculate Ebbinghaus Forgetting Curve
        $curve = [];
        foreach ($curveData as $item) {
            $curve[] = [
                'concept_id' => $item->concept_id,
                'retention_rate' => $item->retention_rate,
                'last_reviewed' => $item->last_reviewed_at,
                'next_review' => $item->next_review_at,
                'review_interval' => $item->review_interval_days,
                'review_count' => $item->review_count,
                'urgency' => $this->getReviewUrgency($item)
            ];
        }

        return response()->json([
            'status_code' => 1,
            'data' => $curve
        ]);
    }

    /**
     * Get review urgency for forgetting curve
     */
    private function getReviewUrgency($item)
    {
        $now = now();
        $nextReview = Carbon::parse($item->next_review_at);
        
        if ($now->gte($nextReview)) return 'overdue';
        if ($now->diffInDays($nextReview) <= 1) return 'today';
        if ($now->diffInDays($nextReview) <= 3) return 'soon';
        return 'ok';
    }

    /**
     * Update forgetting curve after practice
     */
    public function updateForgettingCurve(Request $request)
    {
        $student_id = $request->get('student_id');
        $concept_id = $request->get('concept_id');
        $performance = $request->get('performance', 100);

        if (!$student_id || !$concept_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student ID and Concept ID are required'
            ]);
        }

        $existing = DB::table('lms_forgetting_curve')
            ->where('student_id', $student_id)
            ->where('concept_id', $concept_id)
            ->first();

        if ($existing) {
            // Update based on performance
            $newRetention = ($existing->retention_rate * 0.6) + ($performance * 0.4);
            $reviewCount = $existing->review_count + 1;
            
            // Spaced repetition intervals: 1, 2, 4, 7, 14, 30 days
            $intervals = [1, 2, 4, 7, 14, 30];
            $intervalIndex = min($reviewCount - 1, count($intervals) - 1);
            $nextInterval = $intervals[$intervalIndex];
            
            DB::table('lms_forgetting_curve')
                ->where('id', $existing->id)
                ->update([
                    'retention_rate' => $newRetention,
                    'last_reviewed_at' => now(),
                    'next_review_at' => now()->addDays($nextInterval),
                    'review_interval_days' => $nextInterval,
                    'review_count' => $reviewCount,
                    'updated_at' => now()
                ]);
        } else {
            // Create new entry
            DB::table('lms_forgetting_curve')->insert([
                'student_id' => $student_id,
                'concept_id' => $concept_id,
                'retention_rate' => $performance,
                'last_reviewed_at' => now(),
                'next_review_at' => now()->addDay(),
                'review_interval_days' => 1,
                'review_count' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Forgetting curve updated successfully'
        ]);
    }


    // ============================================================
    // 2. TEACHER INTELLIGENCE
    // ============================================================

    /**
     * Generate Class Insights
     */
    public function generateClassInsights(Request $request)
    {
        $teacher_id = $request->session()->get('user_id');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $chapter_id = $request->get('chapter_id');

        if (!$teacher_id || !$standard_id || !$subject_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Teacher ID, Standard ID, and Subject ID are required'
            ]);
        }

        // Get all students
        $students = DB::table('student_master')
            ->where('standard_id', $standard_id)
            ->where('sub_institute_id', $request->session()->get('sub_institute_id'))
            ->pluck('id');

        // Analyze class performance
        $classData = $this->analyzeClassPerformance($students, $standard_id, $subject_id, $chapter_id);
        
        // Identify struggling students
        $strugglingStudents = $this->identifyStrugglingStudents($classData);
        
        // Identify common misconceptions
        $commonMisconceptions = $this->identifyCommonMisconceptions($students, $standard_id, $subject_id, $chapter_id);
        
        // Generate recommendations
        $recommendations = $this->generateTeacherRecommendations($classData, $strugglingStudents, $commonMisconceptions);

        // Save insights
        $insightId = DB::table('lms_class_insights')->insertGetId([
            'teacher_id' => $teacher_id,
            'standard_id' => $standard_id,
            'subject_id' => $subject_id,
            'chapter_id' => $chapter_id,
            'insight_type' => 'class_performance',
            'data' => json_encode($classData),
            'recommendations' => json_encode($recommendations),
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'status_code' => 1,
            'data' => [
                'insight_id' => $insightId,
                'class_data' => $classData,
                'struggling_students' => $strugglingStudents,
                'common_misconceptions' => $commonMisconceptions,
                'recommendations' => $recommendations
            ]
        ]);
    }

    /**
     * Analyze class performance
     */
    private function analyzeClassPerformance($students, $standard_id, $subject_id, $chapter_id)
    {
        $data = [
            'total_students' => $students->count(),
            'concepts' => [],
            'average_score' => 0,
            'passing_rate' => 0
        ];

        foreach ($students as $studentId) {
            $mastery = $this->getStudentMasterySummary($studentId, $standard_id, $subject_id, $chapter_id);
            $data['concepts'][] = $mastery;
        }

        // Calculate averages
        $allMastery = collect($data['concepts'])->pluck('mastery_level')->filter();
        $data['average_score'] = $allMastery->avg() ?? 0;
        $data['passing_rate'] = $allMastery->filter(function($score) {
            return $score >= 60;
        })->count() / max(1, $allMastery->count()) * 100;

        return $data;
    }

    /**
     * Get student mastery summary
     */
    private function getStudentMasterySummary($student_id, $standard_id, $subject_id, $chapter_id)
    {
        $query = DB::table('lms_concept as c')
            ->leftJoin('lms_question_master as q', 'q.concept_id', '=', 'c.id')
            ->leftJoin('lms_online_exam_answer as a', function($join) use ($student_id) {
                $join->on('a.question_id', '=', 'q.id')
                     ->where('a.student_id', '=', $student_id);
            })
            ->select(
                'c.id as concept_id',
                'c.name as concept_name',
                DB::raw('COUNT(DISTINCT a.id) as attempts'),
                DB::raw('SUM(CASE WHEN a.ans_status = 1 THEN 1 ELSE 0 END) as correct')
            )
            ->where('c.standard_id', $standard_id)
            ->where('c.subject_id', $subject_id);

        if ($chapter_id) {
            $query->where('c.chapter_id', $chapter_id);
        }

        $results = $query->groupBy('c.id', 'c.name')->get();
        
        $totalAttempts = 0;
        $totalCorrect = 0;
        $masteryLevels = [];

        foreach ($results as $concept) {
            $attempts = $concept->attempts ?? 0;
            $correct = $concept->correct ?? 0;
            $mastery = $attempts > 0 ? round(($correct / $attempts) * 100, 1) : 0;
            $masteryLevels[] = $mastery;
            $totalAttempts += $attempts;
            $totalCorrect += $correct;
        }

        return [
            'student_id' => $student_id,
            'mastery_level' => !empty($masteryLevels) ? round(array_sum($masteryLevels) / count($masteryLevels), 1) : 0,
            'total_attempts' => $totalAttempts,
            'total_correct' => $totalCorrect,
            'concepts' => $results->map(function($c) {
                $attempts = $c->attempts ?? 0;
                return [
                    'concept_id' => $c->concept_id,
                    'concept_name' => $c->concept_name,
                    'mastery' => $attempts > 0 ? round(($c->correct / $attempts) * 100, 1) : 0,
                    'attempts' => $attempts
                ];
            })
        ];
    }

    /**
     * Identify struggling students
     */
    private function identifyStrugglingStudents($classData)
    {
        $struggling = [];
        foreach ($classData['concepts'] as $student) {
            if ($student['mastery_level'] < 40) {
                $struggling[] = [
                    'student_id' => $student['student_id'],
                    'mastery_level' => $student['mastery_level'],
                    'weak_concepts' => collect($student['concepts'])
                        ->filter(function($c) { return $c['mastery'] < 40; })
                        ->pluck('concept_name')
                        ->toArray()
                ];
            }
        }
        return $struggling;
    }

    /**
     * Identify common misconceptions
     */
    private function identifyCommonMisconceptions($students, $standard_id, $subject_id, $chapter_id)
    {
        $misconceptions = [];

        foreach ($students as $studentId) {
            $wrongQuestions = DB::table('lms_online_exam_answer as a')
                ->join('lms_question_master as q', 'q.id', '=', 'a.question_id')
                ->join('lms_concept as c', 'c.id', '=', 'q.concept_id')
                ->where('a.student_id', $studentId)
                ->where('a.ans_status', 0)
                ->where('c.standard_id', $standard_id)
                ->where('c.subject_id', $subject_id);

            if ($chapter_id) {
                $wrongQuestions->where('c.chapter_id', $chapter_id);
            }

            $wrongQuestions = $wrongQuestions->select('q.id', 'q.question_title', 'c.name as concept_name')
                ->get();

            foreach ($wrongQuestions as $wq) {
                $key = $wq->concept_name . ' - ' . substr($wq->question_title, 0, 50);
                if (!isset($misconceptions[$key])) {
                    $misconceptions[$key] = [
                        'concept' => $wq->concept_name,
                        'question' => $wq->question_title,
                        'count' => 0
                    ];
                }
                $misconceptions[$key]['count']++;
            }
        }

        // Sort by frequency
        usort($misconceptions, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_slice($misconceptions, 0, 10);
    }

    /**
     * Generate teacher recommendations
     */
    private function generateTeacherRecommendations($classData, $strugglingStudents, $commonMisconceptions)
    {
        $recommendations = [];

        // 1. Whole class recommendations
        if ($classData['average_score'] < 50) {
            $recommendations[] = [
                'type' => 'class',
                'priority' => 'high',
                'title' => 'Class-wide Remediation Needed',
                'description' => 'The class average is below 50%. Consider reteaching key concepts.',
                'action' => 'Schedule a review session focusing on the most missed concepts.'
            ];
        }

        // 2. Group recommendations
        if (!empty($strugglingStudents)) {
            $recommendations[] = [
                'type' => 'group',
                'priority' => 'high',
                'title' => 'Small Group Intervention',
                'description' => count($strugglingStudents) . ' students are struggling significantly.',
                'action' => 'Form a small group for targeted remediation.',
                'students' => $strugglingStudents
            ];
        }

        // 3. Common misconception recommendations
        if (!empty($commonMisconceptions)) {
            $topMisconception = $commonMisconceptions[0] ?? null;
            if ($topMisconception) {
                $recommendations[] = [
                    'type' => 'misconception',
                    'priority' => 'medium',
                    'title' => 'Address Common Misconception',
                    'description' => $topMisconception['count'] . ' students struggled with: ' . $topMisconception['concept'],
                    'action' => 'Use a demonstration or alternative explanation to address this misconception.'
                ];
            }
        }

        // 4. Peer tutoring recommendations
        $peerGroups = $this->suggestPeerTutoring($classData);
        if (!empty($peerGroups)) {
            $recommendations[] = [
                'type' => 'peer_tutoring',
                'priority' => 'medium',
                'title' => 'Peer Tutoring Groups',
                'description' => count($peerGroups) . ' peer tutoring groups suggested.',
                'action' => 'Create peer tutoring pairs to help struggling students.',
                'groups' => $peerGroups
            ];
        }

        return $recommendations;
    }

    /**
     * Suggest peer tutoring pairs
     */
    private function suggestPeerTutoring($classData)
    {
        $groups = [];
        $mastered = [];
        $struggling = [];

        foreach ($classData['concepts'] as $student) {
            if ($student['mastery_level'] >= 70) {
                $mastered[] = $student['student_id'];
            } else {
                $struggling[] = $student['student_id'];
            }
        }

        // Pair each struggling student with a mastered student
        for ($i = 0; $i < min(count($struggling), count($mastered)); $i++) {
            $groups[] = [
                'tutor' => $mastered[$i % count($mastered)],
                'tutee' => $struggling[$i]
            ];
        }

        return $groups;
    }

    /**
     * Get Teacher Dashboard
     */
    public function teacherDashboard(Request $request)
    {
        $teacher_id = $request->session()->get('user_id');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');

        if (!$teacher_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Teacher ID is required'
            ]);
        }

        // Get recent insights
        $insights = DB::table('lms_class_insights')
            ->where('teacher_id', $teacher_id)
            ->where('standard_id', $standard_id)
            ->where('subject_id', $subject_id)
            ->orderBy('generated_at', 'desc')
            ->limit(5)
            ->get();

        // Get active interventions
        $interventions = DB::table('lms_teacher_interventions')
            ->where('teacher_id', $teacher_id)
            ->where('status', 'in_progress')
            ->get();

        // Get peer tutoring groups
        $peerGroups = DB::table('lms_peer_tutoring_groups')
            ->where('teacher_id', $teacher_id)
            ->where('status', 'active')
            ->get();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'insights' => $insights,
                'interventions' => $interventions,
                'peer_groups' => $peerGroups,
                'summary' => [
                    'total_students' => DB::table('student_master')
                        ->where('standard_id', $standard_id)
                        ->count(),
                    'active_interventions' => $interventions->count(),
                    'active_peer_groups' => $peerGroups->count()
                ]
            ]
        ]);
    }


    // ============================================================
    // 3. CONTENT INTELLIGENCE
    // ============================================================

    /**
     * Build Knowledge Graph
     */
    public function buildKnowledgeGraph(Request $request)
    {
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        // Get all concepts
        $concepts = DB::table('lms_concept')
            ->where('standard_id', $standard_id)
            ->where('subject_id', $subject_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->get();

        foreach ($concepts as $concept) {
            // Find prerequisite concepts based on question mappings
            $prerequisites = $this->findPrerequisites($concept->id);
            
            foreach ($prerequisites as $prereq) {
                DB::table('lms_knowledge_graph')->updateOrInsert(
                    [
                        'concept_id' => $concept->id,
                        'prerequisite_concept_id' => $prereq
                    ],
                    [
                        'relationship_type' => 'requires',
                        'strength' => 1,
                        'updated_at' => now()
                    ]
                );
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Knowledge graph built successfully'
        ]);
    }

    /**
     * Find prerequisites for a concept
     */
    private function findPrerequisites($concept_id)
    {
        // Look for concepts that appear in pre_grade_topic of questions
        $prereqs = DB::table('lms_question_master')
            ->where('concept_id', $concept_id)
            ->whereNotNull('pre_grade_topic')
            ->where('pre_grade_topic', '!=', '')
            ->pluck('pre_grade_topic')
            ->map(function($item) {
                // Parse pre_grade_topic format: "chapter_id####topic_id"
                $parts = explode('####', $item);
                return $parts[0] ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return $prereqs;
    }

    /**
     * Get Concept Prerequisites
     */
    public function getConceptPrerequisites(Request $request)
    {
        $concept_id = $request->get('concept_id');

        if (!$concept_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Concept ID is required'
            ]);
        }

        $prerequisites = DB::table('lms_knowledge_graph as kg')
            ->join('lms_concept as c', 'c.id', '=', 'kg.prerequisite_concept_id')
            ->where('kg.concept_id', $concept_id)
            ->select('c.id', 'c.name', 'c.description', 'kg.strength')
            ->get();

        // Check if student has mastered prerequisites
        $student_id = $request->get('student_id');
        if ($student_id) {
            foreach ($prerequisites as $prereq) {
                $mastery = $this->getConceptMasteryForStudent($student_id, $prereq->id);
                $prereq->mastered = $mastery >= 60;
                $prereq->mastery_level = $mastery;
            }
        }

        return response()->json([
            'status_code' => 1,
            'data' => $prerequisites
        ]);
    }

    /**
     * Get concept mastery for a student
     */
    private function getConceptMasteryForStudent($student_id, $concept_id)
    {
        $attempts = DB::table('lms_online_exam_answer as a')
            ->join('lms_question_master as q', 'q.id', '=', 'a.question_id')
            ->where('a.student_id', $student_id)
            ->where('q.concept_id', $concept_id)
            ->get();

        $total = $attempts->count();
        $correct = $attempts->where('ans_status', 1)->count();

        return $total > 0 ? round(($correct / $total) * 100, 1) : 0;
    }

    /**
     * Recommend Content Based on Student Profile
     */
    public function recommendContent(Request $request)
    {
        $student_id = $request->get('student_id');
        $concept_id = $request->get('concept_id');
        $limit = (int) $request->get('limit', 5);
        $sub_institute_id = $request->session()->get('sub_institute_id');

        if (!$student_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student ID is required'
            ]);
        }

        // Get student profile
        $profile = DB::table('lms_student_profile')
            ->where('student_id', $student_id)
            ->first();

        // Get student mastery
        $masteryData = $this->getStudentMasterySummary($student_id, null, null, null);

        // Find weak concepts
        $weakConcepts = collect($masteryData['concepts'] ?? [])
            ->filter(function($c) { return $c['mastery'] < 40; })
            ->pluck('concept_id')
            ->toArray();

        // If concept_id is specified, add it
        if ($concept_id && !in_array($concept_id, $weakConcepts)) {
            $weakConcepts[] = $concept_id;
        }

        // Get content recommendations
        $content = DB::table('teacher_content as tc')
            ->leftJoin('lms_content_metadata as cm', 'cm.content_id', '=', 'tc.id')
            ->where('tc.sub_institute_id', $sub_institute_id)
            ->where('tc.status', 1);

        if (!empty($weakConcepts)) {
            $content->whereIn('tc.concept_id', $weakConcepts);
        }

        // Apply learning style preference
        if ($profile && $profile->preferred_content_types) {
            $preferredTypes = json_decode($profile->preferred_content_types, true);
            if (!empty($preferredTypes)) {
                $content->whereIn('tc.file_type', $preferredTypes);
            }
        }

        $recommendations = $content->limit($limit)->get();

        // Save recommendations
        foreach ($recommendations as $rec) {
            DB::table('lms_content_recommendations')->insert([
                'student_id' => $student_id,
                'concept_id' => $rec->concept_id,
                'content_id' => $rec->id,
                'content_type' => $rec->file_type ?? 'document',
                'recommendation_reason' => 'Based on mastery level and learning preferences',
                'priority' => $this->calculateRecommendationPriority($rec, $weakConcepts),
                'status' => 'pending',
                'recommended_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'status_code' => 1,
            'data' => $recommendations
        ]);
    }

    /**
     * Calculate recommendation priority
     */
    private function calculateRecommendationPriority($content, $weakConcepts)
    {
        $priority = 1;
        
        // Higher priority for weak concepts
        if (in_array($content->concept_id, $weakConcepts)) {
            $priority += 2;
        }
        
        return $priority;
    }

    /**
     * Track Content View
     */
    public function trackContentView(Request $request)
    {
        $student_id = $request->get('student_id');
        $content_id = $request->get('content_id');
        $time_spent = $request->get('time_spent', 0);

        if (!$student_id || !$content_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student ID and Content ID are required'
            ]);
        }

        // Update content recommendation status
        DB::table('lms_content_recommendations')
            ->where('student_id', $student_id)
            ->where('content_id', $content_id)
            ->update([
                'status' => 'viewed',
                'viewed_at' => now(),
                'updated_at' => now()
            ]);

        // Log engagement
        DB::table('lms_student_engagement')->insert([
            'student_id' => $student_id,
            'activity_type' => 'content_view',
            'activity_id' => $content_id,
            'time_spent_seconds' => $time_spent,
            'started_at' => now()->subSeconds($time_spent),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Content view tracked successfully'
        ]);
    }


    // ============================================================
    // 4. PEDAGOGY ENGINE API - COMBINED RECOMMENDATIONS
    // ============================================================

    /**
     * Get Complete Pedagogy Recommendations for a Student
     */
    public function getPedagogyRecommendations(Request $request)
    {
        $student_id = $request->get('student_id');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $chapter_id = $request->get('chapter_id');

        if (!$student_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student ID is required'
            ]);
        }

        // 1. Student Intelligence
        $profile = DB::table('lms_student_profile')
            ->where('student_id', $student_id)
            ->first();

        if (!$profile) {
            $this->buildStudentProfile($request);
            $profile = DB::table('lms_student_profile')
                ->where('student_id', $student_id)
                ->first();
        }

        // 2. Get forgetting curve
        $forgettingCurve = DB::table('lms_forgetting_curve')
            ->where('student_id', $student_id)
            ->where('next_review_at', '<=', now())
            ->get();

        // 3. Get mastery data
        $masteryData = $this->getStudentMasterySummary($student_id, $standard_id, $subject_id, $chapter_id);

        // 4. Get content recommendations
        $contentRecommendations = DB::table('lms_content_recommendations')
            ->where('student_id', $student_id)
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->limit(5)
            ->get();

        // 5. Generate combined recommendations
        $recommendations = [
            'student_profile' => $profile,
            'mastery_summary' => $masteryData,
            'forgetting_curve_due' => $forgettingCurve,
            'content_recommendations' => $contentRecommendations,
            'next_steps' => $this->generateNextSteps($masteryData, $forgettingCurve, $profile)
        ];

        return response()->json([
            'status_code' => 1,
            'data' => $recommendations
        ]);
    }

    /**
     * Generate next steps for student
     */
    private function generateNextSteps($masteryData, $forgettingCurve, $profile)
    {
        $steps = [];

        // 1. Review due concepts
        if ($forgettingCurve->count() > 0) {
            $steps[] = [
                'action' => 'review',
                'title' => 'Review Due Concepts',
                'description' => $forgettingCurve->count() . ' concepts need review based on the forgetting curve.',
                'priority' => 'high'
            ];
        }

        // 2. Practice weak concepts
        $weakConcepts = collect($masteryData['concepts'] ?? [])
            ->filter(function($c) { return $c['mastery'] < 40; })
            ->count();

        if ($weakConcepts > 0) {
            $steps[] = [
                'action' => 'practice',
                'title' => 'Practice Weak Concepts',
                'description' => $weakConcepts . ' concepts need focused practice.',
                'priority' => 'high'
            ];
        }

        // 3. Content exploration
        $steps[] = [
            'action' => 'explore',
            'title' => 'Explore Recommended Content',
            'description' => 'Based on your learning style, here are some resources for you.',
            'priority' => 'medium'
        ];

        return $steps;
    }

    public function getPedagogySuggestedContent(Request $request)
    {
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $chapter_id = $request->get('chapter_id');
        $grade_id = $request->get('grade_id');
        $student_id = $request->session()->get('user_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        if (!$student_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student not logged in'
            ]);
        }

        // ============================================================
        // 1. STUDENT INTELLIGENCE - Analyze Student Profile
        // ============================================================
        $studentProfile = $this->getOrCreateStudentProfile($student_id);
        $learningStyle = $studentProfile->learning_style ?? 'visual';
        $learningPace = $studentProfile->learning_pace ?? 'medium';
        $preferredContentTypes = json_decode($studentProfile->preferred_content_types ?? '[]', true);

        // Get forgetting curve data
        $forgettingData = $this->getForgettingCurveForStudent($student_id, $chapter_id);
        
        // Get concept mastery
        $masteryData = $this->getStudentConceptMastery($student_id, $standard_id, $subject_id, $chapter_id);

        // ============================================================
        // 2. CONTENT INTELLIGENCE - Find Best Content
        // ============================================================
        
        // Identify weak concepts (mastery < 40%)
        $weakConcepts = collect($masteryData)
            ->filter(function($c) { 
                return ($c['mastery_level'] ?? 0) < 40; 
            })
            ->pluck('concept_id')
            ->toArray();

        // Identify concepts due for review (forgetting curve)
        $dueConcepts = $forgettingData
            ->filter(function($c) { 
                return Carbon::parse($c->next_review_at)->lte(now()); 
            })
            ->pluck('concept_id')
            ->toArray();

        // Combine priority concepts
        $priorityConcepts = array_unique(array_merge($weakConcepts, $dueConcepts));

        // Get content with pedagogy metadata
        $contentQuery = DB::table('teacher_content as tc')
            ->leftJoin('lms_content_metadata as cm', 'cm.content_id', '=', 'tc.id')
            ->where('tc.sub_institute_id', $sub_institute_id)
            ->where('tc.status', 1)
            ->where('tc.standard_id', $standard_id)
            ->where('tc.subject_id', $subject_id);

        if ($chapter_id) {
            $contentQuery->where('tc.chapter_id', $chapter_id);
        }

        // Prioritize content based on student needs
        if (!empty($priorityConcepts)) {
            $contentQuery->whereIn('tc.concept_id', $priorityConcepts);
        }

        // Apply learning style preference
        if (!empty($preferredContentTypes)) {
            $contentQuery->whereIn('tc.file_type', $preferredContentTypes);
        }

        $content = $contentQuery->limit(10)->get();

        // If not enough content, get more
        if ($content->count() < 5) {
            $additionalContent = DB::table('teacher_content as tc')
                ->leftJoin('lms_content_metadata as cm', 'cm.content_id', '=', 'tc.id')
                ->where('tc.sub_institute_id', $sub_institute_id)
                ->where('tc.status', 1)
                ->where('tc.standard_id', $standard_id)
                ->where('tc.subject_id', $subject_id)
                ->where('tc.chapter_id', $chapter_id)
                ->limit(10 - $content->count())
                ->get();
            
            $content = $content->merge($additionalContent);
        }

        // ============================================================
        // 3. TEACHER INTELLIGENCE - Get Teacher Recommendations
        // ============================================================
        $teacherRecommendations = $this->getTeacherRecommendationsForStudent(
            $student_id,
            $standard_id,
            $subject_id,
            $chapter_id,
            $masteryData,
            $weakConcepts
        );

        // ============================================================
        // 4. PEDAGOGY ENGINE - Combine Everything
        // ============================================================
        
        // Categorize content with pedagogy labels
        $categorizedContent = $this->categorizeContentWithPedagogy(
            $content,
            $masteryData,
            $forgettingData,
            $learningStyle
        );

        // Generate pedagogy recommendations
        $pedagogyRecommendations = $this->generatePedagogyRecommendations(
            $studentProfile,
            $masteryData,
            $forgettingData,
            $weakConcepts,
            $dueConcepts
        );

        // Build response
        $response = [
            'status_code' => 1,
            'message' => 'Pedagogy-Enhanced Content Loaded',
            'content_data' => $categorizedContent,
            'student_level' => $this->getStudentLevel($masteryData),
            'pedagogy' => $pedagogyRecommendations,
            'student_profile' => [
                'learning_style' => $learningStyle,
                'learning_pace' => $learningPace,
                'preferred_content_types' => $preferredContentTypes
            ],
            'mastery_summary' => [
                'total_concepts' => count($masteryData),
                'mastered' => collect($masteryData)->filter(function($c) { 
                    return ($c['mastery_level'] ?? 0) >= 70; 
                })->count(),
                'developing' => collect($masteryData)->filter(function($c) { 
                    $m = $c['mastery_level'] ?? 0;
                    return $m >= 40 && $m < 70; 
                })->count(),
                'needs_practice' => count($weakConcepts),
                'due_for_review' => count($dueConcepts)
            ],
            'teacher_insights' => $teacherRecommendations
        ];

        return response()->json($response);
    }

    /**
     * Get or create student profile
     */
    private function getOrCreateStudentProfile($student_id)
    {
        $profile = DB::table('lms_student_profile')
            ->where('student_id', $student_id)
            ->first();

        if (!$profile) {
            // Create default profile
            $profileId = DB::table('lms_student_profile')->insertGetId([
                'student_id' => $student_id,
                'learning_style' => 'visual',
                'learning_pace' => 'medium',
                'motivation_level' => 'medium',
                'attention_span' => 50,
                'preferred_content_types' => json_encode(['video', 'interactive', 'text']),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $profile = DB::table('lms_student_profile')
                ->where('id', $profileId)
                ->first();
        }

        return $profile;
    }

    /**
     * Get forgetting curve data for student
     */
    private function getForgettingCurveForStudent($student_id, $chapter_id = null)
    {
        $query = DB::table('lms_forgetting_curve as fc')
            ->join('lms_concept as c', 'c.id', '=', 'fc.concept_id')
            ->where('fc.student_id', $student_id);

        if ($chapter_id) {
            $query->where('c.chapter_id', $chapter_id);
        }

        return $query->select('fc.*', 'c.name as concept_name')
            ->get();
    }

    /**
     * Get student concept mastery
     */
    private function getStudentConceptMastery($student_id, $standard_id, $subject_id, $chapter_id = null)
    {
        $query = DB::table('lms_concept as c')
            ->leftJoin('lms_question_master as q', 'q.concept_id', '=', 'c.id')
            ->leftJoin('lms_online_exam_answer as a', function($join) use ($student_id) {
                $join->on('a.question_id', '=', 'q.id')
                     ->where('a.student_id', '=', $student_id);
            })
            ->select(
                'c.id as concept_id',
                'c.name as concept_name',
                'c.mastery_threshold',
                DB::raw('COUNT(DISTINCT a.id) as attempts'),
                DB::raw('SUM(CASE WHEN a.ans_status = 1 THEN 1 ELSE 0 END) as correct'),
                DB::raw('MAX(a.created_at) as last_practiced')
            )
            ->where('c.standard_id', $standard_id)
            ->where('c.subject_id', $subject_id)
            ->where('c.sub_institute_id', session('sub_institute_id'));

        if ($chapter_id) {
            $query->where('c.chapter_id', $chapter_id);
        }

        $results = $query->groupBy('c.id', 'c.name', 'c.mastery_threshold')
            ->get();

        $masteryData = [];
        foreach ($results as $concept) {
            $attempts = $concept->attempts ?? 0;
            $correct = $concept->correct ?? 0;
            $mastery = $attempts > 0 ? round(($correct / $attempts) * 100, 1) : 0;
            
            $threshold = $concept->mastery_threshold ?? 70;
            $status = $attempts == 0 ? 'not_started' : 
                     ($mastery >= $threshold ? 'mastered' : 
                     ($mastery >= $threshold * 0.6 ? 'developing' : 'needs_practice'));

            $masteryData[] = [
                'concept_id' => $concept->concept_id,
                'concept_name' => $concept->concept_name,
                'mastery_level' => $mastery,
                'attempts' => $attempts,
                'correct' => $correct,
                'status' => $status,
                'last_practiced' => $concept->last_practiced
            ];
        }

        return $masteryData;
    }

    /**
     * Get teacher recommendations for student
     */
    private function getTeacherRecommendationsForStudent($student_id, $standard_id, $subject_id, $chapter_id, $masteryData, $weakConcepts)
    {
        $recommendations = [];

        // Get class average for comparison
        $classAvg = DB::table('lms_online_exam_answer as a')
            ->join('lms_question_master as q', 'q.id', '=', 'a.question_id')
            ->join('lms_concept as c', 'c.id', '=', 'q.concept_id')
            ->where('c.standard_id', $standard_id)
            ->where('c.subject_id', $subject_id)
            ->where('c.sub_institute_id', session('sub_institute_id'))
            ->select(
                DB::raw('AVG(CASE WHEN a.ans_status = 1 THEN 1 ELSE 0 END) * 100 as avg_score')
            )
            ->first();

        $studentAvg = collect($masteryData)->avg('mastery_level') ?? 0;
        $classAverage = $classAvg->avg_score ?? 0;

        // Compare student with class
        if ($studentAvg < $classAverage - 20) {
            $recommendations[] = [
                'type' => 'alert',
                'title' => '⚠️ Below Class Average',
                'description' => "Student is {$studentAvg}% vs Class {$classAverage}%. Needs intervention.",
                'priority' => 'high'
            ];
        }

        // Check for weak concepts
        if (!empty($weakConcepts)) {
            $conceptNames = DB::table('lms_concept')
                ->whereIn('id', $weakConcepts)
                ->pluck('name')
                ->toArray();

            $recommendations[] = [
                'type' => 'remediation',
                'title' => '📚 Focus Areas',
                'description' => 'Needs practice on: ' . implode(', ', $conceptNames),
                'priority' => 'high'
            ];
        }

        return $recommendations;
    }

    /**
     * Categorize content with pedagogy labels
     */
    private function categorizeContentWithPedagogy($content, $masteryData, $forgettingData, $learningStyle)
    {
        $categories = [
            'remediation_content' => [],
            'practice_content' => [],
            'enrichment_content' => [],
            'misconception_content' => []
        ];

        $masteryMap = collect($masteryData)->keyBy('concept_id');
        $forgettingMap = collect($forgettingData)->keyBy('concept_id');

        foreach ($content as $item) {
            $conceptId = $item->concept_id;
            $mastery = $masteryMap[$conceptId] ?? null;
            $forgetting = $forgettingMap[$conceptId] ?? null;

            // Determine category based on student needs
            if ($mastery && $mastery['mastery_level'] < 40) {
                // Needs remediation
                $categories['remediation_content'][] = $this->enrichContentWithPedagogy($item, 'remediation', $mastery);
            } elseif ($forgetting && Carbon::parse($forgetting->next_review_at)->lte(now())) {
                // Due for review
                $categories['practice_content'][] = $this->enrichContentWithPedagogy($item, 'review', $mastery);
            } elseif ($mastery && $mastery['mastery_level'] >= 70) {
                // Enrichment
                $categories['enrichment_content'][] = $this->enrichContentWithPedagogy($item, 'enrichment', $mastery);
            } else {
                // General practice
                $categories['practice_content'][] = $this->enrichContentWithPedagogy($item, 'practice', $mastery);
            }
        }

        // Add misconception content if there are weak concepts
        $weakConcepts = collect($masteryData)->filter(function($c) {
            return ($c['mastery_level'] ?? 0) < 40;
        })->pluck('concept_name')->toArray();

        if (!empty($weakConcepts)) {
            $categories['misconception_content'] = $this->generateMisconceptionContent($weakConcepts);
        }

        return $categories;
    }

    /**
     * Enrich content with pedagogy metadata
     */
    private function enrichContentWithPedagogy($content, $pedagogyType, $masteryData)
    {
        $enriched = (array) $content;
        $enriched['pedagogy_type'] = $pedagogyType;
        $enriched['recommended_pedagogy'] = $this->getPedagogyForType($pedagogyType);
        $enriched['student_level'] = $masteryData ? $this->getLevelFromMastery($masteryData['mastery_level']) : 'medium';
        $enriched['mapping'] = $this->getContentMapping($content->id);
        
        // Add learning style tips
        $enriched['learning_tips'] = $this->getLearningTips($pedagogyType);
        
        return $enriched;
    }

    /**
     * Get pedagogy for type
     */
    private function getPedagogyForType($type)
    {
        $pedagogyMap = [
            'remediation' => [
                'label' => 'Concept Building',
                'method' => 'Scaffolded Learning',
                'approach' => 'Break down complex concepts into smaller parts'
            ],
            'practice' => [
                'label' => 'Active Practice',
                'method' => 'Spaced Repetition',
                'approach' => 'Practice with increasing difficulty'
            ],
            'review' => [
                'label' => 'Review & Reinforcement',
                'method' => 'Retrieval Practice',
                'approach' => 'Active recall of previously learned concepts'
            ],
            'enrichment' => [
                'label' => 'Deep Dive',
                'method' => 'Inquiry-Based Learning',
                'approach' => 'Explore advanced applications and connections'
            ]
        ];

        return $pedagogyMap[$type] ?? $pedagogyMap['practice'];
    }

    /**
     * Get level from mastery
     */
    private function getLevelFromMastery($mastery)
    {
        if ($mastery >= 70) return 'advanced';
        if ($mastery >= 40) return 'intermediate';
        return 'beginner';
    }

    /**
     * Get content mapping
     */
    private function getContentMapping($contentId)
    {
        return DB::table('lms_question_mapping as m')
            ->join('lms_mapping_type as t', 't.id', '=', 'm.mapping_type_id')
            ->join('lms_mapping_type as v', 'v.id', '=', 'm.mapping_value_id')
            ->where('m.questionmaster_id', $contentId)
            ->select('t.name as type_name', 'v.name as value_name')
            ->get()
            ->toArray();
    }

    /**
     * Get learning tips based on pedagogy type
     */
    private function getLearningTips($type)
    {
        $tips = [
            'remediation' => [
                '📖 Start with the basics',
                '✍️ Take notes while reading',
                '🔄 Practice each step before moving on'
            ],
            'practice' => [
                '⏱️ Set a timer for focused practice',
                '📊 Track your progress',
                '🎯 Focus on one concept at a time'
            ],
            'review' => [
                '🧠 Test yourself without looking at notes',
                '📝 Write down what you remember first',
                '🔁 Review multiple times with breaks'
            ],
            'enrichment' => [
                '🔗 Connect to real-world examples',
                '🤔 Ask "what if" questions',
                '📚 Explore additional resources'
            ]
        ];

        return $tips[$type] ?? $tips['practice'];
    }

    /**
     * Generate misconception content
     */
    private function generateMisconceptionContent($weakConcepts)
    {
        $content = [];
        foreach ($weakConcepts as $concept) {
            $content[] = [
                'id' => 'misconception_' . time() . '_' . rand(100, 999),
                'title' => 'Common Misconceptions: ' . $concept,
                'description' => 'Many students struggle with ' . $concept . '. Here are common mistakes to avoid.',
                'content_type' => 'misconception_content',
                'student_level' => 'beginner',
                'mapping' => [
                    ['type_name' => 'Pedagogy', 'value_name' => 'Misconception Clarification']
                ]
            ];
        }
        return $content;
    }

    /**
     * Generate pedagogy recommendations
     */
    private function generatePedagogyRecommendations($profile, $masteryData, $forgettingData, $weakConcepts, $dueConcepts)
    {
        $recommendations = [];

        // 1. Based on learning style
        $learningStyle = $profile->learning_style ?? 'visual';
        $styleTips = [
            'visual' => 'Use diagrams, charts, and videos',
            'auditory' => 'Use audio explanations and discussions',
            'kinesthetic' => 'Use hands-on activities and simulations',
            'reading' => 'Use text-based materials and written exercises'
        ];

        $recommendations[] = [
            'type' => 'learning_style',
            'label' => 'Learning Style: ' . ucfirst($learningStyle),
            'reason' => 'You learn best through ' . $learningStyle . ' content',
            'cognitive_load_display' => 'Low',
            'tip' => $styleTips[$learningStyle] ?? 'Use a mix of content types'
        ];

        // 2. Based on weak concepts
        if (!empty($weakConcepts)) {
            $conceptNames = DB::table('lms_concept')
                ->whereIn('id', $weakConcepts)
                ->pluck('name')
                ->toArray();

            $recommendations[] = [
                'type' => 'focus_area',
                'label' => 'Focus Areas: ' . implode(', ', $conceptNames),
                'reason' => 'These concepts need immediate attention (mastery < 40%)',
                'cognitive_load_display' => 'High',
                'tip' => 'Start with the easiest concept first'
            ];
        }

        // 3. Based on forgetting curve
        if (!empty($dueConcepts)) {
            $conceptNames = DB::table('lms_concept')
                ->whereIn('id', $dueConcepts)
                ->pluck('name')
                ->toArray();

            $recommendations[] = [
                'type' => 'review',
                'label' => 'Review Due: ' . implode(', ', $conceptNames),
                'reason' => 'These concepts are due for review based on the forgetting curve',
                'cognitive_load_display' => 'Medium',
                'tip' => 'Review these before moving to new concepts'
            ];
        }

        // 4. Overall recommendation
        $totalMastery = collect($masteryData)->avg('mastery_level') ?? 0;
        if ($totalMastery >= 70) {
            $recommendations[] = [
                'type' => 'status',
                'label' => 'You\'re on track! 🎉',
                'reason' => 'Overall mastery is ' . round($totalMastery) . '%',
                'cognitive_load_display' => 'Low',
                'tip' => 'Keep up the good work! Try challenging yourself with advanced content.'
            ];
        } elseif ($totalMastery >= 40) {
            $recommendations[] = [
                'type' => 'status',
                'label' => 'Keep practicing! 💪',
                'reason' => 'Overall mastery is ' . round($totalMastery) . '%',
                'cognitive_load_display' => 'Medium',
                'tip' => 'Focus on the concepts that need the most improvement.'
            ];
        } else {
            $recommendations[] = [
                'type' => 'status',
                'label' => 'Let\'s build your foundation 🏗️',
                'reason' => 'Overall mastery is ' . round($totalMastery) . '%',
                'cognitive_load_display' => 'High',
                'tip' => 'Start with the basics and work your way up. You\'ve got this!'
            ];
        }

        return $recommendations;
    }

    /**
     * Get student level
     */
    private function getStudentLevel($masteryData)
    {
        $avgMastery = collect($masteryData)->avg('mastery_level') ?? 0;
        
        if ($avgMastery >= 70) return 'advanced';
        if ($avgMastery >= 40) return 'intermediate';
        return 'beginner';
    }

    /**
     * Store suggested content for student
     */
    public function storeSuggestedContent(Request $request)
    {
        $student_id = $request->session()->get('user_id');
        $content_data = $request->get('content_data');
        $student_level = $request->get('student_level');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $chapter_id = $request->get('chapter_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        if (!$student_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Student not logged in'
            ]);
        }

        // Store content recommendations
        $stored = 0;
        $skipped = 0;

        if ($content_data && is_array($content_data)) {
            foreach ($content_data as $category => $contents) {
                if (!empty($contents)) {
                    foreach ($contents as $content) {
                        // Check if already stored
                        $exists = DB::table('lms_content_recommendations')
                            ->where('student_id', $student_id)
                            ->where('content_id', $content['id'] ?? 0)
                            ->exists();

                        if (!$exists) {
                            DB::table('lms_content_recommendations')->insert([
                                'student_id' => $student_id,
                                'content_id' => $content['id'] ?? 0,
                                'concept_id' => $content['concept_id'] ?? null,
                                'content_type' => $content['content_type'] ?? 'document',
                                'recommendation_reason' => 'Suggested based on pedagogy engine',
                                'priority' => $category === 'remediation_content' ? 3 : 2,
                                'status' => 'pending',
                                'recommended_at' => now(),
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $stored++;
                        } else {
                            $skipped++;
                        }
                    }
                }
            }
        }

        // Update student profile
        if ($student_level) {
            DB::table('lms_student_profile')
                ->where('student_id', $student_id)
                ->update([
                    'learning_pace' => $student_level,
                    'updated_at' => now()
                ]);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Content stored successfully',
            'stored' => $stored,
            'skipped' => $skipped
        ]);
    }
}