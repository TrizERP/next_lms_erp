<?php

namespace App\Agents\TeachLearn;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Services\Lms\ContentAgentService;
use App\Services\Mcp\LmsActivityService;
use App\Services\Mcp\McpRequestContext;

/**
 * Activity and Content Analysis Agent (Agent 2) for Teach/Learn.
 *
 * Analyzes scheduled classroom sessions and homework over a sliding window,
 * audits chapter content coverage across all 5 resource types, and isolates
 * verified existing content from missing content requiring generation.
 *
 * Read-only against all activity and homework tables.
 */
class ActivityContentAnalysisAgent implements Agent
{
    public const AGENT_KEY = 'lms.activities';

    public function __construct(
        private readonly LmsActivityService $activityService,
        private readonly ContentAgentService $contentAgent,
    ) {
    }

    /**
     * Execute the activity analysis and content audit.
     *
     * @param AgentContext $context Contains runtime scope, inputs, and governance
     * @return array The analysis findings
     */
    public function run(AgentContext $context): array
    {
        $tenantId = (int) $context->scope->selectedInstituteId;
        $standardId = (int) ($context->input['standard_id'] ?? 0);
        $daysBack = (int) ($context->input['days_back'] ?? 15);
        $daysAhead = (int) ($context->input['days_ahead'] ?? 15);
        $limit = (int) ($context->input['limit'] ?? 50);

        // Build authenticated MCP context
        $mcpContext = new McpRequestContext(
            userId: (int) ($context->scope->actorUserId ?? 1),
            role: (string) ($context->scope->actorRole ?? 'admin'),
            selectedInstituteId: $tenantId,
            allowedInstituteIds: [$tenantId],
            userProfileId: (int) ($context->scope->userProfileId ?? 1),
            clientId: (int) ($context->scope->clientId ?? 1),
            academicYear: null,
            termId: null,
            isAdmin: true,
            isStudent: false
        );

        $timelineResult = $this->activityService->timeline($mcpContext, [
            'standard_id' => $standardId,
            'days_back' => $daysBack,
            'days_ahead' => $daysAhead,
            'limit' => $limit,
        ]);

        $coverage = [];
        if ($standardId > 0) {
            $chapterId = !empty($context->input['chapter_id']) ? (int) $context->input['chapter_id'] : null;
            $subjectId = !empty($context->input['subject_id']) ? (int) $context->input['subject_id'] : null;
            $coverage = $this->contentAgent->checkChapterContentCoverage($tenantId, $standardId, $subjectId, $chapterId);
        }

        return [
            'agent_key' => self::AGENT_KEY,
            'standard_id' => $standardId,
            'standard_name' => $timelineResult['standard_name'] ?? 'General',
            'activities_count' => $timelineResult['count'] ?? 0,
            'activities' => $timelineResult['activities'] ?? [],
            'by_when' => $timelineResult['by_when'] ?? [],
            'coverage_summary' => $coverage,
            'status' => 'success',
        ];
    }

    /**
     * Summary description of run results.
     */
    public function summarize(array $result): string
    {
        $count = $result['activities_count'] ?? 0;
        $std = $result['standard_name'] ?? 'Class';
        $coverage = $result['coverage_summary'] ?? [];
        $existing = $coverage['existing_count'] ?? 0;
        $missing = $coverage['missing_count'] ?? 0;

        return sprintf(
            'Activity & Content Analysis: Analyzed %d activities for Standard %s. Content audit found %d existing resources and %d missing resources.',
            $count,
            $std,
            $existing,
            $missing
        );
    }
}
