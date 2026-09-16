<?php

namespace Tests\Feature\AI\Fees;

use App\Domain\AI\Fees\Guardrails\FeesGuardrailService;
use App\Domain\AI\Fees\Guardrails\FeesPolicyGuardrail;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;

class FeesGuardrailTest extends TestCase
{
    private function makeContext(array $overrides = []): McpRequestContext
    {
        return new McpRequestContext(
            userId: $overrides['userId'] ?? 1,
            role: $overrides['role'] ?? 'admin',
            selectedInstituteId: $overrides['selectedInstituteId'] ?? 1,
            allowedInstituteIds: $overrides['allowedInstituteIds'] ?? [1],
            userProfileId: $overrides['userProfileId'] ?? null,
            clientId: $overrides['clientId'] ?? null,
            academicYear: $overrides['academicYear'] ?? null,
            termId: $overrides['termId'] ?? null,
            isAdmin: $overrides['isAdmin'] ?? true,
            isStudent: $overrides['isStudent'] ?? false,
        );
    }

    private function makePolicyGuardrail(): FeesPolicyGuardrail
    {
        return new FeesPolicyGuardrail();
    }

    public function test_service_exists(): void
    {
        $service = new FeesGuardrailService($this->makePolicyGuardrail());
        $this->assertInstanceOf(FeesGuardrailService::class, $service);
    }

    public function test_read_operation_with_valid_context(): void
    {
        $service = new FeesGuardrailService($this->makePolicyGuardrail());
        $context = $this->makeContext();

        $result = $service->validateReadOperation($context, []);

        $this->assertTrue($result['allowed']);
        $this->assertSame('read', $result['operation_type']);
    }

    public function test_read_operation_denied_without_institute(): void
    {
        $service = new FeesGuardrailService($this->makePolicyGuardrail());
        $context = $this->makeContext(['selectedInstituteId' => 0]);

        $result = $service->validateReadOperation($context, []);

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['failures']);
    }

    public function test_write_operation_denied_for_non_admin(): void
    {
        $service = new FeesGuardrailService($this->makePolicyGuardrail());
        $context = $this->makeContext(['role' => 'student']);

        $result = $service->validateWriteOperation($context, []);

        $this->assertFalse($result['allowed']);
    }

    public function test_write_operation_passes_for_admin(): void
    {
        $service = new FeesGuardrailService($this->makePolicyGuardrail());
        $context = $this->makeContext(['role' => 'admin']);

        $result = $service->validateWriteOperation($context, []);

        $this->assertTrue($result['allowed']);
    }

    public function test_guardrail_passes_for_admin_with_confirmation(): void
    {
        $service = new FeesGuardrailService($this->makePolicyGuardrail());
        $context = $this->makeContext(['role' => 'admin']);

        $result = $service->validateWriteOperation($context, ['confirm' => true]);

        $this->assertTrue($result['allowed']);
    }

    public function test_policy_guardrail_separates_read_write(): void
    {
        $policy = new FeesPolicyGuardrail();

        $read = $policy->separateReadWrite('read');
        $this->assertFalse($read['is_write']);
        $this->assertTrue($read['read_only']);

        $write = $policy->separateReadWrite('collect');
        $this->assertTrue($write['is_write']);
        $this->assertFalse($write['read_only']);
    }

    public function test_policy_guardrail_accepts_allowed_institute(): void
    {
        $policy = new FeesPolicyGuardrail();
        $context = $this->makeContext(['allowedInstituteIds' => [1, 2, 99999]]);

        $result = $policy->checkUnauthorizedAccess($context, 99999);

        $this->assertSame('unauthorized_data_block', $result['rule']);
        $this->assertSame('passed', $result['status']);
    }
}