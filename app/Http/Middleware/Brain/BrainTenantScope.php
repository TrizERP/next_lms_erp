<?php

namespace App\Http\Middleware\Brain;

use Closure;
use Illuminate\Http\Request;

class BrainTenantScope
{
    public function handle(Request $request, Closure $next)
    {
        $tokenTenant = (string) $request->attributes->get('auth.tenantId', '');
        $routeTenant = (string) ($request->route('tenantId') ?? '');

        if ($tokenTenant === '') {
            return response()->json(['error' => 'brain_tenant_missing'], 401);
        }

        if ($routeTenant !== '' && $routeTenant !== $tokenTenant) {
            return response()->json(['error' => 'brain_tenant_mismatch'], 403);
        }

        $request->attributes->set('tenantId', $tokenTenant);

        return $next($request);
    }
}
