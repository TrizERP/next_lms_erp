<?php

namespace App\Mcp;

use App\Services\Mcp\McpRequestContext;

interface ConfirmableMcpToolInterface extends McpToolInterface
{
    public function preview(array $arguments, McpRequestContext $context): array;

    public function executeConfirmed(array $arguments, McpRequestContext $context, array $confirmation): array;
}
