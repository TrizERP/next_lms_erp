<?php

namespace Tests\Feature\ExamEvaluation;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_10_13_100000_add_review_fields_to_exam_evaluation, exercised on in-memory
 * sqlite. The base case has already run up() once (the real migration files build
 * the test schema), so each test starts from a migrated database.
 *
 * What this cannot show: MySQL's own locking and index-build behaviour on a large
 * production table. Both tables are small, and the migration adds one nullable
 * column and one index, but that part is reasoned about, not run.
 */
class ReviewFieldsMigrationTest extends ExamEvaluationTestCase
{
    private const INDEX = 'ees_batch_student_unique';

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require base_path('database/migrations/2026_10_13_100000_add_review_fields_to_exam_evaluation.php');
    }

    private function hasIndex(): bool
    {
        return collect(Schema::getIndexes('exam_evaluation_sheet'))->contains(fn ($index) => ($index['name'] ?? null) === self::INDEX);
    }

    private function hasFeedbackColumn(): bool
    {
        return Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback');
    }

    private function sheetRow(int $batchId, ?int $studentId): int
    {
        return DB::table('exam_evaluation_sheet')->insertGetId([
            'batch_id' => $batchId, 'sub_institute_id' => self::TENANT, 'syear' => self::SYEAR, 'file_name' => 'f-' . uniqid() . '.pdf',
            'student_id' => $studentId, 'status' => 'Evaluated', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_up_adds_the_column_and_the_index_and_is_safe_to_run_again(): void
    {
        $this->assertTrue($this->hasFeedbackColumn());
        $this->assertTrue($this->hasIndex());

        $this->migration->up();
        $this->migration->up();

        $this->assertTrue($this->hasFeedbackColumn());
        $this->assertTrue($this->hasIndex());
        $this->assertSame(1, collect(Schema::getIndexes('exam_evaluation_sheet'))->where('name', self::INDEX)->count(), 'never a second copy of the index');
    }

    public function test_up_on_a_database_that_has_neither_creates_both(): void
    {
        $this->migration->down();
        $this->assertFalse($this->hasFeedbackColumn());
        $this->assertFalse($this->hasIndex());

        $this->migration->up();

        $this->assertTrue($this->hasFeedbackColumn());
        $this->assertTrue($this->hasIndex());
    }

    public function test_up_leaves_existing_rows_untouched_and_the_new_column_empty(): void
    {
        $this->migration->down();
        $batch = $this->makeBatch();
        $sheet = $this->makeSheet($batch);
        $before = (array) DB::table('exam_evaluation_answer')->where('sheet_id', $sheet)->orderBy('question_no')->first();

        $this->migration->up();

        $after = (array) DB::table('exam_evaluation_answer')->where('sheet_id', $sheet)->orderBy('question_no')->first();
        $this->assertNull($after['teacher_feedback'], 'new column starts NULL');
        unset($after['teacher_feedback']);
        $this->assertSame($before, $after, 'no existing column value may change');
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheet)->count());
    }

    public function test_down_removes_both_and_a_second_down_is_harmless(): void
    {
        $this->migration->down();

        $this->assertFalse($this->hasFeedbackColumn());
        $this->assertFalse($this->hasIndex());

        $this->migration->down(); // e.g. rolled back after up() skipped the index
        $this->assertFalse($this->hasFeedbackColumn());
    }

    public function test_down_discards_teacher_feedback_and_nothing_else(): void
    {
        $batch = $this->makeBatch();
        $sheet = $this->makeSheet($batch);
        DB::table('exam_evaluation_answer')->where('sheet_id', $sheet)->where('question_no', 2)
            ->update(['teacher_feedback' => 'Good attempt.', 'teacher_marks' => 4]);

        $this->migration->down();

        $row = DB::table('exam_evaluation_answer')->where('sheet_id', $sheet)->where('question_no', 2)->first();
        $this->assertEquals(4.0, $row->teacher_marks, 'marks survive a rollback');
        $this->assertFalse(property_exists($row, 'teacher_feedback'), 'ROLLBACK DISCARDS TEACHER FEEDBACK: this is the one irreversible part');
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $sheet)->count());
    }

    public function test_the_index_makes_a_second_sheet_for_the_same_student_impossible(): void
    {
        $batch = $this->makeBatch();
        $this->sheetRow($batch, 101);

        $this->expectException(QueryException::class);
        $this->sheetRow($batch, 101);
    }

    public function test_the_same_student_may_have_a_sheet_in_a_different_batch(): void
    {
        $this->sheetRow($this->makeBatch(), 101);
        $this->sheetRow($this->makeBatch(['name' => 'Another evaluation']), 101);

        $this->assertSame(2, DB::table('exam_evaluation_sheet')->where('student_id', 101)->count());
    }

    public function test_any_number_of_unmatched_sheets_may_share_a_batch(): void
    {
        $batch = $this->makeBatch();

        foreach (range(1, 5) as $ignored) {
            $this->sheetRow($batch, null);
        }

        $this->assertSame(5, DB::table('exam_evaluation_sheet')->where('batch_id', $batch)->whereNull('student_id')->count());
    }

    public function test_existing_duplicate_sheets_make_it_skip_the_index_and_log_instead_of_failing(): void
    {
        $this->migration->down();
        $batch = $this->makeBatch();
        $first = $this->sheetRow($batch, 101);
        $second = $this->sheetRow($batch, 101);

        Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context) => str_contains($message, 'duplicate sheets exist') && count($context['duplicates']) === 1);

        $this->migration->up();   // must not throw

        $this->assertTrue($this->hasFeedbackColumn(), 'the column is still added');
        $this->assertFalse($this->hasIndex(), 'the index is skipped while duplicates exist');
        $this->assertSame(2, DB::table('exam_evaluation_sheet')->where('batch_id', $batch)->count(), 'no row is touched, merged or deleted');

        // Once the teacher resolves the duplicate, running migrate again picks the index up.
        DB::table('exam_evaluation_sheet')->where('id', $second)->update(['student_id' => null]);
        $this->migration->up();

        $this->assertTrue($this->hasIndex());
        $this->assertNotNull(DB::table('exam_evaluation_sheet')->find($first));
    }

    public function test_the_pre_deploy_health_queries_run_and_count_what_they_claim(): void
    {
        $batch = $this->makeBatch();

        // A healthy approved sheet.
        $healthy = $this->makeSheet($batch, ['student_id' => 101, 'status' => 'Approved', 'teacher_total' => 5, 'max_marks' => 10, 'percentage' => 50]);
        DB::table('exam_evaluation_answer')->where('sheet_id', $healthy)->update(['teacher_marks' => 1]);
        // Approved, but a question never got a teacher mark.
        $unmarked = $this->makeSheet($batch, ['student_id' => 102, 'status' => 'Approved', 'teacher_total' => 5, 'max_marks' => 10, 'percentage' => 50]);
        DB::table('exam_evaluation_answer')->where('sheet_id', $unmarked)->where('question_no', '!=', 3)->update(['teacher_marks' => 1]);
        // The old bug: teacher total edited, percentage left at the AI's value.
        $this->makeSheet($batch, ['student_id' => null, 'status' => 'Needs review', 'teacher_total' => 8, 'max_marks' => 10, 'percentage' => 50]);
        // A mark above its maximum (the old clamp made this impossible; a manual edit could not).
        DB::table('exam_evaluation_answer')->where('sheet_id', $healthy)->where('question_no', 1)->update(['teacher_marks' => 9]);

        $byStatus = collect(DB::select('SELECT status, COUNT(*) AS sheets FROM exam_evaluation_sheet GROUP BY status ORDER BY status'))->pluck('sheets', 'status')->map(fn ($n) => (int) $n)->all();
        $this->assertSame(['Approved' => 2, 'Needs review' => 1], $byStatus);

        $unmarkedApproved = DB::select(
            "SELECT COUNT(*) AS n FROM exam_evaluation_sheet s
             WHERE s.status = 'Approved'
               AND EXISTS (SELECT 1 FROM exam_evaluation_answer a WHERE a.sheet_id = s.id AND a.teacher_marks IS NULL)"
        );
        $this->assertSame(1, (int) $unmarkedApproved[0]->n);

        $above = DB::select('SELECT COUNT(*) AS n FROM exam_evaluation_answer WHERE teacher_marks > max_marks');
        $this->assertSame(1, (int) $above[0]->n);

        $stale = DB::select(
            'SELECT COUNT(*) AS n FROM exam_evaluation_sheet
             WHERE teacher_total IS NOT NULL AND max_marks > 0
               AND ABS(COALESCE(percentage, 0) - ROUND(teacher_total * 100.0 / max_marks, 2)) > 0.01'
        );
        $this->assertSame(1, (int) $stale[0]->n, 'only the 8/10 sheet showing 50% is stale');
    }

    public function test_the_read_only_duplicate_report_finds_duplicates_and_reports_only_counts(): void
    {
        $this->migration->down();
        $batch = $this->makeBatch();
        $this->sheetRow($batch, 101);
        $this->sheetRow($batch, 101);
        $this->sheetRow($batch, 101);
        $this->sheetRow($batch, 102);
        $this->sheetRow($batch, null);
        $this->sheetRow($batch, null);

        // The aggregate-only form given in the deployment checklist (no names, files or student ids selected).
        $report = DB::select(
            'SELECT batch_id, COUNT(*) AS sheets_for_one_student, COUNT(*) - 1 AS extra_sheets
             FROM exam_evaluation_sheet
             WHERE student_id IS NOT NULL
             GROUP BY batch_id, student_id
             HAVING COUNT(*) > 1'
        );

        $this->assertCount(1, $report);
        $this->assertSame(3, (int) $report[0]->sheets_for_one_student);
        $this->assertSame(2, (int) $report[0]->extra_sheets);
        $this->assertSame(['batch_id', 'sheets_for_one_student', 'extra_sheets'], array_keys((array) $report[0]), 'no student-identifying column is returned');
    }
}
