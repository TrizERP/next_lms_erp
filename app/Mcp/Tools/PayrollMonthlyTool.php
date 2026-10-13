<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\PayrollOverviewService;
use App\Services\Mcp\McpRequestContext;

/**
 * Payslips generated per month for this institute with total payment and total deduction. Totals only, never one named person pay.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class PayrollMonthlyTool extends AbstractMcpTool
{
    public function __construct(private readonly PayrollOverviewService $service)
    {
    }

    public function name(): string
    {
        return 'payroll.monthly';
    }

    public function description(): string
    {
        return 'Payslips generated per month for this institute with total payment and total deduction. Totals only, never one named person pay. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'year' => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'payroll.read'];
    }

    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        return ['admin'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->monthly($context, $arguments);
    }
}
