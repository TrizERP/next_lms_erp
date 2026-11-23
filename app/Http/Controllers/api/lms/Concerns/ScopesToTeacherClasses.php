<?php

namespace App\Http\Controllers\api\lms\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Limits a teacher to the students of their own subject and standard.
 *
 * A teacher's allocation is the `timetable` (teacher_id + standard + division + subject for the
 * year) -- the same source the subject dropdowns and the result module already use. A row is
 * visible to a teacher when the timetable puts them on that standard + division + subject, or
 * when they created it themselves. Every other profile (admin, principal, ...) is not narrowed.
 *
 * The profile and user id are read from the session that `api.session` hydrates from the
 * signed token, NOT from the request body, so a caller cannot widen their own view by sending
 * a different `user_profile_name`.
 */
trait ScopesToTeacherClasses
{
    /** True when the signed-in user is a subject teacher whose view must be narrowed. */
    protected function isScopedTeacher(): bool
    {
        $profile = strtolower(trim((string) session('user_profile_name')));

        return $profile === 'teacher' && (int) session('user_id') > 0;
    }

    /**
     * Narrow a list query to the teacher's own classes. A no-op for any other profile.
     *
     * @param  string  $standardCol   e.g. 'a.standard_id'
     * @param  string  $divisionCol   e.g. 'a.division_id'
     * @param  string  $subjectCol    e.g. 'a.subject_id'
     * @param  string|null  $createdByCol  e.g. 'a.created_by' -- rows the teacher created stay visible
     */
    protected function restrictToTeacherClasses(
        Builder $query,
        int|string $subInstituteId,
        int|string $syear,
        string $standardCol,
        string $divisionCol,
        string $subjectCol,
        ?string $createdByCol = null
    ): Builder {
        if (! $this->isScopedTeacher()) {
            return $query;
        }

        $teacherId = (int) session('user_id');

        $query->where(function ($scope) use ($teacherId, $subInstituteId, $syear, $standardCol, $divisionCol, $subjectCol, $createdByCol) {
            $scope->whereExists(function ($sub) use ($teacherId, $subInstituteId, $syear, $standardCol, $divisionCol, $subjectCol) {
                $sub->select(DB::raw(1))
                    ->from('timetable as tt')
                    ->where('tt.teacher_id', $teacherId)
                    ->where('tt.sub_institute_id', $subInstituteId)
                    ->where('tt.syear', $syear)
                    ->whereColumn('tt.standard_id', $standardCol)
                    ->whereColumn('tt.subject_id', $subjectCol)
                    ->where(function ($division) use ($divisionCol) {
                        $division->whereColumn('tt.division_id', $divisionCol)
                            ->orWhereNull($divisionCol);
                    });
            });

            if ($createdByCol !== null) {
                $scope->orWhere($createdByCol, $teacherId);
            }
        });

        return $query;
    }

    /** Whether the signed-in teacher may open one specific row. Always true for non-teachers. */
    protected function teacherMayAccess(
        int|string $subInstituteId,
        int|string $syear,
        int|string|null $standardId,
        int|string|null $divisionId,
        int|string|null $subjectId,
        int|string|null $createdBy = null
    ): bool {
        if (! $this->isScopedTeacher()) {
            return true;
        }

        $teacherId = (int) session('user_id');

        if ($createdBy !== null && (int) $createdBy === $teacherId) {
            return true;
        }

        return DB::table('timetable')
            ->where('teacher_id', $teacherId)
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->when($divisionId !== null && $divisionId !== '', fn ($q) => $q->where('division_id', $divisionId))
            ->exists();
    }
}
