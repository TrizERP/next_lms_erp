<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\InitializeRequest;

class InitializeController extends McpController
{
    public function __invoke(InitializeRequest $request)
    {
        return $this->success($request, 'MCP server initialized successfully.', [
            'server' => [
                'name' => config('mcp.server.name'),
                'version' => config('mcp.server.version'),
                'protocol_version' => config('mcp.server.protocol_version'),
            ],
            'capabilities' => [
                'tools' => true,
                'confirmation_flow' => true,
            ],
            'context' => $request->attributes->get('mcp_context')?->toArray(),
        ]);
    }
}
