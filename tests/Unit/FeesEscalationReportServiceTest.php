<?php

namespace Tests\Unit;

use App\Services\Platform\Fees\FeesEscalationReportService;
use PHPUnit\Framework\TestCase;

/**
 * The ₹5,000/₹25,000 concession escalation rule and the step→tier mapping
 * it is reported against. Both `classify()` and `tierForStep()` are pure
 * (no DB, no container), matching FeesArrearsServiceTest's own reasoning
 * for extending PHPUnit's TestCase directly rather than Tests\TestCase.
 */
class FeesEscalationReportServiceTest extends TestCase
{
    private function service(): FeesEscalationReportService
    {
        return new FeesEscalationReportService();
    }

    // ---------------------------------------------------------------- classify()

    public function test_amount_at_the_accounts_boundary_stays_at_accounts(): void
    {
        $result = $this->service()->classify(5000.0);

        $this->assertSame('accounts', $result['tier']);
        $this->assertFalse($result['is_escalated_to_trustee']);
    }

    public function test_amount_just_above_the_accounts_boundary_requires_principal(): void
    {
        $result = $this->service()->classify(5001.0);

        $this->assertSame('principal', $result['tier']);
        $this->assertFalse($result['is_escalated_to_trustee']);
    }

    public function test_amount_at_the_principal_boundary_stays_at_principal(): void
    {
        $result = $this->service()->classify(25000.0);

        $this->assertSame('principal', $result['tier']);
        $this->assertFalse($result['is_escalated_to_trustee']);
    }

    public function test_amount_just_above_the_principal_boundary_escalates_to_trustee(): void
    {
        $result = $this->service()->classify(25001.0);

        $this->assertSame('trustee', $result['tier']);
        $this->assertTrue($result['is_escalated_to_trustee']);
    }

    public function test_zero_amount_is_classified_at_accounts_not_rejected(): void
    {
        $result = $this->service()->classify(0.0);

        $this->assertSame('accounts', $result['tier']);
    }

    // ---------------------------------------------------------------- tierForStep()

    public function test_principal_approver_type_maps_to_principal_tier_regardless_of_label(): void
    {
        $this->assertSame('principal', $this->service()->tierForStep('principal', 'Step 2', ''));
    }

    public function test_trustee_label_maps_to_trustee_tier(): void
    {
        $this->assertSame('trustee', $this->service()->tierForStep('role', 'Trustee Sign-off', 'Trustee'));
    }

    public function test_management_label_maps_to_trustee_tier(): void
    {
        $this->assertSame('trustee', $this->service()->tierForStep('role', 'Management Approval', ''));
    }

    public function test_accounts_label_maps_to_accounts_tier(): void
    {
        $this->assertSame('accounts', $this->service()->tierForStep('role', 'Accounts verification', 'Accounts'));
    }

    public function test_clerk_label_maps_to_accounts_tier(): void
    {
        $this->assertSame('accounts', $this->service()->tierForStep('role', 'Clerk review', ''));
    }

    public function test_unrecognised_label_maps_to_unmapped_rather_than_guessed(): void
    {
        $this->assertSame('unmapped', $this->service()->tierForStep('role', 'Finance head', 'Finance head'));
    }
}
