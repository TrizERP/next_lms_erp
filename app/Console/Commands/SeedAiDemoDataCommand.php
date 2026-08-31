<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a synthetic school for the AI lifecycle to answer questions about.
 *
 * The smoke tests run against a tenant whose attendance table holds thirty-four rows,
 * so half of them honestly report finding nothing. That is correct behaviour and a poor
 * demonstration. This command creates a school with enough history for the detectors to
 * have something to detect.
 *
 * **It writes only to a dedicated sub_institute_id, never to one holding real records.**
 * Every AI query scopes by that id, so the synthetic school is sealed off from the live
 * estate by the same mechanism that separates two real schools from each other — and
 * `--purge` removes it completely. The command refuses to run against an institute that
 * already contains students it did not create.
 *
 * ## Why this is synthetic rather than mock
 *
 * Mock data is written to produce a chosen answer: pick three children, mark them
 * absent, watch the detector find them. It demonstrates nothing, because the finding was
 * placed there rather than discovered.
 *
 * So no student here is flagged as at-risk. Each is given latent traits — an academic
 * ability, a diligence, an attendance propensity, and a term-long drift that may be
 * upward or downward — drawn from distributions with realistic spread. Every attendance
 * mark, homework submission and assessment score is then *sampled from those traits*
 * with noise. The struggling children emerge in the tail the way they do in a real
 * school, nobody knows in advance which ones they will be, and re-running with a
 * different seed produces a different cohort with a similarly shaped tail.
 *
 * That is also what makes it useful for testing: the detectors have to do real work, and
 * a threshold change visibly moves how many children are found.
 *
 *   php artisan ai:seed-demo --institute=9001 --students=140
 *   php artisan ai:seed-demo --institute=9001 --purge
 */
class SeedAiDemoDataCommand extends Command
{
    protected $signature = 'ai:seed-demo
        {--institute=9001 : the sub_institute_id to build the synthetic school in}
        {--students=140 : how many children to enrol}
        {--year= : academic year (defaults to the current calendar year)}
        {--seed=20260827 : RNG seed — same seed, same school}
        {--with-menu-access : also grant this institute ERP menu rights so its admin can sign in to the browser UI}
        {--purge : delete everything for this institute and stop}';

    protected $description = 'Create a synthetic school — students, attendance, homework, assessments, admissions — for the AI lifecycle to answer questions about.';

    /** Tables this command writes, in the order they must be deleted. */
    private const OWNED_TABLES = [
        'attendance_student',
        'homework',
        'tblstudent_enrollment',
        'admission_registration',
        'admission_enquiry',
        'sub_std_map',
        'tblstudent',
        'tbluser',
        'subject',
        'standard',
        'division',
        'academic_section',
    ];

    /**
     * What the AI layer produced *about* this school, as opposed to the school itself.
     *
     * These have to be cleared alongside the records they describe. A purge that removed
     * the children but kept the cases left the next scan reporting on students who no
     * longer existed — thirty-six of sixty-eight cases, in the first run that hit this.
     * Findings outliving their subject is the most confusing possible state for a
     * fixture, because nothing about the answer looks wrong.
     *
     * `ai_agents`, `ai_modules` and `ai_api_keys` are deliberately absent: those are
     * platform configuration shared with every school, not output about this one.
     */
    private const AI_OUTPUT_TABLES = [
        'ai_conversation_turns',
        'ai_conversations',
        'ai_decisions',
        'ai_recommendations',
        'ai_explanations',
        'ai_hypotheses',
        'ai_outcomes',
        'ai_cases',
        'ai_signals',
        'ai_evidence',
        'ai_agent_runs',
        'ai_interaction_logs',
        'ai_generation_outputs',
        'ai_generation_requests',
        'ai_audit_logs',
        'workflow_approvals',
        'workflow_run_steps',
        'workflow_runs',
    ];

    /**
     * The password every seeded account gets.
     *
     * Deliberately self-describing: anyone who finds this account in a log or a database
     * should be able to tell at a glance that it is a fixture and not somebody's real
     * login. It only ever exists on the synthetic institute, and `--purge` removes it.
     */
    public const DEMO_PASSWORD = 'SyntheticDemo!2026';

