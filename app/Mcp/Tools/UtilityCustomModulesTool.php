<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\UtilityService;

/**
 * Custom modules defined from the Utility screens, with the columns each defines.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class UtilityCustomModulesTool extends AbstractMcpTool
{
    public function __construct(private readonly UtilityService $service)
    {
    }

    public function name(): string
    {
        return 'utility.custom_modules';
    }

    public function description(): string
    {
        return 'Tables somebody has defined from this institute\'s custom-module screen, with the columns on '
            .'each. These are DEFINITIONS, not data: nothing says how many records the resulting table holds '
            .'or whether anybody uses it. Note that the Utility module in this ERP is bulk data operations — '
            .'rollover, student transfer, custom modules — and NOT electricity, water, gas or utility bills, '
            .'none of which this estate records anywhere. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module_type' => ['type' => 'string', 'description' => 'As stored, e.g. MASTER.'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'utility.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->customModules($context, $arguments);
    }
}
