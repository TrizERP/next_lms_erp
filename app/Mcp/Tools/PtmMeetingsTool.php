<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PtmMeetingService;

/**
 * The PTM schedule, with take-up per meeting.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class PtmMeetingsTool extends AbstractMcpTool
{
    public function __construct(private readonly PtmMeetingService $service)
    {
    }

    public function name(): string
    {
        return 'ptm.meetings';
    }

    public function description(): string
    {
        return 'Parent-teacher meetings scheduled for this institute and academic year, with the class '
            .'each was opened for and how many families booked, attended, did not attend, or have no '
            .'attendance recorded yet. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'title' => ['type' => 'string', 'description' => 'Match part of the meeting title.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'ptm.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->meetings($context, $arguments);
    }
}
