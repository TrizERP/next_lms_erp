<?php

namespace App\Agents\Fees;

use App\Domain\AI\Agents\Agent;
use App\Domain\AI\Agents\AgentContext;
use App\Domain\AI\Fees\KnowledgeBase\FeesKnowledgeBaseService;
use App\Services\AI\Fees\FeesPromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeesAgent implements Agent
{
    public function __construct(
        private readonly FeesPromptService $prompts,
        private readonly FeesKnowledgeBaseService $knowledgeBase,
    ) {
    }

    public function run(AgentContext $context): array
    {
        $input = $context->input;
        $scope = $context->scope;
        $studentId = $input['student_id'] ?? null;
        $instituteId = $scope->selectedInstituteId;
        $syear = $scope->academicYear ?? (int) date('Y');

        $intent = $input['intent'] ?? 'pending_fees';

        switch ($intent) {
            case 'fee_details':
                $data = $this->prompts->feeDetailsPrompt([
                    'student_id' => $studentId,
                    'sub_institute_id' => $instituteId,
                    'syear' => $syear,
                ]);
                break;
            case 'pending_fees_report':
                $data = $this->prompts->pendingFeesReportPrompt([
                    'sub_institute_id' => $instituteId,
                    'syear' => $syear,
                    'standard_id' => $input['standard_id'] ?? null,
                    'section_id' => $input['section_id'] ?? null,
                ]);
                break;
            case 'fee_summary':
                $data = $this->prompts->feeSummaryPrompt([
                    'sub_institute_id' => $instituteId,
                    'syear' => $syear,
                ]);
                break;
            case 'fee_reminder':
                $data = $this->prompts->feeReminderPrompt([
                    'sub_institute_id' => $instituteId,
                    'syear' => $syear,
                    'student_id' => $studentId,
                ]);
                break;
            case 'fee_status_explanation':
                $data = $this->prompts->feeStatusExplanationPrompt([
                    'student_id' => $studentId,
                    'sub_institute_id' => $instituteId,
                    'syear' => $syear,
                ]);
                break;
            case 'collection_workflow':
                $data = $this->prompts->collectionWorkflowPrompt([
                    'sub_institute_id' => $instituteId,
                    'syear' => $syear,
                    'student_id' => $studentId,
                ]);
                break;
            case 'pending_fees':
            default:
                $data = $this->prompts->pendingFeesPrompt([
                    'student_id' => $studentId,
                    'sub_institute_id' => $instituteId,
                    'syear' => $syear,
                ]);
                break;
        }

        $policySummary = $this->knowledgeBase->summaryForStudent(
            $studentId ?? 0,
            $instituteId,
            $syear
        );

        $breakoffData = $this->fetchBreakoffSummary($instituteId, $syear, $studentId);

        return [
            'intent' => $intent,
            'student_id' => $studentId,
            'sub_institute_id' => $instituteId,
            'syear' => $syear,
            'prompt' => $data,
            'knowledge_base' => $policySummary,
            'breakoff_summary' => $breakoffData,
        ];
    }

    public function summarize(array $result): string
    {
        $studentId = $result['student_id'] ?? null;
        $intent = $result['intent'] ?? 'fees';
        $name = $studentId ? "Student #{$studentId}" : 'all students';

        return "Fees agent answered a {$intent} question for {$name} at institute #{$result['sub_institute_id']}.";
    }

    private function fetchBreakoffSummary(int $instituteId, ?int $syear, ?int $studentId): array
    {
        if (! Schema::hasTable('fees_breackoff')) {
            return ['error' => 'Fees tables not available.'];
        }

        $query = DB::table('fees_breackoff as fb')
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->when($studentId, fn ($q) => $q->where('fb.student_id', $studentId));

        $totalDemand = (float) $query->clone()->sum('amount');
        $totalBalance = (float) $query->clone()->where('balance', '>', 0)->sum('balance');
        $studentCount = (int) $query->clone()->distinct('student_id')->count('student_id');
        $pendingCount = (int) $query->clone()->where('balance', '>', 0)->count();

        $headwise = DB::table('fees_breackoff as fb')
            ->join('fees_title as ft', function ($join) use ($instituteId) {
                $join->on('ft.id', '=', 'fb.fee_type_id')
                    ->whereColumn('ft.sub_institute_id', '=', 'fb.sub_institute_id')
                    ->whereColumn('ft.syear', '=', 'fb.syear');
            })
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->selectRaw('ft.fees_title, ft.display_name, SUM(fb.amount) as total_demand, SUM(fb.balance) as total_pending')
            ->groupBy('ft.fees_title', 'ft.display_name')
            ->get()
            ->map(static fn ($row) => [
                'fee_title' => (string) $row->fees_title,
                'display_name' => (string) $row->display_name,
                'total_demand' => round((float) $row->total_demand, 2),
                'total_pending' => round((float) $row->total_pending, 2),
            ])
            ->all();

        return [
            'total_demand' => round($totalDemand, 2),
            'total_outstanding' => round($totalBalance, 2),
            'students_with_fees' => $studentCount,
            'pending_records' => $pendingCount,
            'headwise' => $headwise,
        ];
    }
}