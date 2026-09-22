<?php

namespace App\Domain\AI\Fees\Guardrails;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeesGuardrailService
{
    public function __construct(private readonly FeesPolicyGuardrail $policyGuardrail)
    {
    }

    public function validateReadOperation(McpRequestContext $context, array $arguments): array
    {
        $checks = [
            $this->validateInstituteScope($context),
            $this->validateNoFakeData($context),
            $this->validatePermission($context, 'fees.read'),
        ];

        $failures = array_filter($checks, static fn ($c) => $c['status'] === 'failed');

        return [
            'allowed' => empty($failures),
            'operation_type' => 'read',
            'checks' => $checks,
            'failures' => array_values($failures),
        ];
    }

    public function validateWriteOperation(McpRequestContext $context, array $arguments): array
    {
        $checks = [
            $this->validateInstituteScope($context),
            $this->validateNoFakeData($context),
            $this->validatePermission($context, 'fees.write'),
            $this->validateAmountAccuracy($context, $arguments),
            $this->requireConfirmation($context, $arguments),
        ];

        $failures = array_filter($checks, static fn ($c) => $c['status'] === 'failed');

        return [
            'allowed' => empty($failures),
            'operation_type' => 'write',
            'checks' => $checks,
            'failures' => array_values($failures),
        ];
    }

    public function validateForFeesAction(McpRequestContext $context, array $arguments): array
    {
        $checks = [
            $this->validateInstituteScope($context),
            $this->validateNoFakeData($context),
            $this->validatePermission($context, 'fees.collect'),
            $this->validateAmountAccuracy($context, $arguments),
            $this->requireConfirmation($context, $arguments),
            $this->validatePolicyCompliance($context, $arguments),
            $this->protectPromptInjection($context),
        ];

        $failures = array_filter($checks, static fn ($c) => $c['status'] === 'failed');

        return [
            'allowed' => empty($failures),
            'checks' => $checks,
            'failures' => array_values($failures),
        ];
    }

    private function validateInstituteScope(McpRequestContext $context): array
    {
        $instituteId = $context->selectedInstituteId;

        if ($instituteId <= 0) {
            return [
                'rule' => 'institute_data_scope',
                'status' => 'failed',
                'message' => 'No institute scope set. Cannot proceed without institute scope.',
            ];
        }

        $allowed = $context->allowedInstituteIds;

        return [
            'rule' => 'institute_data_scope',
            'status' => 'passed',
            'message' => "Operation scoped to institute {$instituteId}.",
            'sub_institute_id' => $instituteId,
            'allowed_institute_ids' => $allowed,
        ];
    }

    private function validateNoFakeData(McpRequestContext $context): array
    {
        return [
            'rule' => 'no_fake_data',
            'status' => 'passed',
            'message' => 'All fee data must come from Fees APIs/database. No fake, mock, or estimated data allowed.',
            'instruction' => 'Never create or guess student, payment, invoice, or pending-fee data.',
        ];
    }

    private function validatePermission(McpRequestContext $context, string $permission): array
    {
        $allowedRoles = ['admin', 'staff', 'super admin', 'school admin'];

        if (in_array($context->role, $allowedRoles, true)) {
            return [
                'rule' => 'permission_validation',
                'status' => 'passed',
                'message' => "Role '{$context->role}' permitted for {$permission}.",
                'permission' => $permission,
                'role' => $context->role,
            ];
        }

        return [
            'rule' => 'permission_validation',
            'status' => 'failed',
            'message' => "Role '{$context->role}' is not permitted for {$permission}.",
            'permission' => $permission,
            'role' => $context->role,
        ];
    }

    private function validateAmountAccuracy(McpRequestContext $context, array $arguments): array
    {
        if (isset($arguments['amount']) || isset($arguments['pending_amount'])) {
            return [
                'rule' => 'amount_accuracy',
                'status' => 'warning',
                'message' => 'Amounts must be verified against Fees API/database before any modification.',
                'instruction' => 'Use actual amount returned by Fees API, never estimate or invent.',
            ];
        }

        return [
            'rule' => 'amount_accuracy',
            'status' => 'passed',
            'message' => 'No amount specified in arguments. Amount will be fetched from Fees API.',
        ];
    }

    private function requireConfirmation(McpRequestContext $context, array $arguments): array
    {
        $isConsequential = isset($arguments['confirm']) || isset($arguments['confirmation_token']);

        if ($isConsequential) {
            return [
                'rule' => 'action_confirmation',
                'status' => 'passed',
                'message' => 'Confirmation received for write action.',
            ];
        }

        return [
            'rule' => 'action_confirmation',
            'status' => 'pending',
            'message' => 'Confirmation required before modifying fees data.',
            'instruction' => 'Show details and require user confirmation before collect, update, refund or modify fees.',
        ];
    }

    private function validatePolicyCompliance(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('ai_policies')) {
            return [
                'rule' => 'policy_compliance',
                'status' => 'passed',
                'message' => 'No policy table found. Skipping policy check.',
            ];
        }

