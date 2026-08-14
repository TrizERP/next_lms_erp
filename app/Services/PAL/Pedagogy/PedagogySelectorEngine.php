<?php

namespace App\Services\PAL\Pedagogy;

use App\Models\PAL\LearnerPreference;
use App\Models\PAL\PedagogyEffectiveness;
use App\Services\PAL\Framework\FrameworkCatalogService;

/**
 * Pedagogy Selector Engine
 * Five-tier pedagogy selection based on learner state and concept requirements
 */
class PedagogySelectorEngine
{
    public function __construct(
        private readonly FrameworkCatalogService $catalog
    ) {
    }

    /**
     * Select best pedagogy using 5-tier process
     * @param int $learnerId
     * @param int $conceptId
     * @param array $context
     * @return array
     */
    public function select(int $learnerId, int $conceptId, array $context = []): array
    {
        if (!empty($context['teacher_override'])) {
            return $this->buildCandidate(
                $this->catalog->normalizePedagogy((string) $context['teacher_override']),
                100,
                'Teacher override'
            );
        }

        // Tier 1 - Hard Constraints
        $constraints = $this->checkConstraints($learnerId, $context);
        if ($constraints['blocked']) {
            return $constraints['fallback'];
        }

        // Tier 2 - Learner State
        $learnerState = $this->getLearnerState($learnerId);

        // Tier 3 - Concept Requirements
        $conceptReqs = $this->getConceptRequirements($conceptId);

        // Tier 4 - Historical Effectiveness
        $historical = $this->getHistoricalEffectiveness($learnerId);

        // Tier 5 - Novelty & Fatigue
        $novelty = $this->checkNovelty($learnerId);

        if (($context['type'] ?? null) === 'spaced_review' || !empty($context['spaced_review'])) {
            return $this->buildCandidate('flashcard', 98, 'Spaced review due');
        }

        if (($learnerState['declining_engagement'] ?? false) === true) {
            return $this->buildCandidate('game_based', 92, 'Engagement is declining');
        }

        if (($context['new_concept'] ?? false) === true) {
            return $this->buildCandidate(
                $conceptReqs['recommended_pedagogy'] ?? 'inquiry_based',
                88,
                'New concept default'
            );
        }

        // Combine all factors
        return $this->combineSelections($learnerState, $conceptReqs, $historical, $novelty);
    }

    /**
     * Get alternative pedagogies
     * @param int $conceptId
     * @return array
     */
    public function getAlternatives(int $conceptId): array
    {
        $concept = \App\Models\PAL\Concept::find($conceptId);
        
        if (!$concept) {
            return $this->getDefaultAlternatives();
        }

        $tags = $concept->pedagogy_tags ?? ['inquiry_based'];
        $alternatives = [];

        foreach ($tags as $tag) {
            $normalized = $this->catalog->normalizePedagogy((string) $tag);
            if ($normalized !== 'primary') {
                $alternatives[] = $this->buildCandidate($normalized, 60, 'Available for concept');
            }
        }

        return array_merge($alternatives, $this->getDefaultAlternatives());
    }

    /**
     * Get reason for selection
     * @param int $learnerId
     * @param string $pedagogyType
     * @return string
     */
    public function getReason(int $learnerId, string $pedagogyType): string
    {
        $learnerState = $this->getLearnerState($learnerId);
        $pedagogyType = $this->catalog->normalizePedagogy($pedagogyType);
        
        return match ($pedagogyType) {
            'inquiry_based' => $learnerState['high_mastery'] ?? false
                ? 'Ready for exploration'
                : 'Strong default for first exposure',
            'experiential' => 'Connects learning to real experience',
            'art_integrated' => $learnerState['visual_learner'] ?? false
                ? 'Matches creative and visual learning preference'
                : 'Supports conceptual expression',
            'game_based' => 'Used as a motivational intervention',
            'activity_based' => 'Builds understanding through concrete practice',
            'project_based' => 'Supports deeper application and collaboration',
            'flashcard' => 'Targets review and fluency',
            'flipped_classroom' => 'Supports pre-learning and guided application',
            'scenario_based' => 'Supports contextual decision-making',
            'spiritual_science' => 'Builds reflection, wellbeing, and awareness',
            'competency_based' => 'Requires demonstrated performance',
            'concept_sports' => 'Transfers learning through sports contexts',
            default => 'Standard delivery',
        };
    }

