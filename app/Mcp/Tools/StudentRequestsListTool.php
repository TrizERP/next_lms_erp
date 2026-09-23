<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StudentRequestService;

/**
 * The student change requests on file, with their status.
 *
 * Reads `student_change_request` only. It names the child a request is about so the row is
 * usable, and reads no academic, attendance, fee or medical field - a question about how a
 * student is doing belongs to the Student module and its own rights.
 */
class StudentRequestsListTool extends AbstractMcpTool
{
    public function __construct(private readonly StudentRequestService $service)
    {
    }

    public function name(): string
    {
        return 'student_requests.list';
    }

    public function description(): string
    {
        return 'Student change requests for this institute and academic year: the request type, the '
            .'reason and description given, whether proof was required and supplied, the student and '
            .'class it concerns, and whether it is pending, approved or rejected. Reports the whole '
            .'queue size and a breakdown by status beside the rows it returns. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['Pending', 'Approved', 'Rejected'],
                    'description' => 'Omit for every status.',
                ],
                'only_pending' => [
                    'type' => 'boolean',
                    'description' => 'Undecided requests, including rows written before the status column existed.',
                ],
                'request_type_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'search_text' => [
                    'type' => 'string',
                    'description' => 'Match the request title or reason, or the student name or enrolment number.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student_request.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->list($context, $arguments);
    }
}
