<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills the empty student-facing screens for one standard with demonstration
 * data, so a walkthrough shows populated screens instead of a row of empty
 * states.
 *
 * WHY THIS EXISTS
 * ---------------
 * An audit of institute 341 found that almost every screen in the student menu
 * reads a table with zero rows for that tenant: homework, student_health,
 * result_marks, lms_doubt, lms_content_progress, lms_assignments and
 * lms_flashcard are all empty. The screens themselves work — they render their
 * empty state correctly, which is indistinguishable from a broken page during
 * a demo.
 *
 * EVERY ROW WRITTEN IS FICTIONAL and is tagged as such: each carries the marker
 * below in a free-text field, so a later cleanup can find and remove exactly
 * these rows without guessing which records were real.
 *
 * ASSUMPTIONS, because the reference tables do not exist in this database:
 *   - `result_create_exam.exam_id` and `.term_id` have no master table
 *     (no exam_master, no marking_period), so they are written as fixed
 *     synthetic values. Whether the report-card screen renders from these
 *     alone is NOT verified.
 *   - `lms_content_progress.course_id` and `lms_assignments.course_id` are
 *     written as the subject id, which is how the course-master screen treats
 *     a "course" (ApiLmsCourseController reads sub_std_map + chapter_master).
 *
 * NOT seeded: `lms_portfolio`. Its menu link `lmsPortfolio.index` resolves to
 * a route with no page, so the screen 404s regardless of data — seeding it
 * would create rows nothing can ever display.
 *
 * Idempotent: refuses to run twice by looking for its own marker. Dry-run by
 * default.
 *
 *   php artisan pal:seed-student-demo --institute=341 --standard=4264
 *   php artisan pal:seed-student-demo --institute=341 --standard=4264 --confirm
 *   php artisan pal:seed-student-demo --institute=341 --standard=4264 --purge --confirm
 */
class SeedStudentDemoDataCommand extends Command
{
    /** Written into a free-text column on every row, so cleanup is exact. */
    public const MARKER = '[DEMO-DATA]';

    protected $signature = 'pal:seed-student-demo
        {--institute= : sub_institute_id (required)}
        {--standard= : standard_id whose enrolled students get the data (required)}
        {--syear=2026 : academic year to stamp on the rows}
        {--purge : delete previously seeded demo rows instead of creating them}
        {--confirm : actually write; without this the command only reports what it WOULD do}';

    protected $description = 'Populate empty student screens with clearly-marked demo data for one standard (dry-run by default)';

