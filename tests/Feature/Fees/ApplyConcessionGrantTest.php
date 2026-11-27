<?php

namespace Tests\Feature\Fees;

use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Models\Platform\PlatformWorkflow;
use App\Services\Platform\Fees\FeesConcessionRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\Support\FeesCollectTestHelpers;
use Tests\TestCase;

/**
 * Applying an approved concession to a real receipt — the `pay_fees()`
 * hook that books an approved `fees_concession_grants` row against the
 * actual discount the ledger just recorded, in the same transaction as
 * the receipt itself.
 *
 * Mirrors CollectFeeTest's own fixture chain and driving style
 * (Session seeded directly, pay_fees() called with a real Request shaped
 * like the Next.js Collect Fees screen's payload) since this is the same
 * method under test, now with `concession_grant_id` + `discount_data[]`
 * added to the payload.
 */
class ApplyConcessionGrantTest extends TestCase
{
    use DatabaseTransactions;
    use FeesCollectTestHelpers;

    private int $institute;

    private int $syear;

    private int $sectionId;

    private int $standardId;

    private int $quotaId;

    private int $feeTypeId;

    private int $monthId;

    private int $userId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syear = (int) date('Y');
        $this->institute = $this->makeFeesInstitute();
        $this->sectionId = $this->makeFeesAcademicSection($this->institute);
        $this->standardId = $this->makeFeesStandard($this->institute, $this->sectionId);
        $this->quotaId = $this->makeFeesQuota($this->institute);
        $this->userId = $this->makeFeesUser($this->institute, $this->syear);
        $this->makeFeesAcademicYear($this->institute, $this->syear);
        $this->makeFeesMapYearRow($this->institute, $this->syear);
        $this->feeTypeId = $this->makeFeesHead($this->institute, $this->syear);
        $this->monthId = 4 * 10000 + $this->syear;
        $this->makeFeesStructure($this->institute, $this->syear, $this->sectionId, $this->standardId, $this->quotaId, $this->feeTypeId, $this->monthId, 5000);
        $this->makeFeesReceiptBook($this->institute, $this->syear, $this->sectionId, $this->standardId, $this->feeTypeId);
        $this->makeFeesReceiptTemplate($this->institute, $this->syear);

        $this->studentId = $this->makeFeesStudent($this->institute, $this->syear);
        $this->enrolFeesStudent($this->studentId, $this->institute, $this->syear, $this->sectionId, $this->standardId, $this->quotaId);

