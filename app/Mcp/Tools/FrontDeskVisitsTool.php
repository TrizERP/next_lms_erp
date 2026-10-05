<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FrontDeskService;
use App\Services\Mcp\McpRequestContext;

/**
 * The front desk register: who came in about which student, and who they came to meet.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class FrontDeskVisitsTool extends AbstractMcpTool
{
    public function __construct(private readonly FrontDeskService $service)
    {
    }

    public function name(): string
    {
        return 'front_desk.visits';
    }

    public function description(): string
    {
        return 'The front desk register for this institute: one row per person coming in to meet a member of '
            .'staff about a student, with the entry and exit times recorded. A non-admin caller sees only the '
            .'rows where they are the person being met, which is the rule the front desk screen applies. THIS '
            .'IS NOT THE SCHOOL\'S WHOLE VISITOR LOG — the Visitor Management module keeps a separate and '
            .'much larger one — so an empty result never means nobody visited. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'staff_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The person being met.'],
                'visitor_type' => ['type' => 'string'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'front_desk.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->visits($context, $arguments);
    }
}
