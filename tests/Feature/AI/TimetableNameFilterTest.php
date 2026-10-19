<?php

namespace Tests\Feature\AI;

use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TimetableService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A class can be asked for by name, and the count is the table's own.
 *
 * READ-ONLY: compared with an independent count of the same rows.
 */
class TimetableNameFilterTest extends TestCase
{
    public function test_a_class_named_in_words_returns_only_that_classs_periods(): void
    {
        if (! Schema::hasTable('timetable')) {
            $this->markTestSkipped('No timetable in this estate.');
        }

        $row = DB::table('timetable as t')
            ->join('standard as s', 's.id', '=', 't.standard_id')
            ->orderBy('t.id')
            ->first(['t.sub_institute_id', 't.syear', 's.name']);

        if ($row === null) {
            $this->markTestSkipped('No timetable rows in this estate.');
        }

        $context = new McpRequestContext(1, 'admin', (int) $row->sub_institute_id, [(int) $row->sub_institute_id], null, null, (int) $row->syear, null, true, false);

        $result = app(TimetableService::class)->schedule($context, ['standard' => 'Class '.$row->name, 'limit' => 5]);

        $expected = DB::table('timetable as t')
            ->join('standard as s', 's.id', '=', 't.standard_id')
            ->where('t.sub_institute_id', $row->sub_institute_id)
            ->where('t.syear', $row->syear)
            ->whereRaw('LOWER(TRIM(s.name)) = ?', [strtolower(trim($row->name))])
            ->count();

        $this->assertGreaterThan(0, $result['count']);
        $this->assertLessThanOrEqual($expected, $result['count'], 'a name filter can only narrow what the table holds');
        $this->assertLessThanOrEqual(5, $result['row_count']);

        foreach ($result['periods'] as $period) {
            $this->assertSame(strtolower(trim($row->name)), strtolower(trim((string) $period['standard_name'])));
        }
    }

    public function test_a_class_with_no_timetable_is_reported_as_none_not_as_another_class(): void
    {
        if (! Schema::hasTable('timetable')) {
            $this->markTestSkipped('No timetable in this estate.');
        }

        $context = new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);

        $result = app(TimetableService::class)->schedule($context, ['standard' => 'Class no-such-class-zzz']);

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['periods']);
    }
}
