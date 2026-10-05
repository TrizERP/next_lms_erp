<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FeesPositionService;
use App\Services\Mcp\McpRequestContext;

/**
 * Students who owe fees, ranked by how much — across the school, one class, or a named set.
 *
 * `student_ids` is what makes a conversation possible. "How much does each of them owe?"
 * after a list of defaulters is answered by asking this tool about exactly those students,
 * so the follow-up is read from the ledger again rather than recalled from the last answer.
 */
class FeesOutstandingAccountsTool extends AbstractMcpTool
{
    public function __construct(private readonly FeesPositionService $service)
    {
    }

    public function name(): string
    {
        return 'fees.outstanding_accounts';
    }

    public function description(): string
    {
        return 'List students who owe fees, largest balance first, with their class, enrolment number, '
            . 'amount outstanding and failed-payment count. Covers the whole school, so "who owes the '
            . 'most?" is a true ranking. Filter to one class with standard_id, or to specific students '
            . 'with student_ids (use this to follow up on students already listed). Set '
            . 'include_due_months to also get each student\'s oldest unpaid fee month and how many '
            . 'months are overdue - needed for "who has been unpaid longest?" and "who is overdue?".';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'description' => 'Restrict to one class.'],
                'student_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'maxItems' => 50,
                    'description' => 'Restrict to these students, for follow-ups on students already listed.',
                ],
                'include_due_months' => [
                    'type' => 'boolean',
                    'description' => 'Also return the oldest unpaid month and overdue months for up to 15 students.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                    'description' => 'How many students to list. Defaults to 10.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return [
            'risk' => 'read',
            'required_permission' => 'fees.collect',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->accounts($context, $arguments);
    }
}