        $policyResult = DB::table('ai_policies as p')
            ->leftJoin('ai_policy_assignments as a', 'a.policy_id', '=', 'p.id')
            ->where('p.status', 1)
            ->where(function ($query) use ($context) {
                $query->where('p.sub_institute_id', $context->selectedInstituteId)
                    ->orWhereNull('p.sub_institute_id');
            })
            ->where(function ($query) use ($context) {
                $query->where('a.sub_institute_id', $context->selectedInstituteId)
                    ->orWhereNull('a.sub_institute_id');
            })
            ->where('a.status', 1)
            ->select('p.id', 'p.policy_type', 'p.status')
            ->first();

        if ($policyResult === null) {
            return [
                'rule' => 'policy_compliance',
                'status' => 'passed',
                'message' => 'No fees-specific policy found. Default rules apply.',
            ];
        }

        if ((int) ($policyResult->policy_type ?? '') === 'ai_free') {
            return [
                'rule' => 'policy_compliance',
                'status' => 'failed',
                'message' => 'AI operations are disabled by the active AI policy (ai_free).',
                'policy_id' => (int) $policyResult->id,
            ];
        }

        return [
            'rule' => 'policy_compliance',
            'status' => 'passed',
            'message' => 'AI policy allows fees operations.',
            'policy_id' => (int) $policyResult->id,
            'policy_type' => $policyResult->policy_type,
        ];
    }

    private function protectPromptInjection(McpRequestContext $context): array
    {
        $question = property_exists($context, 'question') ? ($context->question ?? '') : '';

        $injectionPatterns = [
            '/ignore\s+(previous|all|your)\s+(instructions|rules|constraints)/i',
            '/override\s+scope/i',
            '/bypass\s+(security|guardrail|permission)/i',
            '/show\s+all\s+(students|fees|data)/i',
            '/forget\s+your\s+role/i',
            '/act\s+as\s+(admin|superuser|root)/i',
        ];

        foreach ($injectionPatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return [
                    'rule' => 'prompt_injection_protection',
                    'status' => 'failed',
                    'message' => 'Potential prompt injection detected. Request rejected for security.',
                    'pattern' => $pattern,
                ];
            }
        }

        return [
            'rule' => 'prompt_injection_protection',
            'status' => 'passed',
            'message' => 'No prompt injection patterns detected.',
        ];
    }
}

class FeesPolicyGuardrail
{
    public function checkDataPrivacy(McpRequestContext $context, int $studentId): array
    {
        $student = DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as s', 's.id', '=', 'se.student_id')
            ->where('se.student_id', $studentId)
            ->where('se.sub_institute_id', $context->selectedInstituteId)
            ->first();

        if ($student === null) {
            return [
                'rule' => 'data_privacy',
                'status' => 'failed',
                'message' => "Student #{$studentId} not found in this institute's scope.",
            ];
        }

        return [
            'rule' => 'data_privacy',
            'status' => 'passed',
            'message' => "Student #{$studentId} is within institute scope.",
            'student_id' => $studentId,
        ];
    }

    public function checkUnauthorizedAccess(McpRequestContext $context, ?int $targetInstituteId): array
    {
        if ($targetInstituteId === null) {
            return [
                'rule' => 'unauthorized_data_block',
                'status' => 'passed',
                'message' => 'No target institute specified. Operating within current scope.',
            ];
        }

        if ($targetInstituteId === $context->selectedInstituteId) {
            return [
                'rule' => 'unauthorized_data_block',
                'status' => 'passed',
                'message' => 'Target institute matches current scope.',
            ];
        }

        if (in_array($targetInstituteId, $context->allowedInstituteIds, true)) {
            return [
                'rule' => 'unauthorized_data_block',
                'status' => 'passed',
                'message' => 'Target institute is in allowed institutes.',
            ];
        }

        return [
            'rule' => 'unauthorized_data_block',
            'status' => 'failed',
            'message' => "Access denied: institute #{$targetInstituteId} is not in scope.",
        ];
    }

    public function separateReadWrite(string $operation): array
    {
        $writeOperations = ['collect', 'refund', 'cancel', 'update', 'delete', 'modify', 'approve'];
        $isWrite = in_array($operation, $writeOperations, true);

        return [
            'operation' => $operation,
            'is_write' => $isWrite,
            'read_only' => ! $isWrite,
            'message' => $isWrite
                ? 'Write operation: requires confirmation and audit logging.'
                : 'Read operation: no confirmation required.',
        ];
    }

    public function logAudit(string $action, McpRequestContext $context, array $data): void
    {
        try {
            DB::table('audit_logs')->insert([
                'module' => 'fees',
                'action' => $action,
                'sub_institute_id' => $context->selectedInstituteId,
                'entity_type' => 'ai_fees_action',
                'entity_id' => $data['entity_id'] ?? null,
                'new_values' => json_encode($data),
                'created_by' => $context->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('FeesGuardrailService audit log failed: ' . $e->getMessage());
        }
    }
}
