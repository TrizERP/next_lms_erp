<?php

namespace App\Services\Mcp;

class ToolResult
{
    public static function success(string $tool, string $message, array $data = [], array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'tool' => $tool,
            'message' => $message,
            'data' => $data,
            'error' => null,
            'conversationPatch' => [],
            'uiAction' => null,
            'requiresConfirmation' => false,
            'permission' => ['allowed' => true],
        ], $extra);
    }

    public static function failure(string $tool, string $message, string $code, array $details = [], array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'tool' => $tool,
            'message' => $message,
            'data' => null,
            'error' => [
                'code' => $code,
                'details' => $details,
            ],
            'conversationPatch' => [],
            'uiAction' => null,
            'requiresConfirmation' => false,
            'permission' => ['allowed' => true],
        ], $extra);
    }
}
