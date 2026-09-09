<?php

namespace App\Http\Middleware\Brain;

use App\Brain\Authorization\Role;
use App\Brain\Support\SchemaCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrainAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->bearer($request);
        if (! $token) {
            return response()->json(['error' => 'brain_unauthenticated'], 401);
        }

        $payload = $this->decodeJwt($token);
        if (! $payload || empty($payload['id']) || ! isset($payload['sub_institute_id'])) {
            return response()->json(['error' => 'brain_invalid_token'], 401);
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return response()->json(['error' => 'brain_token_expired'], 401);
        }

        $role = $this->roleFor($payload);

        $request->attributes->set('auth.userId', (string) $payload['id']);
        $request->attributes->set('auth.tenantId', (string) $payload['sub_institute_id']);
        $request->attributes->set('auth.role', $role);
        $request->attributes->set('brain.payload', $payload);

        return $next($request);
    }

    private function bearer(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return $request->query('token') ? (string) $request->query('token') : null;
    }

    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;

        // Every secret the token may legitimately have been signed with: this
        // installation's own, plus any deployment the LMS front end signs in
        // against (config/brain.php). The token is still verified — an
        // unrecognised signature is still rejected.
        $secrets = array_values(array_unique(array_filter(array_merge(
            (array) config('brain.jwt_secrets', []),
            [(string) env('JWT_SECRET', ''), (string) config('app.key')]
        ))));

        $signed = false;
        foreach ($secrets as $secret) {
            $expected = $this->base64UrlEncode(hash_hmac('sha256', $header64.'.'.$payload64, $secret, true));
            if (hash_equals($expected, $signature64)) {
                $signed = true;
                break;
            }
        }

        if (! $signed) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payload64), true);
        return is_array($payload) ? $payload : null;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * The LMS profile a token carries, expressed as a Brain role.
     *
     * Tried in order of how specific the statement is:
     *
     *  1. An explicit user_profile_id -> role entry, for a deployment that wants
     *     to pin one institute's profile to one role.
     *  2. tbluser.is_admin, when the LMS sets it.
     *  3. The PROFILE NAME from tbluserprofilemaster, scoped to the token's own
     *     institute. This is the branch that actually fires in vivek_erp: every
     *     row there has is_admin NULL, and profile ids are per-institute, so
     *     without it an institute's own administrator resolved to `viewer` and
     *     could not run ingestion, refresh intelligence or record a decision.
     *  4. default_role — read-only.
     *
     * The lookup is by (id, sub_institute_id) so a token cannot borrow another
     * institute's profile row to claim a role it was not granted.
     */
    private function roleFor(array $payload): string
    {
        $profileId = isset($payload['user_profile_id']) ? (string) $payload['user_profile_id'] : '';

        $mapped = config('brain.profile_roles.'.$profileId);
        if ($mapped && Role::tryFromName((string) $mapped)) {
            return (string) $mapped;
        }

        $adminValues = array_map('strval', (array) config('brain.admin_values', [1, 2]));
        if (isset($payload['is_admin']) && in_array((string) $payload['is_admin'], $adminValues, true)) {
            return Role::TENANT_ADMIN;
        }

        $named = $this->roleForProfileName($profileId, (string) ($payload['sub_institute_id'] ?? ''));
        if ($named !== null) {
            return $named;
        }

        $default = (string) config('brain.default_role', Role::VIEWER);

        return Role::tryFromName($default) ?: Role::VIEWER;
    }

    /** The configured role for this institute's profile name, or null. */
    private function roleForProfileName(string $profileId, string $tenantId): ?string
    {
        $map = (array) config('brain.profile_name_roles', []);
        if ($profileId === '' || $map === [] || ! SchemaCache::hasTable('tbluserprofilemaster')) {
            return null;
        }

        $key = $profileId.'@'.$tenantId;
        if (! array_key_exists($key, self::$profileNames)) {
            $query = DB::table('tbluserprofilemaster')->where('id', $profileId);
            if ($tenantId !== '' && SchemaCache::hasColumn('tbluserprofilemaster', 'sub_institute_id')) {
                $query->where('sub_institute_id', $tenantId);
            }
            self::$profileNames[$key] = (string) ($query->value('name') ?? '');
        }

        $name = strtolower(trim(self::$profileNames[$key]));
        if ($name === '' || ! isset($map[$name])) {
            return null;
        }

        return Role::tryFromName((string) $map[$name]);
    }

    /** Memo so a burst of Brain calls in one page load costs one profile lookup. */
    private static array $profileNames = [];
}
