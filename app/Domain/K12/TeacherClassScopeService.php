<?php

namespace App\Domain\K12;

use Illuminate\Support\Facades\DB;

/**
 * Whether a teacher's assigned classes reach a given student/standard, and —
 * batch form — which students they reach at all.
 *
 * The two per-target checks were previously duplicated, with slightly
 * different join shapes, in `PalApiAuth::isStudentInTeacherScope()` and
 * `StudentActivityReportController::isClassInTeacherScope()`. Both now
 * delegate here unchanged (same tables, same SQL); this class adds the one
 * check neither needed before: the full set of a teacher's reachable
 * students, for callers (like the centralized Knowledge Graph browser) that
 * must filter a batch rather than check one target.
 *
 * Two sources count as "assigned", matching every other scope check in this
 * codebase: `class_teacher` (homeroom) and `timetable` (subject teacher).
 * Matched on the SAME `syear` on both sides (assignment and enrollment)
 * rather than resolving "the current year" independently — there is no
 * authoritative current-year flag on `academic_year` — which both avoids
 * guessing and fails closed (a student with no matching-year enrollment
 * simply won't match).
 */
class TeacherClassScopeService
{
    public function isStudentInTeacherScope(int $teacherId, int $subInstituteId, int $studentId): bool
    {
        $classTeacherMatch = DB::table('class_teacher as ct')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.standard_id', '=', 'ct.standard_id')
                    ->on('se.section_id', '=', 'ct.division_id')
                    ->on('se.sub_institute_id', '=', 'ct.sub_institute_id')
                    ->on('se.syear', '=', 'ct.syear');
            })
            ->where('ct.teacher_id', $teacherId)
            ->where('ct.sub_institute_id', $subInstituteId)
            ->where('se.student_id', $studentId)
            ->exists();

        if ($classTeacherMatch) {
            return true;
        }

        return DB::table('timetable as t')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.standard_id', '=', 't.standard_id')
                    ->on('se.section_id', '=', 't.division_id')
                    ->on('se.sub_institute_id', '=', 't.sub_institute_id')
                    ->on('se.syear', '=', 't.syear');
            })
            ->where('t.teacher_id', $teacherId)
            ->where('t.sub_institute_id', $subInstituteId)
            ->where('se.student_id', $studentId)
            ->exists();
    }

    public function isClassInTeacherScope(int $teacherId, int $subInstituteId, int $standardId, ?int $sectionId, string $syear): bool
    {
        $classTeacherMatch = DB::table('class_teacher')
            ->where('teacher_id', $teacherId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('syear', $syear)
            ->when($sectionId !== null, fn ($q) => $q->where('division_id', $sectionId))
            ->exists();

        if ($classTeacherMatch) {
            return true;
        }

        return DB::table('timetable')
            ->where('teacher_id', $teacherId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('syear', $syear)
            ->when($sectionId !== null, fn ($q) => $q->where('division_id', $sectionId))
            ->exists();
    }

    /**
     * Course-grain variant of `isClassInTeacherScope()`, for endpoints keyed
     * on (standard, subject) rather than just (standard, section) — e.g. the
     * Context Graph's course/assessment context endpoints.
     *
     * A homeroom `class_teacher` still reaches the whole class regardless of
     * subject (correct, unchanged from `isClassInTeacherScope()`). A plain
     * subject teacher via `timetable`, however, must additionally match
     * `subject_id` — `isClassInTeacherScope()` deliberately does not require
     * this (it answers "is this teacher assigned to this class at all", which
     * is right for its own callers), but a course-grain check that ignored
     * subject would let any subject teacher in the class reach every OTHER
     * subject's course/assessment data too.
     */
    public function isCourseInTeacherScope(int $teacherId, int $subInstituteId, int $standardId, int $subjectId, string $syear): bool
    {
        $classTeacherMatch = DB::table('class_teacher')
            ->where('teacher_id', $teacherId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('syear', $syear)
            ->exists();

        if ($classTeacherMatch) {
            return true;
        }

        return DB::table('timetable')
            ->where('teacher_id', $teacherId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->where('syear', $syear)
            ->exists();
    }

    /**
     * Every student id this teacher reaches via either source, any syear —
     * the batch form `isStudentInTeacherScope()` has no equivalent for. An
     * empty result means "no assigned classes", which callers must treat as
     * "reaches nobody," not "unrestricted."
     *
     * @return int[]
     */
    public function allowedStudentIds(int $teacherId, int $subInstituteId): array
    {
        $viaClassTeacher = DB::table('class_teacher as ct')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.standard_id', '=', 'ct.standard_id')
                    ->on('se.section_id', '=', 'ct.division_id')
                    ->on('se.sub_institute_id', '=', 'ct.sub_institute_id')
                    ->on('se.syear', '=', 'ct.syear');
            })
            ->where('ct.teacher_id', $teacherId)
            ->where('ct.sub_institute_id', $subInstituteId)
            ->pluck('se.student_id');

        $viaTimetable = DB::table('timetable as t')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.standard_id', '=', 't.standard_id')
                    ->on('se.section_id', '=', 't.division_id')
                    ->on('se.sub_institute_id', '=', 't.sub_institute_id')
                    ->on('se.syear', '=', 't.syear');
            })
            ->where('t.teacher_id', $teacherId)
            ->where('t.sub_institute_id', $subInstituteId)
            ->pluck('se.student_id');

        return $viaClassTeacher->merge($viaTimetable)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
