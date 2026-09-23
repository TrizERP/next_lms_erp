<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\LibraryService;
use App\Services\Mcp\McpRequestContext;

/**
 * Library loans, with the ones still out and the ones overdue.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class LibraryCirculationTool extends AbstractMcpTool
{
    public function __construct(private readonly LibraryService $service)
    {
    }

    public function name(): string
    {
        return 'library.circulation';
    }

    public function description(): string
    {
        return 'Loans recorded in this institute\'s library, with the title, borrower, issue date, due date and '
            .'return date. A loan is OUT when no return date is recorded and OVERDUE when the due date has '
            .'also passed — both exact derivations from recorded columns. A loan with no due date is '
            .'undated, not overdue. No fine or penalty is recorded, so nothing may state what a borrower owes. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'state' => ['type' => 'string', 'enum' => ['any', 'out', 'overdue', 'returned'], 'default' => 'any'],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'book_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'library.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->circulation($context, $arguments);
    }
}
