<?php

namespace App\Console\Commands\PAL;

use App\Models\Eso\PilotEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-pilot readiness — cohort / Arm A-B assignment tool.
 * See docs/CHAPTER_1014_PILOT_MEASUREMENT_PLAN.md §3/§10.
 *
 * Enrolls students into `pal_pilot_enrollments` for one chapter, one arm, by
 * class/section (reusing the existing tblstudent/tblstudent_enrollment
 * relationships — no student id is ever hard-coded here). Defaults to
 * --dry-run so running it never silently enrolls anyone; a real enrollment
 * requires explicitly passing --confirm.
 *
 * This command has NOT been run against any real student — it is provided
 * for whoever operates the actual pilot to use when they are ready.
 */
class PilotEnrollCommand extends Command
{
    protected $signature = 'pal:pilot-enroll
        {chapterId : lms_concept.chapter_id this pilot is scoped to (e.g. 1014)}
        {arm : A or B}
        {--institute= : sub_institute_id}
        {--standard= : standard_id}
        {--division= : division/section_id}
        {--syear= : academic year (defaults to the current one on tblstudent_enrollment)}
        {--cohort= : a label for this batch, e.g. 2026-pilot-1}
        {--confirm : actually write the enrollments; without this flag the command only reports what it WOULD do}';

    protected $description = 'Enroll a class/section of students into one arm of the Chapter pilot (dry-run by default)';

    public function handle(): int
    {
        $chapterId = (int) $this->argument('chapterId');
        $arm = strtoupper((string) $this->argument('arm'));

        if (! in_array($arm, [PilotEnrollment::ARM_A, PilotEnrollment::ARM_B], true)) {
            $this->error("arm must be 'A' or 'B', got '{$arm}'.");

            return self::FAILURE;
        }

        $instituteId = $this->option('institute') !== null ? (int) $this->option('institute') : null;
        $standardId = $this->option('standard') !== null ? (int) $this->option('standard') : null;
        $divisionId = $this->option('division') !== null ? (int) $this->option('division') : null;
        $syear = $this->option('syear');
        $cohortLabel = $this->option('cohort');
        $confirm = (bool) $this->option('confirm');

        if ($instituteId === null || $standardId === null) {
            $this->error('--institute and --standard are required (add --division to narrow to one section).');

            return self::FAILURE;
        }

        $query = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('s.id', '=', 'se.student_id')->whereNull('se.end_date');
            })
            ->where('s.sub_institute_id', $instituteId)
            ->where('se.standard_id', $standardId)
            ->when($divisionId !== null, fn ($q) => $q->where('se.section_id', $divisionId))
            ->when($syear !== null, fn ($q) => $q->where('se.syear', $syear));

        $students = $query->select(['s.id', 's.first_name', 's.last_name'])->distinct()->get();

        if ($students->isEmpty()) {
            $this->warn('No enrolled students matched that institute/standard/division/year — nothing to do.');

            return self::SUCCESS;
        }

        $alreadyEnrolled = PilotEnrollment::where('chapter_id', $chapterId)
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('arm', 'student_id');

        $this->info("Chapter {$chapterId}, Arm {$arm}, cohort " . ($cohortLabel ?? '(none)') . ':');
        $toEnroll = [];
        foreach ($students as $student) {
            $existingArm = $alreadyEnrolled->get($student->id);
            if ($existingArm !== null) {
                $this->line("  SKIP {$student->first_name} {$student->last_name} (#{$student->id}) — already enrolled in Arm {$existingArm} for this chapter (unique per student+chapter).");
                continue;
            }
            $toEnroll[] = $student->id;
            $this->line("  " . ($confirm ? 'ENROLL' : 'WOULD ENROLL') . " {$student->first_name} {$student->last_name} (#{$student->id})");
        }

        $this->newLine();
        if (! $confirm) {
            $this->info(count($toEnroll) . ' student(s) would be enrolled. Re-run with --confirm to actually write these rows.');

            return self::SUCCESS;
        }

        $now = now();
        foreach ($toEnroll as $studentId) {
            PilotEnrollment::create([
                'student_id' => $studentId,
                'sub_institute_id' => $instituteId,
                'chapter_id' => $chapterId,
                'arm' => $arm,
                'cohort_label' => $cohortLabel,
                'status' => PilotEnrollment::STATUS_ACTIVE,
                'enrolled_at' => $now,
                'enrolled_by' => null,
            ]);
        }

        $this->info(count($toEnroll) . ' student(s) enrolled.');

        return self::SUCCESS;
    }
}
