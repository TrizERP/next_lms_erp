<?php

namespace Tests\Feature\Pal;

use App\Services\PAL\Intelligence\LearnerStateEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression coverage extending the Phase 6/7 no-data-vs-zero fix (previously
 * applied only to inferCompetency()/getMasteryMap()) to the remaining five
 * learner-state dimensions. Each reads from a table with no confirmed
 * production writer (pal_session_events, pal_collaboration_activities) or a
 * real-but-likely-unused one (pal_reflections) -- without has_data, an
 * untracked learner reads identically to one genuinely measured at zero
 * across behavior, motivation, social, contextual and metacognitive signals.
 */
class LearnerStateDimensionsNoDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_behavioral_dimension_reports_no_data_for_untracked_learner(): void
    {
        $state = app(LearnerStateEngine::class)->inferBehavior(920001, 1);

        $this->assertFalse($state['has_data']);
    }

    public function test_motivational_dimension_reports_no_data_for_untracked_learner(): void
    {
        $state = app(LearnerStateEngine::class)->inferMotivation(920002, 1);

        $this->assertFalse($state['has_data']);
    }

    public function test_social_dimension_reports_no_data_for_untracked_learner(): void
    {
        $state = app(LearnerStateEngine::class)->inferSocial(920003);

        $this->assertFalse($state['has_data']);
    }

    public function test_contextual_dimension_reports_no_data_for_untracked_learner(): void
    {
        $state = app(LearnerStateEngine::class)->inferContextual(920004);

        $this->assertFalse($state['has_data']);
    }

    public function test_metacognition_dimension_reports_no_data_for_untracked_learner(): void
    {
        $state = app(LearnerStateEngine::class)->inferMetacognition(920005);

        $this->assertFalse($state['has_data']);
    }
}
