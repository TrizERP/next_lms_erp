<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TimetableService;

/**
 * Teachers booked into two different classes in the same period on the same day.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TimetableConflictsTool extends AbstractMcpTool
{
    public function __construct(private readonly TimetableService $service)
    {
    }

    public function name(): string
    {
        return 'timetable.conflicts';
    }

    public function description(): string
    {
        return 'Teachers booked into two or more different classes in the same period on the same weekday. '
            .'Two rows for the same class are a merged or split session, not a clash. The table records no '
            .'room and no teacher availability, so nothing else about a timetable is judged. Changes '
            .'nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'teacher_id' => ['type' => 'integer', 'minimum' => 1],
                'week_day' => ['type' => 'string', 'description' => 'A weekday number 1-7 or its name.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'timetable.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->conflicts($context, $arguments);
    }
}
