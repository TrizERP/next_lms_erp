<?php

namespace Tests\Feature\AI\Fees;

use App\Agents\Fees\FeesAgent;
use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Fees\KnowledgeBase\FeesKnowledgeBaseService;
use App\Services\AI\Fees\FeesPromptService;
use PHPUnit\Framework\TestCase;

class FeesAgentTest extends TestCase
{
    public function test_agent_implements_agent_interface(): void
    {
        $agent = new FeesAgent(
            new FeesPromptService(),
            new FeesKnowledgeBaseService()
        );

        $this->assertInstanceOf(Agent::class, $agent);
    }

    public function test_agent_has_run_and_summarize_methods(): void
    {
        $agent = new FeesAgent(
            new FeesPromptService(),
            new FeesKnowledgeBaseService()
        );

        $this->assertTrue(method_exists($agent, 'run'));
        $this->assertTrue(method_exists($agent, 'summarize'));
    }

    public function test_summarize_returns_string(): void
    {
        $agent = new FeesAgent(
            new FeesPromptService(),
            new FeesKnowledgeBaseService()
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