    private const FIRST_NAMES = [
        'Aarav', 'Vivaan', 'Aditya', 'Arjun', 'Reyansh', 'Ishaan', 'Kabir', 'Ayaan', 'Rudra', 'Vihaan',
        'Ananya', 'Diya', 'Aadhya', 'Saanvi', 'Myra', 'Kiara', 'Anika', 'Navya', 'Riya', 'Ira',
        'Rohan', 'Kartik', 'Devansh', 'Yash', 'Nikhil', 'Manav', 'Parth', 'Tanish', 'Aryan', 'Veer',
        'Meera', 'Tara', 'Nisha', 'Sneha', 'Pooja', 'Ritika', 'Shreya', 'Kavya', 'Aisha', 'Lakshmi',
    ];

    private const MIDDLE_NAMES = [
        'Rajesh', 'Suresh', 'Mahesh', 'Nilesh', 'Prakash', 'Ramesh', 'Dinesh', 'Ashok',
        'Sunita', 'Rekha', 'Anita', 'Vandana', 'Priya', 'Shobha',
    ];

    private const LAST_NAMES = [
        'Patel', 'Sharma', 'Desai', 'Mehta', 'Shah', 'Joshi', 'Nair', 'Iyer', 'Reddy', 'Rao',
        'Kulkarni', 'Chauhan', 'Bhatt', 'Trivedi', 'Pandya', 'Solanki', 'Vora', 'Gandhi',
    ];

    private const SUBJECTS = [
        'Mathematics', 'Science', 'English', 'Social Studies', 'Hindi', 'Computer Science',
    ];

    /** Homework titles that read like a teacher wrote them, per subject. */
    private const HOMEWORK = [
        'Mathematics' => ['Exercise 4.2 — quadratic equations', 'Worksheet: ratio and proportion', 'Practice set 7 — mensuration', 'Revision sums, chapter 5', 'Graph plotting exercise'],
        'Science' => ['Diagram: parts of the human eye', 'Lab record — acids and bases', 'Chapter 6 questions 1-8', 'Observation notes: plant cell', 'Revision: laws of motion'],
        'English' => ['Comprehension passage 3', 'Write a letter to the editor', 'Character sketch — Portia', 'Grammar worksheet: tenses', 'Book review, 200 words'],
        'Social Studies' => ['Map work: physical features of India', 'Notes on the Revolt of 1857', 'Chapter 4 short answers', 'Timeline: freedom movement', 'Case study — local government'],
        'Hindi' => ['पाठ 5 प्रश्न उत्तर', 'निबंध: मेरा विद्यालय', 'व्याकरण अभ्यास — संधि', 'कविता का सारांश', 'पत्र लेखन अभ्यास'],
        'Computer Science' => ['Flowchart: sorting an array', 'HTML practice page', 'Chapter 3 — questions', 'Scratch project: quiz game', 'Spreadsheet formulas worksheet'],
    ];

