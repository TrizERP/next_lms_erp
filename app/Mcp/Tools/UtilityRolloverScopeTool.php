<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\UtilityService;

/**
 * What a rollover or student transfer would operate on: the years with enrolments and the sibling institutes.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class UtilityRolloverScopeTool extends AbstractMcpTool
{
    public function __construct(private readonly UtilityService $service)
    {
    }

    public function name(): string
    {
        return 'utility.rollover_scope';
    }

    public function description(): string
    {
        return 'The academic years this institute has enrolments recorded against, and the institutes of the '
            .'same client a student transfer could target. This describes what an operation WOULD act on and '
            .'nothing about what has been done: THIS MODULE RECORDS NO OPERATION HISTORY — no rollover log, '
            .'no transfer log, no bulk-update audit exists anywhere — so nothing may state that a rollover '
            .'has been run, when, or how many students moved. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
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

        return $this->service->rolloverScope($context, $arguments);
    }
}
