<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\Exceptions\NoTokenException;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;

/**
 * Authentication for the LMS content API surface.
 *
 * This is section 1 of App\Http\Middleware\PalApiAuth — require a valid GenTux JWT,
 * decode it, and normalise the payload into a request attribute. PalApiAuth's section 2
 * (learner ownership scoping) is deliberately NOT copied: it resolves a `{learnerId}`
 * route param and has no meaning for content routes.
 *
 * WHY THIS IS NEEDED AT ALL
 * The content endpoints have no authentication today. `routes/api.php` is registered with
 * only the `api` middleware group, which is `throttle:1000,1` + `SubstituteBindings`
 * (app/Http/Kernel.php:45-50; Sanctum is commented out). So
 * `POST /api/lms-chapter-content/upload` is reachable anonymously, and
 * ApiLmsCourseController.php reads `sub_institute_id` straight from request input, which
 * makes tenancy caller-controlled. Gating a button behind a permission while the endpoint
 * behind it is anonymous would be decoration.
 *
 * SCOPE — read this before extending it.
 * This middleware is applied ONLY to the content/authoring routes this work touches. It is
 * deliberately NOT a fix for the platform-wide auth surface — that is load-bearing for every
 * (Citations corrected 2026-09-08: the `type=API` bypass this originally cited has since been
 * removed from checkPermission, and SessionMiddleware now hydrates a JWT-verified session. The
 * merge(['type' => 'API']) lives in Concerns/HydratesLegacyApiSession.php:147. What remains
 * open there is the commented-out no-rights rejection, the menu-id allowlists, and the
 * client-supplied user_profile_name role on routes/api.php.)
 * Blade module in the ERP and belong to Track D (Decisions & Risk Log #2, Central Engines
 * rows 1-2). Widening this middleware to `routes/api.php` as a whole would break the other
 * 170-odd unauthenticated routes and collide with their work.
 *
 * ROLLOUT SAFETY
 * `config('lms_content.api_auth_enforce')` defaults to WARN-ONLY: an unauthenticated
 * request is logged and allowed through. The entire content area of the frontend currently
 * calls Laravel with a bare fetch() and no Authorization header, so enforcing on day one
 * would black out the screen for 56 tenants. Flip to enforce once the logs show zero
 * anonymous traffic.
 */
class LmsApiAuth
{
    use GetsJwtToken;

    public function handle(Request $request, Closure $next)
    {
        $auth = $this->resolveAuth($request);

        if ($auth === null) {
            if (! config('lms_content.api_auth_enforce', false)) {
                // Warn-only: record it, let it through, and mark the request so
                // downstream permission checks know identity is unverified.
                $request->attributes->set('lms_auth', null);
                $request->attributes->set('lms_auth_unverified', true);

                \Illuminate\Support\Facades\Log::channel('daily')->warning('lms.auth: unauthenticated content API request', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'sub_institute_id' => $request->input('sub_institute_id'),
                ]);

                return $next($request);
            }

            return response()->json([
                'status_code' => 0,
                'message' => 'Authentication token is required.',
            ], 401);
        }

        $request->attributes->set('lms_auth', $auth);

        return $next($request);
    }

    /**
     * Decode the JWT into a normalised identity, or null when there is not a valid one.
     *
     * Same payload shape as PalApiAuth so the two stay legible side by side.
     *
     * @return array<string,mixed>|null
     */
    private function resolveAuth(Request $request): ?array
    {
        try {
            $jwt = $this->jwtToken($request);
        } catch (NoTokenException $e) {
            return null;
        }

        try {
            if (! $jwt->validate()) {
                return null;
            }
            $payload = $jwt->payload();
        } catch (\Throwable $e) {
            return null;
        }

        $userId = (int) ($payload['id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        return [
            'user_id'          => $userId,
            'sub_institute_id' => $payload['sub_institute_id'] ?? null,
            'user_profile_id'  => isset($payload['user_profile_id']) ? (int) $payload['user_profile_id'] : null,
            'is_admin'         => (int) ($payload['is_admin'] ?? 0),
            'is_student'       => ! empty($payload['is_student']),
            'client_id'        => $payload['client_id'] ?? null,
        ];
    }
}
