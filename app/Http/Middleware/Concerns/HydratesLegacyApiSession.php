<?php

namespace App\Http\Middleware\Concerns;

use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Validates the bearer JWT and hydrates the (in-memory) session with the
 * verified claims, so every legacy session()-reading controller/model/helper
 * behaves identically whether it was reached through a web login or through
 * the type=API path the Next.js frontend (and mobile apps) always use.
 *
 * Tenant and user identity are taken exclusively from the verified token
 * payload, never from client-supplied request parameters, so a request
 * cannot claim to act as a school or user other than the one the token was
 * issued to.
 */
trait HydratesLegacyApiSession
{
    use GetsJwtToken;

    protected function hydrateSessionFromToken(Request $request): ?JsonResponse
    {
        try {
            if (! $this->jwtToken($request)->validate()) {
                return $this->unauthorizedApiSession('Token Auth Failed');
            }
        } catch (\Exception $e) {
            return $this->unauthorizedApiSession($e->getMessage());
        }

        if ($error = $this->hydrateSessionFromClaims($this->jwtPayload(null, $request), $request)) {
            return $error;
        }

        // Existing controllers switch to their JSON branches on type=API
        // (is_mobile() helper / checkPermission behave the same way they
        // already do for the mobile apps).
        $request->merge(['type' => 'API']);

        return null;
    }

    /**
     * Hydrates the session from claims that have ALREADY been verified by the
     * caller, and returns null on success or a JSON error on failure.
     *
     * Split out of hydrateSessionFromToken() so the WebView handoff bridge
     * (App\Http\Controllers\MobileWebBridgeController) can build a real
     * browser session from a redeemed handoff ticket without a second copy of
     * this logic. A copy is how the two paths would quietly drift apart on,
     * say, class-teacher scoping.
     *
     * Unlike the token path this does NOT force type=API, because the bridge
     * wants the page's normal HTML response, not its JSON branch.
     *
     * $payload is trusted: every caller must have verified it first, either by
     * validating the JWT signature or by redeeming a single-use ticket whose
     * claims were written server-side.
     */
    protected function hydrateSessionFromClaims(array $payload, Request $request): ?JsonResponse
    {
        // Defence in depth: an alumni-portal token is signed with a different secret and so should never get here, but if one ever
        // did, `id` would be read as a staff user id. Refuse anything marked as an alumni token.
        if (($payload['aud'] ?? null) === 'alumni' || ($payload['typ'] ?? null) === 'alumni_access') {
            return $this->unauthorizedApiSession('Invalid token payload');
        }

        $userId         = $payload['id'] ?? null;
        $subInstituteId = $payload['sub_institute_id'] ?? null;
        $userProfileId  = $payload['user_profile_id'] ?? null;
        $clientId       = $payload['client_id'] ?? null;
        $isAdmin        = $payload['is_admin'] ?? 0;
        $isStudent      = (bool) ($payload['is_student'] ?? false);

        if (empty($userId) || empty($subInstituteId)) {
            return $this->unauthorizedApiSession('Invalid token payload');
        }

        // The frontend may switch academic year/term explicitly (like the web
        // year switcher); otherwise resolve the *current* term the same way
        // loginController does.
        $syear  = $request->input('syear');
        $termId = $request->input('term_id');

        if (empty($syear) || empty($termId)) {
            $today = date('Y-m-d');
            $currentTerm = $this->rememberLookup('term:'.$subInstituteId.':'.$today, function () use ($subInstituteId, $today) {
                return DB::table('academic_year')
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereRaw('"' . $today . '" between start_date and end_date')
                    ->first();
            }, false);

            if (empty($currentTerm)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic Term Date Expired',
                    'data'    => null,
                    'errors'  => null,
                ], 422);
            }

