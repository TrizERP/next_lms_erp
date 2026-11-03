<?php

namespace App\Agents\CurriculumPlanning;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Services\Mcp\CurriculumPlanningReportService;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The Curriculum Planning Agent: continuously audits syllabus hierarchy, unit progress,
 * and scheduled lesson pacing against learning outcomes.
 *
 * max_verb = recommend and may_execute_actions = 0.
 * The agent may detect, analyze, diagnose, and draft. It may not alter course configurations
 * or change scheduled periods directly. All recommendations are stored in the pending
 * approval queue for academic coordinator / educator sign-off.
 */
class CurriculumPlanningAgent implements Agent
{
    private const CASE_TYPE = 'curriculum_planning_review';
    private const WORKFLOW_KEY = 'curriculum_planning_review';

    public function __construct(
        private readonly CurriculumPlanningReportService $reportService,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $tenantId = (int) $scope->selectedInstituteId;

        $standardId = isset($input['standard_id']) && is_numeric($input['standard_id'])
            ? (int) $input['standard_id']
            : null;
        $subjectId = isset($input['subject_id']) && is_numeric($input['subject_id'])
            ? (int) $input['subject_id']
            : null;
        $syear = isset($input['syear']) && is_numeric($input['syear'])
            ? (int) $input['syear']
            : null;

        $mcpContext = new McpRequestContext(
            userId: (int) ($context->scope->actingUserId ?? 1),
            role: 'staff',
            selectedInstituteId: $tenantId,
            allowedInstituteIds: [$tenantId],
            userProfileId: null,
            clientId: 1,
            academicYear: $syear,
            termId: null,
            isAdmin: false,
            isStudent: false,
        );

        // Step 1: Read curriculum status and coverage
        $coverageData = $this->reportService->status($mcpContext, [
            'standard_id' => $standardId,
            'subject_id' => $subjectId,
            'syear' => $syear,
        ]);

        // Step 2: Audit lesson pacing
        $paceData = $this->reportService->lessonPace($mcpContext, [
            'standard_id' => $standardId,
            'subject_id' => $subjectId,
            'syear' => $syear,
        ]);

        $curricula = $coverageData['curricula'] ?? [];
        $behindPeriods = $paceData['behind_schedule_periods'] ?? [];

        $cases = [];
        foreach ($behindPeriods as $period) {
            $cases[] = [
                'case_type' => self::CASE_TYPE,
                'target_entity' => 'period',
                'target_id' => $period['period_id'],
                'chapter_name' => $period['chapter_name'],
                'scheduled_date' => $period['scheduled_date'],
                'teacher_name' => $period['teacher_name'],
                'severity' => 'moderate',
                'recommendation' => 'Review lesson schedule and assign revision period',
            ];
        }

        return [
            'sub_institute_id' => $tenantId,
            'curricula_scanned' => count($curricula),
            'total_units' => $coverageData['total_units'] ?? 0,
            'total_chapters' => $coverageData['total_chapters'] ?? 0,
            'gaps_detected' => count($behindPeriods),
            'average_coverage_pct' => $coverageData['average_coverage_pct'] ?? 0.0,
            'completion_rate_pct' => $paceData['completion_rate_pct'] ?? 0.0,
            'cases' => $cases,
            'confidence' => 1.0,
            'message' => sprintf(
                'Scanned %d curricula across %d units. Detected %d behind-schedule lesson period(s).',
                count($curricula),
                $coverageData['total_units'] ?? 0,
                count($behindPeriods)
            ),
        ];
    }
}

