<?php

namespace Tests\Feature\AI\Fees;

use App\Agents\Fees\FeesPredictiveAgent;
use App\Domain\AI\Agents\Agent;
use App\Domain\Fees\Collection\FeesRiskTrendDetector;
use Tests\TestCase;

class FeesPredictiveAgentTest extends TestCase
{
    public function test_agent_implements_agent_interface(): void
    {
        $agent = new FeesPredictiveAgent(app(FeesRiskTrendDetector::class));

        $this->assertInstanceOf(Agent::class, $agent);
    }

    public function test_agent_has_run_and_summarize_methods(): void
    {
        $agent = new FeesPredictiveAgent(app(FeesRiskTrendDetector::class));

        $this->assertTrue(method_exists($agent, 'run'));
        $this->assertTrue(method_exists($agent, 'summarize'));
    }

    public function test_summarize_reports_the_no_findings_message_when_nothing_was_found(): void
    {
        $agent = new FeesPredictiveAgent(app(FeesRiskTrendDetector::class));

        $summary = $agent->summarize([
            'students_with_worsening_trend' => 0,
            'message' => 'No current defaulter in this scope has enough recorded history to show a worsening trend, or none is worsening.',
        ]);

        $this->assertIsString($summary);
        $this->assertSame('No current defaulter in this scope has enough recorded history to show a worsening trend, or none is worsening.', $summary);
    }

    public function test_summarize_reports_a_count_when_cases_were_found(): void
    {
        $agent = new FeesPredictiveAgent(app(FeesRiskTrendDetector::class));

        $summary = $agent->summarize(['students_with_worsening_trend' => 3]);

        $this->assertIsString($summary);
        $this->assertStringContainsString('3 student', $summary);
    }

    public function test_agent_is_registered_with_the_correct_runner_class(): void
    {
        $row = \Illuminate\Support\Facades\DB::table('ai_agents')
            ->where('agent_key', 'k12_fees_predictive')
            ->first();

        if ($row === null) {
            $this->markTestSkipped('k12_fees_predictive is not registered in this database.');
        }

        $this->assertSame(FeesPredictiveAgent::class, $row->runner_class);
        $this->assertSame('fees_proactive_outreach', $row->authorized_workflow_keys);
        $this->assertSame(0, (int) $row->may_execute_actions, 'Predictive agent must never be authorised to execute actions unattended.');
    }
}
