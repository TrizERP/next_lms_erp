<?php

namespace App\Http\Controllers\G2gLms\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant + actor identity for the G2G "LMS" (People & Competency) migration.
 *
 * hp_erp's source controllers (`LmsLearningController`, `LmsCourseController`,
 * `AiCourseController`) resolved identity per-request from a Sanctum bearer
 * token via their own `Concerns\ResolvesLmsIdentity`. This repo instead
 * authenticates every `g2g-lms/*` route through the `api.session` middleware
 * (`HydratesLegacyApiSession`), which hydrates the same claims into the
 * Laravel session before the controller ever runs — exactly the precedent
 * `TaskManagement\Concerns\ResolvesTaskManagementContext` and
 * `TalentManagement\Competency\Concerns\ResolvesCompetencyContext` already
 * follow for this same People & Competency area. So identity here is read
 * from `session()`, never re-derived from a token or trusted from client
 * input.
 */
trait ResolvesLmsIdentity
{
    /**
     * @return array{sub_institute_id:int, user_id:int, syear:string, is_admin:int, profile_name:string}
     */
    protected function lmsContext(Request $request): array
    {
        return [
            'sub_institute_id' => (int) session()->get('sub_institute_id'),
            'user_id' => (int) session()->get('user_id'),
            'syear' => (string) (session()->get('syear') ?: $request->input('syear', '')),
            'is_admin' => (int) session()->get('is_admin', 0),
            'profile_name' => (string) session()->get('user_profile_name', ''),
        ];
    }

    /**
     * Whether the caller may act on OTHER people's records (view "all" scope,
     * re-issue a certificate, assign a course to an audience). Mirrors the
     * source's `user.role === 'admin' || 'hr'` check: this repo has no
     * equivalent role enum, so Super Admin (`is_admin` 1|2, the same gate
     * `RequiresTalentAdmin` uses elsewhere in this area) or an HR profile
     * name both qualify.
     */
    protected function isLmsStaffAdmin(array $context): bool
    {
        if (in_array($context['is_admin'], [1, 2], true)) {
            return true;
        }

        return str_contains(strtolower($context['profile_name']), 'hr')
            || str_contains(strtolower($context['profile_name']), 'admin');
    }

