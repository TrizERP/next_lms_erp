<?php

namespace Tests\Feature\ExamEvaluation\IsolatedMysql;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\ExamEvaluation\ExamEvaluationTestCase;

/**
 * The two exam-evaluation migrations on a REAL MySQL/MariaDB -- the part SQLite cannot show:
 * the column order and exact types the ALTER produces, the unique index on InnoDB, and what
 * happens on a table that already holds duplicate sheets.
 *
 * Opt-in and throwaway-only; see UsesIsolatedMysql. Skipped when not pointed at a scratch server.
 */
class MigrationsMysqlTest extends ExamEvaluationTestCase
{
    use UsesIsolatedMysql;

    private const CREATE = '2026_09_21_000000_create_exam_evaluation_tables';
    private const FIRST = '2026_10_13_100000_add_review_fields_to_exam_evaluation';
    private const SECOND = '2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedMysql('mig');
        // The sqlite schema built by the base class is on a different connection; start clean here.
        $this->migrate(self::CREATE);
    }

    protected function tearDown(): void
    {
        $this->dropIsolatedMysql();
        parent::tearDown();
    }

    private function migrate(string $name, string $direction = 'up'): void
    {
        (require base_path("database/migrations/{$name}.php"))->{$direction}();
    }

    /** @return array<string,mixed> */
    private function column(string $table, string $column): array
    {
        return (array) DB::selectOne(
            'select column_name, data_type, column_type, is_nullable, column_default, ordinal_position
               from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?',
            [$table, $column]
        );
    }

    /** @return array<int,string> */
    private function uniqueIndexColumns(string $index): array
    {
        return collect(DB::select(
            'select column_name from information_schema.statistics
              where table_schema = database() and table_name = ? and index_name = ? and non_unique = 0 order by seq_in_index',
            ['exam_evaluation_sheet', $index]
        ))->pluck('column_name')->all();
    }

    /** @param  array<string,mixed>  $over */
    private function sheet(array $over = []): int
    {
        return DB::table('exam_evaluation_sheet')->insertGetId($over + [
            'batch_id' => 1, 'sub_institute_id' => self::TENANT, 'syear' => self::SYEAR, 'file_name' => 'f-' . uniqid() . '.pdf',
            'status' => 'Needs review', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // -- the chain, and what it leaves ---------------------------------------------------

    public function test_the_chain_applies_and_leaves_the_columns_where_and_what_they_should_be(): void
    {
        $this->migrate(self::FIRST);
        $this->migrate(self::SECOND);

        $source = $this->column('exam_evaluation_sheet', 'identity_source');
        $at = $this->column('exam_evaluation_sheet', 'identity_confirmed_at');
        $by = $this->column('exam_evaluation_sheet', 'identity_confirmed_by');

        // MySQL reports "no default" as a real NULL, MariaDB as the string 'NULL'; same meaning.
        $noDefault = static fn ($value) => $value === null || strtoupper((string) $value) === 'NULL';

        $this->assertSame('timestamp', $at['data_type']);
        $this->assertSame('YES', $at['is_nullable']);
        $this->assertTrue($noDefault($at['column_default']), 'no default: existing rows must not look confirmed');
        $this->assertSame('bigint(20) unsigned', strtolower((string) $by['column_type']));
        $this->assertSame('YES', $by['is_nullable']);
        $this->assertTrue($noDefault($by['column_default']));

        // Placed together, right after the column they qualify, in a readable order.
        $this->assertSame((int) $source['ordinal_position'] + 1, (int) $at['ordinal_position']);
        $this->assertSame((int) $source['ordinal_position'] + 2, (int) $by['ordinal_position']);

        $feedback = $this->column('exam_evaluation_answer', 'teacher_feedback');
        $remark = $this->column('exam_evaluation_answer', 'ai_remark');
        $this->assertSame('text', $feedback['data_type']);
        $this->assertSame('YES', $feedback['is_nullable']);
        $this->assertSame((int) $remark['ordinal_position'] + 1, (int) $feedback['ordinal_position']);

        $this->assertSame(['batch_id', 'student_id'], $this->uniqueIndexColumns('ees_batch_student_unique'));
    }

    public function test_running_the_whole_chain_twice_changes_nothing_and_never_duplicates_anything(): void
    {
        foreach ([self::FIRST, self::SECOND, self::FIRST, self::SECOND] as $name) {
            $this->migrate($name);
        }

        $names = collect(DB::select('select column_name from information_schema.columns where table_schema = database() and table_name = ?', ['exam_evaluation_sheet']))->pluck('column_name');
        $this->assertSame($names->count(), $names->unique()->count());
        $this->assertSame(1, (int) DB::selectOne("select count(*) c from information_schema.statistics where table_schema = database() and table_name = 'exam_evaluation_sheet' and index_name = 'ees_batch_student_unique' and seq_in_index = 1")->c);
    }

    public function test_the_two_are_independent_and_either_order_gives_the_same_table(): void
    {
        $this->migrate(self::SECOND);
        $this->migrate(self::FIRST);

        $this->assertTrue(Schema::hasColumns('exam_evaluation_sheet', ['identity_confirmed_at', 'identity_confirmed_by']));
        $this->assertTrue(Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback'));
        $this->assertSame(['batch_id', 'student_id'], $this->uniqueIndexColumns('ees_batch_student_unique'));
    }

    public function test_a_table_missing_one_of_the_two_columns_is_completed(): void
    {
        $this->migrate(self::SECOND);
        Schema::table('exam_evaluation_sheet', fn (Blueprint $table) => $table->dropColumn('identity_confirmed_by'));

        $this->migrate(self::SECOND);

        $this->assertTrue(Schema::hasColumns('exam_evaluation_sheet', ['identity_confirmed_at', 'identity_confirmed_by']));
    }

    public function test_the_after_position_is_skipped_rather_than_failing_when_its_anchor_is_missing(): void
    {
        // MySQL answers AFTER a missing column with "Unknown column" and aborts the ALTER.
        Schema::table('exam_evaluation_sheet', fn (Blueprint $table) => $table->dropColumn('identity_source'));

        $this->migrate(self::SECOND);

        $this->assertTrue(Schema::hasColumns('exam_evaluation_sheet', ['identity_confirmed_at', 'identity_confirmed_by']));
    }

    // -- existing data ------------------------------------------------------------------------

    public function test_rows_that_already_exist_keep_every_value_and_get_empty_confirmations(): void
    {
        $this->sheet(['student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confidence' => 95.5, 'status' => 'Evaluated']);
        $this->sheet(['student_id' => 102, 'identity_source' => 'manual', 'identity_confidence' => 100, 'status' => 'Approved', 'approved_by' => 55]);
        $this->sheet(['student_id' => null, 'identity_source' => 'unmatched']);
        $before = DB::table('exam_evaluation_sheet')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->migrate(self::FIRST);
        $this->migrate(self::SECOND);

        $after = DB::table('exam_evaluation_sheet')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $this->assertCount(3, $after);

        foreach ($after as $i => $row) {
            $this->assertNull($row['identity_confirmed_at']);
            $this->assertNull($row['identity_confirmed_by']);
            unset($row['identity_confirmed_at'], $row['identity_confirmed_by']);
            $this->assertEquals($before[$i], $row);
        }
    }

    // -- the unique index ---------------------------------------------------------------------------

    public function test_the_index_forbids_a_second_sheet_for_a_student_and_allows_any_number_of_unmatched_ones(): void
    {
        $this->migrate(self::FIRST);

        $this->sheet(['batch_id' => 7, 'student_id' => 101]);
        $this->sheet(['batch_id' => 8, 'student_id' => 101]);               // another batch: fine
        $this->sheet(['batch_id' => 7, 'student_id' => null]);
        $this->sheet(['batch_id' => 7, 'student_id' => null]);              // unmatched ones never clash

        $this->expectException(UniqueConstraintViolationException::class);
        $this->sheet(['batch_id' => 7, 'student_id' => 101]);
    }

    public function test_duplicates_make_it_skip_the_index_log_ids_only_and_pick_it_up_once_they_are_resolved(): void
    {
        $this->sheet(['batch_id' => 7, 'student_id' => 101]);
        $dupe = $this->sheet(['batch_id' => 7, 'student_id' => 101, 'original_name' => 'asha_patel.pdf']);

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message . ' ' . json_encode($event->context);
        });

        $this->migrate(self::FIRST);   // must not fail on the duplicate

        $this->assertSame([], $this->uniqueIndexColumns('ees_batch_student_unique'), 'no index while a duplicate exists');
        $this->assertTrue(Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback'), 'the rest of the migration still ran');
        $this->assertSame(2, DB::table('exam_evaluation_sheet')->count(), 'and nothing was deleted');

        $warning = collect($logged)->first(fn ($line) => str_contains($line, 'unique index'));
        $this->assertNotNull($warning, 'the skipped index is reported');
        $this->assertStringContainsString('"batch_id":7', $warning);
        $this->assertStringNotContainsString('asha', strtolower($warning), 'ids and counts only, never a scan\'s name');

        DB::table('exam_evaluation_sheet')->where('id', $dupe)->delete();
        $this->migrate(self::FIRST);

        $this->assertSame(['batch_id', 'student_id'], $this->uniqueIndexColumns('ees_batch_student_unique'));
    }

    // -- reversing ---------------------------------------------------------------------------------------

    public function test_rolling_both_back_removes_what_they_added_keeps_the_rows_and_is_harmless_twice(): void
    {
        $this->migrate(self::FIRST);
        $this->migrate(self::SECOND);
        $this->sheet(['batch_id' => 7, 'student_id' => 101, 'identity_source' => 'roll_no', 'identity_confirmed_at' => now(), 'identity_confirmed_by' => 55]);

        foreach ([self::SECOND, self::FIRST, self::SECOND, self::FIRST] as $name) {
            $this->migrate($name, 'down');
        }

        $this->assertFalse(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_at'));
        $this->assertFalse(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_by'));
        $this->assertFalse(Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback'));
        $this->assertSame([], $this->uniqueIndexColumns('ees_batch_student_unique'));
        $this->assertSame(1, DB::table('exam_evaluation_sheet')->count());
        $this->assertSame('roll_no', DB::table('exam_evaluation_sheet')->value('identity_source'));
        // The original tables and their own index are intact.
        $this->assertTrue(Schema::hasTable('exam_evaluation_batch'));
        $this->assertSame(1, (int) DB::selectOne("select count(*) c from information_schema.statistics where table_schema = database() and table_name = 'exam_evaluation_sheet' and index_name = 'ees_batch_status' and seq_in_index = 1")->c);
    }

    public function test_rolling_the_first_back_alone_leaves_the_second_one_s_columns(): void
    {
        $this->migrate(self::FIRST);
        $this->migrate(self::SECOND);

        $this->migrate(self::FIRST, 'down');

        $this->assertTrue(Schema::hasColumns('exam_evaluation_sheet', ['identity_confirmed_at', 'identity_confirmed_by']));
        $this->assertFalse(Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback'));
    }
}
