<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exam results, as recorded.
 *
 * `result_marks` denormalises the exam, standard and subject names onto each row, so a
 * result can be read without joining four masters — and, more usefully, a result stays
 * readable after a standard is renamed. The ids are still returned for anything that
 * needs to navigate.
 *
 * Absences are reported, never averaged. A child marked absent has no score, and folding
 * that into a mean as a zero would turn a missed exam into a failed one.
 */
class ResultReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('result_marks')) {
            return ['count' => 0, 'results' => [], 'note' => 'Exam results are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 100), 1), 300);

        $query = DB::table('result_marks as m')
            ->leftJoin('tblstudent as s', 's.id', '=', 'm.student_id')
            ->where('m.sub_institute_id', $context->selectedInstituteId);

        if (! empty($filters['student_id'])) {
            $query->where('m.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['exam_id'])) {
            $query->where('m.exam_id', (int) $filters['exam_id']);
        }

        foreach (['subject_name', 'standard_name', 'exam_title'] as $column) {
            if (! empty($filters[$column])) {
                $query->where('m.' . $column, 'like', '%' . $filters[$column] . '%');
            }
        }

        $rows = $query
            ->selectRaw(
                "m.id, m.student_id, m.exam_id, m.points, m.per, m.grade, m.is_absent,
                 m.exam_title, m.standard_name, m.subject_name, m.comment,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name"
            )
            ->orderByDesc('m.id')
            ->limit($limit)
            ->get();

        $results = [];
        $percentages = [];
        $absent = 0;

        foreach ($rows as $row) {
            $isAbsent = in_array(strtolower(trim((string) $row->is_absent)), ['y', 'yes', '1', 'true'], true);

            if ($isAbsent) {
                $absent++;
            } elseif ($row->per !== null) {
                $percentages[] = (float) $row->per;
            }

            $results[] = [
                'result_id' => (int) $row->id,
                'student_id' => $row->student_id ? (int) $row->student_id : null,
                'student_name' => trim((string) $row->student_name) ?: null,
                'exam_id' => $row->exam_id ? (int) $row->exam_id : null,
                'exam_title' => $row->exam_title,
                'standard_name' => $row->standard_name,
                'subject_name' => $row->subject_name,
                'points' => $isAbsent ? null : ($row->points === null ? null : (float) $row->points),
                'percentage' => $isAbsent ? null : ($row->per === null ? null : (float) $row->per),
                'grade' => $isAbsent ? null : $row->grade,
                'absent' => $isAbsent,
                'comment' => $row->comment,
            ];
        }

        return [
            'count' => count($results),
            'scored_count' => count($percentages),
            'absent_count' => $absent,
            'average_percentage' => $percentages === []
                ? null
                : round(array_sum($percentages) / count($percentages), 2),
            'results' => $results,
            'rule' => 'The average is taken over scored entries only. An absence carries no score '
                . 'and is counted separately rather than averaged in as a zero.',
        ];
    }

    /**
     * The exams that exist, so a result question can name one.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function exams(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('result_exam_master')) {
            return ['count' => 0, 'exams' => []];
        }

        $query = DB::table('result_exam_master')
            ->where('SubInstituteId', $context->selectedInstituteId);

        if (! empty($filters['standard_id'])) {
            $query->where('standard_id', (int) $filters['standard_id']);
        }

        $exams = $query
            ->orderBy('SortOrder')
            ->limit(min(max((int) ($filters['limit'] ?? 50), 1), 200))
            ->get(['Id', 'Code', 'ExamTitle', 'ExamType', 'standard_id', 'term_id', 'weightage'])
            ->map(static fn ($row) => [
                'exam_id' => (int) $row->Id,
                'code' => $row->Code,
                'title' => $row->ExamTitle,
                'exam_type' => $row->ExamType ? (int) $row->ExamType : null,
                'standard_id' => $row->standard_id ? (int) $row->standard_id : null,
                'term_id' => $row->term_id ? (int) $row->term_id : null,
                'weightage' => $row->weightage ? (int) $row->weightage : null,
            ])
            ->all();

        return [
            'count' => count($exams),
            'exams' => $exams,
        ];
    }
}