        Session::put('sub_institute_id', $this->institute);
        Session::put('syear', $this->syear);
        Session::put('user_id', $this->userId);
    }

    private function approvedGrant(int $amount): array
    {
        PlatformWorkflow::create([
            'sub_institute_id' => $this->institute,
            'flow_key' => 'fees.concession.flow',
            'module' => 'fees',
            'component' => 'concession',
            'name' => 'Test chain',
            'status' => 'active',
            'condition' => '',
            'steps' => [['name' => 'Accounts', 'approver_type' => 'role', 'approver' => 'Accounts', 'sla_hours' => 24]],
        ]);

        $requests = app(FeesConcessionRequestService::class);
        $run = $requests->submitConcession($this->institute, $this->studentId, $amount, 'Test concession', $this->userId, 'Tester');
        $result = $requests->act($this->institute, $run['id'], 'approve', $this->userId, 'Tester', 'Approved', null);

        return $result['grant'];
    }

    private function collectWithGrant(int $amount, ?int $grantId, ?int $discount = null): array
    {
        $payload = [
            'type' => 'API',
            'syear' => $this->syear,
            'student_id' => $this->studentId,
            'standard_id' => $this->standardId,
            'grade_id' => $this->sectionId,
            'months' => [$this->monthId],
            'fees_data' => ['tution_fee' => $amount],
            'PAYMENT_MODE' => 'Cash',
            'receiptdate' => now()->toDateString(),
            'bank_branch' => '', 'bank_name' => '', 'cheque_no' => '', 'cheque_date' => '',
            'remarks' => 'Fees Intelligence apply-grant test',
            'full_name' => 'Apply Grant Test', 'std_div' => 'Standard-1 (Fees Intelligence Test)',
            'mobile' => '9999999999',
        ];

        if ($grantId !== null) {
            $payload['concession_grant_id'] = $grantId;
            $payload['discount_data'] = ['tution_fee' => $discount ?? $amount];
            $payload['totalDis'] = $discount ?? $amount;
        }

        $_REQUEST = $payload;
        $request = Request::create('/fees/fees_collect', 'POST', $payload);

        return app(fees_collect_controller::class)->pay_fees($request);
    }

    public function test_applying_an_approved_concession_records_the_discount_and_completes_the_grant(): void
    {
        $grant = $this->approvedGrant(2000);

        $response = $this->collectWithGrant(2000, (int) $grant['id'], 2000);

        $this->assertArrayNotHasKey('status_code', $response, 'a status_code key means pay_fees() bailed out: '.json_encode($response));

        $collectRow = DB::table('fees_collect')->where('student_id', $this->studentId)->first();
        $this->assertNotNull($collectRow);
        $this->assertSame(2000, (int) $collectRow->fees_discount);

        $grantRow = DB::table('fees_concession_grants')->where('id', $grant['id'])->first();
        $this->assertSame('applied', $grantRow->status);
        $this->assertSame(2000.0, (float) $grantRow->applied_amount);
        $this->assertNotNull($grantRow->applied_at);
    }

    public function test_a_grant_for_a_different_student_is_refused_and_nothing_is_saved(): void
    {
        $otherStudent = $this->makeFeesStudent($this->institute, $this->syear, 'Other', 'Student');
        $this->enrolFeesStudent($otherStudent, $this->institute, $this->syear, $this->sectionId, $this->standardId, $this->quotaId);

        $grant = $this->approvedGrant(2000); // approved against $this->studentId, not $otherStudent

        $before = DB::table('fees_collect')->count();

        $payload = [
            'type' => 'API', 'syear' => $this->syear, 'student_id' => $otherStudent,
            'standard_id' => $this->standardId, 'grade_id' => $this->sectionId, 'months' => [$this->monthId],
            'fees_data' => ['tution_fee' => 2000], 'PAYMENT_MODE' => 'Cash', 'receiptdate' => now()->toDateString(),
            'bank_branch' => '', 'bank_name' => '', 'cheque_no' => '', 'cheque_date' => '', 'remarks' => '',
            'full_name' => 'Other Student', 'std_div' => 'Standard-1 (Fees Intelligence Test)', 'mobile' => '9999999999',
            'concession_grant_id' => (int) $grant['id'], 'discount_data' => ['tution_fee' => 2000], 'totalDis' => 2000,
        ];
        $_REQUEST = $payload;
        $response = app(fees_collect_controller::class)->pay_fees(Request::create('/fees/fees_collect', 'POST', $payload));

        $this->assertSame(0, $response['status_code'] ?? null);
        $this->assertSame($before, DB::table('fees_collect')->count(), 'no receipt row must be written when the grant check fails');

        $grantRow = DB::table('fees_concession_grants')->where('id', $grant['id'])->first();
        $this->assertSame('approved_pending_application', $grantRow->status, 'the grant itself must be untouched');
    }

    public function test_an_already_applied_grant_cannot_be_applied_again(): void
    {
        $grant = $this->approvedGrant(2000);
        $this->collectWithGrant(2000, (int) $grant['id'], 2000);

        $before = DB::table('fees_collect')->count();
        $response = $this->collectWithGrant(2000, (int) $grant['id'], 2000);

        $this->assertSame(0, $response['status_code'] ?? null);
        $this->assertSame($before, DB::table('fees_collect')->count(), 'a second application of a fully-applied grant must not write a new receipt');
    }

    public function test_collecting_without_a_grant_id_behaves_exactly_as_before(): void
    {
        $response = $this->collectWithGrant(2000, null);

        $this->assertArrayNotHasKey('status_code', $response);
        $collectRow = DB::table('fees_collect')->where('student_id', $this->studentId)->first();
        $this->assertSame(0, (int) $collectRow->fees_discount);
    }
}
