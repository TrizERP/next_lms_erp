<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PeopleDirectoryService;

/**
 * Departments and their headcount.
 *
 * The description below says plainly what this tool does *not* hold, and that is
 * deliberate. A planner asked "which department needs the most training?" will reach for
 * whatever department tool exists, and the only number here is headcount — which is not
 * a capability judgement. Saying so in the description is what lets the plan refuse
 * instead of dressing a staff count up as an answer.
 */
class HrDepartmentsTool extends AbstractMcpTool
{
    public function __construct(private readonly PeopleDirectoryService $service)
    {
    }

    protected function name(): string
    {
        return 'hr.departments';
    }

    protected function description(): string
    {
        return 'List the departments at this institute, each with its head and how many active '
            . 'staff are assigned to it. Reports headcount only — it holds no training, competency, '
            . 'appraisal or performance data, so it cannot rank departments by capability or need.';
    }

    protected function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'hrms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->departments($context);
    }
}
