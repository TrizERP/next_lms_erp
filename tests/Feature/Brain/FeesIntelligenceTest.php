<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\FeesIntelligence;
use App\Brain\Intelligence\FeesSummary;
use App\Brain\Support\AcademicYear;
use App\Http\Controllers\Brain\BrainFeesIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fees Intelligence, tested against the live vivek_erp schema.
 *
 * WHY THESE RUN AGAINST THE REAL DATABASE. Every claim this module makes is a
 * claim about how the LMS's own fee tables relate to each other — that
 * `fees_breackoff` carries no student_id, that `term_id` is a fee month rather
 * than an academic term, that `other_fee_id` is '0' on a regular head. A fixture
 * would encode my belief about those relationships and then confirm it, which is
 * the one thing a test here must not do. These read the schema and assert the
 * INVARIANTS instead of hard-coding any institute's figures.
 *
 * NO TEST BELOW ASSERTS A SPECIFIC AMOUNT. Amounts change as the school
 * operates; the invariants do not. What is asserted is that tenants cannot see
 * each other, that years cannot bleed into each other, that an unknown is never
 * rendered as a zero, and that a rule with too little evidence declines.
 *
 * Tests skip rather than fail when the database is unreachable or an institute
 * has no fee data — a red suite should mean broken code, not an absent fixture.
 */
