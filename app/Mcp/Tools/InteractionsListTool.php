<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\InteractionReportService;
use App\Services\Mcp\McpRequestContext;

class InteractionsListTool extends AbstractMcpTool
{
    public function __construct(private readonly InteractionReportService $service)
    {
    }

    public function name(): string
    {
        return 'interactions.list';
    }

    public function description(): string
    {
        return 'Logged touchpoints with a student, parent or staff member — calls, meetings, notes '
            . 'and follow-ups — as staff recorded them, filtered by who it is about or its status. '
            . 'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'related_type' => ['type' => 'string', 'enum' => ['student', 'parent', 'staff', 'visitor']],
                'related_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => ['type' => 'string', 'enum' => ['open', 'closed']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->list($context, $arguments);
    }
}
