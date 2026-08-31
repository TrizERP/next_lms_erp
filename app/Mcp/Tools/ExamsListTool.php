<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ResultReportService;

class ExamsListTool extends AbstractMcpTool
{
    public function __construct(private readonly ResultReportService $service)
    {
    }

    protected function name(): string
    {
        return 'exams.list';
    }

    protected function description(): string
    {
        return 'The exams defined for this institute, with their titles, terms and weightings. '
            . 'Use it to resolve an exam named in a question into the id exams.results needs.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'result.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->exams($context, $arguments);
    }
}
