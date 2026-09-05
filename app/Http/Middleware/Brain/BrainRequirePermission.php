<?php

namespace App\Http\Middleware\Brain;

use App\Brain\Authorization\Permission;
use App\Brain\Authorization\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\Schema;

class BrainRequirePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $permission = Permission::tryFrom($permission);
        $role = Role::tryFromName((string) $request->attributes->get('auth.role'));

        if (! $permission || ! $role || ! Role::grants($role, $permission)) {
            $this->auditDenied($request, (string) $permission);
            return response()->json(['error' => 'brain_forbidden'], 403);
        }

        return $next($request);
    }

    private function auditDenied(Request $request, string $permission): void
    {
        try {
            if (! SchemaCache::hasTable('hpbrain_audit_logs')) {
                return;
            }

            DB::table('hpbrain_audit_logs')->insert([
                'id' => $this->uuid(),
                'tenant_id' => (string) $request->attributes->get('tenantId', ''),
                'actor_id' => (string) $request->attributes->get('auth.userId', ''),
                'actor_name' => (string) $request->attributes->get('auth.role', ''),
                'action' => 'permission.denied',
                'entity_type' => 'BrainRoute',
                'entity_id' => $permission,
                'changes' => json_encode(['path' => $request->path(), 'method' => $request->method()]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'source' => 'lms',
                'status' => 'denied',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Authorization must fail closed even if auditing is unavailable.
        }
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
