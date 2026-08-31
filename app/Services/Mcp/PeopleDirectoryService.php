<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who is in the school, by where they sit in it.
 *
 * `students.search` already finds a person by name or enrolment number. This answers the
 * other half — "everyone in 8B", "the teachers" — which is what a cohort question needs
 * and what a name search cannot express.
 *
 * Placement comes from `tblstudent_enrollment`, scoped to the academic year on the
 * request. A student with no enrolment row for that year is genuinely unplaced rather
 * than missing, and the count says so instead of quietly shrinking the class.
 */
class PeopleDirectoryService
{
    /**
     * Students, filtered by where they are enrolled.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function students(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('tblstudent') || ! Schema::hasTable('tblstudent_enrollment')) {
            return ['count' => 0, 'students' => [], 'unresolved_filters' => []];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as e', 'e.student_id', '=', 's.id')
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->leftJoin('academic_section as g', 'g.id', '=', 'e.grade_id')
            ->where('s.sub_institute_id', $context->selectedInstituteId)
            ->where('e.sub_institute_id', $context->selectedInstituteId);

        if ($context->academicYear !== null) {
            $query->where('e.syear', $context->academicYear);
        }

        foreach ([
            'grade_id' => 'e.grade_id',
            'standard_id' => 'e.standard_id',
            'division_id' => 'e.section_id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        // Named rather than numbered: a question says "8B", not "standard 14".
        $unresolved = [];

        if (! empty($filters['standard_name'])) {
            $standardId = $this->idFor('standard', 'name', (string) $filters['standard_name'], $context);

            $standardId === null
                ? $unresolved[] = 'standard "' . $filters['standard_name'] . '"'
                : $query->where('e.standard_id', $standardId);
        }

        if (! empty($filters['division_name'])) {
            $divisionId = $this->idFor('division', 'name', (string) $filters['division_name'], $context);

            $divisionId === null
                ? $unresolved[] = 'division "' . $filters['division_name'] . '"'
                : $query->where('e.section_id', $divisionId);
        }

        // A filter that names something the school does not have must not silently
        // return the whole cohort — that is how "students in 9C" becomes "every student".
        if ($unresolved !== []) {
            return [
                'count' => 0,
                'students' => [],
                'unresolved_filters' => $unresolved,
                'note' => 'This institute has no ' . implode(' or ', $unresolved)
                    . ' for the current academic year, so no student list could be produced.',
            ];
        }

        if (($filters['active_only'] ?? true) === true) {
            $query->where('s.status', 1)
                ->where(function ($builder) {
                    $builder->whereNull('s.student_inactive')
                        ->orWhere('s.student_inactive', '!=', 'Y');
                });
        }

        $students = $query
            ->selectRaw(
                "s.id, s.enrollment_no, s.mobile, s.email,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                 e.roll_no, e.standard_id, e.section_id, e.grade_id,
                 st.name AS standard_name, d.name AS division_name, g.title AS grade_title"
            )
            ->orderBy('st.name')
            ->orderBy('d.name')
            ->orderByRaw('CAST(e.roll_no AS UNSIGNED)')
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [
                'student_id' => (int) $row->id,
                'student_name' => trim((string) $row->student_name),
                'enrollment_no' => $row->enrollment_no,
                'roll_no' => $row->roll_no,
                'grade' => $row->grade_title,
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'standard_id' => $row->standard_id ? (int) $row->standard_id : null,
                'division_id' => $row->section_id ? (int) $row->section_id : null,
                'mobile' => $row->mobile,
                'email' => $row->email,
            ])
            ->all();

        return [
            'academic_year' => $context->academicYear,
            'count' => count($students),
            'limit' => $limit,
            'students' => $students,
            'unresolved_filters' => [],
        ];
    }

    /**
     * Teachers and other staff, by profile.
     *
     * Profile matching is a `like` on `tbluserprofilemaster.name` because that is how the
     * estate's own admin API identifies a teacher — there is no role flag on the user
     * row to read instead.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function teachers(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('tbluser') || ! Schema::hasTable('tbluserprofilemaster')) {
            return ['count' => 0, 'teachers' => []];
        }

        $profile = trim((string) ($filters['profile'] ?? 'Teacher'));
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', function ($join) {
                $join->on('p.id', '=', 'u.user_profile_id')
                    ->on('p.sub_institute_id', '=', 'u.sub_institute_id');
            })
            ->leftJoin('hrms_departments as dep', 'dep.id', '=', 'u.department_id')
            ->where('u.sub_institute_id', $context->selectedInstituteId)
            ->where('u.status', 1);

        if ($profile !== '') {
            $query->where('p.name', 'like', '%' . $profile . '%');
        }

        $search = trim((string) ($filters['query'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->whereRaw("CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) like ?", ['%' . $search . '%'])
                    ->orWhere('u.email', 'like', '%' . $search . '%')
                    ->orWhere('u.employee_no', 'like', '%' . $search . '%');
            });
        }

        $teachers = $query
            ->selectRaw(
                "u.id, u.email, u.mobile, u.employee_no, u.user_profile_id,
                 CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name,
                 p.name AS profile_name, dep.department AS department_name"
            )
            ->orderByRaw("CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)")
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [
                'user_id' => (int) $row->id,
                'full_name' => trim((string) $row->full_name),
                'profile' => $row->profile_name,
                'department' => $row->department_name,
                'employee_no' => $row->employee_no,
                'email' => $row->email,
                'mobile' => $row->mobile,
            ])
            ->all();

        return [
            'profile_filter' => $profile,
            'count' => count($teachers),
            'teachers' => $teachers,
        ];
    }

    /**
     * Departments, with their head and how many people sit in each.
     *
     * @return array<string, mixed>
     */
    public function departments(McpRequestContext $context): array
    {
        if (! Schema::hasTable('hrms_departments')) {
            return ['count' => 0, 'departments' => []];
        }

        $departments = DB::table('hrms_departments as d')
            ->leftJoin('tbluser as head', 'head.id', '=', 'd.head_user_id')
            ->where('d.sub_institute_id', $context->selectedInstituteId)
            ->where('d.status', 1)
            ->whereNull('d.deleted_at')
            ->selectRaw(
                "d.id, d.department, d.code, d.description, d.parent_id,
                 CONCAT_WS(' ', head.first_name, head.middle_name, head.last_name) AS head_name"
            )
            ->orderBy('d.sort_order')
            ->get();

        $headcounts = DB::table('tbluser')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('status', 1)
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->pluck(DB::raw('count(*)'), 'department_id');

        return [
            'count' => $departments->count(),
            'departments' => $departments->map(static fn ($row) => [
                'department_id' => (int) $row->id,
                'name' => $row->department,
                'code' => $row->code,
                'description' => $row->description,
                'parent_id' => $row->parent_id ? (int) $row->parent_id : null,
                'head' => trim((string) $row->head_name) ?: null,
                'active_staff' => (int) ($headcounts[$row->id] ?? 0),
            ])->all(),
            'note' => 'Staff counts include every active user assigned to the department, '
                . 'whatever their profile.',
        ];
    }

