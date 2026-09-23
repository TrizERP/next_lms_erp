<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TimetableService;

/**
 * The published timetable: one row per period of one class on one weekday.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TimetableScheduleTool extends AbstractMcpTool
{
    public function __construct(private readonly TimetableService $service)
    {
    }

    public function name(): string
    {
        return 'timetable.schedule';
    }

    public function description(): string
    {
        return 'The published timetable for this institute and academic year: one row per period of one '
            .'class on one weekday, with the period times, subject, teacher and class. Drafts still being '
            .'arranged are deliberately excluded. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'teacher_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
                'period_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->schedule($context, $arguments);
    }
}
