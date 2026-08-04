<?php

namespace App\Mcp;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Auth\Access\AuthorizationException;

abstract class AbstractMcpTool implements McpToolInterface
{
    abstract protected function name(): string;

    abstract protected function description(): string;

    abstract protected function inputSchema(): array;

    protected function annotations(): array
    {
        return [];
    }

    protected function allowedRoles(): array
    {
        return ['admin', 'staff'];
    }

    protected function isReadOnly(): bool
    {
        return true;
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'input_schema' => $this->inputSchema(),
            'annotations' => array_merge(
                [
                    'read_only' => $this->isReadOnly(),
                    'allowed_roles' => $this->allowedRoles(),
                    'requires_confirmation' => $this instanceof ConfirmableMcpToolInterface,
                ],
                $this->annotations()
            ),
        ];
    }

    protected function authorize(McpRequestContext $context): void
    {
        if (! in_array($context->role, $this->allowedRoles(), true)) {
            throw new AuthorizationException('You do not have permission to use this MCP tool.');
        }
    }
}
