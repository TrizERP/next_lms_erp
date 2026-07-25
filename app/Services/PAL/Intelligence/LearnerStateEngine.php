<?php

namespace App\Services\PAL\Intelligence;

use App\Models\PAL\LearnerState;
use App\Models\PAL\Competency;
use App\Models\PAL\LearningPattern;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Learner State Inference Engine
 * Continuously maintains real-time learner state vector across 6 dimensions
 */
class LearnerStateEngine
{
    /**
     * Get complete learner state
     * @param int $learnerId
     * @param int|null $sessionId
     * @return array
     */
    public function getState(int $learnerId, ?int $sessionId = null): array
    {
        return [
            'competency' => $this->inferCompetency($learnerId),
            'behavioral' => $sessionId ? $this->inferBehavior($learnerId, $sessionId) : null,
            'motivational' => $sessionId ? $this->inferMotivation($learnerId, $sessionId) : null,
            'social' => $this->inferSocial($learnerId),
            'contextual' => $this->inferContextual($learnerId),
            'metacognition' => $this->inferMetacognition($learnerId),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Infer competency dimension
     * Tracks: mastery, Bloom level, knowledge gaps, misconceptions, concept dependencies, learning velocity
     * @param int $learnerId
     * @return array
     */
    public function inferCompetency(int $learnerId): array
    {
        $competencies = Competency::where('learner_id', $learnerId)
            ->with(['concept', 'misconceptions'])
            ->get();

        $masteryScore = $competencies->avg('mastery_score') ?? 0;
        $bloomLevel = $this->calculateBloomLevel($competencies);
        $knowledgeGaps = $this->identifyKnowledgeGaps($competencies);
        $misconceptions = $this->getActiveMisconceptions($learnerId);
        $conceptDeps = $this->getConceptDependencies($learnerId);
        $velocity = $this->calculateCompetencyVelocity($learnerId);

        return [
            'mastery_score' => round($masteryScore, 2),
            'bloom_level' => $bloomLevel,
            'knowledge_gaps' => $knowledgeGaps,
            'active_misconceptions' => $misconceptions,
            'concept_dependencies' => $conceptDeps,
            'learning_velocity' => $velocity,
            'proficiency_trend' => $this->getProficiencyTrend($learnerId),
        ];
    }

    /**
     * Infer behavioral dimension
     * Tracks: session frequency, time-on-task, rewatch behavior, hint usage, drop-off points, interaction depth
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function inferBehavior(int $learnerId, int $sessionId): array
    {
        $session = \App\Models\PAL\LearningSession::find($sessionId);
        
        if (!$session || (int) $session->learner_id !== $learnerId) {
            return ['error' => 'Session not found'];
        }

        $events = \App\Models\PAL\SessionEvent::where('session_id', $sessionId)
            ->where('learner_id', $learnerId)
            ->get();

        $timeOnTask = $events->sum('duration_seconds') ?? 0;
        $rewatchCount = $events->where('event_type', 'video_replayed')->count();
        $hintUsage = $events->where('event_type', 'hint_opened')->count();
        $dropOffPoints = $this->identifyDropOffPoints($events);
        $interactionDepth = $this->calculateInteractionDepth($events);
        $consistency = $this->calculateConsistency($learnerId);

        return [
            'session_frequency' => $this->getSessionFrequency($learnerId),
            'time_on_task' => $timeOnTask,
            'rewatch_behavior' => $rewatchCount,
            'hint_usage' => $hintUsage,
            'drop_off_points' => $dropOffPoints,
            'interaction_depth' => $interactionDepth,
            'consistency_patterns' => $consistency,
            'avg_session_duration' => $this->getAvgSessionDuration($learnerId),
        ];
    }

    /**
     * Infer motivational dimension
     * Tracks: confidence level, frustration indicators, persistence, self-efficacy, engagement decay
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function inferMotivation(int $learnerId, $sessionId): array
    {
        $events = \App\Models\PAL\SessionEvent::where('session_id', $sessionId)
            ->where('learner_id', $learnerId)
            ->get();

        $retryBehavior = $events->where('event_type', 'retry')->count();
        $rageClicks = $events->where('event_type', 'rage_click')->count();
        $rapidGuessing = $events->where('event_type', 'rapid_guessing')->count();
        $sessionAbandonment = $events->where('event_type', 'inactivity')->count() > 2;
        $feedbackCalls = $events->where('event_type', 'feedback_opened')->count();

        $confidence = $this->calculateConfidence($learnerId);
        $persistence = $this->calculatePersistence($learnerId);
        $selfEfficacy = $this->calculateSelfEfficacy($learnerId);
        $frustrationIndicators = $this->detectFrustrationIndicators($events);
        $engagementDecay = $this->calculateEngagementDecay($learnerId);

        return [
            'confidence_level' => $confidence,
            'persistence_score' => $persistence,
            'self_efficacy' => $selfEfficacy,
            'frustration_indicators' => $frustrationIndicators,
            'engagement_decay' => $engagementDecay,
            'retry_count' => $retryBehavior,
            'rage_clicks' => $rageClicks,
            'rapid_guessing' => $rapidGuessing,
            'session_abandonment' => $sessionAbandonment,
            'feedback_engagement' => $feedbackCalls,
        ];
    }

    /**
     * Infer social dimension
     * Tracks: peer collaboration, classroom participation, discussion interactions
     * @param int $learnerId
     * @return array
     */
    public function inferSocial(int $learnerId): array
    {
        $collaboration = \App\Models\PAL\CollaborationActivity::where('learner_id', $learnerId)->get();
        
        return [
            'peer_collaboration_count' => $collaboration->count(),
            'classroom_participation' => $this->getClassroomParticipation($learnerId),
            'discussion_interactions' => $this->getDiscussionInteractions($learnerId),
            'group_activity_performance' => $this->getGroupPerformance($learnerId),
            'social_learning_engagement' => $collaboration->avg('engagement_score') ?? 0,
        ];
    }

    /**
     * Infer contextual dimension
     * Tracks: device type, bandwidth, time-of-day performance, language preference
     * @param int $learnerId
     * @return array
     */
    public function inferContextual(int $learnerId): array
    {
        $sessions = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return [
            'preferred_device' => $sessions->mode('device_type') ?? 'desktop',
            'bandwidth_quality' => $this->calculateBandwidthQuality($sessions),
            'time_of_day_pattern' => $this->getTimeOfDayPattern($sessions),
            'language_preference' => $this->getLanguagePreference($learnerId),
            'rural_urban_context' => $this->getRuralUrbanContext($learnerId),
        ];
    }

    /**
     * Infer metacognition dimension
     * Tracks: reflection quality, self-correction ability, planning behavior, learning strategy awareness
     * @param int $learnerId
     * @return array
     */
    public function inferMetacognition(int $learnerId): array
    {
        $reflections = \App\Models\PAL\Reflection::where('learner_id', $learnerId)->get();

        return [
            'reflection_count' => $reflections->count(),
            'reflection_quality' => $reflections->avg('quality_score') ?? 0,
            'self_correction_ability' => $this->calculateSelfCorrection($learnerId),
            'planning_behavior' => $this->getPlanningBehavior($learnerId),
            'strategy_awareness' => $this->getStrategyAwareness($learnerId),
            'learning_journal_engagement' => $this->getJournalEngagement($learnerId),
        ];
    }

    /**
     * Infer recommended pedagogy based on learner state
     * @param int $learnerId
     * @return array
     */
    public function inferRecommendedPedagogy(int $learnerId): array
    {
        $competency = $this->inferCompetency($learnerId);
        $motivation = $this->inferMotivation($learnerId, request()->get('session_id', 0));
        $metacognition = $this->inferMetacognition($learnerId);

        $pedagogies = [];

        // High mastery + high confidence = accelerated pathways
        if ($competency['mastery_score'] > 75 && $motivation['confidence_level'] > 70) {
            $pedagogies[] = ['type' => 'concept-based', 'priority' => 1, 'reason' => 'High mastery, ready for advanced concepts'];
        }

        // Low mastery + low confidence = scaffolded pedagogy
        if ($competency['mastery_score'] < 50 || $motivation['confidence_level'] < 40) {
            $pedagogies[] = ['type' => 'visual-learning', 'priority' => 1, 'reason' => 'Needs visual reinforcement'];
            $pedagogies[] = ['type' => 'story-based', 'priority' => 2, 'reason' => 'Engagement through narrative'];
        }

        // Medium mastery = practice-based
        if ($competency['mastery_score'] >= 50 && $competency['mastery_score'] <= 75) {
            $pedagogies[] = ['type' => 'practice-based', 'priority' => 1, 'reason' => 'Strengthening through practice'];
            $pedagogies[] = ['type' => 'inquiry-based', 'priority' => 2, 'reason' => 'Active exploration'];
        }

        // Knowledge gaps present = concept-based with remediation
        if (count($competency['knowledge_gaps']) > 0) {
            $pedagogies[] = ['type' => 'concept-based', 'priority' => 1, 'reason' => 'Address knowledge gaps'];
        }

        // Active misconceptions = targeted remediation
        if (count($competency['active_misconceptions']) > 0) {
            $pedagogies[] = ['type' => 'remediation', 'priority' => 1, 'reason' => 'Misconceptions need correction'];
        }

        // Low self-efficacy = encouraging approach
        if ($motivation['self_efficacy'] < 40) {
            $pedagogies[] = ['type' => 'socratic', 'priority' => 2, 'reason' => 'Build confidence through questioning'];
        }

        // High metacognition = reflective learning
        if ($metacognition['reflection_quality'] > 60) {
            $pedagogies[] = ['type' => 'reflective', 'priority' => 2, 'reason' => 'Ready for metacognitive learning'];
        }

        // Sort by priority
        usort($pedagogies, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return [
            'recommended_pedagogies' => array_values($pedagogies),
            'state_summary' => [
                'mastery' => $competency['mastery_score'],
                'confidence' => $motivation['confidence_level'],
                'self_efficacy' => $motivation['self_efficacy'],
            ],
        ];
    }

    /**
     * Get mastery map for learner
     * @param int $learnerId
     * @param int|null $subjectId
     * @return array
     */
    public function getMasteryMap(int $learnerId, ?int $subjectId = null): array
    {
        $query = Competency::where('learner_id', $learnerId)
            ->with('concept');

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $competencies = $query->get()->map(function ($comp) {
            return [
                'concept_id' => $comp->concept_id,
                'concept_name' => $comp->concept?->name,
                'mastery_score' => $comp->mastery_score,
                'bloom_level' => $comp->bloom_level,
                'last_assessed' => $comp->updated_at?->toIso8601String(),
                'status' => $comp->getMasteryStatus(),
            ];
        });

        return [
            'learner_id' => $learnerId,
            'subject_id' => $subjectId,
            'concepts' => $competencies,
            'overall_mastery' => $competencies->avg('mastery_score') ?? 0,
            'mastered_concepts' => $competencies->where('status', 'mastered')->count(),
            'learning_concepts' => $competencies->where('status', 'learning')->count(),
            'new_concepts' => $competencies->where('status', 'new')->count(),
        ];
    }

    // Helper methods
    protected function calculateBloomLevel($competencies): int
    {
        $weightedSum = 0;
        $weightSum = 0;
        
        foreach ($competencies as $comp) {
            $weight = $comp->mastery_score / 100;
            $weightedSum += $comp->bloom_level * $weight;
            $weightSum += $weight;
        }
        
        return $weightSum > 0 ? round($weightedSum / $weightSum) : 1;
    }

    protected function identifyKnowledgeGaps($competencies): array
    {
        return $competencies
            ->where('mastery_score', '<', 50)
            ->pluck('concept_id')
            ->toArray();
    }

    protected function getActiveMisconceptions(int $learnerId): array
    {
        return \App\Models\PAL\LearnerMisconception::where('learner_id', $learnerId)
            ->where('status', 'active')
            ->with('misconception')
            ->get()
            ->map(fn($m) => [
                'id' => $m->misconception_id,
                'pattern' => $m->misconception?->pattern,
                'severity' => $m->severity,
            ])
            ->toArray();
    }

    protected function getConceptDependencies(int $learnerId): array
    {
        // Get from Neo4j or cache - placeholder
        return [];
    }

    protected function calculateCompetencyVelocity(int $learnerId): float
    {
        $recent = Competency::where('learner_id', $learnerId)
            ->where('updated_at', '>=', now()->subDays(7))
            ->avg('mastery_score') ?? 0;
        
        $older = Competency::where('learner_id', $learnerId)
            ->whereBetween('updated_at', [now()->subDays(14), now()->subDays(7)])
            ->avg('mastery_score') ?? 0;
        
        return $older > 0 ? (($recent - $older) / $older) * 100 : 0;
    }

    protected function getProficiencyTrend(int $learnerId): string
    {
        $current = Competency::where('learner_id', $learnerId)
            ->where('updated_at', '>=', now()->subDays(7))
            ->avg('mastery_score') ?? 0;
        
        $previous = Competency::where('learner_id', $learnerId)
            ->whereBetween('updated_at', [now()->subDays(14), now()->subDays(7)])
            ->avg('mastery_score') ?? 0;
        
        if ($current > $previous + 5) return 'improving';
        if ($current < $previous - 5) return 'declining';
        return 'stable';
    }

    protected function getSessionFrequency(int $learnerId): int
    {
        return \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    protected function identifyDropOffPoints($events): array
    {
        return $events->where('event_type', 'section_exit')
            ->pluck('timestamp')
            ->toArray();
    }

    protected function calculateInteractionDepth($events): int
    {
        $uniqueTypes = $events->pluck('event_type')->unique()->count();
        return min($uniqueTypes * 10, 100);
    }

    protected function calculateConsistency(int $learnerId): float
    {
        $dailyMasteries = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, AVG(mastery_score) as avg')
            ->groupBy('date')
            ->pluck('avg')
            ->toArray();
        
        if (count($dailyMasteries) < 2) return 100;
        
        $mean = array_sum($dailyMasteries) / count($dailyMasteries);
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $dailyMasteries)) / count($dailyMasteries);
        $stdDev = sqrt($variance);
        
        return max(0, 100 - ($stdDev * 2));
    }

    protected function getAvgSessionDuration(int $learnerId): int
    {
        return \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->avg('duration_minutes') ?? 0;
    }

    protected function calculateConfidence(int $learnerId): float
    {
        $correct = \App\Models\PAL\AssessmentResult::where('learner_id', $learnerId)
            ->where('is_correct', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        
        $total = \App\Models\PAL\AssessmentResult::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        
        return $total > 0 ? ($correct / $total) * 100 : 50;
    }

    protected function calculatePersistence(int $learnerId): float
    {
        $retryCount = \App\Models\PAL\SessionEvent::where('learner_id', $learnerId)
            ->where('event_type', 'retry')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        
        $abandonCount = \App\Models\PAL\SessionEvent::where('learner_id', $learnerId)
            ->where('event_type', 'session_abandon')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        
        return $retryCount > 0 ? min(100, ($retryCount / ($retryCount + $abandonCount + 1)) * 100) : 50;
    }

    protected function calculateSelfEfficacy(int $learnerId): float
    {
        $completed = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(14))
            ->count();
        
        $started = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->count();
        
        return $started > 0 ? ($completed / $started) * 100 : 50;
    }

    protected function detectFrustrationIndicators($events): array
    {
        $indicators = [];
        
        if ($events->where('event_type', 'rage_click')->count() > 3) {
            $indicators[] = 'rage_clicks';
        }
        
        if ($events->where('event_type', 'rapid_guessing')->count() > 5) {
            $indicators[] = 'rapid_guessing';
        }
        
        if ($events->where('event_type', 'hint_abused')->count() > 2) {
            $indicators[] = 'hint_abuse';
        }
        
        return $indicators;
    }

    protected function calculateEngagementDecay(int $learnerId): float
    {
        $recentSessions = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderBy('created_at')
            ->limit(7)
            ->get();
        
        $olderSessions = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '<', now()->subDays(7))
            ->where('created_at', '>=', now()->subDays(14))
            ->orderBy('created_at')
            ->limit(7)
            ->get();
        
        if ($recentSessions->isEmpty() || $olderSessions->isEmpty()) return 0;
        
        $recentEngagement = $recentSessions->avg('engagement_score') ?? 0;
        $olderEngagement = $olderSessions->avg('engagement_score') ?? 0;
        
        return $olderEngagement > 0 ? (($olderEngagement - $recentEngagement) / $olderEngagement) * 100 : 0;
    }

    protected function getClassroomParticipation(int $learnerId): float
    {
        return \App\Models\PAL\ClassroomActivity::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->avg('participation_score') ?? 0;
    }

    protected function getDiscussionInteractions(int $learnerId): int
    {
        return \App\Models\PAL\Discussion::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    protected function getGroupPerformance(int $learnerId): float
    {
        return \App\Models\PAL\GroupActivity::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->avg('performance_score') ?? 0;
    }

    protected function calculateBandwidthQuality($sessions): string
    {
        $slowCount = $sessions->where('load_time_ms', '>', 3000)->count();
        $total = $sessions->count();
        
        if ($total === 0) return 'unknown';
        $ratio = $slowCount / $total;
        
        if ($ratio > 0.5) return 'poor';
        if ($ratio > 0.2) return 'fair';
        return 'good';
    }

    protected function getTimeOfDayPattern($sessions): array
    {
        return $sessions->groupBy(fn($s) => $s->created_at->format('H'))
            ->map(fn($group) => $group->avg('performance_score'))
            ->toArray();
    }

    protected function getLanguagePreference(int $learnerId): string
    {
        $pref = \App\Models\PAL\LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'language')
            ->first();
        
        return $pref?->pref_value ?? 'en';
    }

    protected function getRuralUrbanContext(int $learnerId): string
    {
        return 'urban'; // Placeholder - would come from user profile
    }

    protected function calculateSelfCorrection(int $learnerId): float
    {
        $corrections = \App\Models\PAL\SelfCorrection::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->get();
        
        if ($corrections->isEmpty()) return 0;
        
        return ($corrections->where('successful', true)->count() / $corrections->count()) * 100;
    }

    protected function getPlanningBehavior(int $learnerId): float
    {
        return \App\Models\PAL\LearningPlan::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count() > 0 ? 70 : 30;
    }

    protected function getStrategyAwareness(int $learnerId): float
    {
        return \App\Models\PAL\StrategySelection::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->distinct()
            ->count('strategy_type') > 2 ? 80 : 40;
    }

    protected function getJournalEngagement(int $learnerId): int
    {
        return \App\Models\PAL\LearningJournal::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }
}