<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;

class StudentSearchService
{
    public function search(McpRequestContext $context, array $filters): array
    {
        $limit = min(max((int) ($filters['limit'] ?? 20), 1), 50);
        $queryText = trim((string) ($filters['query'] ?? ''));

        $query = DB::table('tblstudent')
            ->selectRaw("
                id,
                enrollment_no,
                admission_id,
                admission_date,
                mobile,
                student_mobile,
                email,
                status,
                student_inactive,
                CONCAT_WS(' ', first_name, middle_name, last_name) AS student_name
            ")
            ->where('sub_institute_id', $context->selectedInstituteId);

        if ($queryText !== '') {
            $query->where(function ($builder) use ($queryText) {
                $builder->where('enrollment_no', 'like', '%' . $queryText . '%')
                    ->orWhere('admission_id', 'like', '%' . $queryText . '%')
                    ->orWhere('mobile', 'like', '%' . $queryText . '%')
                    ->orWhere('student_mobile', 'like', '%' . $queryText . '%')
                    ->orWhere('email', 'like', '%' . $queryText . '%')
                    ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) like ?", ['%' . $queryText . '%']);
            });
        }

        if (($filters['active_only'] ?? true) === true) {
            $query->where(function ($builder) {
                $builder->whereNull('student_inactive')
                    ->orWhere('student_inactive', '!=', 'Y');
            });
        }

        if (! empty($filters['admission_year'])) {
            $query->where('admission_year', $filters['admission_year']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $students = $query
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static function ($student) {
                return [
                    'id' => (int) $student->id,
                    'enrollment_no' => $student->enrollment_no,
                    'admission_id' => $student->admission_id,
                    'student_name' => trim((string) $student->student_name),
                    'admission_date' => $student->admission_date,
                    'mobile' => $student->mobile ?: $student->student_mobile,
                    'email' => $student->email,
                    'status' => $student->status,
                    'student_inactive' => $student->student_inactive,
                ];
            })
            ->all();

        return [
            'query' => $queryText,
            'count' => count($students),
            'students' => $students,
        ];
    }
}
