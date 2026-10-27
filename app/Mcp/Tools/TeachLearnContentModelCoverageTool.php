<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\PAL\ContentModel\ContentModelCoverageService;

/**
 * Analyzes content model coverage across institute chapters for the Teach/Learn module.
 *
 * Identifies covered and missing content mappings (concept learning, practice ladder,
 * misconceptions, assessment bank) to support curriculum and content planning.
 * Grounded on the institute's real semantic intelligence and chapter records.
 * Read-only; changes nothing.
 */
class TeachLearnContentModelCoverageTool extends AbstractMcpTool
{
    public function __construct(private readonly ContentModelCoverageService $service)
    {
    }

    public function name(): string
    {
        return 'teach_learn.content_model_coverage';
    }

    public function description(): string
    {
        return 'Analyzes content model coverage across institute chapters, identifying covered '
            . 'and missing content mappings (concept learning, practice ladder, misconceptions, assessment bank) '
            . 'to support curriculum and content planning. Scoped to the caller\'s institute. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard' => [
                    'type' => 'integer',
                    'description' => 'Standard or Grade number to filter chapters, e.g. 6 or 8.',
                ],
                'standard_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Standard master id to restrict chapters.',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'Subject name, e.g. "Science" or "Hindi-A".',
                ],
                'subject_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Subject master id to restrict chapters.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 200,
                    'default' => 50,
                    'description' => 'Maximum number of chapter coverage records to return.',
                ],
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

        return $this->service->coverageAnalysis($context->selectedInstituteId, $arguments);
    }
}
