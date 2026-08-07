<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Throwable;

class McpAuditService
{
    public function log(array $payload): void
    {
        try {
            DB::table('mcp_audit_logs')->insert([
                'request_id' => $payload['request_id'] ?? null,
                'endpoint' => $payload['endpoint'] ?? null,
                'tool_name' => $payload['tool_name'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'sub_institute_id' => $payload['sub_institute_id'] ?? null,
                'status_code' => $payload['status_code'] ?? null,
                'outcome' => $payload['outcome'] ?? null,
                'input_payload' => isset($payload['input_payload']) ? json_encode($this->sanitize($payload['input_payload'])) : null,
                'response_payload' => isset($payload['response_payload']) ? json_encode($this->sanitize($payload['response_payload'])) : null,
                'error_code' => $payload['error_code'] ?? null,
                'error_message' => $payload['error_message'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit logging must never break business execution.
        }
    }

    private function sanitize(mixed $value): mixed
    {
        $sensitiveKeys = ['password', 'token', 'access_token', 'refresh_token', 'pan_card', 'aadhar', 'adharnumber'];

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                    $sanitized[$key] = '[REDACTED]';
                    continue;
                }

                $sanitized[$key] = $this->sanitize($item);
            }

            return $sanitized;
        }

        if (is_string($value) && strlen($value) > 4000) {
            return substr($value, 0, 4000) . '...';
        }

        return $value;
    }
}
