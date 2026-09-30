<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Requires a logged-in caller on the `api/*` URLs listed in config/api_guard.php.
 *
 * The caller is either
 *   - a bearer JWT (what the Next.js frontend and mobile apps send), or
 *   - a web login session (what the legacy Blade pages send, cookie only).
 * Both are needed because some of these URLs are declared in web-group route
 * files and are called by Blade pages as well as by the frontend.
 *
 * It also adds the tenant rule the old per-controller checks lack: a request
 * may only name the caller's own school, except that a multi-school admin
 * (is_admin=2, or is_admin=1 for schools of their own client) may name
 * another - the same rule the PAL controllers already apply.
 *
 * Matching is on the concrete request path, not the route definition, so it
 * holds whichever route file or middleware group a route lives in. Unlike
 * `api.session` it does not build the legacy session, which keeps it safe in
 * front of controllers that do their own identity handling.
 */
class RequireApiJwt
{
    use GetsJwtToken;

    public function handle(Request $request, Closure $next)
    {
        if (! config('api_guard.enforce', true) || ! $this->isProtected($request)) {
            return $next($request);
        }

        $caller = $this->callerFromToken($request);
        if ($caller instanceof JsonResponse) {
            return $caller;
        }
        $caller ??= $this->callerFromWebSession($request);
        if ($caller === null) {
            return $this->deny('Authentication required', 401);
        }

        $requested = $request->input('sub_institute_id');
        if (is_array($requested)) {
            return $this->deny('Not valid for this school', 403); // (int) of an array is 1, not a school
        }
        if ($requested !== null && $requested !== '' && (int) $requested !== $caller['school']
            && ! $this->mayActForSchool($caller, (int) $requested)) {
            return $this->deny('Not valid for this school', 403);
        }

        if ($this->matchesAny('staff', $request) && $this->isStudentOrParent($caller)) {
            return $this->deny('Not available to this account', 403);
        }

        return $next($request);
    }

    public function isProtected(Request $request): bool
    {
        $path = $this->apiPath($request);
        if ($path === null) {
            return false;
        }
        $method = $request->method();

        foreach ((array) config('api_guard.public', []) as $pattern) {
            if ($this->matches($pattern, $path, $method)) {
                return false;
            }
        }
        // Public web forms (e.g. the standalone discipline complaint form) fill their
        // class/division dropdowns through these, anonymously, with type=webForm.
        if ($request->input('type') === 'webForm') {
            foreach ((array) config('api_guard.webform', []) as $pattern) {
                if ($this->matches($pattern, $path, $method)) {
                    return false;
                }
            }
        }
        foreach ((array) config('api_guard.protect', []) as $pattern) {
            if ($this->matches($pattern, $path, $method)) {
                return true;
            }
        }

        return false;
    }

    private function apiPath(Request $request): ?string
    {
        // decodedPath(), not path(): the router matches on the URL-decoded path, so
        // /api/get%2Dexam%2Dlist reaches the same controller as /api/get-exam-list and
        // must be judged as that, not as an unfamiliar string.
        $path = trim($request->decodedPath(), '/');

        return strpos($path, 'api/') === 0 ? substr($path, 4) : null;
    }

    private function matchesAny(string $list, Request $request): bool
    {
        $path = $this->apiPath($request);
        if ($path === null) {
            return false;
        }
        foreach ((array) config("api_guard.$list", []) as $pattern) {
            if ($this->matches($pattern, $path, $request->method())) {
                return true;
            }
        }

        return false;
    }

    /** Same rule as RequireStaffRole: the student flag, or a profile named Student/Parent. */
    private function isStudentOrParent(array $caller): bool
    {
        if ($caller['is_student']) {
            return true;
        }
        $name = $caller['profile_name'];
        if ($name === null && ! empty($caller['profile_id'])) {
            $name = (string) DB::table('tbluserprofilemaster')->where('id', $caller['profile_id'])->value('name');
        }

        return in_array(strtolower((string) $name), ['student', 'parent'], true);
    }

    /** @return array{school:int,is_admin:int,client_id:mixed,is_student:bool,profile_id:mixed,profile_name:?string}|JsonResponse|null null = no bearer token sent */
    private function callerFromToken(Request $request)
    {
        if (! $request->bearerToken() && ! $request->headers->has('Authorization')) {
            return null;
        }

        try {
            if (! $this->jwtToken($request)->validate()) {
                return $this->deny('Token Auth Failed', 401);
            }
            $payload = $this->jwtPayload(null, $request);
        } catch (\Exception $e) {
            return $this->deny('Token Auth Failed', 401);
        }

        $school = (int) ($payload['sub_institute_id'] ?? 0);
        if (empty($payload['id']) || $school === 0) {
            return $this->deny('Invalid token payload', 401);
        }

        return [
            'school' => $school,
            'is_admin' => (int) ($payload['is_admin'] ?? 0),
            'client_id' => $payload['client_id'] ?? null,
            'is_student' => (bool) ($payload['is_student'] ?? false),
            'profile_id' => $payload['user_profile_id'] ?? null,
            'profile_name' => null,
        ];
    }

    private function callerFromWebSession(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }
        $session = $request->session();
        $school = (int) $session->get('sub_institute_id', 0);
        if (! $session->get('user_id') || $school === 0) {
            return null;
        }

        return [
            'school' => $school,
            'is_admin' => (int) $session->get('is_admin', 0),
            'client_id' => $session->get('client_id'),
            'is_student' => (bool) $session->get('is_student', false),
            'profile_id' => $session->get('user_profile_id'),
            'profile_name' => (string) $session->get('user_profile_name', ''),
        ];
    }

    private function matches(string $pattern, string $path, string $method): bool
    {
        if (preg_match('/^([A-Z]+)\s+(.+)$/', $pattern, $m)) {
            if ($m[1] !== $method) {
                return false;
            }
            $pattern = $m[2];
        }

        return fnmatch($pattern, $path);
    }

    private function mayActForSchool(array $caller, int $requestedSchool): bool
    {
        if ($caller['is_admin'] === 2) {
            return true;
        }
        if ($caller['is_admin'] !== 1 || empty($caller['client_id'])) {
            return false;
        }

        return (int) DB::table('school_setup')->where('Id', $requestedSchool)->value('client_id')
            === (int) $caller['client_id'];
    }

    private function deny(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null, 'errors' => null], $status);
    }
}
