<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentIcardService;

/**
 * The students an identity card can be printed for, with only the card's own fields.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class StudentIcardRosterTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentIcardService $service)
    {
    }

    public function name(): string
    {
        return 'student_icard.roster';
    }

    public function description(): string
    {
        return 'The students an identity card can be printed for in this institute and academic year, with '
            .'only the fields a card prints: name, roll and enrolment number, class, photo, blood group '
            .'and the bus and stop where transport is mapped. Reports which cards are missing a photo, '
            .'roll number or class. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'grade_id' => ['type' => 'integer', 'minimum' => 1],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'search_text' => ['type' => 'string', 'description' => 'Match a name or enrolment number.'],
                'with_transport' => ['type' => 'boolean', 'description' => 'Only students with a bus mapped.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student_icard.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->roster($context, $arguments);
    }
}
