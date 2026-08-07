<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Requests\Mcp\CallToolRequest;
use App\Mcp\ToolRegistry;
use Throwable;

class ToolsCallController extends McpController
{
    public function __construct(private readonly ToolRegistry $registry)
    {
    }

    public function __invoke(CallToolRequest $request)
    {
        $toolName = (string) $request->input('tool');

        try {
            $result = $this->registry->execute(
                $toolName,
                $request->input('arguments', []),
                $request->attributes->get('mcp_context'),
                $request->input('confirmation_token')
            );

            return $this->success($request, 'MCP tool executed successfully.', [
                'tool' => $toolName,
                'result' => $result,
            ]);
        } catch (Throwable $exception) {
            return $this->handleFailure($request, $exception, $toolName);
        }
    }
}
