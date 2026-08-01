<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\Exceptions\NoTokenException;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;

class McpAuth
{
    use GetsJwtToken;

    public function handle(Request $request, Closure $next)
    {
        try {
            $jwt = $this->jwtToken($request);
        } catch (NoTokenException) {
            return $this->deny('MCP authentication token is required.', 401);
        }

        try {
            if (! $jwt->validate()) {
                return $this->deny('MCP authentication token is invalid or expired.', 401);
            }

            $payload = $jwt->payload();
        } catch (\Throwable) {
            return $this->deny('MCP authentication failed.', 401);
        }

        $userId = (int) ($payload['id'] ?? 0);
        $isAdmin = (int) ($payload['is_admin'] ?? 0);
        $isStudent = ! empty($payload['is_student']);

        if ($userId <= 0) {
            return $this->deny('MCP authentication token payload is malformed.', 401);
        }

        $request->attributes->set('mcp_auth', [
            'user_id' => $userId,
            'sub_institute_id' => (string) ($payload['sub_institute_id'] ?? ''),
            'is_admin' => $isAdmin,
            'is_student' => $isStudent,
            'user_profile_id' => $payload['user_profile_id'] ?? null,
            'client_id' => $payload['client_id'] ?? null,
            'role' => $isStudent ? 'student' : ($isAdmin >= 1 ? 'admin' : 'staff'),
        ]);

        return $next($request);
    }

    private function deny(string $message, int $status)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => null,
        ], $status);
    }
}
