<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\MobileAppService;

/**
 * The sections each mobile app is built from, and how full each one is.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class MobileAppsSectionsTool extends AbstractMcpTool
{
    public function __construct(private readonly MobileAppService $service)
    {
    }

    public function name(): string
    {
        return 'mobile_apps.sections';
    }

    public function description(): string
    {
        return 'The sections each mobile app is built from, with how many tiles sit under each and how many '
            .'are switched on. Reported per app, because the parent app and the teacher app are configured '
            .'separately. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'mobile_apps.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->sections($context, $arguments);
    }
}
