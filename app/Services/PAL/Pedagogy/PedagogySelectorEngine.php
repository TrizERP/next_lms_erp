<?php

namespace App\Services\PAL\Pedagogy;

use App\Models\PAL\LearnerPreference;
use App\Models\PAL\PedagogyEffectiveness;

/**
 * Pedagogy Selector Engine
 * Five-tier pedagogy selection based on learner state and concept requirements
 */
class PedagogySelectorEngine
{
    /**
     * Select best pedagogy using 5-tier process
     * @param int $learnerId
     * @param int $conceptId
     * @param array $context
     * @return array
     */
    public function select(int $learnerId, int $conceptId, array $context = []): array
    {
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

        $tags = $concept->pedagogy_tags ?? ['concept-based'];
        $alternatives = [];

        foreach ($tags as $tag) {
            if ($tag !== 'primary') {
                $alternatives[] = ['type' => $tag, 'reason' => "Available for concept"];
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
        
        return match ($pedagogyType) {
            'concept-based' => $learnerState['has_gaps'] ?? false 
                ? 'Addresses knowledge gaps' 
                : 'Foundation building',
            'inquiry-based' => $learnerState['high_mastery'] ?? false 
                ? 'Ready for exploration' 
                : 'Promotes engagement',
            'practice-based' => $learnerState['medium_mastery'] ?? false 
                ? 'Strengthens through practice' 
                : 'Active reinforcement',
            'visual-learning' => $learnerState['visual_learner'] ?? false 
                ? 'Matches learning preference' 
                : 'Clarifies complex concepts',
            'story-based' => $learnerState['low_confidence'] ?? false 
                ? 'Builds engagement' 
                : 'Enhances retention',
            'socratic' => $learnerState['low_self_efficacy'] ?? false 
                ? 'Builds confidence' 
                : 'Deepens understanding',
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
                'fallback' => ['type' => 'concept-based', 'reason' => 'Language constraint']
            ];
        }

        // Check accessibility
        $access = LearnerPreference::where('learner_id', $learnerId)
            ->where('pref_key', 'accessibility')
            ->first();

        if ($access && $access->pref_value === 'screen_reader') {
            return [
                'blocked' => true,
                'fallback' => ['type' => 'audio', 'reason' => 'Accessibility requirement']
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
        ];
    }

    protected function getConceptRequirements(int $conceptId): array
    {
        $concept = \App\Models\PAL\Concept::find($conceptId);
        
        if (!$concept) {
            return ['default' => 'concept-based'];
        }

        return [
            'abstractness' => $concept->abstractness_level ?? 1,
            'visual_dependency' => $concept->requires_visual ?? false,
            'manipulation_need' => $concept->requires_manipulation ?? false,
            'simulation_required' => $concept->requires_simulation ?? false,
            'recommended_pedagogy' => $concept->recommended_pedagogy ?? 'concept-based',
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
            ->map(fn($group) => $group->avg('effectiveness_score'))
            ->toArray();
    }

    protected function checkNovelty(int $learnerId): array
    {
        $recent = PedagogyEffectiveness::where('learner_id', $learnerId)
            ->where('created_at', '>=', now()->subDays(3))
            ->pluck('pedagogy_type')
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

        return $candidates[0] ?? ['type' => 'concept-based', 'score' => 50];
    }

    protected function generateCandidates(array $learnerState, array $conceptReqs): array
    {
        $candidates = [];

        // Always available
        $candidates[] = ['type' => 'concept-based', 'base_score' => 30];

        if ($conceptReqs['bloom_level'] >= 2) {
            $candidates[] = ['type' => 'inquiry-based', 'base_score' => 25];
        }

        $candidates[] = ['type' => 'practice-based', 'base_score' => 25];

        if ($conceptReqs['visual_dependency'] ?? false) {
            $candidates[] = ['type' => 'visual-learning', 'base_score' => 30];
        }

        if ($learnerState['low_confidence'] ?? false) {
            $candidates[] = ['type' => 'story-based', 'base_score' => 25];
            $candidates[] = ['type' => 'gamified', 'base_score' => 20];
        }

        if ($conceptReqs['simulation_required'] ?? false) {
            $candidates[] = ['type' => 'simulation', 'base_score' => 25];
        }

        if (!empty($conceptReqs['manipulation_need'])) {
            $candidates[] = ['type' => 'problem-based', 'base_score' => 20];
        }

        return $candidates;
    }

    protected function getDefaultAlternatives(): array
    {
        return [
            ['type' => 'concept-based', 'reason' => 'Default fallback'],
            ['type' => 'practice-based', 'reason' => 'Active reinforcement'],
        ];
    }
}