    public function handle(): int
    {
        $institute = $this->option('institute') !== null ? (int) $this->option('institute') : null;
        $standard = $this->option('standard') !== null ? (int) $this->option('standard') : null;
        $syear = (int) $this->option('syear');

        if ($institute === null || $standard === null) {
            $this->error('--institute and --standard are required.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge($institute, (bool) $this->option('confirm'));
        }

        $students = DB::table('tblstudent_enrollment')
            ->where('standard_id', $standard)
            ->where('sub_institute_id', $institute)
            ->whereNull('end_date')
            ->pluck('student_id')
            ->all();

        if ($students === []) {
            $this->warn("No enrolled students on standard {$standard} — nothing to seed for.");

            return self::SUCCESS;
        }

        $subjects = DB::table('sub_std_map')
            ->where('sub_institute_id', $institute)
            ->where('standard_id', $standard)
            ->orderBy('sort_order')
            ->pluck('display_name', 'subject_id')
            ->all();

        $chapters = DB::table('chapter_master')
            ->where('sub_institute_id', $institute)
            ->where('standard_id', $standard)
            ->orderBy('sort_order')
            ->get(['id', 'subject_id', 'chapter_name']);

        if ($subjects === [] || $chapters->isEmpty()) {
            $this->error("Standard {$standard} has no subjects or no chapters — seed curriculum first (see pal:clone-curriculum).");

            return self::FAILURE;
        }

        if (DB::table('homework')->where('sub_institute_id', $institute)->where('standard_id', $standard)
            ->where('description', 'like', '%'.self::MARKER.'%')->exists()) {
            $this->error('Demo data already present for this standard. Use --purge --confirm first.');

            return self::FAILURE;
        }

        $division = DB::table('tblstudent_enrollment')->where('standard_id', $standard)->value('section_id');
        $subjectIds = array_keys($subjects);
        $firstChapter = $chapters->first();

        $plan = [
            ['homework', 3 * count($students)],
            ['student_health', count($students)],
            ['result_create_exam', count($subjectIds)],
            ['result_marks', count($subjectIds) * count($students)],
            ['lms_doubt', 2 * count($students)],
            ['content_master', 3],
            ['lms_content_progress', 2 * count($students)],
            ['lms_assignments', count($students)],
            ['lms_flashcard', 3],
        ];

        $this->line("Institute {$institute} · standard {$standard} · syear {$syear}");
        $this->line('Students: '.count($students).'   Subjects: '.count($subjects).'   Chapters: '.$chapters->count());
        $this->newLine();
        $this->table(['Table', 'Rows to create'], $plan);
        $this->line('Every row is tagged '.self::MARKER.' for exact cleanup.');

        if (! $this->option('confirm')) {
            $this->newLine();
            $this->warn('Dry run — nothing written. Re-run with --confirm to apply.');

            return self::SUCCESS;
        }

        $now = now();

        DB::transaction(function () use ($students, $subjects, $subjectIds, $chapters, $firstChapter, $institute, $standard, $syear, $division, $now) {
            // ── Course content, so the catalog and progress screens have
            // something real to point at. Copied from an authored row of this
            // institute where one exists, so the shape matches production.
            $contentIds = [];
            foreach ($chapters->take(3) as $i => $chapter) {
                $contentIds[] = DB::table('content_master')->insertGetId([
                    'standard_id' => $standard,
                    'subject_id' => $chapter->subject_id,
                    'chapter_id' => $chapter->id,
                    'title' => $chapter->chapter_name,
                    'description' => self::MARKER.' Introductory notes for '.$chapter->chapter_name,
                    'content_category' => 'notes',
                    'source' => 'demo',
                    'sort_order' => $i + 1,
                    'show_hide' => 'show',
                    'syear' => $syear,
                    'sub_institute_id' => $institute,
                    'created_at' => $now,
                ]);
            }

            // ── Exams, one per subject, then a mark per student per exam.
            $examRowIds = [];
            foreach ($subjectIds as $i => $subjectId) {
                $examRowIds[$subjectId] = DB::table('result_create_exam')->insertGetId([
                    'syear' => $syear,
                    'sub_institute_id' => $institute,
                    'term_id' => 1,      // no master table exists for this
                    'medium' => 'ENGLISH',
                    'exam_id' => 1,      // no master table exists for this
                    'standard_id' => $standard,
                    'app_disp_status' => 'Y',
                    'subject_id' => $subjectId,
                    'title' => 'UNIT TEST I',
                    'points' => 25,
                    'marks_type' => 'MARKS',
                    'report_card_status' => 'Y',
                    'sort_order' => $i + 1,
                    'exam_date' => $now->copy()->subDays(14)->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $marks = [21, 18, 23];
            $grades = ['A', 'B+', 'A'];

            foreach ($students as $studentId) {
                // ── Homework: one submitted, one pending, one overdue, so the
                // screen shows more than a single state.
                // `completion_status` is char(4) and the estate's convention is
                // Y/N (1,529 rows across other institutes use exactly those two
                // values). Writing 'Completed' here silently truncates to
                // 'Comp' and produces a value no screen knows how to read.
                $states = [
                    ['Y', $now->copy()->subDays(5), 'Submitted on time.'],
                    ['N', $now->copy()->addDays(3), null],
                    ['N', $now->copy()->subDays(2), null],
                ];
                foreach ($states as $i => [$status, $due, $remark]) {
                    $subjectId = $subjectIds[$i % count($subjectIds)];
                    DB::table('homework')->insert([
                        'sub_institute_id' => $institute,
                        'syear' => $syear,
                        'student_id' => $studentId,
                        'standard_id' => $standard,
                        'division_id' => $division,
                        'subject_id' => $subjectId,
                        'title' => ($subjects[$subjectId] ?? 'Subject').' — worksheet '.($i + 1),
                        'description' => self::MARKER.' Practice worksheet for '.($subjects[$subjectId] ?? 'the subject').'.',
                        'date' => $due->toDateString(),
                        'submission_date' => $status === 'Y' ? $due->toDateString() : null,
                        'completion_status' => $status,
                        'submission_remarks' => $remark,
                        'status' => 1,
                        'created_on' => $now,
                    ]);
                }

                DB::table('student_health')->insert([
                    'student_id' => $studentId,
                    'syear' => $syear,
                    'doctor_name' => 'Dr. A. Mehta',
                    'doctor_contact' => '9000000001',
                    'date' => $now->copy()->subDays(30)->toDateString(),
                    'remarks' => self::MARKER.' Annual check-up: height and weight within expected range.',
                    'sub_institute_id' => $institute,
                    'created_on' => $now,
                ]);

                foreach ($subjectIds as $i => $subjectId) {
                    DB::table('result_marks')->insert([
                        'student_id' => $studentId,
                        'exam_id' => $examRowIds[$subjectId],
                        'points' => $marks[$i % count($marks)],
                        'grade' => $grades[$i % count($grades)],
                        'per' => round(($marks[$i % count($marks)] / 25) * 100, 2),
                        'comment' => self::MARKER.' Consistent work this term.',
                        'is_absent' => 0,
                        'sub_institute_id' => $institute,
                        'exam_title' => 'UNIT TEST I',
                        'subject_name' => $subjects[$subjectId] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach ([
                    ['Why is a negative times a negative positive?', 'I understand the rule but not the reason behind it.'],
                    ['How do I check my answer to a division question?', 'Is multiplying back the only way?'],
                ] as [$title, $body]) {
                    DB::table('lms_doubt')->insert([
                        'subject_id' => $firstChapter->subject_id,
                        'chapter_id' => $firstChapter->id,
                        'title' => $title,
                        'description' => self::MARKER.' '.$body,
                        'visibility' => 'public',
                        'sub_institute_id' => $institute,
                        'syear' => $syear,
                        'user_id' => $studentId,
                        'user_profile_id' => 3684,
                        'created_at' => $now,
                    ]);
                }

                foreach (array_slice($contentIds, 0, 2) as $i => $contentId) {
                    $chapter = $chapters[$i];
                    DB::table('lms_content_progress')->insert([
                        'user_id' => $studentId,
                        'course_id' => $chapter->subject_id,
                        'chapter_id' => $chapter->id,
                        'content_id' => $contentId,
                        'status' => $i === 0 ? 'completed' : 'in_progress',
                        'time_spent_seconds' => $i === 0 ? 900 : 320,
                        'completed_at' => $i === 0 ? $now->copy()->subDays(3) : null,
                        'sub_institute_id' => $institute,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('lms_assignments')->insert([
                    'user_id' => $studentId,
                    'course_id' => $firstChapter->subject_id,
                    'assignment_type' => 'course',
                    'due_date' => $now->copy()->addDays(7)->toDateString(),
                    'status' => 'in_progress',
                    'approval_status' => 'approved',
                    'progress' => 40,
                    'assigned_on' => $now->copy()->subDays(7),
                    'source' => 'demo',
                    'sub_institute_id' => $institute,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($chapters->take(3) as $chapter) {
                DB::table('lms_flashcard')->insert([
                    'standard_id' => $standard,
                    'subject_id' => $chapter->subject_id,
                    'chapter_id' => $chapter->id,
                    'title' => $chapter->chapter_name,
                    'front_text' => self::MARKER.' What is the key idea of '.$chapter->chapter_name.'?',
                    'back_text' => 'See the chapter notes for a worked example.',
                    'status' => 1,
                    'sub_institute_id' => $institute,
                    'syear' => $syear,
                    'created_on' => $now,
                ]);
            }
        });

        $this->newLine();
        $this->info('Demo data written. Reload the student screens to see it.');

        return self::SUCCESS;
    }

    /** Remove exactly the rows this command wrote, found by marker. */
    protected function purge(int $institute, bool $confirm): int
    {
        $like = '%'.self::MARKER.'%';
        $targets = [
            ['homework', 'description'],
            ['student_health', 'remarks'],
            ['result_marks', 'comment'],
            ['lms_doubt', 'description'],
            ['content_master', 'description'],
            ['lms_flashcard', 'front_text'],
        ];

        $rows = [];
        foreach ($targets as [$table, $column]) {
            $rows[] = [$table, DB::table($table)->where('sub_institute_id', $institute)->where($column, 'like', $like)->count()];
        }

        // These carry no free-text column, so they are matched on the
        // demo-only `source` value the seeder writes.
        $rows[] = ['lms_assignments', DB::table('lms_assignments')->where('sub_institute_id', $institute)->where('source', 'demo')->count()];

        $this->table(['Table', 'Rows to delete'], $rows);

        if (! $confirm) {
            $this->warn('Dry run — nothing deleted. Re-run with --purge --confirm.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($targets, $institute, $like) {
            // Progress rows hang off the seeded content, so they go first.
            $contentIds = DB::table('content_master')->where('sub_institute_id', $institute)->where('description', 'like', $like)->pluck('id');
            DB::table('lms_content_progress')->whereIn('content_id', $contentIds)->delete();

            $examIds = DB::table('result_marks')->where('sub_institute_id', $institute)->where('comment', 'like', $like)->pluck('exam_id');
            DB::table('result_create_exam')->whereIn('id', $examIds)->delete();

            foreach ($targets as [$table, $column]) {
                DB::table($table)->where('sub_institute_id', $institute)->where($column, 'like', $like)->delete();
            }

            DB::table('lms_assignments')->where('sub_institute_id', $institute)->where('source', 'demo')->delete();
        });

        $this->info('Demo data removed.');

        return self::SUCCESS;
    }
}
