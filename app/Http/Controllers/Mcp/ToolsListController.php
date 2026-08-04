<?php

namespace App\Http\Controllers\Mcp;

use App\Mcp\ToolRegistry;
use Illuminate\Http\Request;

class ToolsListController extends McpController
{
    public function __construct(private readonly ToolRegistry $registry)
    {
    }

    public function __invoke(Request $request)
    {
        return $this->success($request, 'MCP tools listed successfully.', [
            'tools' => $this->registry->definitions(),
        ]);
    }
}