    /**
     * Record outcome for tracking
     * @param int $learnerId
     * @param string $pedagogyType
     * @param string $outcome
     * @return void
     */
    public function recordOutcome(int $learnerId, string $pedagogyType, string $outcome, array $context = []): PedagogyEffectiveness
    {
        $pedagogyType = $this->catalog->normalizePedagogy($pedagogyType);

        return PedagogyEffectiveness::create([
            'learner_id' => $learnerId,
            'pedagogy_type' => $pedagogyType,
            'outcome' => $outcome,
            'effectiveness_score' => $context['effectiveness_score'] ?? ($outcome === 'success' ? 100 : ($outcome === 'partial' ? 50 : 0)),
            'concept_id' => $context['concept_id'] ?? null,
            'session_id' => $context['session_id'] ?? null,
            'content_id' => $context['content_id'] ?? null,
            'context_data' => $context['context_data'] ?? $context,
        ]);
    }

    protected function checkConstraints(int $learnerId, array $context): array
    {
        // Check device capability
        if (($context['device_type'] ?? 'desktop') === 'mobile') {
            return ['blocked' => false, 'fallback' => null]; 
        }

        // Check language availability
        $pref = LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'language')
            ->first();

        if ($pref && !in_array($pref->pref_value, ['en', $context['available_languages'] ?? ['en']])) {
            return [
                'blocked' => true,
                'fallback' => $this->buildCandidate('inquiry_based', 40, 'Language constraint')
            ];
        }

