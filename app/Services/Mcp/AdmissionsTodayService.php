<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;

class AdmissionsTodayService
{
    public function todaysAdmissions(McpRequestContext $context, array $filters): array
    {
        $date = $filters['date'] ?? now()->toDateString();
        $limit = min(max((int) ($filters['limit'] ?? 25), 1), 100);

        $query = DB::table('admission_registration as ar')
            ->selectRaw("
                id,
                enquiry_id,
                enquiry_no,
                enrollment_no,
                mother_name,
                mother_mobile_number,
                payment_mode,
                amount,
                admission_date,
                admission_status,
                created_by,
                created_on,
                remarks
            ")
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->whereDate('admission_date', $date);

        if (! empty($filters['admission_status'])) {
            $query->where('admission_status', $filters['admission_status']);
        }

        $records = $query
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static function ($row) {
                return [
                    'id' => (int) $row->id,
                    'enquiry_id' => $row->enquiry_id,
                    'enquiry_no' => $row->enquiry_no,
                    'enrollment_no' => $row->enrollment_no,
                    'mother_name' => $row->mother_name,
                    'mother_mobile_number' => $row->mother_mobile_number,
                    'payment_mode' => $row->payment_mode,
                    'amount' => (float) $row->amount,
                    'admission_date' => $row->admission_date,
                    'admission_status' => $row->admission_status,
                    'created_by' => $row->created_by,
                    'created_on' => $row->created_on,
                    'remarks' => $row->remarks,
                ];
            })
            ->all();

        return [
            'date' => $date,
            'count' => count($records),
            'admissions' => $records,
        ];
    }
}
