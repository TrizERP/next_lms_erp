<?php

namespace Tests\Feature\Fees;

use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Track A / Day 11 — "Student Fee Ledger (basic view)".
 *
 * DoD: shows demand, payment, and running balance for this one flow - NOT
 * the full discount/refund ledger.
 *
 * The existing `getBk()`/`edit()` machinery (exercised by the "Collect Fee"
 * and "Receipt generation" issues) is exactly the FULL ledger this DoD
 * excludes: it folds in discount, previous-year balances, imprest, and
 * institute-specific fine logic. So this issue adds a small, separate
 * `fees_collect_controller::ledger()` (GET /fees/fees_collect/{id}/ledger)
 * that sticks to only the three numbers asked for, reusing the same real
 * `FeeBreackoff()` demand computation and `fees_collect` payment rows every
 * other Track A test in this session already relies on - so "basic" doesn't
 * mean "reimplemented from scratch," it means "the minimum slice of the
 * real data everyone else in the flow already uses."
 */
class StudentFeeLedgerTest extends TestCase
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

    public function test_ledger_shows_full_demand_before_any_payment(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $ledger = $this->ledger($studentId);

        $this->assertSame(5000, $ledger['total_demand']);
        $this->assertSame(0, $ledger['total_paid']);
        $this->assertSame(5000, $ledger['running_balance']);
        $this->assertCount(1, $ledger['months']);
        $this->assertSame($this->monthId, $ledger['months'][0]['month_id']);
        $this->assertSame(5000, $ledger['months'][0]['demand']);
        $this->assertSame(0, $ledger['months'][0]['paid']);
        $this->assertSame(5000, $ledger['months'][0]['balance']);
    }

    public function test_ledger_running_balance_updates_after_a_partial_payment(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $this->collect($studentId, 2000);
        $ledger = $this->ledger($studentId);

        $this->assertSame(5000, $ledger['total_demand'], 'demand must not change just because a payment was made');
        $this->assertSame(2000, $ledger['total_paid']);
        $this->assertSame(3000, $ledger['running_balance']);
        $this->assertSame(2000, $ledger['months'][0]['paid']);
        $this->assertSame(3000, $ledger['months'][0]['balance']);
    }

    public function test_ledger_running_balance_reaches_zero_once_fully_paid(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $this->collect($studentId, 2000);
        $this->collect($studentId, 3000);
        $ledger = $this->ledger($studentId);

        $this->assertSame(5000, $ledger['total_paid']);
        $this->assertSame(0, $ledger['running_balance']);
        $this->assertSame(0, $ledger['months'][0]['balance']);
    }

    public function test_ledger_is_scoped_to_this_flow_only_not_the_full_discount_refund_ledger(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $ledger = $this->ledger($studentId);

        $this->assertEqualsCanonicalizing(
            ['student_id', 'syear', 'months', 'total_demand', 'total_paid', 'running_balance'],
            array_keys($ledger),
            'the basic ledger must expose only demand/payment/balance, nothing from the full discount/refund/imprest ledger'
        );
        $this->assertEqualsCanonicalizing(
            ['month_id', 'demand', 'paid', 'balance'],
            array_keys($ledger['months'][0]),
            'each month row must carry only demand/paid/balance - no discount, fine, or imprest fields'
        );
    }

    public function test_a_second_students_ledger_is_independent(): void
    {
        $paidStudent = $this->makeStudent();
        $this->enrolStudent($paidStudent);
        $this->collect($paidStudent, 5000);

        $otherStudent = $this->makeStudent();
        $this->enrolStudent($otherStudent);

        $this->assertSame(0, $this->ledger($paidStudent)['running_balance']);
        $this->assertSame(5000, $this->ledger($otherStudent)['running_balance'], 'an unrelated student\'s ledger must be untouched by someone else\'s payment');
    }

    private function ledger(int $studentId): array
    {
        $request = Request::create('/fees/fees_collect/' . $studentId . '/ledger', 'GET', [
            'type' => 'API',
            'syear' => $this->syear,
        ]);

        $response = app(fees_collect_controller::class)->ledger($request, $studentId);

        return $response->getData(true);
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
            'remarks' => 'Track A Day 11 ledger test',
            'full_name' => 'Ledger Test',
            'std_div' => 'Standard-1 (Ledger Test)',
            'mobile' => '9999999999',
        ];

        $_REQUEST = $payload;
        $request = Request::create('/fees/fees_collect', 'POST', $payload);

        $response = app(fees_collect_controller::class)->store($request);
        $data = $response->getData(true);
        $this->assertArrayNotHasKey('status_code', $data, 'setup payment must succeed: ' . json_encode($data));

        return $response;
    }

    private function makeInstitute(): int
    {
        return (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'SYNTHETIC Student Fee Ledger Test School',
            'ShortCode' => 'SFL' . random_int(1000, 9999),
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
            'title' => 'Primary (Ledger Test)',
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
            'name' => 'Standard-1 (Ledger Test)',
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
            'user_name' => 'sfl_admin' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Ledger',
            'last_name' => 'Tester',
            'email' => 'sfl' . random_int(10000, 99999) . '@example.com',
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
            'first_name' => 'Ledger',
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
