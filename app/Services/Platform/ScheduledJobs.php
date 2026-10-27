<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Jobs the scheduler can run, one per module task.
 *
 * Each job is READ ONLY against the module's own tables: it counts and reports,
 * and returns a one-line summary the dispatcher stores as the run's message and in
 * the audit trail. A job never changes another module's records. The one job that
 * writes (`reports.export.cleanup`) only touches the platform's own report files.
 *
 * Every job runs for the one school the task row belongs to.
 *
 * Add a job by adding a method here and a line in `registry()`; the dispatcher and
 * the screen pick it up from there.
 */
class ScheduledJobs
{
    /** @return array<string,callable(PlatformScheduledTask):string> task_key => job */
    public static function registry(): array
    {
        // Jobs for the other modules live beside this file, grouped by area, so
        // each can be read on its own. A group that is absent simply adds nothing.
        $groups = [];
        foreach ([ScheduledJobsAcademic::class, ScheduledJobsOperations::class, ScheduledJobsFinance::class] as $group) {
            if (class_exists($group)) {
                $groups[] = $group::registry();
            }
        }

        $own = [
            'fees.defaulter.overdue_scan' => [self::class, 'feesInstalmentsPastDue'],
            'library.circulation.overdue_scan' => [self::class, 'libraryOverdue'],
            'attendance.student.unmarked_check' => [self::class, 'attendanceUnmarked'],
            'lms.assignment.overdue_scan' => [self::class, 'lmsAssignmentsOverdue'],
            'lms.course.hierarchy_validation' => [self::class, 'courseCatalogueHierarchyValidation'],
            'reports.export.cleanup' => [self::class, 'reportExportCleanup'],
        ];

        return array_merge($own, ...$groups);
    }

    /**
     * Instalments whose late date has passed, for the most recent year that has
     * late dates. This is the due-date side only: whether each student has paid is
     * held elsewhere and is not evaluated here, so this does not count defaulters.
     */
    public static function feesInstalmentsPastDue(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $syear = DB::table('fees_late_master')->where('sub_institute_id', $tenant)->max('syear');
        if (! $syear) {
            return 'No late dates are set up for this school, so there is nothing to check.';
        }

        $q = DB::table('fees_late_master')->where('sub_institute_id', $tenant)->where('syear', $syear)
            ->where('status', '1')->whereDate('late_date', '<', Carbon::today());
        $rows = (clone $q)->count();
        $classes = (clone $q)->distinct()->count('standard_id');
        $oldest = (clone $q)->min('late_date');

        return $rows === 0
            ? "No instalments are past their late date for {$syear}."
            : "{$rows} instalments across {$classes} classes are past their late date for {$syear} (oldest {$oldest}). Payment status per student was not evaluated.";
    }

    public static function libraryOverdue(PlatformScheduledTask $task): string
    {
        $q = DB::table('library_book_circulations')
            ->where('sub_institute_id', (int) $task->sub_institute_id)
            ->whereNull('return_date')->whereNull('deleted_at')
            ->whereDate('due_date', '<', Carbon::today());
        $books = (clone $q)->count();
        if ($books === 0) {
            return 'No library books are overdue.';
        }
        $students = (clone $q)->distinct()->count('student_id');
        $oldest = (clone $q)->min('due_date');

        return "{$books} library books are overdue, held by {$students} students (oldest due {$oldest}).";
    }

    /**
     * Classes that took attendance in the last 14 days but have not today. Skips
     * Sundays. A class that has never taken attendance is not reported, so an
     * unused section does not look like a missed one.
     */
    public static function attendanceUnmarked(PlatformScheduledTask $task): string
    {
        $today = Carbon::today();
        if ($today->isSunday()) {
            return 'Today is a Sunday, so no attendance is expected.';
        }
        $tenant = (int) $task->sub_institute_id;

        $recent = DB::table('attendance_student')->where('sub_institute_id', $tenant)
            ->whereDate('attendance_date', '>=', $today->copy()->subDays(14))->whereDate('attendance_date', '<', $today)
            ->select('standard_id', 'section_id')->distinct()->get();
        $marked = DB::table('attendance_student')->where('sub_institute_id', $tenant)->whereDate('attendance_date', $today)
            ->select('standard_id', 'section_id')->distinct()->get()
            ->map(fn ($r) => $r->standard_id.'-'.$r->section_id)->flip();

        $missing = $recent->filter(fn ($r) => ! isset($marked[$r->standard_id.'-'.$r->section_id]))->count();

        return $missing === 0
            ? "Every class that took attendance recently has marked it today ({$recent->count()} classes)."
            : "{$missing} of {$recent->count()} classes that took attendance recently have not marked it today.";
    }

    public static function lmsAssignmentsOverdue(PlatformScheduledTask $task): string
    {
        $q = DB::table('lms_assignment')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->where('student_submission_status', 'N')->whereDate('submission_date', '<', Carbon::today());
        $count = (clone $q)->count();
        if ($count === 0) {
            return 'No assignments are past their submission date.';
        }
        $students = (clone $q)->distinct()->count('student_id');
        $oldest = (clone $q)->min('submission_date');

        return "{$count} assignments are past their submission date and not submitted, across {$students} students (oldest due {$oldest}).";
    }

    /** Removes scheduled-report files older than 30 days from File storage (a soft remove; the stored copy stays). */
    public static function reportExportCleanup(PlatformScheduledTask $task): string
    {
        $n = DB::table('platform_file_attachments')
            ->where('sub_institute_id', (int) $task->sub_institute_id)->where('entity_type', 'report_schedule')
            ->whereNull('deleted_at')->where('created_at', '<', now()->subDays(30))
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        return "Removed {$n} scheduled report files older than 30 days.";
    }

    /**
     * Course Catalogue Reader Agent - Continuous 5-tier content hierarchy validation.
     * Evaluates: Standard -> Subject -> Chapter -> Topic -> Intelligence.
     */
    public static function courseCatalogueHierarchyValidation(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $validator = app(\App\Services\Lms\CourseCatalogueHierarchyValidator::class);
        $res = $validator->validate($tenant, false);
        $counts = $res['counts'] ?? [];

        return sprintf(
            'Course Catalogue Reader: %d standards, %d subjects, %d chapters, %d topics, %d intel configs checked (%d complete 5-tier chains, %d issues detected). Status: %s.',
            $counts['standards_checked'] ?? 0,
            $counts['subjects_checked'] ?? 0,
            $counts['chapters_checked'] ?? 0,
            $counts['topics_checked'] ?? 0,
            $counts['intelligence_configs_checked'] ?? 0,
            $counts['complete_hierarchies_validated'] ?? 0,
            $counts['total_issues_detected'] ?? 0,
            $res['status'] ?? 'ok'
        );
    }
}
