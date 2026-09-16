<?php

namespace App\Domain\AI\Recommendations;

use App\Domain\AI\Fees\KnowledgeBase\FeesKnowledgeBaseService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeesRecommendationService
{
    public function __construct(
        private readonly FeesKnowledgeBaseService $knowledgeBase,
    ) {
    }

    public function draftForStudent(int $studentId, McpRequestContext $context): array
    {
        $student = DB::table('tblstudent')
            ->where('id', $studentId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        if (! $student) {
            return [
                'action_type' => 'fee_collection_reminder',
                'title' => 'No student found',
                'body' => "Student #{$studentId} not found in this institute.",
                'risk_level' => 'low',
                'confidence' => 0.0,
                'is_consequential' => false,
            ];
        }

        $pending = DB::table('fees_breackoff as fb')
            ->where('fb.student_id', $studentId)
            ->where('fb.sub_institute_id', $context->selectedInstituteId)
            ->where('fb.syear', $context->academicYear)
            ->where('fb.balance', '>', 0)
            ->count();

        if ($pending === 0) {
            return [
                'action_type' => 'no_action',
                'title' => 'No pending fees',
                'body' => "Student #{$studentId} has no pending fees for the current academic year.",
                'risk_level' => 'low',
                'confidence' => 0.9,
                'is_consequential' => false,
            ];
        }

        $totalDue = (float) DB::table('fees_breackoff as fb')
            ->where('fb.student_id', $studentId)
            ->where('fb.sub_institute_id', $context->selectedInstituteId)
            ->where('fb.syear', $context->academicYear)
            ->where('fb.balance', '>', 0)
            ->sum('balance');

        $policyHint = $this->knowledgeBase->summaryForStudent(
            $studentId,
            $context->selectedInstituteId,
            $context->academicYear
        );

        return [
            'action_type' => 'fee_collection_reminder',
            'title' => "Send fee collection reminder to Student #{$studentId}",
            'body' => "Student {$student->first_name} {$student->last_name} has {$pending} pending fee records totaling ₹{$totalDue}. Consider sending a payment reminder.",
            'rationale' => $policyHint,
            'risk_level' => $totalDue > 5000 ? 'high' : 'medium',
            'confidence' => 0.8,
            'is_consequential' => false,
            'subject_entity_key' => 'student',
            'subject_id' => $studentId,
        ];
    }

    public function draftCollectionReport(McpRequestContext $context): array
    {
        $instituteId = $context->selectedInstituteId;
        $syear = $context->academicYear ?? (int) date('Y');

        $summary = DB::table('fees_breackoff as fb')
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->selectRaw('COUNT(DISTINCT fb.student_id) as students,
                SUM(fb.amount) as total_demand, SUM(fb.balance) as total_outstanding')
            ->first();

        $collected = DB::table('fees_collect as fc')
            ->where('fc.sub_institute_id', $instituteId)
            ->where('fc.syear', $syear)
            ->where('fc.is_deleted', 'N')
            ->selectRaw('COUNT(*) as receipts, SUM(fc.amount) as total_collected')
            ->first();

        return [
            'action_type' => 'fee_collection_review',
            'title' => 'Fee collection review — current academic year',
            'body' => sprintf(
                '%d students have fees. Total demand: ₹%s. Total outstanding: ₹%s. Collected: %d receipts totaling ₹%s.',
                (int) ($summary->students ?? 0),
                number_format((float) ($summary->total_demand ?? 0), 2),
                number_format((float) ($summary->total_outstanding ?? 0), 2),
                (int) ($collected->receipts ?? 0),
                number_format((float) ($collected->total_collected ?? 0), 2)
            ),
            'risk_level' => (float) ($summary->total_outstanding ?? 0) > 100000 ? 'high' : 'medium',
            'confidence' => 0.9,
            'is_consequential' => false,
            'subject_entity_key' => 'fees',
            'subject_id' => $instituteId,
        ];
    }

    public function forModule(McpRequestContext $context, array $input): array
    {
        $studentId = $input['student_id'] ?? null;

        if ($studentId !== null) {
            return $this->draftForStudent($studentId, $context);
        }

        return $this->draftCollectionReport($context);
    }
}