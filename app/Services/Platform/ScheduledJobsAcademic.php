<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academic-module scheduled jobs. Same contract as ScheduledJobs: strictly READ ONLY,
 * scoped to the task's school, returning a one-line summary that says only what was
 * measured. Nothing here sends a message or changes a record.
 *
 * Not registered (cannot be measured honestly from the tables that exist):
 *   admissions.application.document_chase  documents are keyed to student_id, not to an application form
 *   admissions.confirmation.offer_expiry   no offer or offer window is recorded on any admission table
 *   students.identity.card_batch           no ID-card issue record exists for students
 *   examination.result.recompute           no computed-at or stale-result state is stored
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
            'attendance.staff.muster_close' => [self::class, 'staffMusterPreview'],
            'examination.marks.entry_reminder' => [self::class, 'marksEntryPending'],
            'academics.syllabus.coverage_rollup' => [self::class, 'lessonCoverage'],
            'lms.content.index_rebuild' => [self::class, 'contentEmbeddingGap'],
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

    /** Latest academic year recorded in a table for this school, or null. */
    private static function latestYear(string $table, int $tenant): mixed
    {
        return DB::table($table)->where('sub_institute_id', $tenant)->max('syear');
    }

    /** Report only: today's staff punches. Nothing is frozen and no absence is marked. */
    public static function staffMusterPreview(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('hrms_attendances')) {
            return 'Report only: the staff punch register is not available, so the muster cannot be checked.';
        }
        foreach (['user_id', 'day', 'punchin_time', 'punchout_time', 'deleted_at'] as $c) {
            if (! Schema::hasColumn('hrms_attendances', $c)) {
                return 'Report only: the staff punch register is not available, so the muster cannot be checked.';
            }
        }
        $base = DB::table('hrms_attendances')->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')->whereDate('day', Carbon::today());
        $in = (clone $base)->distinct()->count('user_id');
        if ($in === 0) {
            return 'Report only: no staff have punched in today. The muster was not closed and no absences were marked.';
        }
        $open = (clone $base)->where(fn ($w) => $w->whereNull('punchout_time')->orWhere('punchout_time', '<', '1000-01-01'))
            ->distinct()->count('user_id');

        return "Report only: {$in} staff have punched in today and {$open} have no punch-out yet. The muster was not closed and no absences were marked.";
    }

    /** Report only: exams already dated that have no marks entered at all. No reminder is sent. */
    public static function marksEntryPending(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('result_create_exam') || ! Schema::hasTable('result_marks')
            || ! Schema::hasColumn('result_create_exam', 'exam_date') || ! Schema::hasColumn('result_create_exam', 'syear')) {
            return 'Report only: the exam or marks tables are not available, so pending marks cannot be checked.';
        }
        $syear = self::latestYear('result_create_exam', $tenant);
        if (! $syear) {
            return 'Report only: no exams are set up, so there are no marks to chase.';
        }
        $q = DB::table('result_create_exam as e')
            ->where('e.sub_institute_id', $tenant)->where('e.syear', $syear)
            ->whereNotNull('e.exam_date')->whereDate('e.exam_date', '>', '1000-01-01')
            ->whereDate('e.exam_date', '<', Carbon::today())
            ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('result_marks as m')
                ->whereColumn('m.exam_id', 'e.id')->where('m.sub_institute_id', $tenant));
        $n = (clone $q)->count();
        if ($n === 0) {
            return "Report only: every exam dated before today has some marks entered for {$syear}.";
        }
        $oldest = (clone $q)->min('e.exam_date');
        $subjects = (clone $q)->distinct()->count('e.subject_id');

        return "Report only: {$n} exams across {$subjects} subjects dated before today have no marks entered for {$syear} (oldest {$oldest}). No reminder was sent.";
    }

    /** Report only: lessons planned up to today against lessons marked taught (YES). Nothing is stored. */
    public static function lessonCoverage(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('lessonplan') || ! Schema::hasTable('lessonplan_execution')
            || ! Schema::hasColumn('lessonplan', 'school_date') || ! Schema::hasColumn('lessonplan_execution', 'lessonplan_status')) {
            return 'Report only: the lesson plan tables are not available, so coverage cannot be checked.';
        }
        $syear = self::latestYear('lessonplan', $tenant);
        if (! $syear) {
            return 'Report only: no lessons are planned, so there is no coverage to measure.';
        }
        $planned = DB::table('lessonplan as l')->where('l.sub_institute_id', $tenant)->where('l.syear', $syear)
            ->whereDate('l.school_date', '<=', Carbon::today());
        $total = (clone $planned)->count();
        if ($total === 0) {
            return "Report only: no lessons are planned up to today for {$syear}.";
        }
        $subjects = (clone $planned)->distinct()->count('l.subject_id');
        $taught = (clone $planned)->whereExists(fn ($s) => $s->select(DB::raw(1))->from('lessonplan_execution as x')
            ->whereColumn('x.lessonplan_id', 'l.id')->where('x.sub_institute_id', $tenant)
            ->whereRaw("upper(trim(x.lessonplan_status)) = 'YES'"))->count();
        $open = $total - $taught;

        return "Report only: {$taught} of {$total} lessons planned up to today across {$subjects} subjects are marked taught for {$syear}; {$open} are not. Coverage was not recalculated.";
    }

    /** Report only: content items with no stored embedding. Nothing is re-indexed. */
    public static function contentEmbeddingGap(PlatformScheduledTask $task): string
    {
        $tenant = (int) $task->sub_institute_id;
        if (! Schema::hasTable('content_master') || ! Schema::hasColumn('content_master', 'embedding')) {
            return 'Report only: the content table has no embedding column, so indexing cannot be checked.';
        }
        $base = DB::table('content_master')->where('sub_institute_id', $tenant);
        $total = (clone $base)->count();
        if ($total === 0) {
            return 'Report only: there is no learning content to index.';
        }
        $missing = (clone $base)->where(fn ($w) => $w->whereNull('embedding')->orWhere('embedding', ''))->count();

        return $missing === 0
            ? "Report only: all {$total} content items have a stored embedding. Nothing needs re-indexing."
            : "Report only: {$missing} of {$total} content items have no stored embedding. Nothing was re-indexed.";
    }
}
