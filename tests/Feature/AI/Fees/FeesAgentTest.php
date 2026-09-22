<?php

namespace Tests\Feature\AI\Fees;

use App\Agents\Fees\FeesAgent;
use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Fees\KnowledgeBase\FeesKnowledgeBaseService;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Domain\K12\Fees\FeeArrearsDetector;
use App\Services\AI\Fees\FeesPromptService;
use App\Services\Mcp\FeesArrearsService;
use App\Services\Mcp\FeesPendingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeesAgentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock detector with minimal dependencies
        $thresholds = new ThresholdRegistry(DB::table('ai_signal_definitions')->get());
        $arrearsService = new FeesArrearsService(app(FeesPendingService::class));
        $this->detector = new FeeArrearsDetector($thresholds, $arrearsService);
    }

    public function test_agent_implements_agent_interface(): void
    {
        $agent = new FeesAgent(
            new FeesPromptService(),
            new FeesKnowledgeBaseService(),
            $this->detector
        );

        $this->assertInstanceOf(Agent::class, $agent);
    }

    public function test_agent_has_run_and_summarize_methods(): void
    {
        $agent = new FeesAgent(
            new FeesPromptService(),
            new FeesKnowledgeBaseService(),
            $this->detector
        );

        $this->assertTrue(method_exists($agent, 'run'));
        $this->assertTrue(method_exists($agent, 'summarize'));
    }

    public function test_summarize_returns_string(): void
    {
        $agent = new FeesAgent(
            new FeesPromptService(),
            new FeesKnowledgeBaseService(),
            $this->detector
        );

        $summary = $agent->summarize([
            'intent' => 'pending_fees',
            'student_id' => 42,
            'sub_institute_id' => 1,
        ]);

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }
}