    protected function lmsOk($data = null, string $message = 'Success', int $code = 200, array $extra = []): JsonResponse
    {
        $payload = ['status' => true, 'message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json(array_merge($payload, $extra), $code);
    }

    protected function lmsError(string $message, int $code = 400, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['status' => false, 'message' => $message], $extra), $code);
    }

    /**
     * Tables owned by Package 1/2 (`lms_course_enroll`, `content_master`,
     * `lms_content_progress`) may not exist yet in this database when this
     * package's code first lands, depending on merge order across the
     * parallel migration. Every read against one of them goes through this
     * guard so a missing table degrades to "no data yet" instead of a 500 —
     * the same defensive `information_schema` pattern already used by
     * `2026_09_02_190000_add_sequential_unlock_to_lms_course_settings.php`.
     */
    protected function lmsTableExists(string $table): bool
    {
        static $cache = [];

        if (! array_key_exists($table, $cache)) {
            $cache[$table] = Schema::hasTable($table);
        }

        return $cache[$table];
    }

    /* ------------------------------------------------------------------ *
     * Package 1 addition — the hp_erp-shaped identity surface.
     *
     * hp_erp's source controllers (LmsCourseController, LmsLearningController,
     * LmsCourseEnrollController) call `lmsIdentity()`, `guardLmsToken()`,
     * `guardLmsProfile()`, `lmsTenantId()` and `contextUserId()` throughout —
     * ~200 call sites across three controllers. Rather than rewrite every one
     * of those call sites against this file's newer `lmsContext()` /
     * `isLmsStaffAdmin()` helpers (added first, for a different package),
     * these methods are ADDED alongside them so the ported controller bodies
     * work with no structural changes, while staying on the exact same
     * session-hydrated identity source `lmsContext()` already reads from
     * (`session()->get('user_id'|'sub_institute_id'|...)`, populated by the
     * `api.session` middleware). Nothing above this block is modified.
     *
     * Reusable as-is by Packages 2-4: `use ResolvesLmsIdentity;` then call
     * `guardLmsToken($request)` / `lmsTenantId($request)` /
     * `contextUserId($request)` / `guardLmsProfile($request, [...], $msg)`.
     * ------------------------------------------------------------------ */

    /** Resolved once per request - these controllers call the guards repeatedly. */
    private ?array $lmsIdentityCache = null;

    /**
     * @return array{user:object, user_id:int, sub_institute_id:int, profile_name:string}|JsonResponse
     */
    protected function lmsIdentity(Request $request)
    {
        if ($this->lmsIdentityCache !== null) {
            return $this->lmsIdentityCache;
        }

        $userId = session()->get('user_id');
        $subInstituteId = session()->get('sub_institute_id');

        if (empty($userId) || empty($subInstituteId)) {
            return response()->json([
                'status' => false,
                'message' => 'Your session has expired. Please sign in again.',
            ], 401);
        }

        $user = DB::table('tbluser')->where('id', $userId)->first();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Your session has expired. Please sign in again.',
            ], 401);
        }

        $identity = [
            'user' => $user,
            'user_id' => (int) $userId,
            'sub_institute_id' => (int) $subInstituteId,
        ];

        $identity['profile_name'] = $this->lmsProfileName($identity['user']);

        return $this->lmsIdentityCache = $identity;
    }

    /**
     * Exact role_key matching, ported from hp_erp's G-AUTH-02 fix (substring
     * matching on the display name previously let 'reporting manager' match a
     * 'manager' gate). `tbluserprofilemaster.role_key` exists in this project
     * too (2026_08_19_175501_add_role_keys_and_is_mobile_rights_columns.php),
     * shared with Role & Permissions and Competency Management. LEGACY_NAMES
     * covers profiles that predate role_key, matched EXACTLY on the lowercased
     * name — never by substring.
     */
    private const LMS_ALIASES = [
        'admin'     => ['administrator'],
        'hr'        => ['hr_manager', 'hr_executive'],
        'manager'   => ['hr_manager', 'reporting_manager'],
        'employee'  => ['employee'],
        'executive' => ['executive'],
        'auditor'   => ['auditor'],
        'recruiter' => ['recruiter'],
    ];

    private const LMS_LEGACY_NAMES = [
        'admin'                      => 'administrator',
        'organization administrator' => 'administrator',
        'hr'                         => 'hr_manager',
    ];

    protected function lmsRoleMatches(object $user, array $allowed): bool
    {
        // Session-hydrated Super Admin callers (is_admin 1/2, stamped
        // user_profile_name = 'Super Admin' by HydratesLegacyApiSession)
        // always match an 'admin' gate, whether or not their
        // tbluserprofilemaster row resolves a role_key.
        if ((int) session()->get('is_admin') > 0 && in_array('admin', array_map('strtolower', $allowed), true)) {
            return true;
        }

        $profileId = (int) ($user->user_profile_id ?? 0);
        if ($profileId <= 0 || $allowed === []) {
            return false;
        }

        $profile = DB::table('tbluserprofilemaster')->where('id', $profileId)->first(['role_key', 'name']);
        if (!$profile) {
            return false;
        }

        $roleKey = trim((string) ($profile->role_key ?? ''));
        if ($roleKey === '') {
            $roleKey = self::LMS_LEGACY_NAMES[strtolower(trim((string) $profile->name))] ?? '';
        }
        if ($roleKey === '') {
            return false;
        }

        foreach ($allowed as $permitted) {
            if (in_array($roleKey, self::LMS_ALIASES[strtolower(trim($permitted))] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The caller's profile name from tbluser.user_profile_id, lowercased.
     * Empty string when the account has no resolvable profile.
     */
    protected function lmsProfileName(object $user): string
    {
        $profileId = (int) ($user->user_profile_id ?? 0);

        if ($profileId <= 0) {
            return '';
        }

        $name = DB::table('tbluserprofilemaster')->where('id', $profileId)->value('name');

        return strtolower(trim((string) $name));
    }

    /**
     * Authentication gate. Null when the request may proceed, otherwise the
     * JsonResponse to return immediately. In practice `api.session` already
     * refused an unauthenticated request before the controller runs; this
     * stays as a defensive re-check so guard call sites ported from hp_erp
     * need no changes.
     */
    protected function guardLmsToken(Request $request)
    {
        $identity = $this->lmsIdentity($request);

        return is_array($identity) ? null : $identity;
    }

    /**
     * Role gate. $allowed is matched via role_key aliasing against the
     * caller's real profile. An unresolvable profile is refused, not waved
     * through.
     */
    protected function guardLmsProfile(Request $request, array $allowed, string $message)
    {
        $identity = $this->lmsIdentity($request);

        if (!is_array($identity)) {
            return $identity;
        }

        if ($this->lmsRoleMatches($identity['user'], $allowed)) {
            return null;
        }

        return response()->json(['status' => false, 'message' => $message], 403);
    }

    /**
     * The caller's own organisation, from the hydrated session - never from
     * whatever sub_institute_id the request asked for.
     */
    protected function lmsTenantId(Request $request): ?int
    {
        $identity = $this->lmsIdentity($request);

        return is_array($identity) ? $identity['sub_institute_id'] : null;
    }

    /**
     * The caller's own user id, from the hydrated session - never from a
     * request field. Across these controllers a `user_id` in the REQUEST
     * BODY (where one exists) is a genuine subject a caller may name, e.g.
     * enrolling or recording progress for somebody else; created_by /
     * updated_by / deleted_by and "my courses" style reads always use this
     * context id instead.
     */
    protected function contextUserId(Request $request): ?int
    {
        $identity = $this->lmsIdentity($request);

        return is_array($identity) ? $identity['user_id'] : null;
    }
}
