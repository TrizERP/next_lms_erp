<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AiReportGenerator;
use App\Services\Mcp\McpRequestContext;

/**
 * Generate a report from live records and save it to the template library.
 *
 * The one tool in the AI-template set that writes. `ai.templates.list` and
 * `ai.templates.render` read the library; this one adds to it, filing the result under
 * the same "AI" category so it appears beside the school's other documents in
 * Settings -> Templates rather than in a place only the assistant knows about.
 *
 * Annotated `write` rather than `read` deliberately: it creates a row a person will
 * later print and send, and the risk annotation is what the governance layer reads to
 * decide whether a caller may reach it at all.
 */
class AiTemplatesGenerateTool extends AbstractMcpTool
{
    public function __construct(private readonly AiReportGenerator $generator)
    {
    }

    public function name(): string
    {
        return 'ai.templates.generate';
    }

    public function description(): string
    {
        return 'Generate a report from live fees, admissions or attendance records and '
            . 'save it to the template library under the AI category, returning a link to it.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'enum' => AiReportGenerator::SUPPORTED,
                    'description' => 'Which module the report is about.',
                ],
                'question' => [
                    'type' => 'string',
                    'description' => 'The question that asked for the report, recorded on the document '
                        . 'so a reader months later knows what it was built to answer.',
                ],
            ],
            'required' => ['module'],
            // Open, because the remaining arguments are the filters the underlying
            // service already understands — a standard, a date range — and restating
            // each module's filter vocabulary here would be a second copy to drift.
            'additionalProperties' => true,
        ];
    }

    protected function toolAnnotations(): array
    {
        return [
            'risk' => 'write',
            'required_permission' => 'template.write',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->generator->generate($context, $arguments);
    }
}
