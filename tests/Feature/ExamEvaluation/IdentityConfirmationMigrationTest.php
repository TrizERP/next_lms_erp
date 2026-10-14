<?php

namespace Tests\Feature\ExamEvaluation;

use App\Services\Evaluation\SheetIdentity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet, exercised on
 * in-memory SQLite. The base case has already run it once (the real migration files build
 * the test schema), so each test starts from a migrated database.
 *
 * What this cannot show: MySQL's own behaviour -- column position (AFTER), the form of the
 * ALTER, and locking on a real table. IsolatedMysql/MigrationsMysqlTest runs the same
 * migrations on a throwaway MySQL/MariaDB when one is pointed at; this file is the
 * engine-independent part.
 */
class IdentityConfirmationMigrationTest extends ExamEvaluationTestCase
{
    private const FILE = '2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet';
    private const FIRST = '2026_10_13_100000_add_review_fields_to_exam_evaluation';
    private const COLUMNS = ['identity_confirmed_at', 'identity_confirmed_by'];

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require base_path('database/migrations/' . self::FILE . '.php');
    }

    private function both(): bool
    {
        return Schema::hasColumns('exam_evaluation_sheet', self::COLUMNS);
    }

    /** @return array<string,array<string,mixed>> */
    private function columns(): array
    {
        return collect(Schema::getColumns('exam_evaluation_sheet'))->keyBy('name')->all();
    }

    private function dropIdentityColumns(): void
    {
        Schema::table('exam_evaluation_sheet', fn (Blueprint $table) => $table->dropColumn(self::COLUMNS));
        SheetIdentity::forgetSchema();
    }

    /** @param  array<string,mixed>  $over */
    private function sheet(array $over = []): int
    {
        return DB::table('exam_evaluation_sheet')->insertGetId($over + [
            'batch_id' => 1, 'sub_institute_id' => self::TENANT, 'syear' => self::SYEAR, 'file_name' => 'f-' . uniqid() . '.pdf',
            'status' => 'Needs review', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // -- what it adds --------------------------------------------------------------

    public function test_it_adds_the_two_columns_as_nullable_with_no_default(): void
    {
        $this->assertTrue($this->both());

        $columns = $this->columns();

        foreach (self::COLUMNS as $name) {
            $this->assertTrue($columns[$name]['nullable'], "{$name} must be nullable: existing rows have no confirmation");
            $this->assertNull($columns[$name]['default'], "{$name} must not default to a value");
        }

        $this->assertStringContainsString('int', strtolower($columns['identity_confirmed_by']['type']));
        $this->assertMatchesRegularExpression('/time/', strtolower($columns['identity_confirmed_at']['type']));
    }

    public function test_it_is_safe_to_run_again_and_never_adds_a_column_twice(): void
    {
        $before = array_keys($this->columns());

        $this->migration->up();
        $this->migration->up();

        $this->assertSame($before, array_keys($this->columns()));
    }

    public function test_on_a_table_that_has_neither_column_it_creates_both(): void
    {
        $this->dropIdentityColumns();
        $this->assertFalse(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_at'));
        $this->assertFalse(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_by'));

        $this->migration->up();

        $this->assertTrue($this->both());
    }

    public function test_on_a_table_that_has_only_one_of_them_it_adds_the_other(): void
    {
        // A previous run that stopped half-way, or a column added by hand.
        Schema::table('exam_evaluation_sheet', fn (Blueprint $table) => $table->dropColumn('identity_confirmed_by'));
        $this->assertTrue(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_at'));

        $this->migration->up();

        $this->assertTrue($this->both());

        Schema::table('exam_evaluation_sheet', fn (Blueprint $table) => $table->dropColumn('identity_confirmed_at'));
        $this->migration->up();

        $this->assertTrue($this->both());
    }

    public function test_it_still_runs_when_the_column_it_is_placed_after_is_missing(): void
    {
        // MySQL refuses AFTER a column that is not there; the position is cosmetic, so it is skipped.
        Schema::table('exam_evaluation_sheet', fn (Blueprint $table) => $table->dropColumn(self::COLUMNS));
        Schema::table('exam_evaluation_sheet', fn (Blueprint $table) => $table->dropColumn('identity_source'));
        SheetIdentity::forgetSchema();

        $this->migration->up();

        $this->assertTrue($this->both());
        $this->assertFalse(Schema::hasColumn('exam_evaluation_sheet', 'identity_source'));
    }

    // -- existing data -----------------------------------------------------------------

    public function test_existing_rows_are_left_untouched_and_the_new_columns_start_empty(): void
    {
        $this->dropIdentityColumns();

        $this->sheet(['student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confidence' => 95, 'status' => 'Evaluated']);
        $this->sheet(['student_id' => 102, 'identity_source' => 'manual', 'identity_confidence' => 100, 'status' => 'Approved', 'approved_by' => 55]);
        $this->sheet(['student_id' => null, 'identity_source' => 'unmatched', 'status' => 'Needs review']);
        $before = DB::table('exam_evaluation_sheet')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->migration->up();

        $after = DB::table('exam_evaluation_sheet')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        foreach ($after as $i => $row) {
            $this->assertNull($row['identity_confirmed_at']);
            $this->assertNull($row['identity_confirmed_by']);
            unset($row['identity_confirmed_at'], $row['identity_confirmed_by']);
            $this->assertEquals($before[$i], $row, 'every pre-existing column of every row is unchanged');
        }

        $this->assertSame(3, DB::table('exam_evaluation_sheet')->count());
    }

    public function test_a_student_chosen_by_hand_before_the_migration_still_counts_as_confirmed_and_an_ai_match_does_not(): void
    {
        $this->dropIdentityColumns();
        $ai = $this->sheet(['student_id' => 101, 'identity_source' => 'roll_no']);
        $hand = $this->sheet(['student_id' => 102, 'identity_source' => 'manual']);

        $this->migration->up();

        $this->assertFalse(SheetIdentity::isConfirmed(DB::table('exam_evaluation_sheet')->find($ai)), 'an old suggestion is not made a confirmation by a migration');
        $this->assertTrue(SheetIdentity::isConfirmed(DB::table('exam_evaluation_sheet')->find($hand)), 'an old hand-picked student already meant a teacher decided');
    }

    // -- reversing it ------------------------------------------------------------------

    public function test_down_removes_exactly_the_two_columns_and_keeps_every_row(): void
    {
        $this->sheet(['student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confirmed_at' => now(), 'identity_confirmed_by' => 55]);
        $before = array_keys($this->columns());

        $this->migration->down();

        $after = array_keys($this->columns());
        $this->assertEqualsCanonicalizing(self::COLUMNS, array_values(array_diff($before, $after)));
        $this->assertSame([], array_values(array_diff($after, $before)));
        $this->assertSame(1, DB::table('exam_evaluation_sheet')->count());
        $this->assertSame('enrollment_no', DB::table('exam_evaluation_sheet')->value('identity_source'));
    }

    public function test_down_is_harmless_twice_and_up_brings_everything_back(): void
    {
        $this->migration->down();
        $this->migration->down();

        $this->assertFalse(Schema::hasColumn('exam_evaluation_sheet', 'identity_confirmed_at'));

        $this->migration->up();

        $this->assertTrue($this->both());
    }

    public function test_after_a_rollback_the_application_falls_back_to_manual_and_loses_no_decision(): void
    {
        $this->sheet(['student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confirmed_at' => now(), 'identity_confirmed_by' => 55]);

        $this->migration->down();
        SheetIdentity::forgetSchema();

        // The confirmation columns are gone; what is left is the safe fallback, and it still protects a hand-picked student.
        $this->assertFalse(SheetIdentity::columnsPresent());
        $this->assertSame(['identity_source' => 'manual'], SheetIdentity::confirmationFields(55));
        $this->assertSame([], SheetIdentity::clearedConfirmation());
    }

    public function test_both_directions_do_nothing_when_the_table_is_not_there(): void
    {
        Schema::rename('exam_evaluation_sheet', 'exam_evaluation_sheet_moved');

        try {
            $this->migration->up();
            $this->migration->down();
            $this->assertFalse(Schema::hasTable('exam_evaluation_sheet'), 'it must not create the table');
        } finally {
            Schema::rename('exam_evaluation_sheet_moved', 'exam_evaluation_sheet');
        }

        $this->assertTrue($this->both());
    }

    // -- the two migrations, together ---------------------------------------------------

    public function test_they_run_in_the_order_they_must_and_after_the_tables_exist(): void
    {
        $names = collect(glob(base_path('database/migrations/*.php')))->map(fn ($path) => basename($path, '.php'))->sort()->values();
        $create = '2026_09_21_000000_create_exam_evaluation_tables';

        $this->assertLessThan($names->search(self::FIRST), $names->search($create));
        $this->assertLessThan($names->search(self::FILE), $names->search(self::FIRST));
        $this->assertSame(1, $names->filter(fn ($name) => $name === self::FILE)->count());
    }

    public function test_the_second_does_not_depend_on_anything_the_first_adds(): void
    {
        $source = file_get_contents(base_path('database/migrations/' . self::FILE . '.php'));

        $this->assertStringNotContainsString('teacher_feedback', $source);
        $this->assertStringNotContainsString('ees_batch_student_unique', $source);

        // And in practice: with the first one rolled back, the second still goes up and down.
        $first = require base_path('database/migrations/' . self::FIRST . '.php');
        $first->down();
        $this->assertFalse(Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback'));

        $this->migration->down();
        $this->migration->up();

        $this->assertTrue($this->both());
    }

    public function test_rolling_the_first_back_does_not_touch_the_columns_of_the_second(): void
    {
        $first = require base_path('database/migrations/' . self::FIRST . '.php');

        $first->down();

        $this->assertTrue($this->both(), 'the first migration only removes what it added');
        $this->assertFalse(Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback'));

        $first->up();

        $this->assertTrue($this->both());
        $this->assertTrue(Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback'));
    }

    public function test_the_second_does_not_rely_on_the_unique_index_so_duplicates_cannot_block_it(): void
    {
        // The first migration skips its index when duplicates exist. The second must not care.
        $first = require base_path('database/migrations/' . self::FIRST . '.php');
        $first->down();
        $this->sheet(['batch_id' => 5, 'student_id' => 101]);
        $this->sheet(['batch_id' => 5, 'student_id' => 101]);   // the duplicate the index would have forbidden

        $this->migration->down();
        $this->migration->up();

        $this->assertTrue($this->both());
        $this->assertSame(2, DB::table('exam_evaluation_sheet')->count());
    }

    // -- a process that started before the migration ran -----------------------------------

    public function test_a_process_that_started_before_the_migration_notices_it_within_a_minute_without_a_restart(): void
    {
        $this->dropIdentityColumns();
        $this->assertFalse(SheetIdentity::columnsPresent(), 'not there yet');

        $this->migration->up();

        $this->assertFalse(SheetIdentity::columnsPresent(), 'a recent "absent" is believed for a moment, so this is not a query per call');

        $this->travel(61)->seconds();

        $this->assertTrue(SheetIdentity::columnsPresent(), 'and then it asks again, with no restart');
    }

    public function test_once_the_columns_are_known_to_be_there_nothing_asks_again(): void
    {
        $this->assertTrue(SheetIdentity::columnsPresent());

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        for ($i = 0; $i < 20; $i++) {
            SheetIdentity::columnsPresent();
        }

        $this->assertSame(0, $queries);
    }
}
