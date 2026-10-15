<?php

namespace Tests\Feature\AI;

use App\Services\Mcp\FeesCollectionReportService;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * What ONE student paid, on the dates asked — filtered by the student AND the date.
 *
 * READ-ONLY. Every figure is compared with an independent query of the same tables; students and dates
 * are taken from whatever receipts the estate holds, so nothing here names a person or an amount.
 */
class FeesStudentPaymentsByDateTest extends TestCase
{
    private function context(int $institute): McpRequestContext
    {
        // An academic year no receipt carries: it must make no difference.
        return new McpRequestContext(1, 'admin', $institute, [$institute], null, null, 1999, null, true, false);
    }

    /** @return array{0:McpRequestContext,1:int,2:string}|null A student, and a day they paid. */
    private function aStudentWhoPaid(): ?array
    {
        if (! Schema::hasTable('fees_collect')) {
            return null;
        }

        $row = DB::table('fees_collect')->where('is_deleted', 'N')->orderByDesc('receiptdate')->first(['sub_institute_id', 'student_id', 'receiptdate']);

        return $row === null ? null : [$this->context((int) $row->sub_institute_id), (int) $row->student_id, (string) $row->receiptdate];
    }

    public function test_one_students_day_equals_an_independent_count_for_that_student_and_that_day(): void
    {
        $found = $this->aStudentWhoPaid();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        [$context, $studentId, $day] = $found;

        $result = app(FeesCollectionReportService::class)->report($context, ['student_id' => $studentId, 'date' => $day]);

        $mine = DB::table('fees_collect')
            ->where('sub_institute_id', $context->selectedInstituteId)->where('is_deleted', 'N')
            ->where('student_id', $studentId)->where('receiptdate', $day);
        $everyone = DB::table('fees_collect')
            ->where('sub_institute_id', $context->selectedInstituteId)->where('is_deleted', 'N')->where('receiptdate', $day);

        $this->assertSame((int) (clone $mine)->count(), $result['summary']['fee_lines']);
        $this->assertSame((int) (clone $mine)->distinct()->count('receipt_no'), $result['summary']['total_receipts'], 'receipts are distinct receipt numbers; a receipt is one row per term');
        $this->assertEqualsWithDelta((float) (clone $mine)->sum('amount'), $result['summary']['total_amount'], 0.01);

        foreach ($result['receipts'] as $receipt) {
            $this->assertSame($studentId, $receipt['student_id'], "another student's receipt was returned");
            $this->assertSame($day, $receipt['receipt_date']);
        }

        // Where others paid that day too, their money is not in this student's figure.
        if ((int) (clone $everyone)->where('student_id', '!=', $studentId)->count() > 0) {
            $this->assertLessThan((float) (clone $everyone)->sum('amount'), $result['summary']['total_amount']);
        }

        $this->assertStringContainsString($result['student']['name'], $result['headline']);
        $this->assertStringNotContainsString('owe', strtolower($result['headline']));
    }

    public function test_a_student_with_no_payment_on_the_day_is_told_so_and_totals_nothing(): void
    {
        $found = $this->aStudentWhoPaid();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        [$context, $studentId] = $found;

        $result = app(FeesCollectionReportService::class)->report($context, ['student_id' => $studentId, 'date' => '1999-01-01']);

        $this->assertSame(0.0, $result['summary']['total_collected']);
        $this->assertSame([], $result['receipts']);
        $this->assertStringStartsWith('No payment was found for '.$result['student']['name'].' on 1 January 1999', $result['headline']);
    }

    public function test_the_student_filter_never_widens_to_the_institute_when_the_student_is_unknown(): void
    {
        $found = $this->aStudentWhoPaid();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        [$context, , $day] = $found;
        $service = app(FeesCollectionReportService::class);

        $unknown = $service->report($context, ['student' => 'Zqxjv Nobodyatall', 'date' => $day]);

        $this->assertSame(0.0, $unknown['summary']['total_collected'], 'an unknown student must not fall back to everyone');
        $this->assertSame([], $unknown['receipts']);
        $this->assertStringContainsString('could not find a student', $unknown['headline']);

        $missing = $service->report($context, ['student_id' => 2147483000, 'date' => $day]);

        $this->assertSame(0.0, $missing['summary']['total_collected']);
        $this->assertStringContainsString('could not find a student', $missing['headline']);
    }

