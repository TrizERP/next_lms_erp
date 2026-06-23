<?php

namespace App\Services\PAL\Intelligence;

use App\Services\PAL\Intelligence\LearnerStateEngine;
use App\Services\PAL\Intelligence\LearningVelocityEngine;
use App\Services\PAL\Intelligence\MisconceptionIntelligenceEngine;
use App\Services\PAL\Intelligence\PredictiveInterventionEngine;
use App\Services\PAL\Intelligence\BehavioralAnalyticsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Intelligence Service Layer - Central Brain of PAL V4
 * Orchestrates all intelligence engines for learner state inference
 */
class IntelligenceService
{
    protected LearnerStateEngine $learnerState;
    protected LearningVelocityEngine $velocity;
    protected MisconceptionIntelligenceEngine $misconception;
    protected PredictiveInterventionEngine $intervention;
    protected BehavioralAnalyticsService $behavioral;

    public function __construct(
        LearnerStateEngine $learnerState,
        LearningVelocityEngine $velocity,
        MisconceptionIntelligenceEngine $misconception,
        PredictiveInterventionEngine $intervention,
        BehavioralAnalyticsService $behavioral
    ) {
        $this->learnerState = $learnerState;
        $this->velocity = $velocity;
        $this->misconception = $misconception;
        $this->intervention = $intervention;
        $this->behavioral = $behavioral;
    }

    /**
     * Get comprehensive learner state across all dimensions
     * @param int $learnerId
     * @param int|null $sessionId
     * @return array
     */
    public function getLearnerState(int $learnerId, ?int $sessionId = null): array
    {
        return $this->learnerState->getState($learnerId, $sessionId);
    }

    /**
     * Infer learner competency dimension
     * @param int $learnerId
     * @return array
     */
    public function inferCompetency(int $learnerId): array
    {
        return $this->learnerState->inferCompetency($learnerId);
    }

    /**
     * Infer learner behavioral dimension
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function inferBehavior(int $learnerId, int $sessionId): array
    {
        return $this->learnerState->inferBehavior($learnerId, $sessionId);
    }

    /**
     * Infer learner motivational dimension
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function inferMotivation(int $learnerId, int $sessionId): array
    {
        return $this->learnerState->inferMotivation($learnerId, $sessionId);
    }

    /**
     * Infer learner metacognition dimension
     * @param int $learnerId
     * @return array
     */
    public function inferMetacognition(int $learnerId): array
    {
        return $this->learnerState->inferMetacognition($learnerId);
    }

    /**
     * Calculate learning velocity
     * @param int $learnerId
     * @param string $period (day|week|month)
     * @return array
     */
    public function calculateVelocity(int $learnerId, string $period = 'week'): array
    {
        return $this->velocity->calculate($learnerId, $period);
    }

    /**
     * Detect plateau in learning progress
     * @param int $learnerId
     * @return array
     */
    public function detectPlateau(int $learnerId): array
    {
        return $this->velocity->detectPlateau($learnerId);
    }

    /**
     * Detect mastery regression
     * @param int $learnerId
     * @return array
     */
    public function detectRegression(int $learnerId): array
    {
        return $this->velocity->detectRegression($learnerId);
    }

    /**
     * Analyze misconception patterns
     * @param int $learnerId
     * @param int $conceptId
     * @param array $attemptData
     * @return array
     */
    public function analyzeMisconception(int $learnerId, int $conceptId, array $attemptData): array
    {
        return $this->misconception->analyze($learnerId, $conceptId, $attemptData);
    }

    /**
     * Cluster misconception patterns
     * @param int $conceptId
     * @return array
     */
    public function clusterMisconceptions(int $conceptId): array
    {
        return $this->misconception->cluster($conceptId);
    }

    /**
     * Get targeted remediation
     * @param int $learnerId
     * @param int $misconceptionId
     * @return array
     */
    public function getRemediation(int $learnerId, int $misconceptionId): array
    {
        return $this->misconception->getRemediation($learnerId, $misconceptionId);
    }

    /**
     * Predict disengagement risk
     * @param int $learnerId
     * @return array
     */
    public function predictDisengagement(int $learnerId): array
    {
        return $this->intervention->predictDisengagement($learnerId);
    }

    /**
     * Predict failure risk
     * @param int $learnerId
     * @return array
     */
    public function predictFailure(int $learnerId): array
    {
        return $this->intervention->predictFailure($learnerId);
    }

    /**
     * Predict burnout risk
     * @param int $learnerId
     * @return array
     */
    public function predictBurnout(int $learnerId): array
    {
        return $this->intervention->predictBurnout($learnerId);
    }

    /**
     * Detect frustration indicators
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function detectFrustration(int $learnerId, int $sessionId): array
    {
        return $this->behavioral->detectFrustration($learnerId, $sessionId);
    }

    /**
     * Calculate engagement score
     * @param int $learnerId
     * @param int $sessionId
     * @return array
     */
    public function calculateEngagement(int $learnerId, int $sessionId): array
    {
        return $this->behavioral->calculateEngagement($learnerId, $sessionId);
    }

    /**
     * Get disengagement risk score
     * @param int $learnerId
     * @return float
     */
    public function getDisengagementRisk(int $learnerId): float
    {
        return $this->intervention->getRiskScore($learnerId);
    }

    /**
     * Process real-time event from xAPI/H5P
     * @param array $eventData
     * @return void
     */
    public function processEvent(array $eventData): void
    {
        $this->behavioral->processEvent($eventData);
    }

    /**
     * Get mastery map for learner
     * @param int $learnerId
     * @param int|null $subjectId
     * @return array
     */
    public function getMasteryMap(int $learnerId, ?int $subjectId = null): array
    {
        return $this->learnerState->getMasteryMap($learnerId, $subjectId);
    }

    /**
     * Get recommended pedagogy based on learner state
     * @param int $learnerId
     * @return array
     */
    public function getRecommendedPedagogy(int $learnerId): array
    {
        return $this->learnerState->inferRecommendedPedagogy($learnerId);
    }
}