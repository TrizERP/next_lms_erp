<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\LmsActivityService;
use App\Services\Mcp\McpRequestContext;

class LmsActivitiesTool extends AbstractMcpTool
{
    public function __construct(private readonly LmsActivityService $service)
    {
    }

    protected function name(): string
    {
        return 'lms.activities';
    }

    protected function description(): string
    {
        return 'What is on for a class: virtual classroom sessions and homework, on one timeline '
            . 'across a window around today. Answers "what is happening this week" and "what is due". '
            . 'Scoped to a class rather than an individual, because these records are stored per '
            . 'standard. Meeting join links are never returned.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Resolves the class from this student\'s current enrolment, and narrows homework to them.',
                ],
                'days_ahead' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 90, 'default' => 7],
                'days_back' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 90, 'default' => 7],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'lms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->timeline($context, $arguments);
    }
}
