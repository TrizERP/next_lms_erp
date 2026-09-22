<?php

namespace App\Services\AI\Fees;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeesPromptService
{
    public function pendingFeesPrompt(array $context): string
    {
        $instituteId = $context['sub_institute_id'] ?? null;
        $syear = $context['syear'] ?? null;
        $studentId = $context['student_id'] ?? null;

        $data = $this->fetchPendingFeesData($instituteId, $syear, $studentId);

        return $this->renderPrompt('pending_fees', [
            'data' => $data,
            'scope' => $this->scopeDescription($context),
        ]);
    }

    public function feeDetailsPrompt(array $context): string
    {
        $studentId = $context['student_id'] ?? null;
        $instituteId = $context['sub_institute_id'] ?? null;
        $syear = $context['syear'] ?? null;

        if ($studentId === null) {
            return 'No student selected. Provide a student ID to fetch fee details.';
        }

        $data = $this->fetchStudentFeeDetails($studentId, $instituteId, $syear);

        return $this->renderPrompt('fee_details', [
            'data' => $data,
            'scope' => $this->scopeDescription($context),
        ]);
    }

    public function pendingFeesReportPrompt(array $context): string
    {
        $instituteId = $context['sub_institute_id'] ?? null;
        $syear = $context['syear'] ?? null;
        $standardId = $context['standard_id'] ?? null;
        $sectionId = $context['section_id'] ?? null;

        $data = $this->fetchPendingFeesReportData($instituteId, $syear, $standardId, $sectionId);

        return $this->renderPrompt('pending_fees_report', [
            'data' => $data,
            'scope' => $this->scopeDescription($context),
            'filters' => $context,
        ]);
    }

    public function feeSummaryPrompt(array $context): string
    {
        $instituteId = $context['sub_institute_id'] ?? null;
        $syear = $context['syear'] ?? null;

        $data = $this->fetchFeeSummaryData($instituteId, $syear);

        return $this->renderPrompt('fee_summary', [
            'data' => $data,
            'scope' => $this->scopeDescription($context),
        ]);
    }

    public function feeReminderPrompt(array $context): string
    {
        $instituteId = $context['sub_institute_id'] ?? null;
        $syear = $context['syear'] ?? null;
        $studentId = $context['student_id'] ?? null;

        $data = $this->fetchFeeReminderData($instituteId, $syear, $studentId);

        return $this->renderPrompt('fee_reminder', [
            'data' => $data,
            'scope' => $this->scopeDescription($context),
        ]);
    }

    public function feeStatusExplanationPrompt(array $context): string
    {
        $studentId = $context['student_id'] ?? null;
        $instituteId = $context['sub_institute_id'] ?? null;
        $syear = $context['syear'] ?? null;

        if ($studentId === null) {
            return 'No student selected. Provide a student ID to explain fee status.';
        }

        $data = $this->fetchStudentFeeDetails($studentId, $instituteId, $syear);
        $knowledgeBase = $this->fetchRelevantPolicies($context);

        return $this->renderPrompt('fee_status_explanation', [
            'data' => $data,
            'knowledge_base' => $knowledgeBase,
            'scope' => $this->scopeDescription($context),
        ]);
    }

    public function collectionWorkflowPrompt(array $context): string
    {
        $instituteId = $context['sub_institute_id'] ?? null;
        $syear = $context['syear'] ?? null;
        $studentId = $context['student_id'] ?? null;

        $data = $this->fetchCollectionWorkflowData($instituteId, $syear, $studentId);

        return $this->renderPrompt('collection_workflow', [
            'data' => $data,
            'scope' => $this->scopeDescription($context),
        ]);
    }

    public function dataDrivenResponsePrompt(array $context, array $realData): string
    {
        return $this->renderPrompt('data_driven_response', [
            'data' => $realData,
            'query' => $context['query'] ?? '',
            'scope' => $this->scopeDescription($context),
        ]);
    }

