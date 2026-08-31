<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\HydratesLegacyApiSession;
use Closure;
use Illuminate\Http\Request;

class SessionMiddleware
{
    use HydratesLegacyApiSession;

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $type = $request->input("type");

        if ($type === "API" || $type === "JSON") {
            // The Next.js frontend (and mobile apps) authenticate these calls
            // with a bearer JWT instead of a browser session cookie. Validate
            // it and hydrate the session from its verified claims before
            // continuing, so checkPermission and every session()-reading
            // controller see a real, tenant-correct identity instead of an
            // empty one. Previously this branch let any type=API request
            // through unauthenticated - see HydratesLegacyApiSession.
            if ($error = $this->hydrateSessionFromToken($request)) {
                return $error;
            }

            return $next($request);
        }

        $user_id = $request->session()->get('user_id');
        if (empty($user_id)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect(route('home'));
        }

        return $next($request);
    }
}