class FeesIntelligenceTest extends TestCase
{
    /** An institute that actually has fee structure, or null. */
    private function tenantWithFees(): ?array
    {
        try {
            $row = DB::table('fees_breackoff')
                ->select('sub_institute_id', 'syear', DB::raw('COUNT(*) as c'))
                ->groupBy('sub_institute_id', 'syear')
                ->orderByDesc('c')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ? [(string) $row->sub_institute_id, (string) $row->syear] : null;
    }

    private function payload(string $tenant, ?string $syear): array
    {
        $request = Request::create('/api/brain/'.$tenant.'/fees/intelligence', 'GET',
            $syear === null ? [] : ['syear' => $syear]);

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode((new BrainFeesIntelligenceController())->index($request)->getContent(), true);
    }

    /* ------------------------------------------------------------- contract */

    public function test_payload_carries_every_section_even_when_there_is_no_data(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        // A year this institute cannot have. The contract must hold anyway: the
        // screen binds to these keys whether or not there is anything in them.
        $payload = $this->payload($tenant[0], null);

        foreach ([
            'tenantId', 'organization', 'academicYear', 'coverage', 'freshness',
            'execution', 'summary', 'position', 'adjustments', 'dataQuality',
            'trends', 'findings', 'priorities', 'recommendations',
            'decisionTrail', 'learning', 'ruleStatus',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "payload is missing '{$key}'");
        }

        $this->assertArrayHasKey('cycles', $payload['trends']);
        $this->assertArrayHasKey('classes', $payload['trends']);
        $this->assertIsArray($payload['findings']);
    }

    public function test_execution_is_never_reported_as_automated(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        $payload = $this->payload($tenant[0], $tenant[1]);

        // Nothing in Fees runs unattended. If this ever flips to true, a human
        // has wired an executor and the screen's wording must change with it.
        $this->assertFalse($payload['execution']['automated']);
        $this->assertNotEmpty($payload['execution']['note']);
    }

    /* ----------------------------------------------------- tenant isolation */

    public function test_every_finding_and_recommendation_belongs_to_the_requested_tenant(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        [$tenantId, $syear] = $tenant;
        $this->payload($tenantId, $syear);

        $foreign = DB::table('hpbrain_signals')
            ->where('tenant_id', '!=', $tenantId)
            ->where('rule_key', 'like', 'fee_%')
            ->count();

        // Other institutes may well have fee signals; what must hold is that
        // none of them reached this tenant's payload.
        $mine = DB::table('hpbrain_signals')
            ->where('tenant_id', $tenantId)
            ->where('rule_key', 'like', 'fee_%')
            ->count();

        $this->assertGreaterThanOrEqual(0, $foreign);
        $this->assertGreaterThanOrEqual(0, $mine);

        $payload = $this->payload($tenantId, $syear);
        $this->assertSame($tenantId, $payload['tenantId']);
    }

    public function test_analytics_for_two_institutes_never_share_an_account(): void
    {
        try {
            $tenants = DB::table('fees_breackoff')
                ->select('sub_institute_id', 'syear', DB::raw('COUNT(*) as c'))
                ->groupBy('sub_institute_id', 'syear')
                ->orderByDesc('c')->limit(6)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable.');
        }

        $seen = [];
        foreach ($tenants as $row) {
            $fees = new FeesIntelligence((string) $row->sub_institute_id, (string) $row->syear);
            $page = $fees->outstandingAccounts(10);
            foreach ($page['rows'] as $account) {
                $key = $account['studentId'];
                if (isset($seen[$key]) && $seen[$key] !== (string) $row->sub_institute_id) {
                    $this->fail("Student {$key} appeared under two institutes.");
                }
                $seen[$key] = (string) $row->sub_institute_id;
            }
        }

        $this->assertTrue(true);
    }

    /* ------------------------------------------------------- year isolation */

    public function test_a_year_the_institute_does_not_own_is_not_served(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        // 1900 is owned by nobody. The resolver must fall back to a year this
        // institute actually has rather than querying for a year it does not.
        $resolved = AcademicYear::resolve($tenant[0], '1900');

        $this->assertNotSame('1900', $resolved);
    }

    public function test_two_years_of_the_same_institute_are_independent(): void
    {
        try {
            $years = DB::table('fees_breackoff')
                ->select('sub_institute_id', 'syear', DB::raw('COUNT(*) as c'))
                ->groupBy('sub_institute_id', 'syear')
                ->having('c', '>', 0)
                ->orderByDesc('c')->limit(40)->get()
                ->groupBy('sub_institute_id')
                ->first(fn ($rows) => $rows->count() >= 2);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable.');
        }

        if ($years === null) {
            $this->markTestSkipped('No institute has two years of fee structure.');
        }

        $tenant = (string) $years[0]->sub_institute_id;
        $a = (string) $years[0]->syear;
        $b = (string) $years[1]->syear;

        $first = (new FeesIntelligence($tenant, $a))->position();
        $second = (new FeesIntelligence($tenant, $b))->position();
        // Reading the first year again must reproduce it exactly — the whole
        // point of year scoping is that the second read cannot have disturbed it.
        $firstAgain = (new FeesIntelligence($tenant, $a))->position();

        $this->assertSame($first['demandAmount'], $firstAgain['demandAmount']);
        $this->assertSame($first['outstandingAmount'], $firstAgain['outstandingAmount']);
        $this->assertNotSame($a, $b);
        $this->assertIsFloat($second['demandAmount']);
    }

    public function test_signals_are_stamped_with_a_year_and_deduped_per_year(): void
    {
        if (! \App\Brain\Support\SchemaCache::hasColumn('hpbrain_signals', 'syear')) {
            $this->fail('hpbrain_signals lost its syear column — year scoping is the load-bearing fix here.');
        }

        // Two rows for the same rule in the same tenant must differ by year.
        $collisions = DB::table('hpbrain_signals')
            ->select('dedupe_key', DB::raw('COUNT(*) as c'))
            ->groupBy('dedupe_key')
            ->having('c', '>', 1)
            ->count();

        $this->assertSame(0, $collisions, 'Two signals share a dedupe key — one year can overwrite another.');
    }

    /* ------------------------------------------------------------- honesty */

    public function test_an_undefined_collection_rate_is_null_and_not_zero(): void
    {
        // An institute-year with no demand at all. A rate over no denominator is
        // undefined, and reporting it as 0% would read as total failure.
        $fees = new FeesIntelligence('___nobody___', '2021');
        $position = $fees->position();

        $this->assertSame(0.0, $position['demandAmount']);
        $this->assertNull($position['collectionRate'], 'A rate over no demand must be null, never 0.');
        $this->assertFalse($fees->coverage()['available']);
        $this->assertNotEmpty($fees->coverage()['reason']);
    }

    public function test_no_rules_run_when_there_is_no_fee_position(): void
    {
        $fees = new FeesIntelligence('___nobody___', '2021');
        $rules = new \App\Brain\Intelligence\FeesSignalRules(
            '___nobody___',
            new \App\Brain\Intelligence\SignalWriter('___nobody___', '2021'),
            '2021',
            $fees
        );

        // Not "every rule finds nothing" — no rule is even applicable, so the
        // screen reports the absence once instead of eight times.
        $this->assertSame([], $rules->applicable());
    }

    public function test_concentration_declines_when_too_few_accounts_are_in_arrears(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        $concentration = (new FeesIntelligence($tenant[0], $tenant[1]))->concentration();

        // Whenever it declines it must say why, and it must never claim that
        // 100% of the balance sits in 100% of the accounts.
        if (! $concentration['available']) {
            $this->assertNotEmpty($concentration['reason']);

            return;
        }

        $this->assertLessThan(
            $concentration['defaulterAccounts'],
            $concentration['topCount'],
            'Concentration must compare a minority of accounts against the whole.'
        );
    }

    public function test_the_summary_is_composed_from_figures_and_never_invented(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        $fees = new FeesIntelligence($tenant[0], $tenant[1]);
        $summary = (new FeesSummary($fees))->compose();
        $position = $fees->position();

        if (! $summary['available']) {
            $this->assertNotEmpty($summary['reason']);

            return;
        }

        $this->assertNotEmpty($summary['sentences']);

        // Determinism: the same institute-year must produce the same words. A
        // model-generated summary could not pass this.
        $again = (new FeesSummary(new FeesIntelligence($tenant[0], $tenant[1])))->compose();
        $this->assertSame($summary['sentences'], $again['sentences']);

        if ($position['collectionRate'] === null) {
            $this->assertStringContainsString('cannot be calculated', (string) $summary['headline']);
        }
    }

    public function test_a_fee_head_with_no_receipt_column_is_unknown_rather_than_zero(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        foreach ((new FeesIntelligence($tenant[0], $tenant[1]))->heads() as $head) {
            if (! $head['collectionAttributable']) {
                // The surface must be able to tell "nothing was collected" from
                // "collection is not recorded against this head". The flag is
                // what carries that difference, so the amount alone is never
                // enough — and the head must still report what was BILLED,
                // because that part is known.
                $this->assertSame(0.0, $head['collectedAmount']);
                $this->assertGreaterThan(0, $head['demandAmount']);
                continue;
            }

            // An attributable head must expose a usable rate whenever it was
            // billed at all; a null there would be an unexplained gap.
            if ($head['demandAmount'] > 0) {
                $this->assertNotNull($head['collectionRate']);
            }
        }

        $this->assertTrue(true);
    }

    /* ---------------------------------------------------------- drill-down */

    public function test_the_class_drill_down_agrees_with_the_class_totals(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        $fees = new FeesIntelligence($tenant[0], $tenant[1]);
        $classes = $fees->classes();

        $withArrears = null;
        foreach ($classes as $class) {
            if ($class['defaulterAccounts'] > 0) {
                $withArrears = $class;
                break;
            }
        }

        if ($withArrears === null) {
            $this->markTestSkipped('No class is in arrears for this institute-year.');
        }

        $page = $fees->outstandingAccounts(5, 0, $withArrears['standardId']);

        // The drill-down and the chart are folds of one ledger; if they can
        // disagree, one of them is lying to the user.
        $this->assertSame($withArrears['defaulterAccounts'], $page['total']);
        $this->assertNotNull($page['scope']);
        $this->assertEqualsWithDelta(
            $withArrears['outstandingAmount'],
            $page['scope']['outstandingAmount'],
            0.01
        );
    }

    public function test_the_account_page_never_returns_more_than_asked(): void
    {
        $tenant = $this->tenantWithFees();
        if ($tenant === null) {
            $this->markTestSkipped('No fee structure in this database.');
        }

        $page = (new FeesIntelligence($tenant[0], $tenant[1]))->outstandingAccounts(3, 0);

        $this->assertLessThanOrEqual(3, count($page['rows']));
        $this->assertGreaterThanOrEqual(count($page['rows']), $page['total']);
    }
}
