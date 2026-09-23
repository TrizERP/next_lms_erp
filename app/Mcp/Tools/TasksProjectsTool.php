<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TaskService;

/**
 * The project and workstream structure layered beside the task list.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TasksProjectsTool extends AbstractMcpTool
{
    public function __construct(private readonly TaskService $service)
    {
    }

    public function name(): string
    {
        return 'tasks.projects';
    }

    public function description(): string
    {
        return 'Projects and their workstreams for this institute. This is a newer structure layered beside the '
            .'task list and holds single-digit row counts across the whole estate; only a handful of tasks are '
            .'mapped to a project, so a project never accounts for the school\'s work. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->projects($context, $arguments);
    }
}
