<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Lifecycle\Plan\ModuleReadPlanner;
use App\Domain\AI\Lifecycle\StageContext;
use App\Services\Mcp\FeesCollectionReportService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "Fees collected yesterday" is that day's receipts and nothing else.
 *
 * READ-ONLY: every figure is compared with an independent query of the same tables, which — like the
 * app's own Daily Summary — does not filter by academic year.
 */
class FeesCollectionByDateTest extends TestCase
{
    /** @return array{0:McpRequestContext,1:string}|null An institute and a day that has receipts. */
    private function aDayWithReceipts(): ?array
    {
        if (! Schema::hasTable('fees_collect')) {
            return null;
        }

        $row = DB::table('fees_collect')
            ->where('is_deleted', 'N')
            ->orderByDesc('receiptdate')
            ->first(['sub_institute_id', 'receiptdate']);

        if ($row === null) {
            return null;
        }

        // The academic year is deliberately one the receipts may not carry: it must make no difference.
        return [
            new McpRequestContext(1, 'admin', (int) $row->sub_institute_id, [(int) $row->sub_institute_id], null, null, 1999, null, true, false),
            (string) $row->receiptdate,
        ];
    }

    public function test_one_days_figures_equal_an_independent_count_of_that_day_whatever_the_academic_year(): void
    {
        $found = $this->aDayWithReceipts();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        [$context, $day] = $found;

        $result = app(FeesCollectionReportService::class)->report($context, ['date' => $day]);

        $regular = DB::table('fees_collect')
            ->where('sub_institute_id', $context->selectedInstituteId)->where('is_deleted', 'N')->where('receiptdate', $day)
            ->selectRaw('COUNT(*) n, COALESCE(SUM(amount),0) a')->first();
        $other = Schema::hasTable('fees_paid_other')
            ? DB::table('fees_paid_other')
                ->where('sub_institute_id', $context->selectedInstituteId)->where('is_deleted', 'N')->where('receiptdate', $day)
                ->selectRaw('COUNT(*) n, COALESCE(SUM(actual_amountpaid),0) a')->first()
            : (object) ['n' => 0, 'a' => 0];

        $this->assertGreaterThan(0, (int) $regular->n + (int) $other->n);
        $this->assertSame((int) $regular->n, $result['summary']['total_receipts']);
        $this->assertEqualsWithDelta((float) $regular->a, $result['summary']['total_amount'], 0.01);
        $this->assertSame((int) $other->n, $result['summary']['other_fee_receipts']);
        $this->assertEqualsWithDelta((float) $regular->a + (float) $other->a, $result['summary']['total_collected'], 0.01);
        $this->assertSame($day, $result['period']['from']);
        $this->assertSame($day, $result['period']['to']);

        foreach ($result['receipts'] as $receipt) {
            $this->assertSame($day, $receipt['receipt_date'], 'a receipt from another date was returned');
        }
    }

    public function test_a_single_day_is_never_the_all_time_total(): void
    {
        $found = $this->aDayWithReceipts();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        [$context, $day] = $found;
        $service = app(FeesCollectionReportService::class);

        $allTimeCount = DB::table('fees_collect')
            ->where('sub_institute_id', $context->selectedInstituteId)->where('is_deleted', 'N')->count();
        $oneDay = $service->report($context, ['date' => $day]);
        $dayBefore = $service->report($context, ['date' => Carbon::parse($day)->subDay()->toDateString()]);

        $this->assertLessThanOrEqual($allTimeCount, $oneDay['summary']['total_receipts']);

        // A day's receipts do not appear in the day before it.
        $ids = array_column($oneDay['receipts'], 'id');
        $this->assertSame([], array_values(array_intersect($ids, array_column($dayBefore['receipts'], 'id'))));
    }

    public function test_relative_words_are_resolved_against_todays_date(): void
    {
        $found = $this->aDayWithReceipts();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        $service = app(FeesCollectionReportService::class);

        $yesterday = $service->report($found[0], ['period' => 'yesterday']);
        $week = $service->report($found[0], ['period' => 'last 7 days']);

        $this->assertSame(now()->subDay()->toDateString(), $yesterday['period']['from']);
        $this->assertSame(now()->subDay()->toDateString(), $yesterday['period']['to']);
        $this->assertSame(now()->subDays(6)->toDateString(), $week['period']['from']);
        $this->assertSame(now()->toDateString(), $week['period']['to']);

        foreach ($week['receipts'] as $receipt) {
            $this->assertGreaterThanOrEqual(now()->subDays(6)->toDateString(), $receipt['receipt_date']);
            $this->assertLessThanOrEqual(now()->toDateString(), $receipt['receipt_date']);
        }
    }

    public function test_a_day_without_collections_says_so_and_totals_nothing(): void
    {
        $found = $this->aDayWithReceipts();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        $result = app(FeesCollectionReportService::class)->report($found[0], ['date' => '1999-01-01']);

        $this->assertSame(0, $result['summary']['all_receipts']);
        $this->assertSame(0.0, $result['summary']['total_collected']);
        $this->assertSame([], $result['receipts']);
        $this->assertStringStartsWith('No fee collection records were found for 1 January 1999', $result['headline']);
    }

    public function test_a_date_that_cannot_be_read_is_refused_not_replaced_by_today_or_all_time(): void
    {
        $found = $this->aDayWithReceipts();

        if ($found === null) {
            $this->markTestSkipped('No fee receipts in this estate.');
        }

        foreach (['31 February 2026', 'gibberish'] as $period) {
            $result = app(FeesCollectionReportService::class)->report($found[0], ['period' => $period]);

            $this->assertStringContainsString('could not read that date', $result['headline'], $period);
            $this->assertSame(0.0, $result['summary']['total_collected'], $period);
            $this->assertSame([], $result['receipts'], $period);
        }
    }

    public function test_the_page_level_fallback_asks_the_period_tool_when_the_question_names_a_date(): void
    {
        $fees = app(ModuleRegistry::class)->find('fees');
        $planner = new ModuleReadPlanner();
        $scope = new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);

        $this->assertNotNull($fees);

        $dated = new StageContext('Show me the fees collected yesterday', $scope, $fees);
        $dated->set('modules_considered', ['fees' => 5.0]);
        $plan = $planner->plan($dated);

        $this->assertSame(['fees.collection_report'], $plan->candidateTools);
        $this->assertSame('Show me the fees collected yesterday', $plan->steps[0]->arguments['period']);

        // Even a date that is not real: the tool will say it cannot read it, rather than the module
        // being read for all-time figures.
        $impossible = new StageContext('fees collected on 31 February', $scope, $fees);
        $impossible->set('modules_considered', ['fees' => 5.0]);
        $this->assertSame(['fees.collection_report'], $planner->plan($impossible)->candidateTools);

        // No date, no period tool: the old behaviour.
        $plain = new StageContext('Which students have pending fees?', $scope, $fees);
        $plain->set('modules_considered', ['fees' => 5.0]);
        $this->assertNotContains('fees.collection_report', $planner->plan($plain)->candidateTools);
    }
}
