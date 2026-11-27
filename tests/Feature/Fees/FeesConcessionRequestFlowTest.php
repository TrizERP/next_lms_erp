<?php

namespace Tests\Feature\Fees;

use App\Models\Platform\PlatformWorkflow;
use App\Services\Platform\Fees\FeesConcessionGrantService;
use App\Services\Platform\Fees\FeesConcessionRequestService;
use App\Services\Platform\WorkflowEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\FeesCollectTestHelpers;
use Tests\TestCase;

/**
 * The new concession/refund request-submission feature end to end: a
 * request has to reach `WorkflowEngine::start()` against the tenant's own
 * active chain (never a second approval mechanism), and a run reaching
 * `approved` — whether through this feature's own act() wrapper or through
 * the generic engine directly — has to produce exactly one
 * `fees_concession_grants` row, never zero and never a duplicate.
 */
class FeesConcessionRequestFlowTest extends TestCase
{
    use DatabaseTransactions;
    use FeesCollectTestHelpers;

    private int $institute;

    private int $syear;

    private int $userId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syear = (int) date('Y');
        $this->institute = $this->makeFeesInstitute();
        $this->userId = $this->makeFeesUser($this->institute, $this->syear);
        $this->studentId = $this->makeFeesStudent($this->institute, $this->syear);
    }

    private function requests(): FeesConcessionRequestService
    {
        return app(FeesConcessionRequestService::class);
    }

    private function grants(): FeesConcessionGrantService
    {
        return app(FeesConcessionGrantService::class);
    }

    private function activateChain(array $steps, string $flowKey = 'fees.concession.flow'): PlatformWorkflow
    {
        return PlatformWorkflow::create([
            'sub_institute_id' => $this->institute,
            'flow_key' => $flowKey,
            'module' => 'fees',
            'component' => explode('.', $flowKey)[1] ?? 'concession',
            'name' => 'Test chain',
            'status' => 'active',
            'condition' => '',
            'steps' => $steps,
        ]);
    }

    private function roleStep(string $name, string $approver = 'Accounts'): array
    {
        return ['name' => $name, 'approver_type' => 'role', 'approver' => $approver, 'sla_hours' => 24];
    }

    public function test_submitting_without_an_active_chain_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->requests()->submitConcession($this->institute, $this->studentId, 8000, 'Needs help', $this->userId, 'Tester');
    }

    public function test_submitting_creates_a_pending_run_with_the_chains_steps(): void
    {
        $this->activateChain([$this->roleStep('Accounts verification'), $this->roleStep('Principal approval')]);

        $run = $this->requests()->submitConcession($this->institute, $this->studentId, 8000, 'Needs help', $this->userId, 'Tester');

        $this->assertSame('pending', $run['status']);
        $this->assertCount(2, $run['steps']);
        $this->assertSame('fees.concession.flow', $run['flow_key']);
    }

    public function test_a_second_pending_request_for_the_same_student_is_refused(): void
    {
        $this->activateChain([$this->roleStep('Accounts verification')]);
        $this->requests()->submitConcession($this->institute, $this->studentId, 3000, 'First', $this->userId, 'Tester');

        $this->expectException(InvalidArgumentException::class);
        $this->requests()->submitConcession($this->institute, $this->studentId, 4000, 'Second', $this->userId, 'Tester');
    }

    public function test_approving_the_only_step_through_the_wrapper_creates_exactly_one_grant(): void
    {
        $this->activateChain([$this->roleStep('Accounts verification')]);
        $run = $this->requests()->submitConcession($this->institute, $this->studentId, 6000, 'Sibling discount', $this->userId, 'Tester');

        $result = $this->requests()->act($this->institute, $run['id'], 'approve', $this->userId, 'Tester', 'Approved', null);

        $this->assertSame('approved', $result['run']['status']);
        $this->assertNotNull($result['grant']);
        $this->assertSame('approved_pending_application', $result['grant']['status']);
        $this->assertSame(6000.0, (float) $result['grant']['amount']);

        $this->assertSame(1, DB::table('fees_concession_grants')->where('run_id', $run['id'])->count());
    }

    public function test_approving_through_the_generic_engine_directly_still_produces_a_grant_after_reconcile(): void
    {
        $this->activateChain([$this->roleStep('Accounts verification')]);
        $run = $this->requests()->submitConcession($this->institute, $this->studentId, 5500, 'Need', $this->userId, 'Tester');

        // Bypasses this feature's own act() wrapper entirely - the same path
        // the pre-existing generic /platform-services/workflow/runs approve
        // button uses (EngineController::actOnRun -> WorkflowEngine::act()).
        app(WorkflowEngine::class)->act($this->institute, $run['id'], 'approve', $this->userId, 'Tester', 'Approved generically', null);

        $this->assertSame(0, DB::table('fees_concession_grants')->where('run_id', $run['id'])->count(), 'no grant yet - nothing has reconciled it');

        $recorded = $this->grants()->reconcile($this->institute);
        $this->assertSame(1, $recorded);
        $this->assertSame(1, DB::table('fees_concession_grants')->where('run_id', $run['id'])->count());

        // Calling reconcile() again must not duplicate the row.
        $this->grants()->reconcile($this->institute);
        $this->assertSame(1, DB::table('fees_concession_grants')->where('run_id', $run['id'])->count());
    }

    public function test_a_rejected_run_produces_no_grant(): void
    {
        $this->activateChain([$this->roleStep('Accounts verification')]);
        $run = $this->requests()->submitConcession($this->institute, $this->studentId, 9000, 'Need', $this->userId, 'Tester');

        $result = $this->requests()->act($this->institute, $run['id'], 'reject', $this->userId, 'Tester', 'Not eligible', null);

        $this->assertSame('returned', $result['run']['status']);
        $this->assertNull($result['grant']);
        $this->assertSame(0, DB::table('fees_concession_grants')->where('run_id', $run['id'])->count());
    }

    public function test_another_tenants_approved_run_is_never_reconciled_into_this_tenant(): void
    {
        $otherInstitute = $this->makeFeesInstitute();
        $otherUser = $this->makeFeesUser($otherInstitute, $this->syear);
        $otherStudent = $this->makeFeesStudent($otherInstitute, $this->syear);

        PlatformWorkflow::create([
            'sub_institute_id' => $otherInstitute,
            'flow_key' => 'fees.concession.flow',
            'module' => 'fees',
            'component' => 'concession',
            'name' => 'Other chain',
            'status' => 'active',
            'condition' => '',
            'steps' => [$this->roleStep('Accounts verification')],
        ]);

        $otherRun = $this->requests()->submitConcession($otherInstitute, $otherStudent, 7000, 'Other tenant', $otherUser, 'Other');
        $this->requests()->act($otherInstitute, $otherRun['id'], 'approve', $otherUser, 'Other', 'Approved', null);

        $recorded = $this->grants()->reconcile($this->institute);

        $this->assertSame(0, $recorded);
        $this->assertSame(0, DB::table('fees_concession_grants')->where('sub_institute_id', $this->institute)->count());
    }
}
