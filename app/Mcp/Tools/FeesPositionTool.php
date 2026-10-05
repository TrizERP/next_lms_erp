<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FeesPositionService;
use App\Services\Mcp\McpRequestContext;

/**
 * The school-wide fee position: how much is pending, how many students owe it, how it
 * ages, and which classes carry the most.
 *
 * Counterpart to `fees.outstanding_accounts` (who, by name) and to `fees.arrears` (a
 * bounded sweep through the fee screen's per-student calculation). This one is the
 * whole-school picture, read from the fee ledger in a single grouped query, so a total is
 * never a sample.
 */
class FeesPositionTool extends AbstractMcpTool
{
    public function __construct(private readonly FeesPositionService $service)
    {
    }

    public function name(): string
    {
        return 'fees.position';
    }

    public function description(): string
    {
        return 'School-wide fee position for the selected academic year: total demand, collected and '
            . 'pending amount, how many students still owe, the overdue amount and its ageing, the '
            . 'collection rate, and the classes with the most pending fees. Use for "how much is '
            . 'pending?", "how many students have unpaid fees?", "which classes owe the most?" and '
            . 'collection summaries. Covers the whole school, never a sample.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                    'description' => 'How many classes to list, highest pending first. Defaults to 10.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return [
            'risk' => 'read',
            'required_permission' => 'fees.collect',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->position($context, $arguments);
    }
}
