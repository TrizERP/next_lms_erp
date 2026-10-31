<?php

namespace App\Agents\Engagement;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Services\Mcp\EngagementReportService;
use App\Services\Mcp\McpRequestContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The Engagement Agent: identifies students needing attention across attendance,
 * homework, and assignments, profiles their activity telemetry, and drafts personalized
 * re-engagement interventions for educator review and approval.
 *
 * WHAT IT MAY DO, AND WHAT IT MAY NOT
 *
 * max_verb = recommend and may_execute_actions = 0.
 * The agent may detect, analyze, diagnose, and recommend. It may NOT send real emails,
 * SMS, WhatsApp messages, or modify student marks directly. All intervention recommendations
 * are stored in the pending approval queue for human educator sign-off.
 */
class EngagementAgent implements Agent
{
    private const CASE_TYPE = 'engagement_follow_up';
    private const WORKFLOW_KEY = 'engagement_intervention_review';

    public function __construct(
        private readonly EngagementReportService $reportService,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $tenantId = (int) $scope->selectedInstituteId;

        $threshold = isset($input['threshold_percent']) && is_numeric($input['threshold_percent'])
            ? (float) $input['threshold_percent']
            : 60.0;
        $daysBack = isset($input['days_back']) && is_numeric($input['days_back'])
            ? (int) $input['days_back']
            : 30;
        $limit = isset($input['limit']) && is_numeric($input['limit'])
            ? min(max((int) $input['limit'], 1), 100)
            : 20;
        $standardId = isset($input['standard_id']) && is_numeric($input['standard_id'])
            ? (int) $input['standard_id']
            : null;

        $mcpContext = new McpRequestContext(
            userId: (int) ($context->scope->actingUserId ?? 1),
            role: 'staff',
            selectedInstituteId: $tenantId,
            allowedInstituteIds: [$tenantId],
            userProfileId: null,
            clientId: 1,
            academicYear: null,
            termId: null,
            isAdmin: false,
            isStudent: false,
        );

        // Step 1: Detect students needing attention
        $attentionData = $this->reportService->studentsNeedingAttention($mcpContext, [
            'standard_id' => $standardId,
            'threshold_percent' => $threshold,
            'days_back' => $daysBack,
            'limit' => $limit,
        ]);

        $flaggedStudents = $attentionData['students'] ?? [];
        $totalFlagged = count($flaggedStudents);

        if ($totalFlagged === 0) {
            return [
                'sub_institute_id' => $tenantId,
                'students_flagged' => 0,
                'signals_detected' => 0,
                'cases' => [],
                'coverage' => [
                    'standard_id' => $standardId,
                    'window_days' => $daysBack,
                    'threshold_percent' => $threshold,
                ],
                'confidence' => 1.0,
                'message' => "All scanned students meet or exceed the {$threshold}% engagement threshold.",
            ];
        }

        // Step 2: Profile student activity
        $studentIds = array_column($flaggedStudents, 'student_id');
        $profilingData = $this->reportService->profileEngagement($mcpContext, [
            'student_ids' => $studentIds,
            'days_back' => $daysBack,
        ]);

        $profiles = $profilingData['profiles'] ?? [];
        $cases = [];

        // Step 3: Draft intervention recommendations
        foreach (array_slice($profiles, 0, 10) as $prof) {
            $sid = (int) $prof['student_id'];
            $draft = $this->reportService->draftIntervention($mcpContext, [
                'student_id' => $sid,
                'student_name' => $prof['student_name'] ?? "Student #{$sid}",
                'attendance_percent' => $prof['attendance_percent'],
                'homework_percent' => $prof['homework_percent'],
                'assignments_percent' => $prof['assignments_percent'],
                'unanswered_doubts_count' => $prof['unanswered_doubts'] ?? 0,
                'deficiency_reasons' => $prof['detected_deficits'] ?? [],
            ]);

            $cases[] = [
                'student_id' => $sid,
                'student_name' => $prof['student_name'] ?? "Student #{$sid}",
                'deficits' => $prof['detected_deficits'] ?? [],
                'primary_vector' => $prof['primary_disengagement_vector'] ?? 'Observation',
                'recommendation' => $draft,
            ];
        }

        return [
            'sub_institute_id' => $tenantId,
            'students_flagged' => $totalFlagged,
            'signals_detected' => count($cases),
            'cases' => $cases,
            'coverage' => [
                'standard_id' => $standardId,
                'window_days' => $daysBack,
                'threshold_percent' => $threshold,
                'total_flagged' => $totalFlagged,
            ],
            'confidence' => 1.0,
            'mode' => 'deep_analysis',
        ];
    }

    public function summarize(array $result): string
    {
        $count = (int) ($result['students_flagged'] ?? 0);
        if ($count === 0) {
            return (string) ($result['message'] ?? 'No student in this scope is flagged below the engagement threshold.');
        }

        return sprintf(
            'Engagement Agent: Found %d student(s) below the engagement threshold across attendance, homework, and coursework. Prepared intervention drafts for educator sign-off.',
            $count
        );
    }
}

