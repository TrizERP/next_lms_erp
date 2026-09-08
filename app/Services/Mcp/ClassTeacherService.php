<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who teaches a class, read from the timetable.
 *
 * `teachers.directory` answers "who works here" from the staff record. It cannot answer
 * "who teaches 8B", because the staff record holds a department, not a class — the
 * teacher-to-class relationship exists only on the timetable, one row per period.
 *
 * That per-period storage is the thing to get right. A teacher taking five periods of
 * one subject for one class is five rows, and listing them raw turns "who teaches 8B"
 * into a schedule dump. So rows are collapsed to one entry per teacher-and-subject, with
 * the period count kept as the useful part of what was collapsed — it is how a reader
 * tells a class teacher from someone who takes them once a week.
 */
class ClassTeacherService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forClass(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('timetable')) {
            return ['count' => 0, 'teachers' => [], 'note' => 'No timetable is installed on this estate.'];
        }

        $unresolved = [];
        $standardId = $this->resolve($context, $filters, 'standard', 'standard', $unresolved);
        $divisionId = $this->resolve($context, $filters, 'division', 'division', $unresolved);
        $teacherId = isset($filters['teacher_id']) ? (int) $filters['teacher_id'] : 0;

        // A name the institute does not have must not silently widen the query to the
        // whole school — the same trap students.directory guards against.
        if ($unresolved !== []) {
            return [
                'count' => 0,
                'teachers' => [],
                'unresolved_filters' => $unresolved,
                'note' => 'This institute has no ' . implode(' or ', $unresolved)
                    . ', so no timetable could be read.',
            ];
        }

        // Without one of these the query is "every timetable row in the school", which
        // for a real institute is tens of thousands of rows and answers nobody's question.
        if ($standardId <= 0 && $teacherId <= 0) {
            return [
                'count' => 0,
                'teachers' => [],
                'note' => 'Name a class or a teacher. Use academics.structure to turn "8B" into a '
                    . 'standard_id and a division_id first.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        // The scoped year only. See the tool's schema for why this is not an argument.
        $year = $context->academicYear;

        $query = DB::table('timetable as t')
            ->join('tbluser as u', function ($join) {
                $join->on('u.id', '=', 't.teacher_id')
                    ->on('u.sub_institute_id', '=', 't.sub_institute_id');
            })
            ->leftJoin('subject as s', 's.id', '=', 't.subject_id')
            ->leftJoin('standard as std', 'std.id', '=', 't.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 't.division_id')
            ->where('t.sub_institute_id', $context->selectedInstituteId)
            ->where('u.status', 1);

        if ($year !== null) {
            $query->where('t.syear', (int) $year);
        }

        if ($standardId > 0) {
            $query->where('t.standard_id', $standardId);
        }

        if ($divisionId > 0) {
            $query->where('t.division_id', $divisionId);
        }

        if ($teacherId > 0) {
            $query->where('t.teacher_id', $teacherId);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('t.subject_id', (int) $filters['subject_id']);
        }

        $rows = $query
            ->selectRaw(
                "t.teacher_id, t.subject_id, t.standard_id, t.division_id,
                 CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS teacher_name,
                 u.email, u.employee_no,
                 s.subject_name, std.name AS standard_name, d.name AS division_name,
                 COUNT(*) AS period_count,
                 GROUP_CONCAT(DISTINCT t.week_day ORDER BY t.week_day) AS week_days"
            )
            ->groupBy(
                't.teacher_id', 't.subject_id', 't.standard_id', 't.division_id',
                'u.first_name', 'u.middle_name', 'u.last_name', 'u.email', 'u.employee_no',
                's.subject_name', 'std.name', 'd.name'
            )
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderByRaw("CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)")
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'count' => 0,
                'teachers' => [],
                'academic_year' => $year !== null ? (int) $year : null,
                'note' => 'No timetable entries match. The class may not be timetabled for this '
                    . 'academic year, rather than having no teachers.',
            ];
        }

        $teachers = $rows->map(static fn ($row) => [
            'teacher_id' => (int) $row->teacher_id,
            'teacher_name' => trim((string) $row->teacher_name),
            'employee_no' => $row->employee_no,
            'email' => $row->email,
            'subject_id' => $row->subject_id !== null ? (int) $row->subject_id : null,
            'subject_name' => $row->subject_name,
            'standard_id' => (int) $row->standard_id,
            'standard_name' => $row->standard_name,
            'division_id' => $row->division_id !== null ? (int) $row->division_id : null,
            'division_name' => $row->division_name,
            'periods_per_week' => (int) $row->period_count,
            'week_days' => $row->week_days !== null ? explode(',', (string) $row->week_days) : [],
        ])->all();

        return [
            'count' => count($teachers),
            'limit' => $limit,
            'academic_year' => $year !== null ? (int) $year : null,
            'distinct_teachers' => count(array_unique(array_column($teachers, 'teacher_id'))),
            'teachers' => $teachers,
        ];
    }

    /**
     * An id from `<key>_id`, or resolved from `<key>_name`.
     *
     * Names matter more than they look. A planner reads the schema and fills it from the
     * user's sentence — "who teaches Standard-10 A" gives it a standard and a division by
     * name, and an id-only tool leaves it nothing to send. That is exactly how this tool
     * and lms.courses both answered "no match" for classes that plainly have data, while
     * students.directory answered correctly: it accepts names.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $unresolved
     */
    private function resolve(
        McpRequestContext $context,
        array $filters,
        string $key,
        string $table,
        array &$unresolved
    ): int {
        if (! empty($filters[$key . '_id'])) {
            return (int) $filters[$key . '_id'];
        }

        $name = trim((string) ($filters[$key . '_name'] ?? ''));

        if ($name === '' || ! Schema::hasTable($table)) {
            return 0;
        }

        $id = DB::table($table)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('name', $name)
            ->value('id');

        if ($id === null) {
            $unresolved[] = $key . ' "' . $name . '"';

            return 0;
        }

        return (int) $id;
    }
}
