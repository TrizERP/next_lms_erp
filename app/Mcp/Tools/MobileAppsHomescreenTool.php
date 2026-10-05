<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\MobileAppService;

/**
 * The tiles configured on a mobile app home screen, per user profile.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class MobileAppsHomescreenTool extends AbstractMcpTool
{
    public function __construct(private readonly MobileAppService $service)
    {
    }

    public function name(): string
    {
        return 'mobile_apps.homescreen';
    }

    public function description(): string
    {
        return 'The tiles configured on the mobile app home screen for this institute, per user profile: the '
            .'section each sits under, the label, the screen it opens, the API it calls and whether it is '
            .'switched on. Configuration, not usage - this estate records no session, device or login. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'app' => ['type' => 'string', 'enum' => ['parent', 'teacher'], 'description' => 'Which app. Defaults to the parent and student app.'],
                'user_profile_name' => ['type' => 'string'],
                'section' => ['type' => 'string', 'description' => 'Match part of the section heading.'],
                'status' => ['type' => 'string', 'enum' => ['on', 'off'], 'description' => 'Omit for both.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->homescreen($context, $arguments);
    }
}
