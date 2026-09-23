<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PtmMeetingService;

/**
 * Individual PTM bookings, with the family, the slot and what a teacher recorded.
 *
 * `attended` filters on the three real states rather than on a nullable column, so
 * "nobody has written it down yet" is askable and is never returned as an absence.
 */
class PtmBookingsTool extends AbstractMcpTool
{
    public function __construct(private readonly PtmMeetingService $service)
    {
    }

    public function name(): string
    {
        return 'ptm.bookings';
    }

    public function description(): string
    {
        return 'Parent-teacher meeting bookings for this institute and academic year: which student and '
            .'family, which slot, the confirmation given, and the attendance a teacher recorded. A '
            .'booking with no attendance saved is reported as not recorded, never as an absence. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slot_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'attended' => [
                    'type' => 'string',
                    'enum' => ['Yes', 'No', 'not_recorded'],
                    'description' => 'Filter by what the register says. Omit for all three.',
                ],
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

        return $this->service->bookings($context, $arguments);
    }
}
