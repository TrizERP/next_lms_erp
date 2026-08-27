<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\HydratesLegacyApiSession;
use Closure;
use Illuminate\Http\Request;

/**
 * Hydrates the (in-memory) session from the JWT payload so that the existing
 * Result module controllers/models/helpers - which read session values set at
 * web login (loginController) - work unchanged when called through the REST API.
 *
 * Authentication mechanism is the SAME JWT already used by the ERP mobile/API
 * login (App\Http\Controllers\api\ApiLoginController -> user_token).
 *
 * NOTE: the session store is never started/saved here, so nothing is persisted;
 * values only live for the duration of the API request (stateless).
 */
class ApiSessionHydrator
{
    use HydratesLegacyApiSession;

    public function handle(Request $request, Closure $next)
    {
        if ($error = $this->hydrateSessionFromToken($request)) {
            return $error;
        }

        return $next($request);
    }
}
