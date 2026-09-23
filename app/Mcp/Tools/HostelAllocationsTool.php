<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\HostelOccupancyService;
use App\Services\Mcp\McpRequestContext;

/**
 * Who is allocated to which hostel room this academic year.
 *
 * An occupant may be a student or a member of staff - the estate allocates rooms to both -
 * and each row says which it is rather than assuming.
 */
class HostelAllocationsTool extends AbstractMcpTool
{
    public function __construct(private readonly HostelOccupancyService $service)
    {
    }

    public function name(): string
    {
        return 'hostel.allocations';
    }

    public function description(): string
    {
        return 'Hostel room allocations for this institute and academic year: the occupant, the hostel, '
            .'building, floor and room, the bed, locker, table and bedsheet numbers recorded, and the '
            .'admission category. Occupants may be students or staff and each row says which. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hostel_id' => ['type' => 'integer', 'minimum' => 1],
                'room_id' => ['type' => 'integer', 'minimum' => 1],
                'admission_category_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'The occupant id. For a student allocation this is the student id.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'hostel.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->allocations($context, $arguments);
    }
}