    private function fetchPendingFeesData(?int $instituteId, ?int $syear, ?int $studentId): array
    {
        if (! Schema::hasTable('fees_breackoff')) {
            return ['error' => 'Fees tables not available.'];
        }

        $query = DB::table('fees_breackoff as fb')
            ->join('fees_title as ft', function ($join) use ($instituteId) {
                $join->on('ft.id', '=', 'fb.fee_type_id')
                    ->whereColumn('ft.sub_institute_id', '=', 'fb.sub_institute_id')
                    ->whereColumn('ft.syear', '=', 'fb.syear');
            })
            ->join('tblstudent_enrollment as se', function ($join) use ($instituteId, $syear) {
                $join->on('se.student_id', '=', 'fb.student_id')
                    ->where('se.sub_institute_id', $instituteId)
                    ->where('se.syear', $syear)
                    ->whereNull('se.end_date');
            })
            ->join('tblstudent as s', 's.id', '=', 'fb.student_id')
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->when($studentId, fn ($q) => $q->where('fb.student_id', $studentId))
            ->selectRaw('fb.student_id, CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) as student_name,
                se.standard_id, se.section_id, fb.month_id, fb.amount as fee_amount,
                ft.fees_title, ft.display_name, fb.paid_amount, fb.balance as pending_amount,
                fb.month_id, fb.syear')
            ->where('fb.balance', '>', 0)
            ->orderBy('fb.student_id')
            ->get()
            ->map(static fn ($row) => [
                'student_id' => (int) $row->student_id,
                'student_name' => (string) $row->student_name,
                'standard_id' => (int) $row->standard_id,
                'section_id' => (int) $row->section_id,
                'month_id' => (int) $row->month_id,
                'fee_title' => (string) $row->fees_title,
                'display_name' => (string) $row->display_name,
                'fee_amount' => (float) $row->fee_amount,
                'paid_amount' => (float) $row->paid_amount,
                'pending_amount' => (float) $row->pending_amount,
                'syear' => (int) $row->syear,
            ])
            ->all();

        $totalPending = array_sum(array_column($query, 'pending_amount'));
        $studentCount = count(array_unique(array_column($query, 'student_id')));

        return [
            'records' => $query,
            'total_pending' => round($totalPending, 2),
            'students_with_pending' => $studentCount,
            'record_count' => count($query),
        ];
    }

    private function fetchStudentFeeDetails(int $studentId, ?int $instituteId, ?int $syear): array
    {
        if (! Schema::hasTable('fees_breackoff')) {
            return ['error' => 'Fees tables not available.'];
        }

        $breakoff = DB::table('fees_breackoff as fb')
            ->join('fees_title as ft', function ($join) use ($instituteId) {
                $join->on('ft.id', '=', 'fb.fee_type_id')
                    ->whereColumn('ft.sub_institute_id', '=', 'fb.sub_institute_id')
                    ->whereColumn('ft.syear', '=', 'fb.syear');
            })
            ->join('tblstudent as s', 's.id', '=', 'fb.student_id')
            ->join('tblstudent_enrollment as se', function ($join) use ($instituteId, $syear) {
                $join->on('se.student_id', '=', 'fb.student_id')
                    ->where('se.sub_institute_id', $instituteId)
                    ->where('se.syear', $syear)
                    ->whereNull('se.end_date');
            })
            ->where('fb.student_id', $studentId)
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->selectRaw('fb.*, ft.fees_title, ft.display_name,
                CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) as student_name,
                s.enrollment_no, se.standard_id, se.section_id')
            ->get()
            ->map(static fn ($row) => [
                'fee_type_id' => (int) $row->fee_type_id,
                'fee_title' => (string) $row->fees_title,
                'display_name' => (string) $row->display_name,
                'student_name' => (string) $row->student_name,
                'enrollment_no' => $row->enrollment_no,
                'standard_id' => (int) $row->standard_id,
                'section_id' => (int) $row->section_id,
                'month_id' => (int) $row->month_id,
                'amount' => (float) $row->amount,
                'paid_amount' => (float) ($row->paid_amount ?? 0),
                'balance' => (float) ($row->balance ?? 0),
                'syear' => (int) $row->syear,
            ])
            ->all();

        $receipts = Schema::hasTable('fees_collect')
            ? DB::table('fees_collect as fc')
                ->join('tblstudent as s', 's.id', '=', 'fc.student_id')
                ->selectRaw('fc.id, fc.receipt_no, fc.receiptdate, fc.payment_mode, fc.amount, fc.fine, fc.fees_discount,
                    CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) as student_name')
                ->where('fc.student_id', $studentId)
                ->where('fc.sub_institute_id', $instituteId)
                ->where('fc.is_deleted', 'N')
                ->where('fc.syear', $syear)
                ->orderByDesc('fc.receiptdate')
                ->get()
                ->map(static fn ($row) => [
                    'id' => (int) $row->id,
                    'receipt_no' => $row->receipt_no,
                    'receipt_date' => (string) $row->receiptdate,
                    'payment_mode' => (string) $row->payment_mode,
                    'amount' => (float) $row->amount,
                    'fine' => (float) $row->fine,
                    'discount' => (float) $row->fees_discount,
                    'student_name' => (string) $row->student_name,
                ])
                ->all()
            : [];

        $totalCollected = array_sum(array_column($receipts, 'amount'));
        $totalPending = array_sum(array_column($breakoff, 'balance'));

        return [
            'student_id' => $studentId,
            'breakoff' => $breakoff,
            'receipts' => $receipts,
            'total_fee_amount' => array_sum(array_column($breakoff, 'amount')),
            'total_collected' => round($totalCollected, 2),
            'total_pending' => round($totalPending, 2),
            'receipt_count' => count($receipts),
        ];
    }

    private function fetchPendingFeesReportData(?int $instituteId, ?int $syear, ?int $standardId, ?int $sectionId): array
    {
        if (! Schema::hasTable('fees_breackoff')) {
            return ['error' => 'Fees tables not available.'];
        }

        $query = DB::table('fees_breackoff as fb')
            ->join('fees_title as ft', function ($join) use ($instituteId) {
                $join->on('ft.id', '=', 'fb.fee_type_id')
                    ->whereColumn('ft.sub_institute_id', '=', 'fb.sub_institute_id')
                    ->whereColumn('ft.syear', '=', 'fb.syear');
            })
            ->join('tblstudent_enrollment as se', function ($join) use ($instituteId, $syear) {
                $join->on('se.student_id', '=', 'fb.student_id')
                    ->where('se.sub_institute_id', $instituteId)
                    ->where('se.syear', $syear)
                    ->whereNull('se.end_date');
            })
            ->join('tblstudent as s', 's.id', '=', 'fb.student_id')
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->where('fb.balance', '>', 0)
            ->when($standardId, fn ($q) => $q->where('se.standard_id', $standardId))
            ->when($sectionId, fn ($q) => $q->where('se.section_id', $sectionId))
            ->selectRaw('fb.student_id, CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) as student_name,
                s.enrollment_no, se.standard_id, se.section_id, fb.month_id, fb.amount,
                fb.paid_amount, fb.balance as pending_amount, ft.fees_title, ft.display_name')
            ->orderBy('fb.student_id')
            ->get()
            ->map(static fn ($row) => [
                'student_id' => (int) $row->student_id,
                'student_name' => (string) $row->student_name,
                'enrollment_no' => $row->enrollment_no,
                'standard_id' => (int) $row->standard_id,
                'section_id' => (int) $row->section_id,
                'month_id' => (int) $row->month_id,
                'fee_title' => (string) $row->fees_title,
                'display_name' => (string) $row->display_name,
                'fee_amount' => (float) $row->amount,
                'paid_amount' => (float) ($row->paid_amount ?? 0),
                'pending_amount' => (float) $row->pending_amount,
            ])
            ->all();

        $byStudent = [];
        foreach ($query as $row) {
            $sid = $row['student_id'];
            if (! isset($byStudent[$sid])) {
                $byStudent[$sid] = [
                    'student_id' => $sid,
                    'student_name' => $row['student_name'],
                    'enrollment_no' => $row['enrollment_no'],
                    'standard_id' => $row['standard_id'],
                    'section_id' => $row['section_id'],
                    'fees' => [],
                    'total_pending' => 0.0,
                ];
            }
            $byStudent[$sid]['fees'][] = [
                'fee_title' => $row['fee_title'],
                'display_name' => $row['display_name'],
                'month_id' => $row['month_id'],
                'fee_amount' => $row['fee_amount'],
                'paid_amount' => $row['paid_amount'],
                'pending_amount' => $row['pending_amount'],
            ];
            $byStudent[$sid]['total_pending'] += $row['pending_amount'];
        }

        $totalPending = array_sum(array_column($byStudent, 'total_pending'));

        return [
            'students' => array_values($byStudent),
            'total_pending' => round($totalPending, 2),
            'student_count' => count($byStudent),
            'record_count' => count($query),
        ];
    }

    private function fetchFeeSummaryData(?int $instituteId, ?int $syear): array
    {
        if (! Schema::hasTable('fees_breackoff') || ! Schema::hasTable('fees_collect')) {
            return ['error' => 'Fees tables not available.'];
        }

        $demand = DB::table('fees_breackoff as fb')
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->selectRaw('COUNT(DISTINCT fb.student_id) as students_with_fees,
                SUM(fb.amount) as total_demand, SUM(fb.balance) as total_outstanding')
            ->first();

        $collected = DB::table('fees_collect as fc')
            ->where('fc.sub_institute_id', $instituteId)
            ->where('fc.syear', $syear)
            ->where('fc.is_deleted', 'N')
            ->selectRaw('COUNT(*) as receipt_count, SUM(fc.amount) as total_collected,
                SUM(fc.fine) as total_fine, SUM(fc.fees_discount) as total_discount')
            ->first();

        $headwise = DB::table('fees_breackoff as fb')
            ->join('fees_title as ft', function ($join) use ($instituteId) {
                $join->on('ft.id', '=', 'fb.fee_type_id')
                    ->whereColumn('ft.sub_institute_id', '=', 'fb.sub_institute_id')
                    ->whereColumn('ft.syear', '=', 'fb.syear');
            })
            ->where('fb.sub_institute_id', $instituteId)
            ->where('fb.syear', $syear)
            ->selectRaw('ft.fees_title, ft.display_name, SUM(fb.amount) as total_demand,
                SUM(fb.balance) as total_pending')
            ->groupBy('ft.fees_title', 'ft.display_name')
            ->get()
            ->map(static fn ($row) => [
                'fee_title' => (string) $row->fees_title,
                'display_name' => (string) $row->display_name,
                'total_demand' => (float) $row->total_demand,
                'total_pending' => (float) $row->total_pending,
            ])
            ->all();

        return [
            'demand' => [
                'students_with_fees' => (int) ($demand->students_with_fees ?? 0),
                'total_demand' => round((float) ($demand->total_demand ?? 0), 2),
                'total_outstanding' => round((float) ($demand->total_outstanding ?? 0), 2),
            ],
            'collection' => [
                'receipt_count' => (int) ($collected->receipt_count ?? 0),
                'total_collected' => round((float) ($collected->total_collected ?? 0), 2),
                'total_fine' => round((float) ($collected->total_fine ?? 0), 2),
                'total_discount' => round((float) ($collected->total_discount ?? 0), 2),
            ],
            'headwise' => $headwise,
            'syear' => $syear,
            'sub_institute_id' => $instituteId,
        ];
    }

    private function fetchFeeReminderData(?int $instituteId, ?int $syear, ?int $studentId): array
    {
        $pending = $this->fetchPendingFeesData($instituteId, $syear, $studentId);

        $overdue = DB::table('fees_collect as fc')
            ->where('fc.sub_institute_id', $instituteId)
            ->where('fc.syear', $syear)
            ->where('fc.is_deleted', 'N')
            ->where('fc.student_id', $studentId)
            ->when($studentId, fn ($q) => $q->where('fc.student_id', $studentId))
            ->orderByDesc('fc.receiptdate')
            ->first();

        return [
            'pending_fees' => $pending,
            'last_receipt' => $overdue ? [
                'receipt_no' => $overdue->receipt_no,
                'receipt_date' => (string) $overdue->receiptdate,
                'amount' => (float) $overdue->amount,
                'payment_mode' => (string) $overdue->payment_mode,
            ] : null,
        ];
    }

    private function fetchCollectionWorkflowData(?int $instituteId, ?int $syear, ?int $studentId): array
    {
        if ($studentId === null) {
            return ['error' => 'Student ID required for collection workflow.'];
        }

        $details = $this->fetchStudentFeeDetails($studentId, $instituteId, $syear);

        return [
            'student' => $details,
            'action_required' => $details['total_pending'] > 0 ? 'collect_pending_fees' : 'no_pending_amounts',
        ];
    }

    private function fetchRelevantPolicies(array $context): array
    {
        if (! Schema::hasTable('knowledge_base_detail')) {
            return [];
        }

        if (! Schema::hasColumn('knowledge_base_detail', 'category')) {
            return [];
        }

        return DB::table('knowledge_base_detail')
            ->where('sub_institute_id', $context['sub_institute_id'] ?? null)
            ->where('category', 'fees')
            ->where('status', 1)
            ->select('id', 'title', 'content', 'category', 'tags')
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'content' => (string) $row->content,
                'category' => (string) $row->category,
                'tags' => $row->tags ?? '',
            ])
            ->all();
    }

    private function renderPrompt(string $type, array $variables): string
    {
        $prompts = [
            'pending_fees' => "You are a Fees assistant. Answer questions about pending fees using ONLY the data provided below. Never invent, estimate, or guess fee amounts, student names, or dates.\n\n" . $this->formatData($variables),
            'fee_details' => "You are a Fees assistant. Show complete fee details using ONLY the data provided below. Report actual amounts from the records. Never invent or estimate amounts.\n\n" . $this->formatData($variables),
            'pending_fees_report' => "You are a Fees assistant. Generate a pending fees report using ONLY the data provided below. Group by student. Report actual pending amounts. Never invent data.\n\n" . $this->formatData($variables),
            'fee_summary' => "You are a Fees assistant. Summarize fee collection using ONLY the data provided below. Report actual totals. Never invent or estimate.\n\n" . $this->formatData($variables),
            'fee_reminder' => "You are a Fees assistant. Draft a fee reminder message using ONLY the data provided below. Use actual pending amounts. Never invent amounts or student details.\n\n" . $this->formatData($variables),
            'fee_status_explanation' => "You are a Fees assistant. Explain a student's fee status using ONLY the fee data provided below and the policies in the knowledge base. Report actual amounts. Follow the policies when explaining status.\n\nFee Data:\n" . $this->formatData($variables['data'] ?? []) . "\n\nRelevant Policies:\n" . $this->formatData($variables['knowledge_base'] ?? []),
            'collection_workflow' => "You are a Fees assistant. Support the fee collection workflow using ONLY the data provided below. Show total, paid, pending and payment information. For any action that modifies fees data, require explicit confirmation before proceeding.\n\n" . $this->formatData($variables),
            'data_driven_response' => "Answer the user's question using ONLY the real Fees data provided below. Never invent, estimate, or guess any fee information. If the data does not contain enough information to answer, say what is missing.\n\nUser Question: {{query}}\n\nData:\n" . $this->formatData($variables['data'] ?? []),
        ];

        return $prompts[$type] ?? 'Assistant prompt for fees.';
    }

    private function formatData(array $data): string
    {
        if (empty($data)) {
            return 'No data provided.';
        }

        if (isset($data['error'])) {
            return 'Error: ' . $data['error'];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function scopeDescription(array $context): string
    {
        $instituteId = $context['sub_institute_id'] ?? 'unknown';
        $syear = $context['syear'] ?? 'unknown';

        return "Institute ID: {$instituteId}, Academic Year: {$syear}";
    }
}