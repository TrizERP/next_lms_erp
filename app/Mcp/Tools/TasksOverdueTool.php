<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TaskService;

/**
 * Tasks past their date and not complete, oldest first.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TasksOverdueTool extends AbstractMcpTool
{
    public function __construct(private readonly TaskService $service)
    {
    }

    public function name(): string
    {
        return 'tasks.overdue';
    }

    public function description(): string
    {
        return 'Tasks for this institute whose date has passed and whose status is not one of the completed '
            .'spellings, oldest first, with how many have no assignee recorded. A task with NO date is undated '
            .'rather than overdue and is excluded. Nothing records why a task is late or whether its date was '
            .'ever agreed. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'assigned_to' => ['type' => 'integer', 'minimum' => 1],
                'allocated_by' => ['type' => 'integer', 'minimum' => 1],
                'task_type' => ['type' => 'string'],
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

        return $this->service->overdue($context, $arguments);
    }
}
