<?php

namespace Tests\Feature\Fees;

use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Track A / Day 11 — "Receipt generation (basic, fixed format)".
 *
 * DoD: a sequential receipt number is issued, and a printable/PDF receipt is
 * produced on successful collection - plain fixed format, no template
 * engine/versioning for Day-11.
 *
 * Both halves already exist in production code, exercised here rather than
 * built fresh:
 *  - Sequential numbering: `fees_collect_controller::gunrate_receipt_number()`
 *    computes MAX(existing RECEIPT_ID_<sort_order>) + 1 per receipt-book
 *    entry (fees_receipt_book_master), so it increments across separate
 *    collections without any counter table of its own.
 *  - Printable receipt: `gunrate_receipt()` renders the receipt HTML and
 *    `pay_fees()` returns it directly in the collect API's JSON response
 *    (`data` key) - the Next.js "Collect Fees" screen renders that HTML
 *    inline and calls `window.print()`; nothing further is fetched. The same
 *    HTML is also persisted to `fees_collect.fees_html` so it can be
 *    reprinted later without recomputing.
 *
 * This test deliberately configures NO institute-specific receipt template
 * (no custom `fees_config_master`/`fees_receipt_css` beyond the one plain
 * "A5" pairing every institute needs to avoid a pre-existing bug documented
 * in CollectFeeTest.php, and no `template_master` row at all) - proving the
 * plain-fixed-format path a fresh Day-11 institute actually gets, with no
 * per-institute template versioning involved.
 */
class ReceiptGenerationTest extends TestCase
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

    public function test_a_successful_collection_issues_receipt_number_1_and_returns_printable_html(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $data = $this->collectAndDecode($studentId, 5000);

        $this->assertSame('1', (string) $data['receipt_id_html'], 'first receipt issued for a fresh receipt book must be number 1');
        $this->assertNotEmpty($data['data'] ?? null, 'the response must carry printable receipt HTML, not just a success flag');
        $this->assertStringContainsString('5000', (string) $data['data'], 'the printable receipt must show the amount actually collected');

        // Persisted for reprinting later, not just returned once.
        $collectRow = DB::table('fees_collect')->where('student_id', $studentId)->first();
        $this->assertNotEmpty($collectRow->fees_html, 'the receipt HTML must be persisted on fees_collect for later reprint');

        $receiptRow = DB::table('fees_receipt')->where('SUB_INSTITUTE_ID', $this->institute)->first();
        $this->assertSame('1', (string) $receiptRow->RECEIPT_ID_1, 'the receipt number returned to the caller must match what was actually persisted');
    }

    public function test_receipt_numbers_are_sequential_across_separate_collections(): void
    {
        $studentA = $this->makeStudent();
        $this->enrolStudent($studentA);
        $dataA = $this->collectAndDecode($studentA, 2000);

        $studentB = $this->makeStudent();
        $this->enrolStudent($studentB);
        $dataB = $this->collectAndDecode($studentB, 3000);

        $this->assertSame('1', (string) $dataA['receipt_id_html']);
        $this->assertSame('2', (string) $dataB['receipt_id_html'], 'a second, unrelated collection must get the next sequential receipt number');

        // A third payment, even against the FIRST student again, continues
        // the same sequence - numbering is per receipt-book, not per student.
        $dataA2 = $this->collectAndDecode($studentA, 500);
        $this->assertSame('3', (string) $dataA2['receipt_id_html']);
    }

    public function test_receipt_generation_works_with_no_institute_specific_template_configured(): void
    {
        // setUp() deliberately configured only the single "A5"/default
        // pairing - no per-institute template_master row, no second
        // fees_config_master variant. This is the plain-fixed-format path.
        $this->assertSame(
            0,
            DB::table('template_master')->where('module_name', 'Fees')->where('sub_institute_id', $this->institute)->count(),
            'sanity check: no institute-specific template was configured for this test'
        );

        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        $data = $this->collectAndDecode($studentId, 5000);

        $this->assertNotEmpty($data['data'], 'receipt generation must succeed on the plain default format alone');
    }

    private function collectAndDecode(int $studentId, int $amount): array
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
            'remarks' => 'Track A Day 11 receipt test',
            'full_name' => 'Collect Test',
            'std_div' => 'Standard-1 (Receipt Test)',
            'mobile' => '9999999999',
        ];

        $_REQUEST = $payload;
        $request = Request::create('/fees/fees_collect', 'POST', $payload);

        $response = app(fees_collect_controller::class)->store($request);
        $data = $response->getData(true);

        $this->assertArrayNotHasKey('status_code', $data, 'collection must succeed before a receipt can be evaluated: ' . json_encode($data));

        return $data;
    }

    private function makeInstitute(): int
    {
        return (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'SYNTHETIC Receipt Generation Test School',
            'ShortCode' => 'RGT' . random_int(1000, 9999),
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
            'title' => 'Primary (Receipt Test)',
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
            'name' => 'Standard-1 (Receipt Test)',
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
            'user_name' => 'rgt_admin' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Receipt',
            'last_name' => 'Tester',
            'email' => 'rgt' . random_int(10000, 99999) . '@example.com',
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
            'first_name' => 'Receipt',
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
