<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class McpHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'MCP server is healthy.',
            'data' => [
                'server' => config('mcp.server.name'),
                'version' => config('mcp.server.version'),
                'protocol_version' => config('mcp.server.protocol_version'),
            ],
            'errors' => null,
        ]);
    }
}
