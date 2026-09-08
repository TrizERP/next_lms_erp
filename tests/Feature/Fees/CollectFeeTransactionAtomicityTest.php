<?php

namespace Tests\Feature\Fees;

use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Track A / Day 11 — "Wrap Fee Demand → Collection → Receipt in one DB
 * transaction".
 *
 * "Fee Demand" has no write of its own to wrap (see GenerateFeeDemandTest's
 * docblock: it's computed live from `fees_breackoff` + enrollment, never
 * persisted). The only writes in this flow are Collection (`fees_collect` /
 * `fees_paid_other`) and Receipt (`fees_receipt`), and
 * `fees_collect_controller::pay_fees()` already wraps all three inserts in a
 * single `DB::transaction()` closure (see the "insert into fees_collect,
 * fees_paid_other and fees_receipt inside a single DB transaction" comment
 * directly above it in the source, and the RECEIPT_GENERATED audit call
 * that fires from inside that same closure).
 *
 * This test doesn't re-assert the happy path (CollectFeeTest.php already
 * does) — it proves the DoD's actual requirement: a forced failure at any
 * point in the write sequence, including the very last statement, leaves
 * zero partial rows behind. The failure is injected via a `DB::listen()`
 * callback that throws the instant a specific SQL statement executes -
 * this fires from inside the same call stack as the transaction's closure
 * (query execution is synchronous), so it propagates exactly like a real
 * mid-write crash would, without touching any production code.
 */
class CollectFeeTransactionAtomicityTest extends TestCase
{
    use DatabaseTransactions;

    private int $institute;

    private int $syear;

    private int $sectionId;

    private int $standardId;

    private int $quotaId;

    private int $feeTypeId;

    private int $monthId;

    private int $structureAmount = 5000;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syear = (int) date('Y');
        $this->institute = $this->makeInstitute();
        $this->sectionId = $this->makeAcademicSection();
        $this->standardId = $this->makeStandard();
        $this->quotaId = $this->makeQuota();
        $this->userId = $this->makeUser();
        $this->makeAcademicYear();
        $this->makeFeesMapYear();
        $this->feeTypeId = $this->makeFeeHead();
        $this->monthId = 4 * 10000 + $this->syear;
        $this->makeFeeStructure();
        $this->makeReceiptBook();
        $this->makeReceiptTemplate();

