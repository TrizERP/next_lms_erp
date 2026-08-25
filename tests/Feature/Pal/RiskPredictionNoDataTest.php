<?php

namespace Tests\Feature\Pal;

use App\Services\PAL\Intelligence\PredictiveInterventionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 17 fix: predictDisengagement()/
 * predictBurnout() compute entirely from pal_learning_sessions/
 * pal_session_events, which nothing writes to (IntelligenceService::
 * processEvent(), the only writer, is never called from any route). Before
 * this fix, an untracked learner silently got risk_score = 0 / risk_level =
 * "low" -- indistinguishable from a learner who was actually tracked and
 * measured as genuinely low risk. That is exactly the "0 vs no data"
 * failure the spec calls out, applied to risk indicators instead of mastery.
 */
class RiskPredictionNoDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_disengagement_risk_for_an_untracked_learner_is_reported_as_no_data(): void
    {
        $result = app(PredictiveInterventionEngine::class)->predictDisengagement(910001);

        $this->assertFalse($result['has_data']);
        $this->assertNull($result['risk_score']);
        $this->assertSame('insufficient_data', $result['risk_level']);
        $this->assertFalse($result['trigger_intervention']);
    }

    public function test_burnout_risk_for_an_untracked_learner_is_reported_as_no_data(): void
    {
        $result = app(PredictiveInterventionEngine::class)->predictBurnout(910002);

        $this->assertFalse($result['has_data']);
        $this->assertNull($result['risk_score']);
        $this->assertSame('insufficient_data', $result['risk_level']);
    }

    public function test_failure_risk_uses_real_assessment_results_when_present(): void
    {
        $learnerId = 910003;

        DB::table('pal_assessment_results')->insert([
            'learner_id' => $learnerId,
            'question_id' => 1,
            'is_correct' => false,
            'response_time_ms' => 5000,
            'score' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(PredictiveInterventionEngine::class)->predictFailure($learnerId);

        $this->assertTrue($result['has_data']);
        $this->assertIsFloat($result['risk_score']);
    }

    public function test_get_risk_score_averages_only_the_dimensions_with_data(): void
    {
        $learnerId = 910004;

        DB::table('pal_assessment_results')->insert([
            'learner_id' => $learnerId,
            'question_id' => 1,
            'is_correct' => true,
            'response_time_ms' => 4000,
            'score' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should not throw despite disengagement/burnout having no session data.
        $score = app(PredictiveInterventionEngine::class)->getRiskScore($learnerId);

        $this->assertIsFloat($score);
    }
}
