<?php

namespace App\Services\Mcp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McpConfirmationService
{
    public function create(string $toolName, array $arguments, McpRequestContext $context, array $preview): array
    {
        $token = (string) Str::uuid();
        $expiresAt = now()->addMinutes((int) config('mcp.confirmation.ttl_minutes', 10));

        DB::table('mcp_confirmation_requests')->insert([
            'token' => $token,
            'tool_name' => $toolName,
            'user_id' => $context->userId,
            'sub_institute_id' => $context->selectedInstituteId,
            'arguments_json' => json_encode($arguments),
            'preview_json' => json_encode($preview),
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function consume(string $token, string $toolName, McpRequestContext $context): array
    {
        $record = DB::table('mcp_confirmation_requests')
            ->where('token', $token)
            ->where('tool_name', $toolName)
            ->where('user_id', $context->userId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'confirmation_token' => ['Confirmation token is invalid.'],
            ]);
        }

        if ($record->status !== 'pending') {
            throw ValidationException::withMessages([
                'confirmation_token' => ['Confirmation token has already been used.'],
            ]);
        }

        if (now()->greaterThan(Carbon::parse($record->expires_at))) {
            DB::table('mcp_confirmation_requests')
                ->where('token', $token)
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

            throw ValidationException::withMessages([
                'confirmation_token' => ['Confirmation token has expired.'],
            ]);
        }

        DB::table('mcp_confirmation_requests')
            ->where('token', $token)
            ->update([
                'status' => 'consumed',
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'token' => $record->token,
            'arguments' => json_decode($record->arguments_json, true) ?: [],
            'preview' => json_decode($record->preview_json, true) ?: [],
        ];
    }
}
