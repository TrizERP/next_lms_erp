<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Academic-module scheduled jobs. Same contract as ScheduledJobs: strictly READ ONLY,
 * scoped to the task's school, returning a one-line summary that says only what was
 * measured. Nothing here sends a message or changes a record.
 *
 * Not registered (cannot be measured reliably from the tables that exist):
 *   admissions.application.document_chase  documents are keyed to student_id, not to an application form
 *   examination.marks.entry_reminder       no single marks-entry table; expected entries are not derivable
 *   academics.syllabus.coverage_rollup     no planned-vs-actual coverage data is recorded
 */
class ScheduledJobsAcademic
{
    /** Attendance percentage below which a student is listed by the shortfall scan. */
    private const ATTENDANCE_FLOOR = 75;

    /** @return array<string,callable(PlatformScheduledTask):string> task_key => job */
    public static function registry(): array
    {
        return [
            'admissions.enquiry.follow_up_scan' => [self::class, 'enquiryFollowUps'],
            'students.profile.data_quality' => [self::class, 'studentDataQuality'],
            'attendance.student.absentee_notice' => [self::class, 'absenteesToday'],
            'attendance.student.shortfall_scan' => [self::class, 'attendanceShortfall'],
            'academics.homework.digest' => [self::class, 'homeworkToday'],
        ];
    }

    /** Open enquiries (not approved, no admission form raised) whose follow-up date has passed. */
    public static function enquiryFollowUps(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $q = DB::table('admission_enquiry as e')
            ->where('e.sub_institute_id', $tenant)->whereNull('e.deleted_at')
            ->whereNotNull('e.followup_date')->whereDate('e.followup_date', '<', Carbon::today())
            ->where(fn ($w) => $w->whereNull('e.status')->orWhere('e.status', '!=', 'approve'))
            ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('admission_form as f')
                ->whereColumn('f.enquiry_id', 'e.id')->where('f.sub_institute_id', $tenant));
        $n = (clone $q)->count();
        if ($n === 0) {
            return 'No enquiries are past their follow-up date.';
        }
        $oldest = (clone $q)->min('e.followup_date');

        return "{$n} enquiries are past their follow-up date and have no admission form yet (oldest {$oldest}).";
    }

    /** Active students with a blank mobile number, date of birth, address or father's name. */
    public static function studentDataQuality(PlatformScheduledTask $task): string
    {
        $blank = fn (string $c) => "({$c} is null or trim({$c})='')";
        $noMobile = $blank('mobile');
        $noAddress = $blank('address');
        $noFather = $blank('father_name');

        $r = DB::table('tblstudent')->where('sub_institute_id', (int) $task->sub_institute_id)->where('status', 1)
            ->selectRaw("count(*) total,
                coalesce(sum({$noMobile}),0) no_mobile,
                coalesce(sum(dob is null),0) no_dob,
                coalesce(sum({$noAddress}),0) no_address,
                coalesce(sum({$noFather}),0) no_father,
                coalesce(sum({$noMobile} or dob is null or {$noAddress} or {$noFather}),0) any_gap")
            ->first();
        $total = (int) $r->total;
        if ($total === 0) {
            return 'There are no active students to check.';
        }
        if ((int) $r->any_gap === 0) {
            return "None of the {$total} active students is missing a mobile number, date of birth, address or father's name.";
        }

        return "{$r->any_gap} of {$total} active students have a blank field: {$r->no_mobile} without a mobile number, {$r->no_dob} without a date of birth, {$r->no_address} without an address, {$r->no_father} without a father's name.";
    }

    /** Students marked absent in today's attendance. Does not notify anyone. */
    public static function absenteesToday(PlatformScheduledTask $task): string
    {
        $base = DB::table('attendance_student')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->whereDate('attendance_date', Carbon::today());
        if (! (clone $base)->exists()) {
            return 'No attendance has been marked today, so absentees cannot be listed yet.';
        }
        $absent = (clone $base)->where('attendance_code', 'A')->distinct()->count('student_id');
        $marked = (clone $base)->distinct()->count('student_id');

        return $absent === 0
            ? "No student is marked absent today ({$marked} students marked)."
            : "{$absent} of {$marked} students marked so far today are absent. No notices were sent.";
    }

    /**
     * Students below ATTENDANCE_FLOOR percent over the last 90 days:
     * present days over marked days. The floor is a fixed default, not a school setting.
     */
    public static function attendanceShortfall(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        $rows = DB::table('attendance_student')->where('sub_institute_id', $tenant)
            ->whereDate('attendance_date', '>=', Carbon::today()->subDays(90))
            ->groupBy('student_id')
            ->selectRaw("student_id, count(*) days, sum(attendance_code='P') present")
            ->get();
        if ($rows->isEmpty()) {
            return 'No attendance has been recorded in the last 90 days.';
        }
        $floor = self::ATTENDANCE_FLOOR;
        $total = $rows->count();
        $below = $rows->filter(fn ($r) => $r->days > 0 && ($r->present / $r->days * 100) < $floor)->count();

        return $below === 0
            ? "No student is below {$floor}% attendance over the last 90 days ({$total} students with attendance recorded)."
            : "{$below} of {$total} students with attendance recorded are below {$floor}% present over the last 90 days (present days over marked days).";
    }

    /** Homework given today, by class and subject. Does not send the digest. */
    public static function homeworkToday(PlatformScheduledTask $task): string
    {
        $q = DB::table('homework')->where('sub_institute_id', (int) $task->sub_institute_id)
            ->whereDate('date', Carbon::today());
        $items = (clone $q)->count();
        if ($items === 0) {
            return 'No homework was given today.';
        }
        $classes = (clone $q)->selectRaw('count(distinct standard_id, division_id) c')->value('c');
        $subjects = (clone $q)->distinct()->count('subject_id');

        return "{$items} homework entries were given today across {$classes} classes and {$subjects} subjects. No digest was sent.";
    }
}