        Session::put('sub_institute_id', $this->institute);
        Session::put('syear', $this->syear);
        Session::put('user_id', $this->userId);
    }

    public function test_a_crash_on_the_final_receipt_write_leaves_no_payment_row_behind(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $this->assertRolledBackForCrashAt('insert into `fees_receipt`', $studentId);

        // Nothing from the failed attempt is visible: not the receipt this
        // simulated crash happened on, and not the fees_collect row that
        // was written moments before it in the same transaction.
        $this->assertSame(0, DB::table('fees_collect')->where('student_id', $studentId)->count());
        $this->assertSame(0, DB::table('fees_receipt')->where('SUB_INSTITUTE_ID', $this->institute)->count());
    }

    public function test_a_crash_on_the_first_collection_write_leaves_no_receipt_row_behind(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $this->assertRolledBackForCrashAt('insert into `fees_collect`', $studentId);

        $this->assertSame(0, DB::table('fees_collect')->where('student_id', $studentId)->count());
        $this->assertSame(0, DB::table('fees_receipt')->where('SUB_INSTITUTE_ID', $this->institute)->count());
    }

    public function test_after_a_forced_crash_the_ledger_is_unaffected_and_a_retry_succeeds_cleanly(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $this->assertRolledBackForCrashAt('insert into `fees_receipt`', $studentId);

        // The failed attempt must not have moved the ledger at all.
        $this->assertSame($this->structureAmount, $this->pendingLedgerBalance($studentId));

        // A clean retry (no listener this time) must succeed as if the
        // crashed attempt never happened - no stray row, no lingering
        // receipt-number reservation, blocks it. assertRolledBackForCrashAt()
        // already cleared the throwing listener in its own finally block.
        $response = $this->collect($studentId, 5000);
        $data = $response->getData(true);
        $this->assertArrayNotHasKey('status_code', $data, 'retry after a forced crash must succeed: ' . json_encode($data));

        $this->assertSame(1, DB::table('fees_collect')->where('student_id', $studentId)->count());
        $this->assertSame(0, $this->pendingLedgerBalance($studentId));
    }

    /**
     * Registers a DB::listen() callback that throws the instant `$sqlFragment`
     * appears in an executed query, calls the real collection flow, and
     * asserts it actually threw (i.e. the injected failure really fired,
     * so a passing test means the fix was verified - not that the fragment
     * silently never matched).
     */
    private function assertRolledBackForCrashAt(string $sqlFragment, int $studentId): void
    {
        $threw = false;

        DB::listen(function ($event) use ($sqlFragment, &$threw) {
            if (Str::contains(strtolower($event->sql), $sqlFragment)) {
                $threw = true;
                throw new \RuntimeException("Simulated mid-flow crash at: {$sqlFragment}");
            }
        });

        try {
            $this->collect($studentId, 5000);
            $this->fail('expected the simulated crash to throw, but collect() returned normally');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated mid-flow crash', $e->getMessage());
        } finally {
            Event::forget(QueryExecuted::class);
        }

        $this->assertTrue($threw, 'the injected failure never fired - the SQL fragment did not match any executed query, so this test proved nothing');
    }

    private function collect(int $studentId, int $amount)
    {
        $payload = [
            'type' => 'API',
            'syear' => $this->syear,
            'student_id' => $studentId,
            'standard_id' => $this->standardId,
            'grade_id' => $this->sectionId,
            'months' => [$this->monthId],
            'fees_data' => ['tution_fee' => $amount],
            'PAYMENT_MODE' => 'Cash',
            'receiptdate' => now()->toDateString(),
            'bank_branch' => '',
            'bank_name' => '',
            'cheque_no' => '',
            'cheque_date' => '',
            'remarks' => 'Track A Day 11 atomicity test payment',
            'full_name' => 'Collect Test',
            'std_div' => 'Standard-1 (Collect Test)',
            'mobile' => '9999999999',
        ];

        $_REQUEST = $payload;
        $request = Request::create('/fees/fees_collect', 'POST', $payload);

        return app(fees_collect_controller::class)->store($request);
    }

    /** Same computation the fees module uses everywhere: structure minus collected. */
    private function pendingLedgerBalance(int $studentId): int
    {
        $demand = \App\Helpers\FeeBreackoff([$studentId], $this->standardId, $this->syear, $this->institute);
        $totalDemand = array_sum(array_map(fn ($row) => (int) $row->bkoff, $demand));

        $collected = (int) DB::table('fees_collect')->where('student_id', $studentId)->sum('amount');

        return $totalDemand - $collected;
    }

    private function makeInstitute(): int
    {
        return (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'SYNTHETIC Transaction Atomicity Test School',
            'ShortCode' => 'TAT' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);
    }

    private function makeAcademicSection(): int
    {
        return (int) DB::table('academic_section')->insertGetId([
            'sub_institute_id' => $this->institute,
            'title' => 'Primary (Atomicity Test)',
            'short_name' => 'PRI',
            'sort_order' => 1,
            'shift' => 'Morning',
            'medium' => 'English',
            'payment_link' => '',
            'created_at' => now(),
        ]);
    }

    private function makeStandard(): int
    {
        return (int) DB::table('standard')->insertGetId([
            'grade_id' => $this->sectionId,
            'name' => 'Standard-1 (Atomicity Test)',
            'short_name' => 'D1',
            'sort_order' => 1,
            'medium' => 'English',
            'sub_institute_id' => $this->institute,
            'created_at' => now(),
        ]);
    }

    private function makeQuota(): int
    {
        return (int) DB::table('student_quota')->insertGetId([
            'title' => 'General',
            'sort_order' => 1,
            'sub_institute_id' => $this->institute,
            'created_by' => 0,
            'created_on' => now(),
        ]);
    }

    private function makeUser(): int
    {
        return (int) DB::table('tbluser')->insertGetId([
            'user_name' => 'tat_admin' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Atomicity',
            'last_name' => 'Tester',
            'email' => 'tat' . random_int(10000, 99999) . '@example.com',
            'mobile' => '9999999999',
            'user_profile_id' => 1,
            'join_year' => (string) $this->syear,
            'sub_institute_id' => $this->institute,
            'is_admin' => 1,
            'status' => 1,
        ]);
    }

    private function makeAcademicYear(): void
    {
        DB::table('academic_year')->insert([
            'term_id' => 1,
            'syear' => $this->syear,
            'sub_institute_id' => $this->institute,
            'title' => 'TERM-1',
            'short_name' => 'T-1',
            'sort_order' => 1,
            'start_date' => "{$this->syear}-04-01",
            'end_date' => ($this->syear + 1) . '-03-31',
            'post_start_date' => "{$this->syear}-04-01",
            'post_end_date' => ($this->syear + 1) . '-03-31',
            'does_grades' => 'Y',
            'does_exams' => 'Y',
            'created_at' => now(),
        ]);
    }

    private function makeFeesMapYear(): void
    {
        DB::table('fees_map_years')->insert([
            'from_month' => 4,
            'to_month' => 3,
            'syear' => $this->syear,
            'sub_institute_id' => $this->institute,
            'type' => null,
            'created_at' => now(),
        ]);
    }

    private function makeFeeHead(): int
    {
        $lookup = DB::table('fees_title_master')->where('id', 2)->first();

        return (int) DB::table('fees_title')->insertGetId([
            'fees_title_id' => $lookup->id,
            'fees_title' => $lookup->fee_paid_title,
            'display_name' => $lookup->title,
            'mandatory' => 1,
            'sort_order' => 1,
            'syear' => $this->syear,
            'sub_institute_id' => $this->institute,
            'other_fee_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFeeStructure(): void
    {
        DB::table('fees_breackoff')->insert([
            'syear' => $this->syear,
            'admission_year' => $this->syear,
            'fee_type_id' => $this->feeTypeId,
            'quota' => $this->quotaId,
            'grade_id' => $this->sectionId,
            'standard_id' => $this->standardId,
            'section_id' => 0,
            'month_id' => $this->monthId,
            'amount' => $this->structureAmount,
            'sub_institute_id' => $this->institute,
            'created_at' => now(),
        ]);
    }

    private function makeReceiptBook(): void
    {
        DB::table('fees_receipt_book_master')->insert([
            'syear' => $this->syear,
            'receipt_line_1' => 'Test Receipt',
            'receipt_prefix' => null,
            'sort_order' => 1,
            'last_receipt_number' => 0,
            'grade_id' => $this->sectionId,
            'standard_id' => $this->standardId,
            'fees_head_id' => $this->feeTypeId,
            'status' => 1,
            'sub_institute_id' => $this->institute,
            'created_on' => now(),
        ]);
    }

    private function makeReceiptTemplate(): void
    {
        DB::table('fees_receipt_css')->insert([
            'receipt_id' => 'A5',
            'css' => '',
            'created_at' => now(),
        ]);

        DB::table('fees_config_master')->insert([
            'fees_receipt_template' => 'A5',
            'syear' => $this->syear,
            'sub_institute_id' => $this->institute,
            'created_on' => now(),
        ]);
    }

    private function makeStudent(): int
    {
        return (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Atomicity',
            'last_name' => 'Test',
            'admission_year' => $this->syear,
            'sub_institute_id' => $this->institute,
            'status' => 1,
            'file_size' => '',
            'file_type' => '',
            'created_on' => now(),
        ]);
    }

    private function enrolStudent(int $studentId): void
    {
        DB::table('tblstudent_enrollment')->insert([
            'syear' => $this->syear,
            'student_id' => $studentId,
            'grade_id' => $this->sectionId,
            'standard_id' => $this->standardId,
            'section_id' => 0,
            'student_quota' => (string) $this->quotaId,
            'start_date' => "{$this->syear}-04-01",
            'term_id' => 1,
            'sub_institute_id' => $this->institute,
            'created_on' => now(),
        ]);
    }
}
