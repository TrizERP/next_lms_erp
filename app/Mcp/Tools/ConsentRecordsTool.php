<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\ConsentService;
use App\Services\Mcp\McpRequestContext;

/**
 * Consents raised for students, with the decision state kept in three states.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class ConsentRecordsTool extends AbstractMcpTool
{
    public function __construct(private readonly ConsentService $service)
    {
    }

    public function name(): string
    {
        return 'consent.records';
    }

    public function description(): string
    {
        return 'Consents recorded for this institute and academic year, with the student, class, title, date, '
            .'accountability, amount and decision state. An empty status means NO DECISION HAS BEEN RECORDED '
            .'— it is never a refusal. No expiry date and no reminder history exist on this table, so no '
            .'consent can be described as expiring or chased. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'decision' => ['type' => 'string', 'enum' => ['any', 'awaiting', 'recorded'], 'default' => 'any'],
                'accountable_status' => ['type' => 'string', 'description' => 'As the office stores it, e.g. Accountable.'],
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
        return ['risk' => 'read', 'required_permission' => 'consent.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->records($context, $arguments);
    }
}
