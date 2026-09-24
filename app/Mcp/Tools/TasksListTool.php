<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TaskService;

/**
 * Tasks with their dates, assignees and status, normalised across both spellings of complete.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TasksListTool extends AbstractMcpTool
{
    public function __construct(private readonly TaskService $service)
    {
    }

    public function name(): string
    {
        return 'tasks.list';
    }

    public function description(): string
    {
        return 'Tasks recorded for this institute, with the title, date, assignee, allocator and status. The '
            .'status column holds TWO SPELLINGS of the same state — COMPLETE and COMPLETED both mean '
            .'finished — so every count here is made on the normalised value and the raw spellings are '
            .'reported beside it. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'state' => ['type' => 'string', 'enum' => ['any', 'open', 'complete', 'overdue'], 'default' => 'any'],
                'assigned_to' => ['type' => 'integer', 'minimum' => 1],
                'allocated_by' => ['type' => 'integer', 'minimum' => 1],
                'task_type' => ['type' => 'string'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'task.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->list($context, $arguments);
    }
}
