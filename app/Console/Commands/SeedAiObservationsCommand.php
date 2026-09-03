<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adds synthetic *observations* to a tenant that already holds real students.
 *
 * This is the deliberately narrow sibling of `ai:seed-demo`. That command builds a whole
 * synthetic school at an unused id and refuses, correctly, to write anywhere near a real
 * roll. This one exists because the opposite need is also real: Triz International
 * (`sub_institute_id = 1`) has 3,435 genuine students and almost no recorded observations
 * — 34 attendance marks and 6 homework rows across the entire school — so every academic
 * detector is either blind or judging three children, and the lifecycle demonstrates
 * nothing on the tenant people actually sign in to.
 *
 * ## What it will and will not touch
 *
 * It writes **only** to the three observation tables, and only for students that already
 * exist:
 *
 *   - `attendance_student`, tagged `created_by = 'ai-synthetic'`
 *   - `homework`, tagged `created_ip = 'ai-synthetic'`
 *   - `lms_online_exam`, tagged by a reserved `question_paper_id` band (900000+)
 *
 * It creates no student, no user, no enrolment, no class and no subject. It never updates
 * or deletes a row it did not insert. `--purge` removes exactly the tagged rows and
 * nothing else, so the tenant returns to its previous state on demand.
 *
 * The tags are the load-bearing part. `lms_online_exam` carries no text column at all, so
 * a reserved id band is the only marker available — real rows on this estate run 1..6000
 * and the band starts at 900000, which is checkable rather than merely intended. Without
 * an exact marker on every table this command would be irreversible, and an irreversible
 * write into a live tenant is not something worth having.
 *
 * ## Why the numbers are sampled rather than chosen
 *
 * Same principle as `ai:seed-demo`: no student is picked to be at risk. Each is given
 * latent traits drawn from distributions, and every mark is then sampled from those
 * traits. Which children the detectors raise is their decision. A fixture that plants its
 * own findings proves only that the finding was planted.
 *
 * ## Dates and the academic year
 *
 * Rows are written under the institute's *currently resolved* academic year — the same
 * `academic_year` lookup `McpContextResolver` performs — because every detector filters on
 * `syear`. Writing a row under the wrong year produces data that exists and is invisible,
 * which is the most expensive kind of nothing.
 *
 * A date that already carries a real attendance mark for that student is skipped, so a
 * synthetic row can never contradict, duplicate or overwrite a real one.
 *
 *   php artisan ai:seed-observations --institute=1                 # dry run, writes nothing
 *   php artisan ai:seed-observations --institute=1 --write
 *   php artisan ai:seed-observations --institute=1 --purge --write
 */
class SeedAiObservationsCommand extends Command
{
    protected $signature = 'ai:seed-observations
        {--institute=1 : the sub_institute_id to top up — must already hold students}
        {--students=120 : how many of its existing students to generate observations for}
        {--days=60 : school days of attendance per student}
        {--year= : academic year to write under (defaults to the institute current term)}
        {--seed=20260827 : RNG seed — same seed, same observations}
        {--write : actually write. Without this the command reports what it would do and stops}
        {--purge : delete every tagged synthetic row for this institute and stop}
        {--from-run= : with --purge, also delete what the AI concluded from those rows — every signal, case, evidence row, explanation and recommendation produced by agent runs with this id or higher}';

    protected $description = 'Add synthetic attendance, homework and assessment observations for students that already exist in a tenant.';

    /** The marker written into `attendance_student.created_by` and `homework.created_ip`. */
    public const TAG = 'ai-synthetic';

    /**
     * Reserved `question_paper_id` band for synthetic assessment attempts.
     *
     * `lms_online_exam` has no text column to tag, so provenance has to live in a value
     * that no real row can hold. Real paper ids on this estate run 1..6000.
     */
    public const QP_BAND_START = 900000;

    public const QP_BAND_END = 999999;

