<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\CircularService;
use App\Services\Mcp\McpRequestContext;

/**
 * The circulars this institute has published.
 *
 * One row is one circular addressed to one class, which is how the table stores it. Both
 * totals are returned and both are labelled - see `CircularService` for why a single
 * number would turn one notice to six classes into six notices.
 */
class CircularsListTool extends AbstractMcpTool
{
    public function __construct(private readonly CircularService $service)
    {
    }

    public function name(): string
    {
        return 'circulars.list';
    }

    public function description(): string
    {
        return 'Circulars published by this institute in this academic year, with the title, message, '
            .'type, date, attachment and the class each was addressed to. One row is one circular to '
            .'one class, so both the row count and the count of distinct circulars are reported. The '
            .'record holds no read receipt, so no readership figure is available. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'type_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'From circulars.types.'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'search_text' => ['type' => 'string', 'description' => 'Match the title or the message.'],
                'with_attachment' => ['type' => 'boolean', 'description' => 'Only circulars carrying a file.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'circular.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->list($context, $arguments);
    }
}