    public function test_a_name_shared_by_several_students_lists_them_and_chooses_none(): void
    {
        if (! Schema::hasTable('tblstudent')) {
            $this->markTestSkipped('No students.');
        }

        $shared = DB::table('tblstudent')
            ->where('last_name', '!=', '')->whereNotNull('last_name')->where('last_name', '!=', '-')
            ->select('sub_institute_id', 'last_name', DB::raw('COUNT(*) n'))
            ->groupBy('sub_institute_id', 'last_name')->having('n', '>', 1)->orderByDesc('n')->first();

        if ($shared === null) {
            $this->markTestSkipped('No two students share a surname.');
        }

        $result = app(FeesCollectionReportService::class)->report(
            $this->context((int) $shared->sub_institute_id),
            ['student' => $shared->last_name, 'date' => '2026-01-01']
        );

        $this->assertStringContainsString('More than one student matches', $result['headline']);
        $this->assertGreaterThan(1, count($result['ambiguous_students']));
        $this->assertSame(0.0, $result['summary']['total_collected']);
        $this->assertSame([], $result['receipts']);
        $this->assertNull($result['student'] ?? null, 'none was chosen');
    }

    public function test_a_period_for_one_student_is_the_sum_of_that_students_days_only(): void
    {
        $found = $this->aStudentWhoPaid();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        [$context, $studentId] = $found;

        $result = app(FeesCollectionReportService::class)->report($context, [
            'student_id' => $studentId, 'from_date' => '2000-01-01', 'to_date' => '2100-01-01',
        ]);

        $regular = (float) DB::table('fees_collect')
            ->where('sub_institute_id', $context->selectedInstituteId)->where('is_deleted', 'N')->where('student_id', $studentId)->sum('amount');
        $other = Schema::hasTable('fees_paid_other')
            ? (float) DB::table('fees_paid_other')
                ->where('sub_institute_id', $context->selectedInstituteId)->where('is_deleted', 'N')->where('student_id', $studentId)->sum('actual_amountpaid')
            : 0.0;

        $this->assertEqualsWithDelta($regular + $other, $result['summary']['total_collected'], 0.01);
    }

    public function test_a_payment_mode_narrows_the_students_receipts(): void
    {
        $found = $this->aStudentWhoPaid();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        [$context, $studentId, $day] = $found;
        $mode = DB::table('fees_collect')->where('student_id', $studentId)->where('receiptdate', $day)->where('is_deleted', 'N')->value('payment_mode');

        $same = app(FeesCollectionReportService::class)->report($context, ['student_id' => $studentId, 'date' => $day, 'payment_mode' => $mode]);
        $other = app(FeesCollectionReportService::class)->report($context, ['student_id' => $studentId, 'date' => $day, 'payment_mode' => 'no-such-mode']);

        $this->assertGreaterThan(0, $same['summary']['total_receipts']);
        $this->assertSame(0, $other['summary']['all_receipts']);
        $this->assertStringContainsString('no-such-mode payment was found', $other['headline']);
    }

    public function test_a_name_with_a_middle_name_between_the_words_is_found(): void
    {
        $row = DB::table('tblstudent')
            ->whereNotNull('middle_name')->where('middle_name', '!=', '')->where('first_name', '!=', '')->where('last_name', '!=', '')
            ->where('last_name', '!=', '-')->orderBy('id')->first(['sub_institute_id', 'id', 'first_name', 'last_name']);

        if ($row === null) {
            $this->markTestSkipped('No student with a middle name.');
        }

        $ids = array_column(
            app(StudentSearchService::class)->search($this->context((int) $row->sub_institute_id), [
                'query' => $row->first_name.' '.$row->last_name, 'limit' => 50, 'active_only' => false,
            ])['students'] ?? [],
            'id'
        );

        $this->assertContains((int) $row->id, $ids, 'first and last name only must still find a student who has a middle name');
    }
}