        // Check accessibility
        $access = LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'accessibility')
            ->first();

        if ($access && $access->pref_value === 'screen_reader') {
            return [
                'blocked' => true,
                'fallback' => $this->buildCandidate('experiential', 40, 'Accessibility requirement')
            ];
        }

        return ['blocked' => false, 'fallback' => null];
    }

    protected function getLearnerState(int $learnerId): array
    {
        $competency = \App\Models\PAL\Competency::where('learner_id', $learnerId)
            ->avg('mastery_score') ?? 0;

        $preference = LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'learning_style')
            ->first();

        $recentFails = \App\Models\PAL\AssessmentResult::where('learner_id', $learnerId)
            ->where('is_correct', false)
            ->where('created_at', '>=', now()->subDays(3))
            ->count();
        $recentSessions = \App\Models\PAL\LearningSession::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('created_at')
            ->limit(6)
            ->pluck('engagement_score')
            ->toArray();
        $recentAvg = collect(array_slice($recentSessions, 0, 3))->avg() ?? 0;
        $olderAvg = collect(array_slice($recentSessions, 3, 3))->avg() ?? 0;

        return [
            'mastery_score' => $competency,
            'high_mastery' => $competency > 75,
            'medium_mastery' => $competency >= 50 && $competency <= 75,
            'low_mastery' => $competency < 50,
            'low_confidence' => $competency < 40,
            'low_self_efficacy' => $recentFails > 5,
            'has_gaps' => \App\Models\PAL\Competency::where('learner_id', $learnerId)
                ->where('mastery_score', '<', 50)->exists(),
            'visual_learner' => $preference?->pref_value === 'visual',
            'auditory_learner' => $preference?->pref_value === 'auditory',
            'kinesthetic_learner' => $preference?->pref_value === 'kinesthetic',
            'declining_engagement' => $olderAvg > 0 && $recentAvg < $olderAvg,
        ];
    }

    protected function getConceptRequirements(int $conceptId): array
    {
        $concept = \App\Models\PAL\Concept::find($conceptId);
        
        if (!$concept) {
            return ['default' => 'inquiry_based', 'recommended_pedagogy' => 'inquiry_based', 'available_pedagogies' => ['inquiry_based']];
        }

        return [
            'abstractness' => $concept->abstractness_level ?? 1,
            'visual_dependency' => $concept->requires_visual ?? false,
            'manipulation_need' => $concept->requires_manipulation ?? false,
            'simulation_required' => $concept->requires_simulation ?? false,
            'recommended_pedagogy' => $this->catalog->normalizePedagogy($concept->recommended_pedagogy ?? 'inquiry_based'),
            'available_pedagogies' => array_values(array_unique(array_map(
                fn ($tag) => $this->catalog->normalizePedagogy((string) $tag),
                (array) ($concept->pedagogy_tags ?? ['inquiry_based'])
            ))),
            'bloom_level' => $concept->bloom_level ?? 1,
        ];
    }

    protected function getHistoricalEffectiveness(int $learnerId): array
    {
        $history = PedagogyEffectiveness::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(14))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $history->groupBy('pedagogy_type')
            ->mapWithKeys(fn($group, $type) => [$this->catalog->normalizePedagogy((string) $type) => $group->avg('effectiveness_score')])
            ->toArray();
    }

    protected function checkNovelty(int $learnerId): array
    {
        $recent = PedagogyEffectiveness::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(3))
            ->pluck('pedagogy_type')
            ->map(fn ($type) => $this->catalog->normalizePedagogy((string) $type))
            ->toArray();

        $counts = array_count_values($recent);
        $overused = array_filter($counts, fn($c) => $c >= 3);

        return [
            'recent_pedagogies' => $recent,
            'overused_pedagogies' => array_keys($overused),
            'should_rotate' => count($overused) > 0,
        ];
    }

    protected function combineSelections(array $learnerState, array $conceptReqs, array $historical, array $novelty): array
    {
        $candidates = $this->generateCandidates($learnerState, $conceptReqs);

        // Score each candidate
        foreach ($candidates as &$candidate) {
            $score = 50; // Base score

            // Boost from historical effectiveness
            $type = $candidate['type'];
            if (isset($historical[$type])) {
                $score += ($historical[$type] - 50) * 0.4;
            }

            // Penalize for overuse
            if (in_array($type, $novelty['overused_pedagogies'] ?? [])) {
                $score -= 20;
            }

            // Boost from concept recommendation
            if ($type === ($conceptReqs['recommended_pedagogy'])) {
                $score += 15;
            }

            $candidate['score'] = round($score);
        }

        // Sort by score
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0] ?? $this->buildCandidate('inquiry_based', 50, 'Default fallback');
    }

    protected function generateCandidates(array $learnerState, array $conceptReqs): array
    {
        $candidates = [];

        foreach (($conceptReqs['available_pedagogies'] ?? ['inquiry_based']) as $tag) {
            $candidates[] = ['type' => $this->catalog->normalizePedagogy($tag), 'base_score' => 30];
        }

        if ($conceptReqs['bloom_level'] >= 2) {
            $candidates[] = ['type' => 'inquiry_based', 'base_score' => 25];
        }

        $candidates[] = ['type' => 'activity_based', 'base_score' => 25];

        if ($conceptReqs['visual_dependency'] ?? false) {
            $candidates[] = ['type' => 'art_integrated', 'base_score' => 30];
        }

        if ($learnerState['low_confidence'] ?? false) {
            $candidates[] = ['type' => 'scenario_based', 'base_score' => 25];
            $candidates[] = ['type' => 'game_based', 'base_score' => 20];
        }

        if ($conceptReqs['simulation_required'] ?? false) {
            $candidates[] = ['type' => 'scenario_based', 'base_score' => 25];
        }

        if (!empty($conceptReqs['manipulation_need'])) {
            $candidates[] = ['type' => 'experiential', 'base_score' => 20];
            $candidates[] = ['type' => 'concept_sports', 'base_score' => 18];
        }

        return collect($candidates)
            ->unique('type')
            ->values()
            ->all();
    }

    protected function getDefaultAlternatives(): array
    {
        return [
            $this->buildCandidate('inquiry_based', 50, 'Default fallback'),
            $this->buildCandidate('activity_based', 45, 'Active reinforcement'),
        ];
    }

    protected function buildCandidate(string $type, int|float $score, string $reason): array
    {
        $normalized = $this->catalog->normalizePedagogy($type);

        return [
            'type' => $normalized,
            'score' => round((float) $score),
            'reason' => $reason,
            'h5p_types' => $this->catalog->allowedH5pForPedagogy($normalized),
            'coverage' => $this->catalog->pedagogyCoverage($normalized),
        ];
    }
}
