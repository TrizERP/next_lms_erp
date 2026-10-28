<?php

namespace App\Agents\TeachLearn;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Services\Lms\CourseCatalogueHierarchyValidator;

/**
 * Course Catalogue Reader Agent.
 *
 * Continuously validates and inspects the 5-tier Teach/Learn content hierarchy:
 *   Standard -> Subject -> Chapter -> Topic -> Intelligence
 *
 * Read-only against all LMS tables.
 * Can be run manually or as a background job independently of user presence.
 */
class CourseCatalogueReaderAgent implements Agent
{
    public function __construct(
        private readonly CourseCatalogueHierarchyValidator $validator,
    ) {
    }

    /**
     * Execute the agent's validation analysis.
     *
     * @param AgentContext $context Contains runtime scope, inputs, and governance
     * @return array The validation findings
     */
    public function run(AgentContext $context): array
    {
        $tenantId = (int) $context->scope->selectedInstituteId;
        $force = (bool) ($context->input['force'] ?? false);

        $report = $this->validator->validate($tenantId, $force);

        return [
            'agent_key' => CourseCatalogueHierarchyValidator::AGENT_KEY,
            'status' => $report['status'],
            'data_hash' => $report['data_hash'] ?? null,
            'last_run_time' => $report['last_run_time'] ?? null,
            'next_scheduled_run' => $report['next_scheduled_run'] ?? null,
            'counts' => $report['counts'] ?? [],
            'summary' => $report['summary'] ?? [],
            'issues' => $report['issues'] ?? [],
            'newly_discovered_issues' => $report['newly_discovered_issues'] ?? [],
            'resolved_issues' => $report['resolved_issues'] ?? [],
            'duration_ms' => $report['duration_ms'] ?? 0,
        ];
    }

    /**
     * Summary description of validation findings.
     */
    public function summarize(array $result): string
    {
        $counts = $result['counts'] ?? [];
        $totalIssues = $counts['total_issues_detected'] ?? 0;
        $completeChains = $counts['complete_hierarchies_validated'] ?? 0;
        $standards = $counts['standards_checked'] ?? 0;

        if ($totalIssues === 0) {
            return sprintf(
                'Course Catalogue Reader: All %d standards have healthy 5-tier hierarchies. %d complete chains verified.',
                $standards,
                $completeChains
            );
        }

        return sprintf(
            'Course Catalogue Reader: Validated %d standards (%d complete 5-tier chains verified). Detected %d hierarchy gaps: %d missing subjects, %d missing chapters, %d missing topics, %d missing intelligence configs, %d broken mappings.',
            $standards,
            $completeChains,
            $totalIssues,
            $counts['missing_subjects_count'] ?? 0,
            $counts['missing_chapters_count'] ?? 0,
            $counts['missing_topics_count'] ?? 0,
            $counts['missing_intelligence_count'] ?? 0,
            $counts['broken_mappings_count'] ?? 0
        );
    }
}
