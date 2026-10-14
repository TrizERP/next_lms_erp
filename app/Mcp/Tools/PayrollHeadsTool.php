<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Mcp\Concerns\RequiresAdministrator;
use App\Services\Mcp\PayrollOverviewService;
use App\Services\Mcp\McpRequestContext;

/**
 * The pay heads (allowances and deductions) this institute has defined. No individual salary is returned.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class PayrollHeadsTool extends AbstractMcpTool
{
    use RequiresAdministrator;

    public function __construct(private readonly PayrollOverviewService $service)
    {
    }

    public function name(): string
    {
        return 'payroll.heads';
    }

    public function description(): string
    {
        return 'The pay heads (allowances and deductions) this institute has defined. No individual salary is returned. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['any', 'allowance', 'deduction'], 'default' => 'any'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'payroll.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->heads($context, $arguments);
    }
}
