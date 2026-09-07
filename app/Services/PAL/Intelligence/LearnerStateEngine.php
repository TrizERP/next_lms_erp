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
        // 'misconceptions' is intentionally not eager-loaded here: active
        // misconceptions come from getActiveMisconceptions() below (a
        // separate, correct query), and Competency::misconceptions() has no
        // real foreign key to eager-load against (pal_learner_misconceptions
        // has no competency_id column -- see Competency::misconceptions()).
        // atFinestGrain, not a bare learner_id filter: pal_competencies holds
        // two grains of the SAME evidence (learner x subject, written live by
        // the quiz path; learner x chapter, written by pal:derive-competencies).
        // Averaging across both double-counts -- learner 282260 read as 85.72%,
        // the meaningless midpoint of a 100% subject row and a 71.43% chapter
        // row. See Competency::scopeAtFinestGrain().
        $competencies = Competency::query()
            ->atFinestGrain($learnerId)
            ->with('concept')
            ->get();

        // No pal_competencies rows means this learner has not been assessed
        // yet -- that is not the same as a measured 0% mastery, and must not
        // be reported as one (a 0 here would read as "knows nothing" instead
        // of "hasn't been tested"). mastery_score/bloom_level stay present
        // (existing consumers already assume they're numbers) but callers
        // that care about the distinction should check has_data first.
        $hasData = $competencies->isNotEmpty();
        $masteryScore = $hasData ? $competencies->avg('mastery_score') : 0;
        $bloomLevel = $this->calculateBloomLevel($competencies);
        $knowledgeGaps = $this->identifyKnowledgeGaps($competencies);
        $misconceptions = $this->getActiveMisconceptions($learnerId);
        $conceptDeps = $this->getConceptDependencies($learnerId);
        $velocity = $this->calculateCompetencyVelocity($learnerId);

        return [
            'has_data' => $hasData,
            'mastery_score' => round($masteryScore, 2),
            'bloom_level' => $bloomLevel,
            'knowledge_gaps' => $knowledgeGaps,
            'active_misconceptions' => $misconceptions,
            'concept_dependencies' => $conceptDeps,
            'learning_velocity' => $velocity,
            'proficiency_trend' => $hasData ? $this->getProficiencyTrend($learnerId) : null,
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
            // has_data alongside the error so callers can rely on the key
            // being present uniformly across every branch, not just the
            // happy path.
            return ['has_data' => false, 'error' => 'Session not found'];
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
            // pal_session_events has no confirmed writer in production (see
            // PredictiveInterventionEngine's same caveat) -- has_data lets a
            // caller tell "this session had zero interaction events" apart
            // from "this student has never been tracked at all".
            'has_data' => $events->isNotEmpty(),
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
            'has_data' => $events->isNotEmpty(),
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
            'has_data' => $collaboration->isNotEmpty(),
            'peer_collaboration_count' => $collaboration->count(),
            'classroom_participation' => $this->getClassroomParticipation($learnerId),
            'discussion_interactions' => $this->getDiscussionInteractions($learnerId),
            'group_activity_performance' => $this->getGroupPerformance($learnerId),
            // pal_collaboration_activities / _classroom_activities / _discussions
            // / _group_activities are all empty with no writer anywhere. These
            // stay null rather than 0 -- "no peer collaboration recorded" is
            // not "this learner does not collaborate".
            'social_learning_engagement' => $collaboration->avg('engagement_score'),
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
            'has_data' => $sessions->isNotEmpty(),
            // No 'desktop' fallback: pal_learning_sessions is empty estate-wide,
            // so that default was reporting a device nobody was measured on.
            'preferred_device' => $sessions->mode('device_type'),
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
            'has_data' => $reflections->isNotEmpty(),
            'reflection_count' => $reflections->count(),
            'reflection_quality' => $reflections->avg('quality_score'),
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
        // Same grain rule as inferCompetency(): mixing the subject-grain and
        // chapter-grain rows would list the same evidence twice and skew
        // overall_mastery.
        $query = Competency::query()
            ->atFinestGrain($learnerId)
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
            'has_data' => $competencies->isNotEmpty(),
            'concepts' => $competencies,
            'overall_mastery' => $competencies->isNotEmpty() ? $competencies->avg('mastery_score') : 0,
            'mastered_concepts' => $competencies->where('status', 'mastered')->count(),
            'learning_concepts' => $competencies->where('status', 'learning')->count(),
            'new_concepts' => $competencies->where('status', 'new')->count(),
        ];
    }

    // Helper methods
    /**
     * Mastery-weighted mean Bloom level, or null when nothing in the set
     * carries a Bloom tag.
     *
     * bloom_level is nullable and 6,043 of 24,003 rows are untagged. Summing
     * them as-is coerced NULL to 0 and dragged the weighted mean below
     * "Remember" for a quarter of the estate; the old `?: 1` floor then made
     * an untagged learner indistinguishable from a genuine Level-1 learner.
     * Untagged rows are skipped entirely instead.
     */
    protected function calculateBloomLevel($competencies): ?int
    {
        $weightedSum = 0.0;
        $weightSum = 0.0;

        foreach ($competencies as $comp) {
            if ($comp->bloom_level === null) {
                continue;
            }

            $weight = $comp->mastery_score / 100;
            $weightedSum += $comp->bloom_level * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? (int) round($weightedSum / $weightSum) : null;
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

    /**
     * Prerequisite edges for the concepts this learner is actually weak on,
     * read from pal_concept_relations.
     *
     * Caveat worth knowing before trusting an empty result: the relations
     * graph and the learner's competency rows currently live in two different
     * concept-id spaces. pal_concept_relations was tagged against the
     * tenant-1 concept set (ids 31-1467, 725 concepts) while
     * pal:derive-competencies seeded its own chapter-grain rows into
     * pal_concepts (ids 26-8677, 547 concepts) -- only 3 ids overlap. So this
     * returns real edges where the graph covers the learner and an empty list
     * otherwise; empty means "no mapped prerequisite", not "no prerequisite".
     * Aligning the two id spaces is a content-tagging job, not a code fix.
     */
    protected function getConceptDependencies(int $learnerId): array
    {
        $weakConceptIds = Competency::query()
            ->atFinestGrain($learnerId)
            ->where('mastery_score', '<', 50)
            ->whereNotNull('concept_id')
            ->pluck('concept_id')
            ->unique()
            ->all();

        if (empty($weakConceptIds)) {
            return [];
        }

        return DB::table('pal_concept_relations as r')
            ->leftJoin('pal_concepts as c', 'c.id', '=', 'r.to_concept_id')
            ->whereIn('r.from_concept_id', $weakConceptIds)
            ->where('r.link_type', 'depends_on')
            ->select([
                'r.from_concept_id as concept_id',
                'r.to_concept_id as depends_on_concept_id',
                'c.name as depends_on_name',
                'r.relation_type',
                'r.mastery_gate',
            ])
            ->limit(50)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Both of these compare the learner's most recent evidence week against
     * the week before it. The windows are anchored to the learner's OWN latest
     * evidence, not to now(): competency rows are backdated to the answers
     * that produced them, so a now()-relative window is empty for ~99.9% of
     * this estate and would report a flat 0 / 'stable' for everyone. See the
     * class docblock on LearningVelocityEngine.
     */
    protected function calculateCompetencyVelocity(int $learnerId): ?float
    {
        [$recent, $older] = $this->masteryWindows($learnerId);

        if ($recent === null || $older === null || $older <= 0) {
            return null;
        }

        return (($recent - $older) / $older) * 100;
    }

    protected function getProficiencyTrend(int $learnerId): ?string
    {
        [$current, $previous] = $this->masteryWindows($learnerId);

        // 'stable' is a claim about two measured windows. With only one window
        // (or none) the trend is unknown, and saying 'stable' there is exactly
        // the kind of confident-looking default that made this screen read as
        // static.
        if ($current === null || $previous === null) {
            return null;
        }

        if ($current > $previous + 5) return 'improving';
        if ($current < $previous - 5) return 'declining';
        return 'stable';
    }

    /**
     * @return array{0: ?float, 1: ?float} [most recent week, preceding week]
     */
    protected function masteryWindows(int $learnerId): array
    {
        $anchor = Competency::evidenceAnchor($learnerId);

        if (!$anchor) {
            return [null, null];
        }

        $recent = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereBetween('updated_at', [$anchor->copy()->subDays(7), $anchor])
            ->avg('mastery_score');

        $older = Competency::query()
            ->atFinestGrain($learnerId)
            ->whereBetween('updated_at', [$anchor->copy()->subDays(14), $anchor->copy()->subDays(7)])
            ->avg('mastery_score');

        return [
            $recent === null ? null : (float) $recent,
            $older === null ? null : (float) $older,
        ];
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

    /**
     * Day-to-day stability of session mastery. Needs at least two distinct
     * days to mean anything -- the old `return 100` reported *perfect*
     * consistency for every learner with fewer, i.e. for the entire estate.
     */
    protected function calculateConsistency(int $learnerId): ?float
    {
        $dailyMasteries = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->selectRaw('DATE(created_at) as date, AVG(mastery_score) as avg')
            ->groupBy('date')
            ->pluck('avg')
            ->toArray();

        if (count($dailyMasteries) < 2) return null;

        $mean = array_sum($dailyMasteries) / count($dailyMasteries);
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $dailyMasteries)) / count($dailyMasteries);
        $stdDev = sqrt($variance);
        
        return max(0, 100 - ($stdDev * 2));
    }

    protected function getAvgSessionDuration(int $learnerId): ?int
    {
        $avg = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->avg('duration_minutes');

        return $avg === null ? null : (int) round($avg);
    }

    /**
     * Accuracy over the learner's most recent week of ANSWERED assessments.
     *
     * Anchored to their own last answer rather than now(): pal_assessment_results
     * runs 2021 -> current term and only ~100 rows estate-wide fall inside a
     * now()-relative week, so the old query returned the `50` fallback for
     * practically every learner. A flat 50 on a confidence gauge is
     * indistinguishable from a real mid-range reading, which is precisely the
     * failure mode this screen had.
     */
    protected function calculateConfidence(int $learnerId): ?float
    {
        $anchor = \App\Models\PAL\AssessmentResult::where('learner_id', $learnerId)
            ->max('created_at');

        if (!$anchor) {
            return null;
        }

        $from = \Carbon\Carbon::parse($anchor)->subDays(7);

        $rows = \App\Models\PAL\AssessmentResult::where('learner_id', $learnerId)
            ->whereBetween('created_at', [$from, $anchor])
            ->selectRaw('COUNT(*) AS total, SUM(is_correct = 1) AS correct')
            ->first();

        $total = (int) ($rows->total ?? 0);

        return $total > 0 ? ((int) $rows->correct / $total) * 100 : null;
    }

    /**
     * Needs pal_session_events, which has 18 rows estate-wide and no
     * production writer on the paths learners actually use -- so this is null
     * for everyone until interaction capture is wired up. Returning the old
     * `50` invented a mid-range persistence score for every learner in the
     * estate.
     */
    protected function calculatePersistence(int $learnerId): ?float
    {
        $counts = \App\Models\PAL\SessionEvent::where('learner_id', $learnerId)
            ->whereIn('event_type', ['retry', 'session_abandon'])
            ->selectRaw("SUM(event_type = 'retry') AS retries, SUM(event_type = 'session_abandon') AS abandons")
            ->first();

        $retryCount = (int) ($counts->retries ?? 0);
        $abandonCount = (int) ($counts->abandons ?? 0);

        if ($retryCount === 0 && $abandonCount === 0) {
            return null;
        }

        return min(100, ($retryCount / ($retryCount + $abandonCount + 1)) * 100);
    }

    /**
     * Session completion ratio. pal_learning_sessions holds 1 row estate-wide
     * (IntelligenceService::processEvent is its only writer and no route calls
     * it), so this is null until session capture exists.
     */
    protected function calculateSelfEfficacy(int $learnerId): ?float
    {
        $sessions = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->selectRaw("COUNT(*) AS started, SUM(status = 'completed') AS completed")
            ->first();

        $started = (int) ($sessions->started ?? 0);

        return $started > 0 ? ((int) $sessions->completed / $started) * 100 : null;
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

    /**
     * Compares the learner's most recent 7 sessions against the 7 before
     * them. Split by session ordinal rather than by calendar week: sessions
     * are sparse and the now()-relative windows this used made both halves
     * empty, which returned a flat 0 ("no decay") for every learner.
     */
    protected function calculateEngagementDecay(int $learnerId): ?float
    {
        $sessions = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->orderByDesc('created_at')
            ->limit(14)
            ->get();

        if ($sessions->count() < 2) {
            return null;
        }

        $recentEngagement = $sessions->take(7)->avg('engagement_score');
        $olderEngagement = $sessions->slice(7)->avg('engagement_score');

        if ($recentEngagement === null || $olderEngagement === null || $olderEngagement <= 0) {
            return null;
        }

        return (($olderEngagement - $recentEngagement) / $olderEngagement) * 100;
    }

    protected function getClassroomParticipation(int $learnerId): ?float
    {
        return \App\Models\PAL\ClassroomActivity::where('learner_id', $learnerId)
            ->avg('participation_score');
    }

    protected function getDiscussionInteractions(int $learnerId): int
    {
        return \App\Models\PAL\Discussion::where('learner_id', $learnerId)->count();
    }

    protected function getGroupPerformance(int $learnerId): ?float
    {
        return \App\Models\PAL\GroupActivity::where('learner_id', $learnerId)
            ->avg('performance_score');
    }

    protected function calculateBandwidthQuality($sessions): ?string
    {
        $slowCount = $sessions->where('load_time_ms', '>', 3000)->count();
        $total = $sessions->count();
        
        if ($total === 0) return null;
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

    /**
     * pal_learner_preferences is empty estate-wide. 'en' was being reported as
     * this learner's *chosen* language for everyone, including learners whose
     * content is delivered in another language.
     */
    protected function getLanguagePreference(int $learnerId): ?string
    {
        $pref = \App\Models\PAL\LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'language')
            ->first();

        return $pref?->pref_value;
    }

    /**
     * There is no rural/urban signal anywhere in this schema -- no locality,
     * region or settlement-type column on any user/profile table. This
     * returned the literal string 'urban' for every learner in the estate,
     * which rendered on the dashboard identically to a measured value and is
     * the single most misleading field this screen had. It stays null until a
     * real source exists.
     */
    protected function getRuralUrbanContext(int $learnerId): ?string
    {
        return null;
    }

    protected function calculateSelfCorrection(int $learnerId): ?float
    {
        $corrections = \App\Models\PAL\SelfCorrection::where('learner_id', $learnerId)->get();

        if ($corrections->isEmpty()) return null;

        return ($corrections->where('successful', true)->count() / $corrections->count()) * 100;
    }

    /**
     * pal_learning_plans and pal_strategy_selections are both empty estate-wide
     * with no writer anywhere in the codebase. The old versions returned a
     * two-valued constant (70/30 and 80/40) that looked like a score on a
     * 0-100 gauge -- every learner in the estate scored the "absent" arm, 30
     * and 40, which reads as a measured weakness rather than as no data.
     */
    protected function getPlanningBehavior(int $learnerId): ?float
    {
        $plans = \App\Models\PAL\LearningPlan::where('learner_id', $learnerId)->count();

        return $plans > 0 ? min(100, $plans * 20) : null;
    }

    protected function getStrategyAwareness(int $learnerId): ?float
    {
        $distinct = \App\Models\PAL\StrategySelection::where('learner_id', $learnerId)
            ->distinct()
            ->count('strategy_type');

        return $distinct > 0 ? min(100, $distinct * 20) : null;
    }

    protected function getJournalEngagement(int $learnerId): int
    {
        return \App\Models\PAL\LearningJournal::where('learner_id', $learnerId)->count();
    }
}