    public function handle(): int
    {
        $institute = (int) $this->option('institute');
        $year = (int) ($this->option('year') ?: now()->year);

        if ($institute <= 0) {
            $this->error('An institute id is required.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge($institute);
        }

        if (! $this->guardTenant($institute)) {
            return self::FAILURE;
        }

        // Same seed, same school. Reproducibility matters for a fixture: a bug found on
        // one run has to be reachable on the next.
        mt_srand((int) $this->option('seed'));

        $count = max(10, min((int) $this->option('students'), 800));

        $this->line('');
        $this->info(sprintf('Building a synthetic school in institute %d for %d.', $institute, $year));
        $this->line('');

        $structure = $this->seedStructure($institute, $year);
        $this->line(sprintf('  structure   %d classes · %d divisions · %d subjects',
            count($structure['standards']), count($structure['divisions']), count($structure['subjects'])));

        $staff = $this->seedStaff($institute, $year);
        $this->line(sprintf('  staff       1 admin · %d teachers', $staff - 1));

        $students = $this->seedStudents($institute, $year, $count, $structure);
        $this->line(sprintf('  students    %d enrolled', count($students)));

        $attendance = $this->seedAttendance($institute, $year, $students);
        $this->line(sprintf('  attendance  %d marks over 60 school days', $attendance));

        $homework = $this->seedHomework($institute, $year, $students, $structure);
        $this->line(sprintf('  homework    %d assignments', $homework));

        $exams = $this->seedAssessments($students);
        $this->line(sprintf('  assessments %d attempts', $exams));

        $admissions = $this->seedAdmissions($institute, $year, $structure);
        $this->line(sprintf('  admissions  %d enquiries', $admissions));

        if ($this->option('with-menu-access')) {
            [$menus, $rights] = $this->grantMenuAccess($institute);
            $this->line(sprintf('  menu access %d menu rows tagged · %d rights rows', $menus, $rights));
        }

        $this->line('');
        $this->reportTail($students);
        $this->line('');
        $this->info(sprintf('Ask it something:  php artisan ai:journey --institute=%d --year=%d', $institute, $year));
        $this->line(sprintf('<fg=gray>Remove it all:     php artisan ai:seed-demo --institute=%d --purge</>', $institute));
        $this->line('');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- safety

    /**
     * Refuse to write into a tenant that holds records this command did not create.
     *
     * The whole safety model is that the synthetic school lives at an id nothing real
     * uses. Checking rather than trusting the flag is the difference between a design
     * that is safe and one that is safe until somebody mistypes.
     */
    private function guardTenant(int $institute): bool
    {
        $existing = DB::table('tblstudent')->where('sub_institute_id', $institute)->count();

        if ($existing === 0) {
            return true;
        }

        $synthetic = DB::table('tblstudent')
            ->where('sub_institute_id', $institute)
            ->where('email', 'like', '%@synthetic.invalid')
            ->count();

        if ($synthetic === $existing) {
            $this->warn(sprintf('Institute %d already holds %d synthetic students — purging first.', $institute, $existing));
            $this->purge($institute, quiet: true);

            return true;
        }

        $this->error(sprintf(
            'Institute %d holds %d students, %d of which are not synthetic. Refusing to write.',
            $institute,
            $existing,
            $existing - $synthetic
        ));
        $this->line('  Pick an unused sub_institute_id. Nothing was written.');

        return false;
    }

    private function purge(int $institute, bool $quiet = false): int
    {
        if (! $quiet) {
            $this->line('');
        }

        $studentIds = DB::table('tblstudent')->where('sub_institute_id', $institute)->pluck('id')->all();
        $total = 0;

        // What the AI concluded about this school goes first, while the rows it points at
        // still exist — and the two link tables go before the rows they link, since they
        // carry no tenant column of their own and can only be found by join.
        $caseIds = Schema::hasTable('ai_cases')
            ? DB::table('ai_cases')->where('sub_institute_id', $institute)->pluck('id')->all()
            : [];
        $evidenceIds = Schema::hasTable('ai_evidence')
            ? DB::table('ai_evidence')->where('sub_institute_id', $institute)->pluck('id')->all()
            : [];

        foreach (['ai_case_evidence' => $caseIds, 'ai_case_signals' => $caseIds] as $link => $ids) {
            if ($ids !== [] && Schema::hasTable($link)) {
                $total += DB::table($link)->whereIn('case_id', $ids)->delete();
            }
        }

        if ($evidenceIds !== [] && Schema::hasTable('ai_case_evidence')) {
            $total += DB::table('ai_case_evidence')->whereIn('evidence_id', $evidenceIds)->delete();
        }

        foreach (self::AI_OUTPUT_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sub_institute_id')) {
                continue;
            }

            $deleted = DB::table($table)->where('sub_institute_id', $institute)->delete();
            $total += $deleted;

            if ($deleted > 0 && ! $quiet) {
                $this->line(sprintf('  %-24s %d rows', $table, $deleted));
            }
        }

        foreach (self::OWNED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $deleted = Schema::hasColumn($table, 'sub_institute_id')
                ? DB::table($table)->where('sub_institute_id', $institute)->delete()
                : 0;

            $total += $deleted;

            if ($deleted > 0 && ! $quiet) {
                $this->line(sprintf('  %-24s %d rows', $table, $deleted));
            }
        }

        // `lms_online_exam` has no tenant column, so it is cleared by student instead.
        if ($studentIds !== [] && Schema::hasTable('lms_online_exam')) {
            $byStudent = DB::table('lms_online_exam')->whereIn('student_id', $studentIds)->delete();
            $total += $byStudent;

            if ($byStudent > 0 && ! $quiet) {
                $this->line(sprintf('  %-24s %d rows', 'lms_online_exam', $byStudent));
            }
        }

        // Also drop the rights rows and untag the shared menus, so a purge genuinely
        // returns the estate to the state it was in before this command ran.
        $rights = DB::table('tblgroupwise_rights')->where('sub_institute_id', $institute)->delete();
        $menus = $this->revokeMenuAccess($institute);
        $total += $rights;

        if (($rights > 0 || $menus > 0) && ! $quiet) {
            $this->line(sprintf('  %-24s %d rows', 'tblgroupwise_rights', $rights));
            $this->line(sprintf('  %-24s %d rows untagged', 'tblmenumaster', $menus));
        }

        if (! $quiet) {
            $this->info(sprintf('Removed %d rows from institute %d.', $total, $institute));
            $this->line('');
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- structure

    /**
     * @return array{grade:int, standards:array<int, array{id:int, name:string}>, divisions:array<int,int>, subjects:array<int, array{id:int, name:string}>}
     */
    private function seedStructure(int $institute, int $year): array
    {
        $gradeId = (int) DB::table('academic_section')->insertGetId([
            'title' => 'Secondary',
            'short_name' => 'SEC',
            'sort_order' => 1,
            'shift' => 'Morning',
            'medium' => 'English',
            'payment_link' => '',
            'sub_institute_id' => $institute,
        ]);

        $standards = [];

        foreach (['6', '7', '8', '9', '10'] as $i => $name) {
            $standards[] = [
                'id' => (int) DB::table('standard')->insertGetId([
                    'grade_id' => $gradeId,
                    'name' => $name,
                    'short_name' => 'STD' . $name,
                    'sort_order' => $i + 1,
                    'sub_institute_id' => $institute,
                ]),
                'name' => $name,
            ];
        }

        $divisions = [];

        foreach (['A', 'B', 'C'] as $name) {
            $divisions[] = (int) DB::table('division')->insertGetId([
                'name' => $name,
                'sub_institute_id' => $institute,
            ]);
        }

        $subjects = [];
        $subjectCols = Schema::getColumnListing('subject');

        foreach (self::SUBJECTS as $i => $name) {
            $row = array_filter([
                'subject_name' => in_array('subject_name', $subjectCols, true) ? $name : null,
                'name' => in_array('name', $subjectCols, true) ? $name : null,
                'short_name' => in_array('short_name', $subjectCols, true) ? strtoupper(substr($name, 0, 3)) : null,
                'sort_order' => in_array('sort_order', $subjectCols, true) ? $i + 1 : null,
                'sub_institute_id' => $institute,
            ], static fn ($v) => $v !== null);

            $subjects[] = ['id' => (int) DB::table('subject')->insertGetId($row), 'name' => $name];
        }

        // Map every subject to every class, which is what a secondary timetable looks like.
        $maps = [];

        foreach ($standards as $standard) {
            foreach ($subjects as $subject) {
                $maps[] = [
                    'subject_id' => $subject['id'],
                    'standard_id' => $standard['id'],
                    'display_name' => $subject['name'],
                    'sub_institute_id' => $institute,
                    'status' => 1,
                ];
            }
        }

        DB::table('sub_std_map')->insert($maps);

        return ['grade' => $gradeId, 'standards' => $standards, 'divisions' => $divisions, 'subjects' => $subjects];
    }

    // ---------------------------------------------------------------- browser access

    /**
     * Let this institute's admin sign in to the browser UI.
     *
     * Opt-in, because it is the one part of this command that touches rows the live
     * estate shares. Logging in requires a menu, and menu visibility is stored as a
     * comma-separated list of institute ids on each of ~478 `tblmenumaster` rows — a
     * table every real user's navigation is read from.
     *
     * Two things keep that safe rather than merely careful:
     *
     *   - The update only ever **appends**. `CONCAT(sub_institute_id, ',9001')` cannot
     *     remove an institute that is already listed, so no existing school can lose a
     *     menu item however the string is shaped.
     *   - It is guarded by `FIND_IN_SET`, so running it twice adds nothing the second
     *     time and the list cannot accumulate duplicates.
     *
     * The rights rows are pure inserts scoped to this institute and touch nothing shared.
     * `--purge` reverses both.
     *
     * @return array{0:int, 1:int}
     */
    private function grantMenuAccess(int $institute): array
    {
        $menus = DB::table('tblmenumaster')
            ->whereRaw('NOT FIND_IN_SET(?, sub_institute_id)', [$institute])
            ->update([
                'sub_institute_id' => DB::raw(sprintf("CONCAT(sub_institute_id, ',%d')", $institute)),
            ]);

        // Mirror the Admin profile's rights from a school that has them, so the synthetic
        // admin sees the same navigation a real one does rather than a hand-picked subset.
        $template = DB::table('tblgroupwise_rights')
            ->where('profile_id', 1)
            ->where('sub_institute_id', 1)
            ->get();

        DB::table('tblgroupwise_rights')
            ->where('sub_institute_id', $institute)
            ->delete();

        $rows = $template->map(static fn ($row) => [
            'menu_id' => $row->menu_id,
            'profile_id' => 1,
            'can_view' => $row->can_view,
            'can_add' => $row->can_add,
            'can_edit' => $row->can_edit,
            'can_delete' => $row->can_delete,
            'dashboard_right' => $row->dashboard_right,
            'sub_institute_id' => $institute,
            'sort_order' => $row->sort_order,
            'is_mobile' => $row->is_mobile,
            'created_at' => now(),
        ])->all();

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('tblgroupwise_rights')->insert($chunk);
        }

        return [$menus, count($rows)];
    }

    /**
     * Take this institute back out of the shared menu rows.
     *
     * Removes the id and tidies the separator it leaves behind, so a list never ends up
     * with an empty element or a trailing comma.
     */
    private function revokeMenuAccess(int $institute): int
    {
        return DB::table('tblmenumaster')
            ->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$institute])
            ->update([
                'sub_institute_id' => DB::raw(sprintf(
                    "TRIM(BOTH ',' FROM REPLACE(CONCAT(',', sub_institute_id, ','), ',%d,', ','))",
                    $institute
                )),
            ]);
    }