            $syear  = $syear ?: $currentTerm->syear;
            $termId = $termId ?: $currentTerm->term_id;
        }

        $profile = $this->rememberLookup('profile:'.$userProfileId, function () use ($userProfileId) {
            return DB::table('tbluserprofilemaster')->where('id', $userProfileId)->first();
        });

        $userProfileName = ((int) $isAdmin === 1 || (int) $isAdmin === 2)
            ? 'Super Admin'
            : ($profile->name ?? '');

        $academicTerms = $this->rememberLookup('terms:'.$subInstituteId.':'.$syear, function () use ($subInstituteId, $syear) {
            return DB::table('academic_year')
                ->where('sub_institute_id', $subInstituteId)
                ->where('syear', $syear)
                ->orderBy('sort_order')
                ->get()->toArray();
        });

        $academicYears = $this->rememberLookup('years:'.$subInstituteId, function () use ($subInstituteId) {
            return DB::table('academic_year')
                ->where('sub_institute_id', $subInstituteId)
                ->groupBy('syear')
                ->get()->toArray();
        });

        $school = $this->rememberLookup('school:'.$subInstituteId, function () use ($subInstituteId) {
            return DB::table('school_setup')->where('Id', $subInstituteId)->first();
        });

        // Attach the (unstarted) session store to the request and hydrate the
        // exact keys web login sets, so all legacy session() reads keep working.
        $store = app('session.store');
        $request->setLaravelSession($store);

        $store->put('user_id', $userId);
        $store->put('sub_institute_id', $subInstituteId);
        $store->put('syear', $syear);
        $store->put('term_id', $termId);
        $store->put('user_profile_id', $userProfileId);
        $store->put('user_profile_name', $userProfileName);
        $store->put('profile_parent_id', $profile->parent_id ?? null);
        $store->put('client_id', $clientId);
        $store->put('is_admin', $isAdmin);
        $store->put('is_student', $isStudent);
        $store->put('academicTerms', $academicTerms);
        $store->put('academicYears', $academicYears);
        $store->put('institute_type', $school->institute_type ?? '');
        $store->put('erpcode', $school->ShortCode ?? '');
        $store->put('school_name', $school->SchoolName ?? '');

        if (! $isStudent) {
            $user = $this->rememberLookup('user:'.$userId, function () use ($userId) {
                return DB::table('tbluser')->where('id', $userId)->first();
            });
            $store->put('user_name', $user->user_name ?? '');
            $store->put('name', trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
            $store->put('email', $user->email ?? '');
        }

        // Class-teacher scoping - same as loginController.
        if ($userProfileName === 'Teacher') {
            $classTeacher = $this->rememberLookup('class_teacher:'.$userId.':'.$subInstituteId.':'.$syear, function () use ($userId, $subInstituteId, $syear) {
                return DB::table('class_teacher')
                    ->where('teacher_id', $userId)
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('syear', $syear)
                    ->get()->toArray();
            });

            $classTeacherGrdArr = $classTeacherStdArr = $classTeacherDivArr = [];
            foreach ($classTeacher as $v) {
                $classTeacherGrdArr[] = $v->grade_id;
                $classTeacherStdArr[] = $v->standard_id;
                $classTeacherDivArr[] = $v->division_id;
            }
            $store->put('classTeacherGrdArr', $classTeacherGrdArr);
            $store->put('classTeacherStdArr', $classTeacherStdArr);
            $store->put('classTeacherDivArr', $classTeacherDivArr);
            $store->put('classTeacher', $classTeacher);
        }

        if ($request->filled('menu_id')) {
            $store->put('right_menu_id', $request->input('menu_id'));
        }

        return null;
    }

    /**
     * These lookups ran on EVERY token-authenticated request (about 7 queries against a remote
     * DB) although the data changes rarely, and the web login path already holds the same values
     * for the whole session. A short TTL keeps edits visible quickly. False stands in for "no
     * row" so misses are cached too (Cache::remember re-queries on null).
     */
    protected function rememberLookup(string $key, \Closure $query, bool $cacheMiss = true)
    {
        $cacheKey = 'api_hydrate:'.$key;
        $value = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($value === null) {
            $value = $query();
            if ($value !== null || $cacheMiss) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $value ?? false, 300);
            }

            return $value;
        }

        return ($value === false) ? null : $value;
    }

    protected function unauthorizedApiSession(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => null,
        ], 401);
    }
}