    /**
     * One student's path through the school, year by year.
     *
     * This is the part of the old LMS dashboard worth keeping: which standard a child was
     * in each year, and how long they have been here. It answers "has this student been
     * held back?" and "how long have we known them?" — neither of which any other tool
     * can, because every other view is scoped to the current year.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function history(McpRequestContext $context, array $filters): array
    {
        $studentId = (int) ($filters['student_id'] ?? 0);

        if ($studentId <= 0) {
            return ToolResult::failure(
                'students.history',
                'A valid student is required.',
                'MISSING_STUDENT_ID'
            );
        }

        if (! Schema::hasTable('tblstudent_enrollment')) {
            return ToolResult::failure(
                'students.history',
                'Enrolment history is not recorded in this estate.',
                'NO_ENROLLMENT_TABLE'
            );
        }

        $student = DB::table('tblstudent')
            ->where('id', $studentId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->selectRaw("id, enrollment_no, admission_date, CONCAT_WS(' ', first_name, middle_name, last_name) AS student_name")
            ->first();

        if (! $student) {
            return ToolResult::failure(
                'students.history',
                'That student is not in your scope.',
                'STUDENT_NOT_IN_SCOPE'
            );
        }

        // Hard cap. Real estates contain students with thousands of enrolment rows —
        // duplicated imports, all carrying syear 0 — and returning every one of them
        // would flood a reply and stall the request for a record that says nothing.
        $cap = 200;

        $totalRows = DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->count();

        $rows = DB::table('tblstudent_enrollment as e')
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->leftJoin('academic_section as g', 'g.id', '=', 'e.grade_id')
            ->where('e.student_id', $studentId)
            ->where('e.sub_institute_id', $context->selectedInstituteId)
            ->orderBy('e.syear')
            ->orderBy('e.id')
            ->limit($cap)
            ->get([
                'e.id', 'e.syear', 'e.roll_no', 'e.standard_id', 'e.section_id',
                'e.start_date', 'e.end_date', 'st.name as standard_name',
                'd.name as division_name', 'g.title as grade_title',
            ]);

        $enrolments = $rows->map(static fn ($row) => [
            // A syear of 0 is not year zero; it is an unset column. Presenting it as a
            // year would put a fictional date in front of a teacher.
            'academic_year' => ($row->syear ?? 0) > 0 ? (int) $row->syear : null,
            'grade' => $row->grade_title,
            'standard_name' => $row->standard_name,
            'division_name' => $row->division_name,
            'roll_no' => $row->roll_no,
            'standard_id' => $row->standard_id ? (int) $row->standard_id : null,
            'start_date' => $row->start_date,
            'end_date' => $row->end_date,
        ])->all();

        // Years, not rows. The field is called `years_enrolled`, and a duplicated import
        // must not turn one year at a school into a claim of two thousand.
        $years = array_values(array_unique(array_filter(
            array_column($enrolments, 'academic_year'),
            static fn ($year) => $year !== null
        )));
        sort($years);

        $undated = count(array_filter(
            $enrolments,
            static fn (array $e) => $e['academic_year'] === null
        ));

        // A standard appearing in two *different* years is what repeating a year looks
        // like here. Rows sharing one year are duplicates, not a repeat, so they are
        // counted by distinct year rather than by row.
        $byStandard = [];

        foreach ($enrolments as $enrolment) {
            $name = $enrolment['standard_name'];
            $year = $enrolment['academic_year'];

            if ($name !== null && $year !== null) {
                $byStandard[$name][$year] = $year;
            }
        }

        $repeated = [];

        foreach ($byStandard as $name => $seen) {
            if (count($seen) > 1) {
                $repeated[] = ['standard_name' => (string) $name, 'years' => array_values($seen)];
            }
        }

        $truncated = $totalRows > $cap;
        $suspect = $undated > 0 || $truncated;

        $notes = [];

        if ($truncated) {
            $notes[] = sprintf(
                'This student has %d enrolment rows; only the first %d were read. A count that high '
                . 'is nearly always duplicated import data rather than a real history.',
                $totalRows,
                $cap
            );
        }

        if ($undated > 0) {
            $notes[] = sprintf(
                '%d row%s carry no academic year and are excluded from the year counts — they cannot '
                . 'be placed on a timeline.',
                $undated,
                $undated === 1 ? '' : 's'
            );
        }

        if ($repeated !== []) {
            $notes[] = 'A standard recorded in more than one year usually means the year was repeated, '
                . 'but it can also be a correction to an earlier record — check the rows before '
                . 'treating it as a finding.';
        }

        // A blank name is real in this data. Falling back to the enrolment number, then
        // the id, keeps the sentence readable instead of opening with a stray space.
        $name = trim((string) $student->student_name)
            ?: (trim((string) $student->enrollment_no) ?: 'Student #' . $student->id);

        return ToolResult::success(
            'students.history',
            $years === []
                ? sprintf('%s has no dated enrolment history on record.', $name)
                : sprintf(
                    '%s has been enrolled across %d academic year%s (%s–%s).',
                    $name,
                    count($years),
                    count($years) === 1 ? '' : 's',
                    $years[0],
                    end($years)
                ),
            [
                'student' => [
                    'student_id' => (int) $student->id,
                    'student_name' => $name,
                    'enrollment_no' => $student->enrollment_no,
                    'admission_date' => $student->admission_date,
                ],
                'years_enrolled' => count($years),
                'academic_years' => $years,
                'first_year' => $years[0] ?? null,
                'latest_year' => $years === [] ? null : end($years),
                'enrolment_rows_total' => $totalRows,
                'enrolment_rows_read' => count($enrolments),
                'undated_rows' => $undated,
                'data_quality_suspect' => $suspect,
                'enrolments' => $enrolments,
                'repeated_standards' => $repeated,
                'note' => $notes === [] ? null : implode(' ', $notes),
            ]
        );
    }

    private function idFor(string $table, string $column, string $value, McpRequestContext $context): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $id = DB::table($table)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where($column, $value)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