    // ---------------------------------------------------------------- staff

    /**
     * An admin to sign in as, and teachers for the school to be staffed by.
     *
     * Without a user in this institute the synthetic school is unreachable from the
     * browser: the panel scopes every question by the signed-in user's institute, and
     * that comes from the auth token rather than from anything the client can ask for.
     * A school with no staff also makes the teacher-facing tools answer honestly but
     * uselessly.
     */
    private function seedStaff(int $institute, int $year): int
    {
        $password = Hash::make(self::DEMO_PASSWORD);
        $created = 0;

        DB::table('tbluser')->insert([
            'user_name' => 'demo.admin',
            'password' => $password,
            'first_name' => 'Priya',
            'last_name' => 'Menon',
            'email' => 'demo.admin@synthetic.invalid',
            'mobile' => '9' . mt_rand(100000000, 999999999),
            'gender' => 'F',
            // 1 is Admin. The admissions confirmation tool requires an admin, so the
            // account handed over has to be one or half the flow cannot be tested.
            'user_profile_id' => 1,
            'join_year' => $year,
            'sub_institute_id' => $institute,
            'status' => 1,
            'created_on' => now(),
        ]);

        $created++;

        $teachers = [
            ['Anand', 'Kulkarni', 'M'], ['Shalini', 'Iyer', 'F'], ['Rakesh', 'Bhatt', 'M'],
            ['Nandini', 'Rao', 'F'], ['Vikram', 'Desai', 'M'], ['Farida', 'Shaikh', 'F'],
        ];

        foreach ($teachers as $i => [$first, $last, $gender]) {
            DB::table('tbluser')->insert([
                'user_name' => strtolower($first . '.' . $last),
                'password' => $password,
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first . '.' . $last) . '@synthetic.invalid',
                'mobile' => '9' . mt_rand(100000000, 999999999),
                'gender' => $gender,
                'user_profile_id' => 5,
                'join_year' => $year - mt_rand(0, 6),
                'sub_institute_id' => $institute,
                'status' => 1,
                'created_on' => now()->subYears(mt_rand(0, 6)),
            ]);

            $created++;
        }

