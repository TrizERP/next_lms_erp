<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentSearchService;

class StudentSearchTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentSearchService $service)
    {
    }

    protected function name(): string
    {
        return 'students.search';
    }

    protected function description(): string
    {
        return 'Search students within the authenticated institute scope.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Exact student id within the authenticated institute scope.'],
                'query' => ['type' => 'string', 'description' => 'Free-text search by name, admission id, enrollment number, mobile, or email.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'active_only' => ['type' => 'boolean', 'default' => true],
                'admission_year' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->search($context, $arguments);
    }
}
