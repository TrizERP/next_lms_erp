<?php

namespace Tests\Feature\Fees;

use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use function App\Helpers\FeeBreackoff;

/**
 * Track A / Day 11 — "Collect Fee (manual/offline entry)".
 *
 * DoD: payment recorded against the demand; ledger balance updates
 * correctly; no gateway integration required.
 *
 * The manual/offline collection endpoint is
 * `fees_collect_controller::store()` (POST /fees/fees_collect,
 * payment_mode=Cash/Cheque/DD/...) which delegates to `pay_fees()` — the
 * same method every payment mode (including the online gateways, which
 * build the same request shape from their callback) ultimately funnels
 * through. This test drives `pay_fees()` directly with a real Request built
 * the same way the Next.js "Collect Fees" screen's payload is shaped,
 * against the exact minimum chain the earlier Track A issues established
 * (Academic Year, Fee Head, Fee Structure, an enrolled student with a real
 * demand computed via `FeeBreackoff()`), then re-computes the ledger the
 * same way to prove the balance actually moved.
 *
 * Session is seeded directly (sub_institute_id/syear/user_id) rather than
 * going through SessionMiddleware/ApiSessionHydrator - that JWT hydration
 * path is already covered by tests/Feature/Security/SessionMiddlewareAuthBypassTest.php;
 * this test is about the payment/ledger logic once a caller is authenticated.
 */
class CollectFeeTest extends TestCase
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
        $this->monthId = 4 * 10000 + $this->syear; // April, this syear.
        $this->makeFeeStructure();
        $this->makeReceiptBook();
        $this->makeReceiptTemplate();

        Session::put('sub_institute_id', $this->institute);
        Session::put('syear', $this->syear);
        Session::put('user_id', $this->userId);
    }

    public function test_a_partial_cash_payment_is_recorded_and_reduces_the_ledger_balance(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $ledgerBefore = $this->pendingLedgerBalance($studentId);
        $this->assertSame($this->structureAmount, $ledgerBefore, 'sanity check: full demand is outstanding before any payment');

        $response = $this->collect($studentId, 2000);

        $this->assertRecorded($response);

        $collectRow = DB::table('fees_collect')->where('student_id', $studentId)->first();
        $this->assertNotNull($collectRow, 'a fees_collect row must exist for this payment');
        $this->assertSame(2000, (int) $collectRow->amount);
        $this->assertSame(2000, (int) $collectRow->tution_fee);
        $this->assertSame('Cash', $collectRow->payment_mode);
        $this->assertSame($this->monthId, (int) $collectRow->term_id);
        $this->assertSame($this->userId, (int) $collectRow->created_by);

        $receiptRow = DB::table('fees_receipt')->where('SUB_INSTITUTE_ID', $this->institute)->first();
        $this->assertNotNull($receiptRow, 'a fees_receipt row must be generated for the payment');
        $this->assertSame((string) $collectRow->id, (string) $receiptRow->FEES_ID);

        $ledgerAfter = $this->pendingLedgerBalance($studentId);
        $this->assertSame(3000, $ledgerAfter, 'ledger balance must drop by exactly the amount collected');
    }

    public function test_paying_the_remaining_balance_brings_the_ledger_to_zero(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $this->collect($studentId, 2000);
        $this->collect($studentId, 3000);

        $this->assertSame(0, $this->pendingLedgerBalance($studentId), 'ledger balance must be fully cleared once the whole demand is paid');

        $totalCollected = DB::table('fees_collect')->where('student_id', $studentId)->sum('amount');
        $this->assertSame($this->structureAmount, (int) $totalCollected, 'sum of recorded payments must equal the structured demand');
    }

    public function test_a_second_students_ledger_is_unaffected_by_the_first_students_payment(): void
    {
        $paidStudent = $this->makeStudent();
        $this->enrolStudent($paidStudent);
        $this->collect($paidStudent, 5000);

        $otherStudent = $this->makeStudent();
        $this->enrolStudent($otherStudent);

        $this->assertSame(0, $this->pendingLedgerBalance($paidStudent));
        $this->assertSame($this->structureAmount, $this->pendingLedgerBalance($otherStudent), 'an unrelated student\'s demand must be untouched by someone else\'s payment');
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
            'remarks' => 'Track A Day 11 test payment',
            'full_name' => 'Collect Test',
            'std_div' => 'Standard-1 (Collect Test)',
            'mobile' => '9999999999',
        ];

        $_REQUEST = $payload;
        $request = Request::create('/fees/fees_collect', 'POST', $payload);

        return app(fees_collect_controller::class)->store($request);
    }

    private function assertRecorded($response): void
    {
        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertArrayNotHasKey('status_code', $data, 'a status_code in the response means pay_fees() bailed out early: ' . ($data['message'] ?? json_encode($data)));
    }

    /** Same computation the fees module uses everywhere: structure minus collected. */
    private function pendingLedgerBalance(int $studentId): int
    {
        $demand = FeeBreackoff([$studentId], $this->standardId, $this->syear, $this->institute);
        $totalDemand = array_sum(array_map(fn ($row) => (int) $row->bkoff, $demand));

        $collected = (int) DB::table('fees_collect')->where('student_id', $studentId)->sum('amount');

        return $totalDemand - $collected;
    }

    private function makeInstitute(): int
    {
        return (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'SYNTHETIC Collect Fee Test School',
            'ShortCode' => 'CFT' . random_int(1000, 9999),
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
            'title' => 'Primary (Collect Test)',
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
            'name' => 'Standard-1 (Collect Test)',
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
            'user_name' => 'cft_admin' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Collect',
            'last_name' => 'Tester',
            'email' => 'cft' . random_int(10000, 99999) . '@example.com',
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
        $lookup = DB::table('fees_title_master')->where('id', 2)->first(); // global "Tution Fee" lookup row.

        return (int) DB::table('fees_title')->insertGetId([
            'fees_title_id' => $lookup->id,
            'fees_title' => $lookup->fee_paid_title, // 'tution_fee' - a real fees_collect column.
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

    /**
     * Without a matching receipt-book row, pay_fees() silently builds an
     * empty insert array (the receipt-number lookup gates which fee heads
     * are even eligible to be recorded) and nothing gets inserted at all.
     */
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

    /**
     * fees_config_master.fees_receipt_template must match a
     * fees_receipt_css.receipt_id row (they're joined on that string) -
     * without it, gunrate_receipt() falls into a broken fallback query
     * (`fees_receipt_css` filtered on a nonexistent `frc.receipt_id` alias)
     * that errors on any institute lacking this row. Pre-existing bug,
     * unrelated to demand/ledger correctness - worked around here the same
     * way real onboarding avoids it, by always seeding this row.
     */
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
            'first_name' => 'Collect',
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
