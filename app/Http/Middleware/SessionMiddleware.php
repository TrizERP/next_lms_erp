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
            if (\App\Support\TenantDeleteGuard::crossesTenant($request)) {
                return response()->json(['message' => 'This record belongs to another school.'], 403);
            }

            // About 90 controllers read the school from the request when type=API. The verified
            // token already named the school, so make the request say the same thing. Platform
            // Super Admins legitimately act across schools and are left alone.
            $tenant = (int) session()->get('sub_institute_id');
            $named = $request->input('sub_institute_id');
            $isAdminFlag = (int) session()->get('is_admin');
            $actsAcross = false;
            if ($tenant > 0 && is_scalar($named) && ctype_digit((string) $named) && (int) $named !== $tenant) {
                if ($isAdminFlag === 2) {
                    $actsAcross = true; // platform admin
                } elseif ($isAdminFlag === 1 && session()->get('client_id')) {
                    // A client-level admin may work in another school of the same client (the rule
                    // RequireApiJwt::mayActForSchool applies); anyone else stays in their own school.
                    $actsAcross = (int) \Illuminate\Support\Facades\DB::table('school_setup')->where('Id', (int) $named)->value('client_id')
                        === (int) session()->get('client_id');
                }
            }
            if ($tenant > 0 && ! $actsAcross && $isAdminFlag !== 2) {
                $request->merge(['sub_institute_id' => $tenant]);
                $_REQUEST['sub_institute_id'] = $tenant;
                if (isset($_POST['sub_institute_id'])) {
                    $_POST['sub_institute_id'] = $tenant;
                }
                if (isset($_GET['sub_institute_id'])) {
                    $_GET['sub_institute_id'] = $tenant;
                }
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

        if (\App\Support\TenantDeleteGuard::crossesTenant($request)) {
            return response()->json(['message' => 'This record belongs to another school.'], 403);
        }

        return $next($request);
    }
}
