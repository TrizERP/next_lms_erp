<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AcademicStructureService;
use App\Services\Mcp\McpRequestContext;

class AcademicsSubjectsTool extends AbstractMcpTool
{
    public function __construct(private readonly AcademicStructureService $service)
    {
    }

    protected function name(): string
    {
        return 'academics.subjects';
    }

    protected function description(): string
    {
        return 'List the subjects taught at this institute, optionally matching a search term. '
            . 'Use it to resolve a subject named in a question into the id other tools need.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Match on subject name, code or short name.'],
                'active_only' => ['type' => 'boolean', 'default' => false],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'academics.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->subjects($context, $arguments);
    }
}
