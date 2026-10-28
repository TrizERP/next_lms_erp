<?php

namespace App\Agents\TeachLearn;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Services\lms\ContentAgentService;

/**
 * Content Agent (Agent 2) for Teach/Learn.
 *
 * Automatically checks, verifies, and generates learning content for topics identified
 * by the Course Catalogue Reader Agent (Agent 1).
 */
class ContentAgent implements Agent
{
    public function __construct(
        private readonly ContentAgentService $contentAgent,
    ) {
    }

    /**
     * Execute Content Agent task.
     */
    public function run(AgentContext $context): array
    {
        $tenantId = (int) $context->scope->selectedInstituteId;
        $batchLimit = (int) ($context->input['limit'] ?? 15);
        $topicId = !empty($context->input['topic_id']) ? (int) $context->input['topic_id'] : null;

        if ($topicId) {
            $topicData = $context->input['topic_data'] ?? ['topic_id' => $topicId];
            return $this->contentAgent->processTopicIntelligence($tenantId, $topicData);
        }

        return $this->contentAgent->batchProcessPendingTopics($tenantId, $batchLimit);
    }

    /**
     * Summary description of run results.
     */
    public function summarize(array $result): string
    {
        if (isset($result['action'])) {
            return $result['message'] ?? 'Processed topic content.';
        }

        $generated = $result['generated_count'] ?? 0;
        $verified = $result['verified_existing_count'] ?? 0;
        $remaining = $result['remaining_pending_estimate'] ?? 0;

        return sprintf(
            'Content Agent: Generated %d new topic modules, verified %d existing modules. %d pending.',
            $generated,
            $verified,
            $remaining
        );
    }
}
