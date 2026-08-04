<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;

class FeesCollectionReportService
{
    public function report(McpRequestContext $context, array $filters): array
    {
        $fromDate = $filters['from_date'] ?? now()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();
        $limit = min(max((int) ($filters['limit'] ?? 25), 1), 100);

        $baseQuery = DB::table('fees_collect as fc')
            ->join('tblstudent as s', 's.id', '=', 'fc.student_id')
            ->selectRaw("
                fc.id,
                fc.receipt_no,
                fc.student_id,
                fc.receiptdate,
                fc.payment_mode,
                fc.amount,
                fc.fine,
                fc.fees_discount,
                fc.term_id,
                fc.created_date,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                s.enrollment_no
            ")
            ->where('fc.sub_institute_id', $context->selectedInstituteId)
            ->where('fc.is_deleted', 'N')
            ->whereBetween('fc.receiptdate', [$fromDate, $toDate]);

        if ($context->academicYear !== null) {
            $baseQuery->where('fc.syear', $context->academicYear);
        }

        if (! empty($filters['student_id'])) {
            $baseQuery->where('fc.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['payment_mode'])) {
            $baseQuery->where('fc.payment_mode', $filters['payment_mode']);
        }

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_receipts, COALESCE(SUM(fc.amount), 0) as total_amount, COALESCE(SUM(fc.fine), 0) as total_fine, COALESCE(SUM(fc.fees_discount), 0) as total_discount')
            ->first();

        $receipts = $baseQuery
            ->orderByDesc('fc.receiptdate')
            ->orderByDesc('fc.id')
            ->limit($limit)
            ->get()
            ->map(static function ($row) {
                return [
                    'id' => (int) $row->id,
                    'receipt_no' => $row->receipt_no,
                    'student_id' => (int) $row->student_id,
                    'student_name' => trim((string) $row->student_name),
                    'enrollment_no' => $row->enrollment_no,
                    'receipt_date' => $row->receiptdate,
                    'payment_mode' => $row->payment_mode,
                    'amount' => (float) $row->amount,
                    'fine' => (float) $row->fine,
                    'discount' => (float) $row->fees_discount,
                    'term_id' => $row->term_id,
                    'created_date' => $row->created_date,
                ];
            })
            ->all();

        return [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'student_id' => $filters['student_id'] ?? null,
                'payment_mode' => $filters['payment_mode'] ?? null,
                'academic_year' => $context->academicYear,
            ],
            'summary' => [
                'total_receipts' => (int) ($summary->total_receipts ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
                'total_fine' => (float) ($summary->total_fine ?? 0),
                'total_discount' => (float) ($summary->total_discount ?? 0),
            ],
            'receipts' => $receipts,
        ];
    }
}
