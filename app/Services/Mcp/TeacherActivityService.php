<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which teachers did their daily tasks, on a given day.
 *
 * Five yes/no checks per teacher: did they mark attendance, set homework, check
 * homework, answer a parent, and handle a leave request. The estate's own report builds
 * exactly these, and this reproduces its query rather than approximating it — the flags
 * are compared between screens and must not disagree.
 *
 * One thing the query does that is easy to lose and worth keeping: teachers are joined
 * through `timetable`, so only staff who actually have periods this year appear. A
 * teacher on leave for the year should not be listed as having failed to mark
 * attendance.
 */
class TeacherActivityService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dailyReport(McpRequestContext $context, array $filters): array
    {
        foreach (['tbluser', 'tbluserprofilemaster', 'timetable'] as $table) {
            if (! Schema::hasTable($table)) {
                return ['count' => 0, 'teachers' => [], 'note' => 'The daily report tables are not present.'];
            }
        }

        $date = (string) ($filters['date'] ?? now()->toDateString());

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ToolResult::failure(
                'teachers.daily_report',
                'A date in YYYY-MM-DD format is required.',
                'INVALID_DATE'
            );
        }

        $query = DB::table('tbluser as teacher')
            ->join('tbluserprofilemaster as profile', function ($join) {
                $join->on('profile.id', '=', 'teacher.user_profile_id')
                    ->where('profile.name', 'Teacher');
            })
            // Only teachers with periods this year — see the class docblock.
            ->join('timetable', function ($join) use ($context) {
                $join->on('timetable.teacher_id', '=', 'teacher.id')
                    ->where('timetable.syear', $context->academicYear);
            })
            ->select(
                'teacher.id as teacher_id',
                DB::raw("concat_ws(' ', teacher.first_name, teacher.middle_name, teacher.last_name) as teacher_name")
            )
            ->selectRaw("IF(EXISTS(SELECT 1 FROM attendance_student a WHERE a.created_by = teacher.id AND DATE(a.created_on) = ?), 'Yes', 'No') as student_attendance", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM homework h WHERE h.created_by = teacher.id AND DATE(h.created_on) = ?), 'Yes', 'No') as homework_assign", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM homework h WHERE h.created_by = teacher.id AND DATE(h.submission_date) = ? AND h.completion_status = 'Y'), 'Yes', 'No') as homework_check", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM parent_communication p WHERE p.reply_by = teacher.id AND DATE(p.created_at) = ?), 'Yes', 'No') as parent_comm", [$date])
            ->selectRaw("IF(EXISTS(SELECT 1 FROM leave_applications l WHERE l.reply = teacher.id AND DATE(l.apply_date) = ?), 'Yes', 'No') as student_leave", [$date])
            ->where('teacher.sub_institute_id', $context->selectedInstituteId)
            ->where('teacher.status', 1)
            ->groupBy('teacher.id', 'teacher.first_name', 'teacher.middle_name', 'teacher.last_name');

        $status = $filters['status'] ?? null;

        if ($status === 'active') {
            $query->havingRaw("student_attendance = 'Yes' OR homework_assign = 'Yes' OR homework_check = 'Yes' OR parent_comm = 'Yes' OR student_leave = 'Yes'");
        } elseif ($status === 'inactive') {
            $query->havingRaw("student_attendance = 'No' AND homework_assign = 'No' AND homework_check = 'No' AND parent_comm = 'No' AND student_leave = 'No'");
        }

        if (! empty($filters['teacher_id'])) {
            $query->where('teacher.id', (int) $filters['teacher_id']);
        }

        $rows = $query
            ->orderBy('teacher.first_name')
            ->limit(min(max((int) ($filters['limit'] ?? 100), 1), 300))
            ->get();

        $teachers = $rows->map(static function ($row) {
            $tasks = [
                'marked_attendance' => $row->student_attendance === 'Yes',
                'assigned_homework' => $row->homework_assign === 'Yes',
                'checked_homework' => $row->homework_check === 'Yes',
                'answered_parent' => $row->parent_comm === 'Yes',
                'handled_leave' => $row->student_leave === 'Yes',
            ];

            return [
                'teacher_id' => (int) $row->teacher_id,
                'teacher_name' => trim((string) $row->teacher_name),
                'tasks' => $tasks,
                'tasks_done' => count(array_filter($tasks)),
                'any_activity' => in_array(true, $tasks, true),
            ];
        })->all();

        $active = count(array_filter($teachers, static fn (array $t) => $t['any_activity']));

        return [
            'date' => $date,
            'academic_year' => $context->academicYear,
            'count' => count($teachers),
            'with_activity' => $active,
            'without_activity' => count($teachers) - $active,
            'teachers' => $teachers,
            'note' => 'Only teachers with timetabled periods this academic year are included, so '
                . 'staff who do not teach are not reported as having missed anything. Each flag is '
                . 'whether the record exists for that date, not a judgement of quality.',
        ];
    }
}
