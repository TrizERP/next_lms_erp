<?php

namespace App\Mcp;

use App\Services\Mcp\McpRequestContext;

/**
 * What a tool owes this application on top of being a Laravel MCP tool.
 *
 * The MCP protocol has no notion of an institute-scoped request context, so
 * Laravel\Mcp\Server\Tool cannot express `execute()`. This interface is that gap and
 * nothing more — every implementation extends AbstractMcpTool, which is where the
 * package's own base class comes in.
 *
 * @see AbstractMcpTool
 */
interface McpToolInterface
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, McpRequestContext $context): array;
}
