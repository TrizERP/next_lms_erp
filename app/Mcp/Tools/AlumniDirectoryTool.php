<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AlumniOverviewService;
use App\Services\Mcp\McpRequestContext;

/**
 * Alumni of this institute with name, batch, passing year, status, employment status, employer and job title. Email, mobile, address, date of birth and photograph are never returned.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class AlumniDirectoryTool extends AbstractMcpTool
{
    public function __construct(private readonly AlumniOverviewService $service)
    {
    }

    public function name(): string
    {
        return 'alumni.directory';
    }

    public function description(): string
    {
        return 'Alumni of this institute with name, batch, passing year, status, employment status, employer and job title. Email, mobile, address, date of birth and photograph are never returned. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string'],
                'employment_status' => ['type' => 'string'],
                'batch' => ['type' => 'string'],
                'passing_year' => ['type' => 'integer', 'minimum' => 1950, 'maximum' => 2100],
                'search_text' => ['type' => 'string', 'description' => 'Matches name, employer or job title.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'alumni.read'];
    }

    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        return ['admin'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->directory($context, $arguments);
    }
}
