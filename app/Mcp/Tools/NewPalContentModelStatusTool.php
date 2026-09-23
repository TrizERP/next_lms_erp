<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\NewPalReportService;

class NewPalContentModelStatusTool extends AbstractMcpTool
{
    public function __construct(private readonly NewPalReportService $service)
    {
    }

    public function name(): string
    {
        return 'new_pal.content_model_status';
    }

    public function description(): string
    {
        return 'Institute-wide coverage of the content model\'s four types (concept learning, '
            . 'practice, misconceptions, application) across chapters. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'lms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->contentModelStatus($context, $arguments);
    }
}
