<?php

namespace App\Mcp;

use App\Services\Mcp\McpRequestContext;

interface McpToolInterface
{
    public function definition(): array;

    public function execute(array $arguments, McpRequestContext $context): array;
}