    private const SUBJECT_FALLBACK = ['Mathematics', 'Science', 'English', 'Social Studies', 'Hindi'];

    /** Homework titles that read like a teacher wrote them. */
    private const HOMEWORK_TITLES = [
        'Exercise 4.2 — practice sums',
        'Worksheet: ratio and proportion',
        'Chapter 6 questions 1-8',
        'Comprehension passage 3',
        'Map work and short answers',
        'Revision notes, chapter 5',
        'Diagram and labelling exercise',
        'Grammar worksheet: tenses',
        'Practice set 7',
        'Case study write-up',
    ];

    public function handle(): int
    {
        $institute = (int) $this->option('institute');

        if ($institute <= 0) {
            $this->error('An institute id is required.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge($institute);
        }

        $year = (int) ($this->option('year') ?: $this->resolveAcademicYear($institute));

        if ($year <= 0) {
            $this->error(sprintf(
                'Could not resolve an academic year for institute %d, and none was given. '
                . 'Pass --year explicitly.',
                $institute
            ));

            return self::FAILURE;
        }

        $students = $this->cohort($institute, max(1, min((int) $this->option('students'), 800)));

        if ($students === []) {
            $this->error(sprintf(
                'Institute %d holds no active students. This command tops up an existing roll; '
                . 'use ai:seed-demo to build a synthetic school instead.',
                $institute
            ));

            return self::FAILURE;
        }

        mt_srand((int) $this->option('seed'));

        $days = $this->schoolDays(max(5, min((int) $this->option('days'), 120)));
        $subjects = $this->subjects($institute);
        $write = (bool) $this->option('write');

        $this->line('');
        $this->info(sprintf(
            '%s observations in institute %d, academic year %d.',
            $write ? 'Writing' : 'Would write (dry run)',
            $institute,
            $year
        ));
        $this->line(sprintf(
            '  cohort      %d existing students (of %d active), ordered by id',
            count($students),
            $this->activeCount($institute)
        ));
        $this->line('');

        $traits = $this->traitsFor($students);
        $existing = $this->existingAttendanceDates($institute, array_keys($students), $days);

        $attendance = $this->attendance($institute, $year, $students, $traits, $days, $existing, $write);
        $this->line(sprintf('  attendance  %d rows%s', $attendance['written'], $this->skipNote($attendance['skipped'])));

        $homework = $this->homework($institute, $year, $students, $traits, $subjects, $write);
        $this->line(sprintf('  homework    %d rows', $homework));

        $exams = $this->assessments($students, $traits, $write);
        $this->line(sprintf('  assessments %d rows', $exams));

        $this->line('');
        $this->reportTail($traits);
        $this->line('');

        if (! $write) {
            $this->warn('Nothing was written. Re-run with --write to perform the insert.');
            $this->line(sprintf(
                '<fg=gray>Reverse it any time:  php artisan ai:seed-observations --institute=%d --purge --write</>',
                $institute
            ));
            $this->line('');

            return self::SUCCESS;
        }

        $this->info(sprintf('Ask it something:  php artisan ai:journey --institute=%d', $institute));
        $this->line(sprintf(
            '<fg=gray>Reverse it all:    php artisan ai:seed-observations --institute=%d --purge --write</>',
            $institute
        ));
        $this->line('');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- purge

    /**
     * Remove exactly the rows this command inserted.
     *
     * Every clause is a tag test. There is deliberately no date range, no student list and
     * no "everything recent" fallback: a purge that deletes by anything other than the
     * marker could take a real row with it, and on this tenant those rows are children's
     * actual attendance records.
     */
    private function purge(int $institute): int
    {
        $write = (bool) $this->option('write');

        $queries = [
            'attendance_student' => DB::table('attendance_student')
                ->where('sub_institute_id', $institute)
                ->where('created_by', self::TAG),
            'homework' => DB::table('homework')
                ->where('sub_institute_id', $institute)
                ->where('created_ip', self::TAG),
        ];

        $this->line('');

        // The ids have to be read before the rows go, because the evidence that cites them
        // is found by `source_table` + `source_id`. Deleting the observations first would
        // destroy the only handle on the findings that describe them.
        $sourceIds = [];
        $subjectIds = [];
        $total = 0;

        foreach ($queries as $table => $query) {
            $rows = (clone $query)->get(['id', 'student_id']);
            $n = $rows->count();
            $total += $n;

            $sourceIds[$table] = $rows->pluck('id')->map(static fn ($id) => (int) $id)->all();
            $subjectIds = array_merge($subjectIds, $rows->pluck('student_id')->all());

            if ($n > 0) {
                $this->line(sprintf('  %-22s %d tagged rows', $table, $n));
            }

            if ($write && $n > 0) {
                $query->delete();
            }
        }

        // `lms_online_exam` has no tenant column, so it is reached by student id and
        // narrowed to the reserved band.
        $studentIds = $this->allStudentIds($institute);

        if ($studentIds !== []) {
            $exams = DB::table('lms_online_exam')
                ->whereIn('student_id', $studentIds)
                ->whereBetween('question_paper_id', [self::QP_BAND_START, self::QP_BAND_END]);

            $rows = (clone $exams)->get(['id', 'student_id']);
            $n = $rows->count();
            $total += $n;

            $sourceIds['lms_online_exam'] = $rows->pluck('id')->map(static fn ($id) => (int) $id)->all();
            $subjectIds = array_merge($subjectIds, $rows->pluck('student_id')->all());

            if ($n > 0) {
                $this->line(sprintf('  %-22s %d tagged rows', 'lms_online_exam', $n));
            }

            if ($write && $n > 0) {
                $exams->delete();
            }
        }

        $subjectIds = array_values(array_unique(array_map('intval', $subjectIds)));

        $total += $this->purgeFindings($institute, $write, $sourceIds, $subjectIds);

        $this->line('');

        if (! $write) {
            $this->warn(sprintf('Dry run: %d rows would be removed from institute %d.', $total, $institute));
            $this->line('<fg=gray>Re-run with --write to perform the delete.</>');
            $this->line('');

            return self::SUCCESS;
        }

        $this->info(sprintf('Removed %d rows from institute %d.', $total, $institute));

        // Deliberately not "no real row was touched". The observation purge can honestly
        // claim that — it deletes by tag. The findings purge cannot: it removes what the
        // AI concluded, and a conclusion drawn partly from real records is still removed
        // when the run that drew it is. Re-running the scan rebuilds anything that the
        // surviving records still support.
        if ($this->option('from-run')) {
            $this->line('<fg=gray>  Tagged observations were removed by tag. Findings were removed by run — '
                . 're-run the scan to rebuild whatever the remaining records still support.</>');
        }
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Remove what the AI concluded *from* the rows being deleted.
     *
     * Opt-in via `--from-run`, and off by default, because the two are genuinely separable:
     * re-seeding the same cohort leaves every finding still pointing at a live row, and
     * throwing away a school's case history to change the size of a fixture would be a
     * far bigger act than the one being asked for.
     *
     * When the observations *do* go, though, the findings have to go with them. A case
     * citing `homework #3939` after that row is deleted is the most confusing state a
     * fixture can be left in, because nothing about the answer looks wrong — the sentence
     * still reads perfectly, and only following the citation reveals there is nothing
     * behind it. `ai:seed-demo` learned this the hard way; thirty-six of sixty-eight cases
     * survived their students on its first run.
     *
     * Scoped by agent run rather than by student. A student in the synthetic cohort may
     * also carry real findings from before the seed — institute 1 has five — and deleting
     * by subject would take those too. The run id is the exact boundary between what the
     * synthetic data caused and what predates it.
     */
    /**
     * @param  array<string, array<int, int>>  $sourceIds  Deleted row ids, keyed by table.
     * @param  array<int, int>  $subjectIds  Students those rows belonged to.
     */
    private function purgeFindings(int $institute, bool $write, array $sourceIds, array $subjectIds): int
    {
        $fromRun = $this->option('from-run');

        if ($fromRun === null || $fromRun === '') {
            return 0;
        }

        $fromRun = (int) $fromRun;

        if ($fromRun <= 0) {
            $this->warn('  --from-run must be a positive agent run id; findings were left alone.');

            return 0;
        }

        $runIds = DB::table('ai_agent_runs')
            ->where('sub_institute_id', $institute)
            ->where('id', '>=', $fromRun)
            ->pluck('id')
            ->all();

        if ($runIds === []) {
            $this->line(sprintf('  %-22s no runs at or after #%d', 'ai_agent_runs', $fromRun));

            return 0;
        }

        $caseIds = DB::table('ai_cases')
            ->where('sub_institute_id', $institute)
            ->whereIn('opened_by_run_id', $runIds)
            ->pluck('id')
            ->all();

        // Evidence carries no run id, so it is reached three ways — and it has to be all
        // three, because a case is not the only thing evidence can belong to. Evidence is
        // deliberately stored whether or not a case opens, so a trend is visible before it
        // is a case: on the run that prompted this, 507 rows were written and only 225
        // were ever cited by one. Reaching only through cases would have left 282 rows
        // describing measurements whose source had been deleted.
        $evidenceIds = [];

        // 1. Cited by a case being removed, and by no case that survives.
        if ($caseIds !== []) {
            $linked = DB::table('ai_case_evidence')->whereIn('case_id', $caseIds)->pluck('evidence_id')->all();
            $keep = DB::table('ai_case_evidence')
                ->whereIn('evidence_id', $linked)
                ->whereNotIn('case_id', $caseIds)
                ->pluck('evidence_id')
                ->all();

            $evidenceIds = array_values(array_diff(array_unique($linked), array_unique($keep)));
        }

        // 2. Pointing straight at a row that has just been deleted. This is the exact
        //    rule — `source_table` and `source_id` are the citation itself — and it needs
        //    no run id, no timestamp and no cohort.
        foreach ($sourceIds as $table => $ids) {
            foreach (array_chunk($ids, 2000) as $chunk) {
                if ($chunk === []) {
                    continue;
                }

                $evidenceIds = array_merge($evidenceIds, DB::table('ai_evidence')
                    ->where('sub_institute_id', $institute)
                    ->where('source_table', $table)
                    ->whereIn('source_id', $chunk)
                    ->pluck('id')
                    ->all());
            }
        }

        // 3. Computed rather than recorded — an averaged rate or a trend, which carries no
        //    source row to match on. Bounded by the students whose observations were just
        //    removed *and* by the window of the runs being deleted, so a real computed
        //    finding about the same child from before the seed is left alone.
        $earliest = DB::table('ai_agent_runs')->whereIn('id', $runIds)->min('started_at');

        if ($subjectIds !== [] && $earliest !== null) {
            $evidenceIds = array_merge($evidenceIds, DB::table('ai_evidence')
                ->where('sub_institute_id', $institute)
                ->whereNull('source_table')
                ->whereIn('subject_id', $subjectIds)
                ->where('created_at', '>=', $earliest)
                ->pluck('id')
                ->all());
        }

        $evidenceIds = array_values(array_unique(array_map('intval', $evidenceIds)));

        $total = 0;

        $steps = [
            // Both directions: links from a case being removed, and links *to* evidence
            // being removed from a case that survives. A dangling link either way is a
            // citation to something that is no longer there.
            'ai_case_evidence' => fn () => $caseIds === [] && $evidenceIds === []
                ? null
                : DB::table('ai_case_evidence')->where(function ($q) use ($caseIds, $evidenceIds) {
                    if ($caseIds !== []) {
                        $q->orWhereIn('case_id', $caseIds);
                    }

                    if ($evidenceIds !== []) {
                        $q->orWhereIn('evidence_id', $evidenceIds);
                    }
                }),
            'ai_case_signals' => fn () => $caseIds === [] ? null : DB::table('ai_case_signals')->whereIn('case_id', $caseIds),
            'ai_explanations' => fn () => $caseIds === [] ? null : DB::table('ai_explanations')->whereIn('case_id', $caseIds),
            'ai_hypotheses' => fn () => $caseIds === [] ? null : DB::table('ai_hypotheses')->whereIn('case_id', $caseIds),
            'ai_recommendations' => fn () => DB::table('ai_recommendations')
                ->where('sub_institute_id', $institute)
                ->whereIn('created_by_run_id', $runIds),
            'ai_evidence' => fn () => $evidenceIds === [] ? null : DB::table('ai_evidence')->whereIn('id', $evidenceIds),
            'ai_cases' => fn () => $caseIds === [] ? null : DB::table('ai_cases')->whereIn('id', $caseIds),
            // `detected_by_run_id` means "last touched by", not "created by".
            //
            // Signals are upserted per subject and key, so a re-detection stamps the new
            // run id onto a row that has existed for weeks. Deleting on the run id alone
            // therefore reaches backwards in time: it removed two genuine signals about
            // the one student on this tenant who had real data before any seeding, purely
            // because the run being purged had refreshed them in place.
            //
            // Pairing it with the creation time fixes that. A row created before these
            // runs began predates them by definition, however recently it was touched.
            'ai_signals' => fn () => $earliest === null ? null : DB::table('ai_signals')
                ->where('sub_institute_id', $institute)
                ->whereIn('detected_by_run_id', $runIds)
                ->where('created_at', '>=', $earliest),
            'ai_agent_runs' => fn () => DB::table('ai_agent_runs')
                ->where('sub_institute_id', $institute)
                ->whereIn('id', $runIds),
        ];

        foreach ($steps as $table => $build) {
            try {
                $query = $build();
            } catch (\Throwable) {
                // A table this estate does not carry is not an error worth stopping for.
                continue;
            }

            if ($query === null) {
                continue;
            }

            try {
                $n = (clone $query)->count();
            } catch (\Throwable) {
                continue;
            }

            $total += $n;

            if ($n > 0) {
                $this->line(sprintf('  %-22s %d rows (from run #%d onward)', $table, $n, $fromRun));
            }

            if ($write && $n > 0) {
                $query->delete();
            }
        }

        return $total;
    }

    // ---------------------------------------------------------------- cohort

    /**
     * Existing students to generate observations for, with their class placement.
     *
     * Placement comes from the student's most recent enrolment whatever year it belongs
     * to. On this tenant 3,262 of 3,443 enrolments sit at `syear = 2021` while the current
     * term resolves to 2022, so filtering by the active year would leave almost every
     * attendance row with a null class and section.
     *
     * @return array<int, array{standard_id:int|null, section_id:int|null}>
     */
    private function cohort(int $institute, int $count): array
    {
        $ids = DB::table('tblstudent')
            ->where('sub_institute_id', $institute)
            ->where(function ($q) {
                $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            })
            ->orderBy('id')
            ->limit($count)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return [];
        }

        $placements = DB::table('tblstudent_enrollment')
            ->whereIn('student_id', $ids)
            ->where('sub_institute_id', $institute)
            ->orderBy('student_id')
            ->orderByDesc('id')
            ->get(['student_id', 'standard_id', 'section_id'])
            ->groupBy('student_id');

        $students = [];

        foreach ($ids as $id) {
            $latest = $placements->get((int) $id)?->first();

            $students[(int) $id] = [
                'standard_id' => $latest && $latest->standard_id ? (int) $latest->standard_id : null,
                'section_id' => $latest && $latest->section_id ? (int) $latest->section_id : null,
            ];
        }

        return $students;
    }

    private function activeCount(int $institute): int
    {
        return (int) DB::table('tblstudent')
            ->where('sub_institute_id', $institute)
            ->where(function ($q) {
                $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            })
            ->count();
    }

    /**
     * @return array<int, int>
     */
    private function allStudentIds(int $institute): array
    {
        return DB::table('tblstudent')
            ->where('sub_institute_id', $institute)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Latent traits per student. Nothing downstream is chosen; everything is sampled.
     *
     * @param  array<int, array<string, mixed>>  $students
     * @return array<int, array<string, float>>
     */
    private function traitsFor(array $students): array
    {
        $traits = [];

        foreach (array_keys($students) as $id) {
            $traits[$id] = [
                'ability' => $this->normal(0.66, 0.15, 0.15, 0.99),
                'diligence' => $this->normal(0.86, 0.16, 0.20, 1.0),
                'attends' => $this->normal(0.94, 0.06, 0.55, 1.0),
                'drift' => $this->normal(0.0, 0.055, -0.20, 0.20),
            ];
        }

        return $traits;
    }

    // ---------------------------------------------------------------- writers

    /**
     * Dates that already carry a real attendance mark, so a synthetic row can never
     * duplicate or contradict one.
     *
     * @param  array<int, int>  $studentIds
     * @param  array<int, string>  $days
     * @return array<string, true>  Keyed "studentId|date"
     */
    private function existingAttendanceDates(int $institute, array $studentIds, array $days): array
    {
        if ($studentIds === [] || $days === []) {
            return [];
        }

        $rows = DB::table('attendance_student')
            ->where('sub_institute_id', $institute)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('attendance_date', [$days[0], $days[count($days) - 1]])
            ->get(['student_id', 'attendance_date']);

        $taken = [];

        foreach ($rows as $row) {
            $taken[$row->student_id . '|' . $row->attendance_date] = true;
        }

        return $taken;
    }

    /**
     * @param  array<int, array<string, mixed>>  $students
     * @param  array<int, array<string, float>>  $traits
     * @param  array<int, string>  $days
     * @param  array<string, true>  $existing
     * @return array{written:int, skipped:int}
     */
    private function attendance(
        int $institute,
        int $year,
        array $students,
        array $traits,
        array $days,
        array $existing,
        bool $write
    ): array {
        $rows = [];
        $written = 0;
        $skipped = 0;
        $total = count($days);

        foreach ($students as $studentId => $placement) {
            $trait = $traits[$studentId];

            foreach ($days as $index => $day) {
                if (isset($existing[$studentId . '|' . $day])) {
                    $skipped++;

                    continue;
                }

                // Attendance decays slightly for a child drifting downward, which is what
                // makes a term-long slide visible rather than uniform noise.
                $rate = $trait['attends'] + ($trait['drift'] * ($index / $total));
                $roll = mt_rand(0, 10000) / 10000;

                $code = match (true) {
                    $roll < max(0.01, 1 - $rate) => 'A',
                    $roll < max(0.02, 1 - $rate + 0.03) => 'L',
                    default => 'P',
                };

                $rows[] = [
                    'syear' => $year,
                    'student_id' => $studentId,
                    'attendance_date' => $day,
                    'attendance_code' => $code,
                    'user_group_id' => 1,
                    'standard_id' => $placement['standard_id'],
                    'section_id' => $placement['section_id'],
                    'sub_institute_id' => $institute,
                    'created_by' => self::TAG,
                    'created_on' => $day . ' 09:' . str_pad((string) mt_rand(5, 55), 2, '0', STR_PAD_LEFT) . ':00',
                ];

                $written++;

                if (count($rows) >= 900) {
                    if ($write) {
                        DB::table('attendance_student')->insert($rows);
                    }

                    $rows = [];
                }
            }
        }

        if ($rows !== [] && $write) {
            DB::table('attendance_student')->insert($rows);
        }

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, array<string, mixed>>  $students
     * @param  array<int, array<string, float>>  $traits
     * @param  array<int, array{id:int, name:string}>  $subjects
     */
    private function homework(
        int $institute,
        int $year,
        array $students,
        array $traits,
        array $subjects,
        bool $write
    ): int {
        $rows = [];
        $written = 0;

        foreach ($students as $studentId => $placement) {
            $trait = $traits[$studentId];

            // Six to eleven pieces over the 45-day detector window — a realistic load and
            // comfortably above its minimum of three.
            $items = mt_rand(6, 11);

            for ($i = 0; $i < $items; $i++) {
                $subject = $subjects[mt_rand(0, count($subjects) - 1)];
                $due = now()->subDays(mt_rand(2, 42));

                $chance = $trait['diligence'] + ($trait['drift'] * 0.8);
                $completed = (mt_rand(0, 10000) / 10000) < $chance;

                $rows[] = [
                    'sub_institute_id' => $institute,
                    'syear' => $year,
                    'student_id' => $studentId,
                    'standard_id' => $placement['standard_id'],
                    'division_id' => $placement['section_id'],
                    'subject_id' => $subject['id'] ?: null,
                    'title' => self::HOMEWORK_TITLES[mt_rand(0, count(self::HOMEWORK_TITLES) - 1)],
                    'description' => 'Complete and submit for review.',
                    'date' => $due->toDateString(),
                    'type' => 'Homework',
                    // A completed item carries a submission date, usually on time and
                    // sometimes a day or two late. A missed one carries neither.
                    'submission_date' => $completed
                        ? $due->copy()->addDays(mt_rand(0, 2))->toDateString()
                        : null,
                    'completion_status' => $completed ? 'Y' : 'N',
                    'created_ip' => self::TAG,
                    'created_on' => $due->copy()->subDays(mt_rand(2, 6)),
                ];

                $written++;

                if (count($rows) >= 900) {
                    if ($write) {
                        DB::table('homework')->insert($rows);
                    }

                    $rows = [];
                }
            }
        }

        if ($rows !== [] && $write) {
            DB::table('homework')->insert($rows);
        }

        return $written;
    }

    /**
     * Assessment attempts, scored from ability plus the term's drift.
     *
     * The decline detector compares recent attempts against earlier ones, so the drift has
     * to accumulate across the sequence rather than being noise on each attempt —
     * otherwise no child shows a trend and the detector has nothing to find.
     *
     * @param  array<int, array<string, mixed>>  $students
     * @param  array<int, array<string, float>>  $traits
     */
    private function assessments(array $students, array $traits, bool $write): int
    {
        $rows = [];
        $written = 0;

        foreach (array_keys($students) as $studentId) {
            $trait = $traits[$studentId];
            $attempts = mt_rand(5, 9);

            for ($i = 0; $i < $attempts; $i++) {
                // Oldest first, spread across the 180-day window the detector reads.
                $daysAgo = (int) round(150 - ($i * (140 / max(1, $attempts - 1)))) + mt_rand(-4, 4);
                $when = now()->subDays(max(1, $daysAgo));

                $progress = $attempts > 1 ? $i / ($attempts - 1) : 1.0;
                $score = $trait['ability'] + ($trait['drift'] * $progress * 2.2);
                $score += (mt_rand(-120, 120) / 1000);
                $score = max(0.05, min(0.99, $score));

                $questions = mt_rand(8, 20);
                $right = (int) round($questions * $score);

                $rows[] = [
                    'student_id' => $studentId,
                    // The reserved band is the only provenance this table can carry.
                    'question_paper_id' => mt_rand(self::QP_BAND_START, self::QP_BAND_START + 999),
                    'total_right' => $right,
                    'total_wrong' => $questions - $right,
                    'obtain_marks' => $right,
                    'start_time' => $when->copy()->setTime(mt_rand(9, 15), mt_rand(0, 59)),
                    'accuracy_rate' => round($score * 100, 2),
                    'created_at' => $when,
                ];

                $written++;

                if (count($rows) >= 900) {
                    if ($write) {
                        DB::table('lms_online_exam')->insert($rows);
                    }

                    $rows = [];
                }
            }
        }

        if ($rows !== [] && $write) {
            DB::table('lms_online_exam')->insert($rows);
        }

        return $written;
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The academic year the detectors will filter on.
     *
     * Mirrors McpContextResolver: the term whose window contains today. Writing under any
     * other year produces rows that exist and are invisible.
     */
    private function resolveAcademicYear(int $institute): int
    {
        $term = DB::table('academic_year')
            ->where('sub_institute_id', $institute)
            ->whereRaw('? between start_date and end_date', [now()->toDateString()])
            ->orderBy('sort_order')
            ->first();

        return $term ? (int) $term->syear : 0;
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    private function subjects(int $institute): array
    {
        $rows = DB::table('subject')
            ->where('sub_institute_id', $institute)
            ->orderBy('id')
            ->limit(12)
            ->get();

        $subjects = [];

        foreach ($rows as $row) {
            $subjects[] = [
                'id' => (int) $row->id,
                'name' => (string) ($row->subject_name ?? $row->name ?? 'Subject'),
            ];
        }

        // A tenant with no subject rows still gets homework, with a null subject rather
        // than an id pointing at another school's row.
        if ($subjects === []) {
            foreach (self::SUBJECT_FALLBACK as $name) {
                $subjects[] = ['id' => 0, 'name' => $name];
            }
        }

        return $subjects;
    }

    /**
     * The last N weekdays, oldest first.
     *
     * @return array<int, string>
     */
    private function schoolDays(int $count): array
    {
        $days = [];
        $cursor = now()->subDay();

        while (count($days) < $count) {
            if (! $cursor->isWeekend()) {
                $days[] = $cursor->toDateString();
            }

            $cursor = $cursor->subDay();
        }

        return array_reverse($days);
    }

    /**
     * A normal draw, clamped. Box-Muller, because summing uniforms gives a distribution
     * with visibly thin tails — and the tail is precisely the part that matters here.
     */
    private function normal(float $mean, float $sd, float $min, float $max): float
    {
        $u1 = max(1e-9, mt_rand(1, 1000000) / 1000000);
        $u2 = mt_rand(1, 1000000) / 1000000;
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        return max($min, min($max, $mean + ($z * $sd)));
    }

    private function skipNote(int $skipped): string
    {
        return $skipped === 0
            ? ''
            : sprintf(' (%d date%s skipped — a real mark already exists)', $skipped, $skipped === 1 ? '' : 's');
    }

    /**
     * Say what shape of cohort was produced, without naming anyone as at-risk.
     *
     * @param  array<int, array<string, float>>  $traits
     */
    private function reportTail(array $traits): void
    {
        $low = static fn (string $key, float $under) => count(array_filter(
            $traits,
            static fn (array $t) => $t[$key] < $under
        ));

        $this->line('<fg=gray>  The cohort that came out — nobody was flagged, these are the tails:</>');
        $this->line(sprintf('<fg=gray>    %d with attendance propensity under 85%%</>', $low('attends', 0.85)));
        $this->line(sprintf('<fg=gray>    %d scoring under 45%% on ability</>', $low('ability', 0.45)));
        $this->line(sprintf('<fg=gray>    %d on a downward term trend</>', $low('drift', -0.05)));
        $this->line(sprintf('<fg=gray>    %d likely to miss homework</>', $low('diligence', 0.7)));
        $this->line('<fg=gray>  Which of them the detectors raise is their decision, not this seeder.</>');
    }
}
