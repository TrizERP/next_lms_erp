<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FeesCollectionReportService;
use App\Services\Mcp\McpRequestContext;

class FeesCollectionReportTool extends AbstractMcpTool
{
    public function __construct(private readonly FeesCollectionReportService $service)
    {
    }

    public function name(): string
    {
        return 'fees.collection_report';
    }

    public function description(): string
    {
        return 'Fees COLLECTED (money received) for a date or date range, from the receipts, with the total, the '
            .'count and a payment-mode split. Pass the person\'s own words in `period` — "yesterday", "today", '
            .'"last 7 days", "this month", "13 October 2026" — and the server resolves them against today\'s '
            .'date; do not work out dates yourself. Only receipts dated inside the period are counted, '
            .'whatever academic year they belong to, and a period with none says so. With no period it is '
            .'today. Not for fees still owed (use fees.arrears or fees.outstanding_accounts). Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'description' => 'The period in the person\'s own words, for example "yesterday", "today", "last 7 days", '
                        .'"last week", "this month", "13 October 2026", "from 1 October to 7 October".',
                ],
                'date' => ['type' => 'string', 'description' => 'One day: YYYY-MM-DD, or "today" / "yesterday".'],
                'from_date' => ['type' => 'string', 'format' => 'date'],
                'to_date' => ['type' => 'string', 'format' => 'date'],
                'student' => [
                    'type' => 'string',
                    'description' => 'One student, by name or enrolment number, when the question is about what a particular '
                        .'student paid. Every word of the name must appear in the student\'s full name. A name that matches '
                        .'nobody, or several students, is reported as that — never guessed.',
                ],
                'student_id' => ['type' => 'integer'],
                'payment_mode' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->report($context, $arguments);
    }
}
