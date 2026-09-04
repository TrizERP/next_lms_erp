<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FeesArrearsService;
use App\Services\Mcp\McpRequestContext;

/**
 * The cohort-level counterpart to `fees.getPending`.
 *
 * `fees.getPending` answers "what does this child owe?" and needs an id. This one answers
 * "who owes anything?", which is the question the fees module's own vocabulary routes to
 * it and which nothing was able to serve.
 */
class FeesArrearsTool extends AbstractMcpTool
{
    public function __construct(private readonly FeesArrearsService $service)
    {
    }

    protected function name(): string
    {
        return 'fees.arrears';
    }

    protected function description(): string
    {
        return 'List students with outstanding fees across a class, section or the school. '
            . 'Answers "who are the fee defaulters?" without needing a student named first. '
            . 'Examines a bounded cohort and reports how many students it checked, so a partial '
            . 'sweep is never presented as a school-wide figure.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'description' => 'Restrict to one class.'],
                'section_id' => ['type' => 'integer', 'description' => 'Restrict to one division.'],
                'min_amount' => [
                    'type' => 'number',
                    'description' => 'Only report students owing at least this much.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'description' => 'How many students to check. Defaults to 25, ceiling 100.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return [
            'risk' => 'read',
            'required_permission' => 'fees.collect',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->arrears($context, $arguments);
    }
}
