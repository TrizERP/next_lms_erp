<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;

/**
 * Guard for the legacy admin and teacher mobile API files (routes/adminapi.php,
 * routes/teacherapi.php), which were mounted with no middleware at all.
 *
 * Requires a valid, unexpired token, then pins the school to the one in the
 * token: any `sub_institute_id` the client sends is replaced, so a token from one
 * school cannot read or write another school's records.
 *
 * Their tokens name the caller as `user_id` (admin), `teacher_id` or `id`, so this
 * does not reuse the `id`-only checks in RequireApiJwt / ApiSessionHydrator.
 */
class RequireMobileApiJwt
{
    use GetsJwtToken;

    /** Login endpoints that have no token yet. */
    private const PUBLIC_PATHS = ['admin_login', 'admin_check_otp'];

    public function handle(Request $request, Closure $next)
    {
        if (in_array(trim($request->path(), '/'), self::PUBLIC_PATHS, true)) {
            return $next($request);
        }

        try {
            if (!$this->jwtToken($request)->validate()) {
                return $this->deny('Token Auth Failed');
            }
            $payload = $this->jwtPayload(null, $request);
        } catch (\Exception $e) {
            return $this->deny('Token Auth Failed');
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return $this->deny('Token expired');
        }

        // These are staff APIs; a student or parent token must not open them.
        if (!empty($payload['is_student']) || !empty($payload['student_id'])) {
            return response()->json(['status' => '2', 'message' => 'Not available to this account', 'data' => []], 403);
        }

        $school = (int) ($payload['sub_institute_id'] ?? 0);
        $caller = $payload['user_id'] ?? $payload['teacher_id'] ?? $payload['id'] ?? null;
        if ($school <= 0 || empty($caller)) {
            return $this->deny('Invalid token payload');
        }

        // Controllers read the school from the request object, $_REQUEST or $_POST
        // depending on the method, so pin it in all of them.
        $request->merge(['sub_institute_id' => $school]);
        foreach (['_REQUEST', '_POST', '_GET'] as $bag) {
            if (isset($GLOBALS[$bag]['sub_institute_id'])) {
                $GLOBALS[$bag]['sub_institute_id'] = $school;
            }
        }
        $_REQUEST['sub_institute_id'] = $school;
        $request->attributes->set('verified_user_id', (int) $caller);

        return $next($request);
    }

    private function deny(string $message)
    {
        return response()->json(['status' => '2', 'message' => $message, 'data' => []], 401);
    }
}