        return $created;
    }

    // ---------------------------------------------------------------- students

    /**
     * Enrol children, each with the latent traits every later observation is drawn from.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seedStudents(int $institute, int $year, int $count, array $structure): array
    {
        $students = [];

        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)];
            $middle = self::MIDDLE_NAMES[mt_rand(0, count(self::MIDDLE_NAMES) - 1)];
            $last = self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];

            $standard = $structure['standards'][mt_rand(0, count($structure['standards']) - 1)];
            $division = $structure['divisions'][mt_rand(0, count($structure['divisions']) - 1)];

            // Age follows the class, with the year-either-way spread a real roll has.
            $age = 11 + (int) $standard['name'] - 6 + (mt_rand(0, 2) - 1);

            $studentId = (int) DB::table('tblstudent')->insertGetId([
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
                'gender' => mt_rand(0, 1) ? 'M' : 'F',
                'dob' => now()->subYears($age)->subDays(mt_rand(0, 364))->toDateString(),
                'mobile' => '9' . mt_rand(100000000, 999999999),
                // .invalid is reserved by RFC 2606 and can never route anywhere. It is
                // also how `--purge` recognises its own rows.
                'email' => strtolower($first . '.' . $last . '.' . ($i + 1)) . '@synthetic.invalid',
                'enrollment_no' => sprintf('%d/%04d', $year, $i + 1),
                'admission_year' => $year,
                'admission_date' => now()->subMonths(mt_rand(3, 30))->toDateString(),
                'file_size' => '',
                'file_type' => '',
                'status' => 1,
                'sub_institute_id' => $institute,
                'created_on' => now(),
            ]);

            DB::table('tblstudent_enrollment')->insert([
                'syear' => $year,
                'student_id' => $studentId,
                'roll_no' => (string) (($i % 40) + 1),
                'grade_id' => $structure['grade'],
                'standard_id' => $standard['id'],
                'section_id' => $division,
                'student_quota' => 'General',
                'start_date' => now()->subMonths(6)->toDateString(),
                'sub_institute_id' => $institute,
                'created_on' => now(),
            ]);

            $students[] = [
                'id' => $studentId,
                'name' => "$first $last",
                'standard_id' => $standard['id'],
                'division_id' => $division,
                // ---- latent traits ------------------------------------------------
                // Nothing downstream is chosen; everything is sampled from these.
                'ability' => $this->normal(0.66, 0.15, 0.15, 0.99),
                'diligence' => $this->normal(0.86, 0.16, 0.20, 1.0),
                'attends' => $this->normal(0.94, 0.06, 0.55, 1.0),
                // A term-long drift. Most children hold steady; a few climb, a few slide.
                'drift' => $this->normal(0.0, 0.055, -0.20, 0.20),
            ];
        }

        return $students;
    }

    // ---------------------------------------------------------------- observations

    /**
     * Sixty school days of attendance, drawn from each child's own propensity.
     */
    private function seedAttendance(int $institute, int $year, array $students): int
    {
        $days = $this->schoolDays(60);
        $rows = [];
        $written = 0;

        foreach ($students as $student) {
            foreach ($days as $index => $day) {
                // Attendance decays slightly for a child who is drifting downward, which
                // is what makes a term-long slide visible rather than uniform noise.
                $rate = $student['attends'] + ($student['drift'] * ($index / count($days)));
                $roll = mt_rand(0, 10000) / 10000;

                $code = match (true) {
                    $roll < max(0.01, 1 - $rate) => 'A',
                    $roll < max(0.02, 1 - $rate + 0.03) => 'L',
                    default => 'P',
                };

                $rows[] = [
                    'syear' => $year,
                    'student_id' => $student['id'],
                    'attendance_date' => $day,
                    'attendance_code' => $code,
                    'user_group_id' => 1,
                    'standard_id' => $student['standard_id'],
                    'section_id' => $student['division_id'],
                    'sub_institute_id' => $institute,
                    'created_on' => $day . ' 09:' . str_pad((string) mt_rand(5, 55), 2, '0', STR_PAD_LEFT) . ':00',
                ];

                if (count($rows) >= 900) {
                    DB::table('attendance_student')->insert($rows);
                    $written += count($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('attendance_student')->insert($rows);
            $written += count($rows);
        }

        return $written;
    }

    /**
     * Homework, with completion drawn from diligence rather than assigned to it.
     */
    private function seedHomework(int $institute, int $year, array $students, array $structure): int
    {
        $rows = [];
        $written = 0;

        foreach ($students as $student) {
            // Six to eleven pieces over the 45-day detector window, which is a realistic
            // load and comfortably above its minimum of three.
            $items = mt_rand(6, 11);

            for ($i = 0; $i < $items; $i++) {
                $subject = $structure['subjects'][mt_rand(0, count($structure['subjects']) - 1)];
                $titles = self::HOMEWORK[$subject['name']];
                $daysAgo = mt_rand(2, 42);
                $due = now()->subDays($daysAgo);

                $chance = $student['diligence'] + ($student['drift'] * 0.8);
                $completed = (mt_rand(0, 10000) / 10000) < $chance;

                $rows[] = [
                    'sub_institute_id' => $institute,
                    'syear' => $year,
                    'student_id' => $student['id'],
                    'standard_id' => $student['standard_id'],
                    'division_id' => $student['division_id'],
                    'subject_id' => $subject['id'],
                    'title' => $titles[mt_rand(0, count($titles) - 1)],
                    'description' => 'Complete and submit for review.',
                    'date' => $due->toDateString(),
                    'type' => 'homework',
                    // A completed item carries a submission date, usually on time and
                    // sometimes a day or two late. A missed one carries neither.
                    'submission_date' => $completed
                        ? $due->copy()->addDays(mt_rand(0, 2))->toDateString()
                        : null,
                    'completion_status' => $completed ? 'Y' : 'N',
                    'created_on' => $due->copy()->subDays(mt_rand(2, 6)),
                ];

                if (count($rows) >= 900) {
                    DB::table('homework')->insert($rows);
                    $written += count($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('homework')->insert($rows);
            $written += count($rows);
        }

        return $written;
    }

    /**
     * Assessment attempts, scored from ability plus the term's drift.
     *
     * The decline detector compares recent attempts against earlier ones, so the drift
     * has to accumulate across the sequence rather than being noise on each attempt —
     * otherwise no child ever shows a trend and the detector has nothing to find.
     */
    private function seedAssessments(array $students): int
    {
        $rows = [];
        $written = 0;

        foreach ($students as $student) {
            $attempts = mt_rand(5, 9);

            for ($i = 0; $i < $attempts; $i++) {
                // Oldest first, spread across the 180-day window the detector reads.
                $daysAgo = (int) round(150 - ($i * (140 / max(1, $attempts - 1)))) + mt_rand(-4, 4);
                $when = now()->subDays(max(1, $daysAgo));

                $progress = $attempts > 1 ? $i / ($attempts - 1) : 1.0;
                $score = $student['ability'] + ($student['drift'] * $progress * 2.2);
                $score += (mt_rand(-120, 120) / 1000);
                $score = max(0.05, min(0.99, $score));

                $questions = mt_rand(8, 20);
                $right = (int) round($questions * $score);
                $wrong = $questions - $right;

                $rows[] = [
                    'student_id' => $student['id'],
                    'question_paper_id' => mt_rand(1, 12),
                    'total_right' => $right,
                    'total_wrong' => $wrong,
                    'obtain_marks' => $right,
                    'start_time' => $when->copy()->setTime(mt_rand(9, 15), mt_rand(0, 59)),
                    'accuracy_rate' => round($score * 100, 2),
                    'created_at' => $when,
                ];

                if (count($rows) >= 900) {
                    DB::table('lms_online_exam')->insert($rows);
                    $written += count($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('lms_online_exam')->insert($rows);
            $written += count($rows);
        }

        return $written;
    }

    /**
     * Admission enquiries at varying stages of completeness.
     *
     * Deliberately uneven: the confirmation flow exists to collect what is missing, so a
     * fixture where every enquiry is complete would never exercise it.
     */
    private function seedAdmissions(int $institute, int $year, array $structure): int
    {
        $count = 12;

        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)];
            $last = self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];
            $standard = $structure['standards'][mt_rand(0, count($structure['standards']) - 1)];

            $raised = now()->subDays(mt_rand(1, 60));

            $enquiryId = (int) DB::table('admission_enquiry')->insertGetId([
                'enquiry_no' => sprintf('ENQ/%d/%03d', $year, $i + 1),
                'first_name' => $first,
                'last_name' => $last,
                'gender' => mt_rand(0, 1) ? 'M' : 'F',
                'admission_standard' => $standard['id'],
                'mobile' => '9' . mt_rand(100000000, 999999999),
                'email' => strtolower($first . '.' . $last) . '.enq@synthetic.invalid',
                'source_of_enquiry' => ['Walk-in', 'Website', 'Referral', 'Phone'][mt_rand(0, 3)],
                'syear' => $year,
                'sub_institute_id' => $institute,
                'created_on' => $raised,
            ]);

            // Two thirds have been registered; of those, some are still incomplete.
            if (mt_rand(0, 100) > 33) {
                $complete = mt_rand(0, 100) > 45;

                DB::table('admission_registration')->insert([
                    'enquiry_id' => $enquiryId,
                    'enquiry_no' => sprintf('ENQ/%d/%03d', $year, $i + 1),
                    'sub_institute_id' => $institute,
                    'admission_division' => $complete ? (string) $structure['divisions'][mt_rand(0, 2)] : null,
                    'student_quota' => $complete ? 'General' : null,
                    'admission_date' => $complete ? now()->subDays(mt_rand(1, 40))->toDateString() : null,
                    'enrollment_no' => $complete ? sprintf('%d/A%03d', $year, $i + 1) : null,
                    'created_on' => $raised->copy()->addDays(mt_rand(1, 5)),
                ]);
            }
        }

        return $count;
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The last N weekdays. A school calendar with Saturday attendance rows in it is the
     * kind of detail that makes a fixture feel generated.
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

    /**
     * Say what shape of cohort was produced, without naming anyone as at-risk.
     *
     * The point of the summary is that nobody chose these numbers — they are what the
     * distributions happened to yield, and the detectors will make their own judgement.
     */
    private function reportTail(array $students): void
    {
        $lowAttendance = count(array_filter($students, static fn ($s) => $s['attends'] < 0.85));
        $lowAbility = count(array_filter($students, static fn ($s) => $s['ability'] < 0.45));
        $declining = count(array_filter($students, static fn ($s) => $s['drift'] < -0.05));
        $lowDiligence = count(array_filter($students, static fn ($s) => $s['diligence'] < 0.7));

        $this->line('<fg=gray>  The cohort that came out — nobody was flagged, these are the tails:</>');
        $this->line(sprintf('<fg=gray>    %d with attendance propensity under 85%%</>', $lowAttendance));
        $this->line(sprintf('<fg=gray>    %d scoring under 45%% on ability</>', $lowAbility));
        $this->line(sprintf('<fg=gray>    %d on a downward term trend</>', $declining));
        $this->line(sprintf('<fg=gray>    %d likely to miss homework</>', $lowDiligence));
        $this->line('<fg=gray>  Which of them the detectors actually raise is their decision, not this seeder\'s.</>');
    }
}
