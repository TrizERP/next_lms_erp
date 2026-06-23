<?php

namespace App\Services\PAL\Pedagogy;

use App\Services\PAL\Pedagogy\PedagogySelectorEngine;
use App\Services\PAL\Pedagogy\CognitiveLoadEngine;
use App\Services\PAL\Pedagogy\EmotionalSafetyEngine;
use App\Services\PAL\Pedagogy\MetacognitionEngine;
use App\Services\PAL\Pedagogy\PedagogyFatigueEngine;

/**
 * Pedagogy Orchestration Service
 * Dynamic orchestration engine that selects best learning strategy
 */
class PedagogyOrchestrationService
{
    protected PedagogySelectorEngine $selector;
    protected CognitiveLoadEngine $cognitive;
    protected EmotionalSafetyEngine $emotional;
    protected MetacognitionEngine $metacognition;
    protected PedagogyFatigueEngine $fatigue;

    public function __construct(
        PedagogySelectorEngine $selector,
        CognitiveLoadEngine $cognitive,
        EmotionalSafetyEngine $emotional,
        MetacognitionEngine $metacognition,
        PedagogyFatigueEngine $fatigue
    ) {
        $this->selector = $selector;
        $this->cognitive = $cognitive;
        $this->emotional = $emotional;
        $this->metacognition = $metacognition;
        $this->fatigue = $fatigue;
    }

    /**
     * Get recommended pedagogy for learner
     * @param int $learnerId
     * @param int $conceptId
     * @param array $context
     * @return array
     */
    public function getRecommendation(int $learnerId, int $conceptId, array $context = []): array
    {
        $selected = $this->selector->select($learnerId, $conceptId, $context);
        
        // Check cognitive load
        $cognitiveLoad = $this->cognitive->assess($learnerId);
        
        // Apply cognitive load adjustments
        if ($cognitiveLoad['status'] === 'overloaded') {
            $selected = $this->cognitive->adjustPedagogy($selected, $cognitiveLoad);
        }
        
        // Apply emotional safety rules
        $selected = $this->emotional->apply($selected);
        
        // Check for pedagogy fatigue
        $fatigueStatus = $this->fatigue->check($learnerId);
        if ($fatigueStatus['is_fatigued']) {
            $selected = $this->fatigue->rotate($selected);
        }

        return [
            'learner_id' => $learnerId,
            'concept_id' => $conceptId,
            'recommended_pedagogy' => $selected,
            'alternative_pedagogies' => $this->selector->getAlternatives($conceptId),
            'cognitive_load_status' => $cognitiveLoad['status'],
            'cognitive_load_level' => $cognitiveLoad['load_level'] ?? 0,
            'cognitive_load_display' => $this->formatCognitiveLoadDisplay($cognitiveLoad),
            'fatigue_status' => $fatigueStatus,
            'selection_reason' => $this->selector->getReason($learnerId, $selected['type']),
            'sequence' => $this->getPedagogySequence($selected),
        ];
    }

    /**
     * Get content variants for a pedagogy
     * @param int $conceptId
     * @param string $pedagogyType
     * @return array
     */
    public function getContentVariants(int $conceptId, string $pedagogyType): array
    {
        return \App\Models\PAL\Content::where('concept_id', $conceptId)
            ->whereJsonContains('pedagogy_tags', $pedagogyType)
            ->where('status', 'active')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'type' => $c->content_type,
                'format' => $c->format,
                'difficulty' => $c->difficulty_level,
                'bloom_level' => $c->bloom_level,
            ])
            ->toArray();
    }

    /**
     * Track pedagogy effectiveness
     * @param int $learnerId
     * @param string $pedagogyType
     * @param string $outcome
     * @return void
     */
    public function trackEffectiveness(int $learnerId, string $pedagogyType, string $outcome, array $context = []): array
    {
        $record = $this->selector->recordOutcome($learnerId, $pedagogyType, $outcome, $context);

        return [
            'id' => $record->id,
            'learner_id' => $learnerId,
            'pedagogy_type' => $pedagogyType,
            'outcome' => $outcome,
        ];
    }

    /**
     * Get metacognitive prompts
     * @param int $learnerId
     * @param string $context
     * @return array
     */
    public function getMetacognitivePrompts(int $learnerId, string $context = 'general'): array
    {
        return $this->metacognition->getPrompts($learnerId, $context);
    }

    /**
     * Record reflection from learner
     * @param int $learnerId
     * @param array $reflection
     * @return \App\Models\PAL\Reflection
     */
    public function recordReflection(int $learnerId, array $reflection): \App\Models\PAL\Reflection
    {
        return $this->metacognition->record($learnerId, $reflection);
    }

    protected function getPedagogySequence(array $primary): array
    {
        return [
            $primary,
            ['type' => 'fallback', 'weight' => 0.2],
        ];
    }

    /**
     * Format cognitive load for display in the UI.
     */
    protected function formatCognitiveLoadDisplay(array $cognitiveLoad): string
    {
        $status = $cognitiveLoad['status'] ?? 'unknown';
        $loadLevel = (int) ($cognitiveLoad['load_level'] ?? 0);

        return match ($status) {
            'critical' => 'Critical (' . $loadLevel . '/10)',
            'overloaded' => 'High (' . $loadLevel . '/10)',
            'elevated' => 'Elevated (' . $loadLevel . '/10)',
            'normal' => 'Normal (' . $loadLevel . '/10)',
            default => 'Not enough data (' . $loadLevel . '/10)',
        };
    }
}
