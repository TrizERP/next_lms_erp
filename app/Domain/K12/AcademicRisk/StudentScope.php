<?php

namespace App\Domain\K12\AcademicRisk;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves which students a detector may look at, and their names.
 *
 * Shared by every academic detector so the tenant filter is written once. It matters
 * more here than elsewhere: `lms_online_exam` has no `sub_institute_id` of its own, so
 * the only thing keeping one school's exam rows out of another school's analysis is
 * that the student id set was scoped first. Every detector starts from this class.
 */
class StudentScope
{
    /**
     * How many students one detector sweep reads when the caller does not name them.
     *
     * This is a cohort size, not a result cap, and the two must never be the same
     * number. They were: every detector passed the agent's `limit` — the most signals
     * the caller wants back, 50 — straight in here as the LIMIT, so a sweep over a
     * 3,435-student school read an arbitrary 50 rows and called it "every student in
     * scope". A student with a critical, fully-evidenced signal simply fell outside
     * the window, and the trace reported "nothing crossed its trigger" in good faith.
     *
     * The ceiling exists so one sweep stays bounded on a large estate; when it bites,
     * `total()` lets the caller report the shortfall rather than imply full coverage.
     */
    public const DEFAULT_COHORT = 5000;

    /**
     * Students in scope, as id => display name.
     *
     * @param  int|null  $limit  Cohort size. Null takes DEFAULT_COHORT.
     * @return array<int, string>
     */
    public function students(McpRequestContext $context, ?array $studentIds = null, ?int $limit = null): array
    {
        if (! Schema::hasTable('tblstudent')) {
            return [];
        }

        $query = DB::table('tblstudent')
            ->where('sub_institute_id', $context->selectedInstituteId);

        // Belt and braces: the selected institute has already been checked against the
        // allowed set by McpContextResolver, but re-stating it here means a detector
        // constructed with a hand-built context still cannot stray.
        if ($context->allowedInstituteIds !== []) {
            $query->whereIn('sub_institute_id', $context->allowedInstituteIds);
        }

        if (Schema::hasColumn('tblstudent', 'student_inactive')) {
            $query->where(function ($inner) {
                $inner->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            });
        }

        if ($studentIds !== null) {
            if ($studentIds === []) {
                return [];
            }

            $query->whereIn('id', $studentIds);
        }

        // A student the caller cannot see is simply absent from the result, which is
        // what makes "detect for this student" safe to expose conversationally.
        //
        // The order is stated rather than left to the database. Without it two runs
        // minutes apart could read two different 50-student windows, so a case would
        // appear and disappear with no change to the data or the thresholds — which is
        // exactly what happened, and it is not something a reader could ever diagnose
        // from the trace.
        return $query->orderBy('id')
            ->limit($limit ?? self::DEFAULT_COHORT)
            ->get(['id', 'first_name', 'middle_name', 'last_name'])
            ->mapWithKeys(fn ($row) => [
                (int) $row->id => trim(implode(' ', array_filter([
                    $row->first_name,
                    $row->last_name,
                ]))) ?: ('Student #' . $row->id),
            ])
            ->all();
    }

    /**
     * How many students are in scope altogether.
     *
     * Read alongside the cohort a sweep actually resolved, so "we looked at 5,000 of
     * 12,000" can be said out loud. A scan that silently truncates reads, to a teacher,
     * exactly like a scan that found nothing.
     */
    public function total(McpRequestContext $context): int
    {
        if (! Schema::hasTable('tblstudent')) {
            return 0;
        }

        $query = DB::table('tblstudent')
            ->where('sub_institute_id', $context->selectedInstituteId);

        if ($context->allowedInstituteIds !== []) {
            $query->whereIn('sub_institute_id', $context->allowedInstituteIds);
        }

        if (Schema::hasColumn('tblstudent', 'student_inactive')) {
            $query->where(function ($inner) {
                $inner->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            });
        }

        return (int) $query->count();
    }

    /**
     * A single student's name, or null when out of scope.
     */
    public function name(int $studentId, McpRequestContext $context): ?string
    {
        return $this->students($context, [$studentId], 1)[$studentId] ?? null;
    }

    /**
     * The student's current class placement, used to word explanations and to route
     * an intervention to the right teacher.
     *
     * The active academic year is preferred, but a miss falls back to the most recent
     * enrolment of any year rather than returning null.
     *
     * The fallback is not a convenience. On this estate an institute's roll and its
     * *current term* routinely disagree: institute 1 carries 3,262 of its 3,443
     * enrolments at `syear = 2021` while the term containing today resolves to 2022, so
     * the year-filtered lookup found nothing for 99% of the school. Every explanation
     * then lost its class and division, and every drafted intervention lost the teacher
     * it should have been routed to — silently, because a null placement reads exactly
     * like a student who has genuinely never been enrolled.
     *
     * `matched_academic_year` says which of the two happened, so a caller can tell a
     * current placement from a stale one instead of having to assume.
     *
     * @return array{standard_id:int|null, section_id:int|null, standard_name:string|null, division_name:string|null, syear:int|null, matched_academic_year:bool}|null
     */
    public function placement(int $studentId, McpRequestContext $context): ?array
    {
        if (! Schema::hasTable('tblstudent_enrollment')) {
            return null;
        }

        $base = fn () => DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $context->selectedInstituteId);

        $enrollment = null;
        $matchedYear = false;

        if ($context->academicYear !== null) {
            $enrollment = $base()
                ->where('syear', $context->academicYear)
                ->orderByDesc('id')
                ->first();

            $matchedYear = $enrollment !== null;
        }

        if ($enrollment === null) {
            $enrollment = $base()
                ->orderByDesc('syear')
                ->orderByDesc('id')
                ->first();
        }

        if (! $enrollment) {
            return null;
        }

        $standardName = Schema::hasTable('standard')
            ? DB::table('standard')->where('id', $enrollment->standard_id)->value('name')
            : null;

        $divisionName = Schema::hasTable('division')
            ? DB::table('division')->where('id', $enrollment->section_id)->value('name')
            : null;

        return [
            'standard_id' => $enrollment->standard_id ? (int) $enrollment->standard_id : null,
            'section_id' => $enrollment->section_id ? (int) $enrollment->section_id : null,
            'standard_name' => $standardName,
            'division_name' => $divisionName,
            'syear' => isset($enrollment->syear) ? (int) $enrollment->syear : null,
            'matched_academic_year' => $matchedYear,
        ];
    }
}
