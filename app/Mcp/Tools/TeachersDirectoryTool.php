<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PeopleDirectoryService;

class TeachersDirectoryTool extends AbstractMcpTool
{
    public function __construct(private readonly PeopleDirectoryService $service)
    {
    }

    protected function name(): string
    {
        return 'teachers.directory';
    }

    protected function description(): string
    {
        return 'List active teachers and other staff at this institute, with their profile and '
            . 'department. Filter by profile name to reach non-teaching staff.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Match on name, email or employee number.'],
                'profile' => [
                    'type' => 'string',
                    'default' => 'Teacher',
                    'description' => 'Profile name to match, e.g. "Teacher" or "Admin". Pass an empty string for all staff.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'staff.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->teachers($context, $arguments);
    }
}
