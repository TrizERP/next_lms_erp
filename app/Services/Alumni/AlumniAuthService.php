<?php

namespace App\Services\Alumni;

use App\Services\Platform\AuditTrail;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Alumni portal sign-in. Everything about accounts, passwords, one-time tokens and login sessions lives here; the controllers
 * only validate input and shape responses. The design and the reasons are in section 8 of
 * docs/ALUMNI_MODULE_IMPLEMENTATION_PLAN.md. In short:
 *
 *  - Alumni tokens are signed with ALUMNI_JWT_SECRET (never the staff JWT_SECRET), carry audience `alumni` and no `id` /
 *    `sub_institute_id` / `is_student` claims, and are accepted only by the `alumni.auth` middleware. Firebase JWT is used
 *    directly with the algorithm pinned to HS256: the wrapper used for staff tokens quietly falls back to the staff secret
 *    and to an environment-chosen algorithm, which is exactly what must not happen here.
 *  - Every request re-checks the session, the account and the alumnus, so disabling an account, resetting a password or a
 *    stolen-refresh-token alarm takes effect at once rather than when the access token expires.
 *  - Nothing here reveals whether an email has an account: registration, resend and forgot-password answer the same way
 *    either way, and a failed sign-in never says which part was wrong.
 *  - One-time tokens (invite, verify email, reset password) are random, stored only as a SHA-256 hash, and consumed with one
 *    atomic UPDATE, so they work once even if two requests race. The emailed link is scrubbed from the platform notification
 *    log right after sending - that table stores message bodies and would otherwise hold live links.
 */
class AlumniAuthService
{
    public const GENERIC_SIGN_IN_FAILURE = 'Sign-in failed. Check the institute code, email and password, or try again in a few minutes.';

    private const BLOCKED_ALUMNUS = ['blocked', 'deceased', 'opted_out'];

    /** Statuses whose alumni may use portal features. `pending_verification` (self-registered, not yet verified by staff) may only see their own status. */
    public const FEATURE_STATUSES = ['registered', 'verified', 'active'];

    private static ?string $dummyHash = null;

    public function __construct(private AlumniService $alumni, private AlumniMessaging $messaging)
    {
    }

    // ------------------------------------------------------------------ tokens

    /** The signing secret, or a 503 when it is missing, weak, or the same as the staff secret. */
    private function secret(): string
    {
        $secret = (string) config('alumni_auth.jwt_secret');
        $staff = (string) config('alumni_auth.staff_jwt_secret');
        if (strlen($secret) < 32 || ($staff !== '' && hash_equals($staff, $secret))) {
            throw new AlumniRuleException('Alumni sign-in is not available right now.', 503);
        }

        return $secret;
    }

    public function isConfigured(): bool
    {
        try {
            $this->secret();

            return true;
        } catch (AlumniRuleException) {
            return false;
        }
    }

    /** @return array{0: string, 1: int} token, seconds until it expires */
    private function accessToken(object $account, object $session): array
    {
        $ttl = (int) config('alumni_auth.access_ttl_minutes', 30) * 60;
        $now = time();
        $jwt = JWT::encode([
            'iss' => 'alumni-portal', 'aud' => 'alumni', 'typ' => 'alumni_access', 'sub' => (int) $account->id, 'aid' => (int) $account->alumni_id,
            'tid' => (int) $account->sub_institute_id, 'sid' => (int) $session->id, 'iat' => $now, 'exp' => $now + $ttl, 'jti' => bin2hex(random_bytes(8)),
        ], $this->secret(), 'HS256');

        return [$jwt, $ttl];
    }

    /**
     * Verify an access token and the live state behind it. Null for anything wrong - never throws, never says why.
     *
     * @return array{account: object, alumnus: object, session: object}|null
     */
    public function authenticate(string $jwt): ?array
    {
        try {
            $p = (array) JWT::decode($jwt, new Key($this->secret(), 'HS256'));   // algorithm pinned: a token cannot choose how it is checked
        } catch (AlumniRuleException $e) {
            throw $e;   // not configured: the caller answers 503
        } catch (Throwable) {
            return null;
        }
        if (($p['aud'] ?? null) !== 'alumni' || ($p['typ'] ?? null) !== 'alumni_access' || ($p['iss'] ?? null) !== 'alumni-portal') {
            return null;
        }
        $session = DB::table('alumni_auth_sessions')->where('id', (int) ($p['sid'] ?? 0))->where('account_id', (int) ($p['sub'] ?? 0))->first();
        if (! $session || $session->revoked_at !== null || strtotime((string) $session->expires_at) <= time()) {
            return null;
        }
        $account = DB::table('alumni_accounts')->where('id', (int) $session->account_id)->first();
        if (! $account || $account->status !== 'active' || (int) $account->sub_institute_id !== (int) ($p['tid'] ?? 0) || (int) $account->alumni_id !== (int) ($p['aid'] ?? 0)) {
            return null;
        }
        $alumnus = DB::table('alumni')->where('sub_institute_id', $account->sub_institute_id)->where('id', $account->alumni_id)->first();
        if (! $alumnus || in_array($alumnus->status, self::BLOCKED_ALUMNUS, true)) {
            return null;
        }

        return ['account' => $account, 'alumnus' => $alumnus, 'session' => $session];
    }

    // ------------------------------------------------------------- one-time tokens

    private function newToken(object $account, string $purpose, int $ttlSeconds, ?int $createdBy = null, ?string $ip = null): array
    {
        // a new token of the same kind replaces the old one
        DB::table('alumni_auth_tokens')->where('account_id', $account->id)->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
        $plain = bin2hex(random_bytes(32));
        $id = (int) DB::table('alumni_auth_tokens')->insertGetId([
            'sub_institute_id' => $account->sub_institute_id, 'account_id' => $account->id, 'purpose' => $purpose, 'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addSeconds($ttlSeconds), 'created_by' => $createdBy, 'created_ip' => $ip ? mb_substr($ip, 0, 45) : null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$plain, $id];
    }

    /** A valid, unused, unexpired token of this purpose - without using it up. */
    private function peekToken(string $plain, string $purpose): ?object
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $plain)) {
            return null;
        }

        return DB::table('alumni_auth_tokens')->where('token_hash', hash('sha256', $plain))->where('purpose', $purpose)->whereNull('used_at')->where('expires_at', '>', now())->first();
    }

    /** Use a token up. One atomic UPDATE decides the winner if two requests race. */
    private function consumeToken(string $plain, string $purpose): ?object
    {
        $row = $this->peekToken($plain, $purpose);
        if (! $row) {
            return null;
        }
        $won = DB::table('alumni_auth_tokens')->where('id', $row->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);

        return $won === 1 ? $row : null;
    }

    // ------------------------------------------------------------------ helpers

    public function tenantByCode(string $code): ?int
    {
        $code = trim($code);
        if ($code === '' || ! \Illuminate\Support\Facades\Schema::hasTable('school_setup')) {
            return null;
        }
        $ids = DB::table('school_setup')->whereRaw('UPPER(ShortCode) = ?', [mb_strtoupper($code)])->pluck('Id');

        return $ids->count() === 1 ? (int) $ids->first() : null;   // an ambiguous code resolves to nothing rather than to a guess
    }

    public function instituteCode(int $tenant): ?string
    {
        return \Illuminate\Support\Facades\Schema::hasTable('school_setup') ? (DB::table('school_setup')->where('Id', $tenant)->value('ShortCode') ?: null) : null;
    }

    /** @return array<string, array<int, string>> field => messages; empty when the password is acceptable */
    public function passwordProblems(string $password, string $email): array
    {
        $min = (int) config('alumni_auth.password_min_length', 10);
        $m = [];
        if (mb_strlen($password) < $min) {
            $m[] = "Use at least {$min} characters.";
        }
        if (strlen($password) > 72) {
            $m[] = 'Use at most 72 characters.';
        }
        if (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
            $m[] = 'Include at least one letter and one number.';
        }
        if ($email !== '' && mb_strtolower($password) === mb_strtolower($email)) {
            $m[] = 'The password cannot be your email address.';
        }

        return $m ? ['password' => $m] : [];
    }

    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(bin2hex(random_bytes(8)));   // so a missing account costs the same time as a wrong password
    }

    private function link(int $tenant, string $path, string $token): string
    {
        $base = rtrim((string) (config('alumni_auth.portal_url') ?: $this->alumni->setting($tenant, 'alumni_portal_url', '') ?: config('app.url')), '/');
        $code = (string) $this->instituteCode($tenant);

        return $base . '/alumni-portal/' . $path . '?token=' . $token . ($code !== '' ? '&i=' . rawurlencode($code) : '');
    }

    /**
     * Email one account holder. Email only (these are not marketing and never go to the in-app inbox), and the one-time link
     * is scrubbed from the platform notification log right after sending. Returns the delivery status (sent/skipped/failed/...).
     */
    private function email(object $account, string $subject, string $body, string $key): string
    {
        $result = $this->messaging->notify((int) $account->sub_institute_id, (int) $account->alumni_id, 'registration', $subject, $body, $key, true, [], ['email']);
        $logId = DB::table('alumni_message_log')->where('sub_institute_id', $account->sub_institute_id)->where('alumni_id', $account->alumni_id)->where('channel', 'email')->where('dedupe_key', $key)->value('notification_log_id');
        if ($logId) {
            DB::table('platform_notification_log')->where('id', $logId)->update(['body' => '[This message contained a one-time link. It was removed from the log after sending.]']);
        }

        return $result['email'] ?? ($result['_'] ?? 'skipped');
    }

    private function audit(int $tenant, string $action, ?object $account, array $after = [], ?Request $request = null, ?string $actor = null, ?int $actorUserId = null): void
    {
        AuditTrail::record($tenant, 'alumni', 'alumni.auth', $action, ['entity_type' => 'alumni_account', 'entity_id' => $account->id ?? null, 'after' => $after,
            'actor_name' => $actor, 'actor_user_id' => $actorUserId], $request);
    }

    private function accountByEmail(int $tenant, string $email): ?object
    {
        return DB::table('alumni_accounts')->where('sub_institute_id', $tenant)->where('email', mb_strtolower(trim($email)))->first();
    }

    // -------------------------------------------------------------- registration

    /**
     * Self-registration. ALWAYS finishes quietly - the caller gives the same answer whether a record was created, the person
     * already exists, or the institute code was unknown - so this cannot be used to find out who is an alumnus.
     * It never links to an existing alumni record: that is what a staff invitation is for.
     *
     * @param  array{first_name:string,last_name:string,email:string,password:string,mobile?:?string,batch?:?string,passing_year?:?int}  $in
     */
    public function register(string $instituteCode, array $in, ?string $ip, ?Request $request = null): void
    {
        $t = $this->tenantByCode($instituteCode);
        $email = mb_strtolower(trim($in['email']));
        if ($t === null) {
            Hash::make($in['password']);   // same cost as a real registration

            return;
        }
        if ($existing = $this->accountByEmail($t, $email)) {
            $this->email($existing, 'Your alumni portal account', "Hello,\n\nSomeone tried to register for the alumni portal with this email address, but an account already exists. If that was you, sign in or use \"Forgot password\". If it was not, you can ignore this message.", 'alumni-auth-dup-' . $existing->id . '-' . now()->format('YmdH'));
            $this->audit($t, 'register_existing_account', $existing, [], $request, 'Visitor (not signed in)');

            return;
        }
        $data = array_filter(['first_name' => $in['first_name'], 'last_name' => $in['last_name'], 'email' => $email, 'mobile' => $in['mobile'] ?? null, 'batch' => $in['batch'] ?? null, 'passing_year' => $in['passing_year'] ?? null], fn ($v) => $v !== null && $v !== '');
        if ($this->alumni->findDuplicates($t, $data) !== []) {
            $this->audit($t, 'register_duplicate', null, ['email_domain' => substr(strrchr($email, '@') ?: '', 1)], $request, 'Visitor (not signed in)');   // staff can see attempts, without the address

            return;   // nothing created, nothing revealed; an existing alumnus asks the institute for an invitation
        }

        try {
            [$account, $token, $tokenId] = DB::transaction(function () use ($t, $data, $email, $in, $ip) {
                $alumnus = $this->alumni->create($t, $data, null, 'self');   // pending_verification: staff still have to verify the person
                $id = (int) DB::table('alumni_accounts')->insertGetId([
                    'sub_institute_id' => $t, 'alumni_id' => $alumnus->id, 'email' => $email, 'password_hash' => Hash::make($in['password']), 'status' => 'unverified',
                    'created_via' => 'self', 'password_changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $account = DB::table('alumni_accounts')->where('id', $id)->first();
                [$token, $tokenId] = $this->newToken($account, 'verify_email', (int) config('alumni_auth.verify_email_ttl_hours', 24) * 3600, null, $ip);

                return [$account, $token, $tokenId];
            });
        } catch (DuplicateAlumniException) {
            return;   // raced with another registration or an import
        }
        $this->sendVerification($account, $token, $tokenId);
        $this->audit($t, 'register', $account, ['via' => 'self'], $request, 'Alumnus (self-registration)');
    }

    private function sendVerification(object $account, string $token, int $tokenId): void
    {
        $t = (int) $account->sub_institute_id;
        $this->email($account, 'Confirm your email for the alumni portal',
            "Hello,\n\nPlease confirm your email address to finish setting up your alumni portal account:\n\n" . $this->link($t, 'verify-email', $token)
            . "\n\nThis link works once and expires in " . (int) config('alumni_auth.verify_email_ttl_hours', 24) . " hours. If you did not ask for this, ignore this message.", 'alumni-auth-verify-' . $tokenId);
    }

    public function verifyEmail(string $token, ?Request $request = null): bool
    {
        $row = $this->consumeToken($token, 'verify_email');
        if (! $row) {
            return false;
        }
        $account = DB::table('alumni_accounts')->where('id', $row->account_id)->first();
        if (! $account) {
            return false;
        }
        $set = ['email_verified_at' => now(), 'updated_at' => now()];
        if ($account->status === 'unverified' && $account->password_hash !== null) {
            $set['status'] = 'active';
        }
        DB::table('alumni_accounts')->where('id', $account->id)->update($set);
        $this->audit((int) $account->sub_institute_id, 'verify_email', $account, [], $request, 'Alumnus');

        return true;
    }

    /** Always silent to the caller. */
    public function resendVerification(string $instituteCode, string $email, ?string $ip): void
    {
        $t = $this->tenantByCode($instituteCode);
        $account = $t ? $this->accountByEmail($t, $email) : null;
        if (! $account || $account->status !== 'unverified' || $account->password_hash === null) {
            return;
        }
        [$token, $id] = $this->newToken($account, 'verify_email', (int) config('alumni_auth.verify_email_ttl_hours', 24) * 3600, null, $ip);
        $this->sendVerification($account, $token, $id);
    }

    // --------------------------------------------------------------------- sign in

    /**
     * @return array{access_token:string, refresh_token:string, expires_in:int, token_type:string}
     *
     * @throws AlumniRuleException 401 for any failed sign-in, always the same message
     */
    public function login(string $instituteCode, string $email, string $password, ?string $ip, ?string $userAgent, ?Request $request = null): array
    {
        $this->secret();   // 503 before anything else if the portal is not configured
        $fail = fn () => new AlumniRuleException(self::GENERIC_SIGN_IN_FAILURE, 401);
        $t = $this->tenantByCode($instituteCode);
        $account = $t ? $this->accountByEmail($t, $email) : null;

        if (! $account || $account->password_hash === null) {
            Hash::check($password, $this->dummyHash());

            throw $fail();
        }
        $locked = $account->locked_until !== null && strtotime((string) $account->locked_until) > time();
        $correct = Hash::check($password, $account->password_hash);   // checked even when locked, so timing does not reveal the lock
        if ($locked || ! $correct) {
            if (! $correct && ! $locked) {
                $attempts = (int) $account->failed_attempts + 1;
                $lock = $attempts >= (int) config('alumni_auth.max_failed_logins', 5);
                DB::table('alumni_accounts')->where('id', $account->id)->update([
                    'failed_attempts' => $lock ? 0 : $attempts, 'locked_until' => $lock ? now()->addMinutes((int) config('alumni_auth.lockout_minutes', 15)) : null, 'updated_at' => now(),
                ]);
                if ($lock) {
                    $this->audit($t, 'account_locked', $account, ['minutes' => (int) config('alumni_auth.lockout_minutes', 15)], $request, 'System');
                }
            }

            throw $fail();
        }
        $alumnus = DB::table('alumni')->where('sub_institute_id', $t)->where('id', $account->alumni_id)->first();
        if ($account->status === 'disabled' || ! $alumnus || in_array($alumnus->status, self::BLOCKED_ALUMNUS, true)) {
            throw $fail();
        }
        if ($account->status !== 'active' || $account->email_verified_at === null) {
            // only reachable with the right password, so this does not tell a stranger anything about the account
            throw new AlumniRuleException('Confirm your email address first. Use the link we sent you, or ask for a new one.', 403, 'email');
        }

        DB::table('alumni_accounts')->where('id', $account->id)->update(['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'updated_at' => now()]);
        $tokens = $this->startSession($account, $ip, $userAgent);
        $this->audit($t, 'login', $account, [], $request, 'Alumnus ' . $alumnus->alumni_code);

        return $tokens;
    }

    private function startSession(object $account, ?string $ip, ?string $userAgent): array
    {
        $refresh = bin2hex(random_bytes(32));
        $id = (int) DB::table('alumni_auth_sessions')->insertGetId([
            'sub_institute_id' => $account->sub_institute_id, 'account_id' => $account->id, 'refresh_hash' => hash('sha256', $refresh),
            'expires_at' => now()->addDays((int) config('alumni_auth.refresh_ttl_days', 30)), 'last_used_at' => now(), 'ip' => $ip ? mb_substr($ip, 0, 45) : null,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        [$access, $ttl] = $this->accessToken($account, DB::table('alumni_auth_sessions')->where('id', $id)->first());

        return ['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => $ttl, 'token_type' => 'Bearer'];
    }

    /**
     * Swap a refresh token for a new pair. The old refresh token stops working. Presenting a token that was ALREADY swapped
     * means two parties hold it - the login is revoked outright.
     */
    public function refresh(string $refreshToken, ?Request $request = null): array
    {
        $this->secret();
        $fail = fn () => new AlumniRuleException('Your session has ended. Please sign in again.', 401);
        if (! preg_match('/^[a-f0-9]{64}$/', $refreshToken)) {
            throw $fail();
        }
        $hash = hash('sha256', $refreshToken);
        $session = DB::table('alumni_auth_sessions')->where('refresh_hash', $hash)->first();
        if (! $session) {
            $reused = DB::table('alumni_auth_sessions')->where('previous_refresh_hash', $hash)->whereNull('revoked_at')->first();
            if ($reused) {
                DB::table('alumni_auth_sessions')->where('id', $reused->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
                $this->audit((int) $reused->sub_institute_id, 'refresh_token_reuse', null, ['session' => (int) $reused->id], $request, 'System');
            }

            throw $fail();
        }
        if ($session->revoked_at !== null || strtotime((string) $session->expires_at) <= time()) {
            throw $fail();
        }
        $account = DB::table('alumni_accounts')->where('id', $session->account_id)->first();
        $alumnus = $account ? DB::table('alumni')->where('sub_institute_id', $account->sub_institute_id)->where('id', $account->alumni_id)->first() : null;
        if (! $account || $account->status !== 'active' || ! $alumnus || in_array($alumnus->status, self::BLOCKED_ALUMNUS, true)) {
            throw $fail();
        }
        $new = bin2hex(random_bytes(32));
        $won = DB::table('alumni_auth_sessions')->where('id', $session->id)->where('refresh_hash', $hash)->whereNull('revoked_at')
            ->update(['previous_refresh_hash' => $hash, 'refresh_hash' => hash('sha256', $new), 'last_used_at' => now(), 'updated_at' => now()]);
        if ($won !== 1) {
            throw $fail();   // a parallel refresh won; this caller holds a token that was just replaced
        }
        [$access, $ttl] = $this->accessToken($account, $session);

        return ['access_token' => $access, 'refresh_token' => $new, 'expires_in' => $ttl, 'token_type' => 'Bearer'];
    }

    public function logout(int $sessionId): void
    {
        DB::table('alumni_auth_sessions')->where('id', $sessionId)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public function revokeSessions(int $accountId, ?int $exceptSessionId = null): int
    {
        return DB::table('alumni_auth_sessions')->where('account_id', $accountId)->whereNull('revoked_at')->when($exceptSessionId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    // -------------------------------------------------------------------- passwords

    /** Always silent to the caller. */
    public function forgotPassword(string $instituteCode, string $email, ?string $ip, ?Request $request = null): void
    {
        $t = $this->tenantByCode($instituteCode);
        $account = $t ? $this->accountByEmail($t, $email) : null;
        if (! $account || $account->password_hash === null || ! in_array($account->status, ['active', 'unverified'], true)) {
            return;
        }
        [$token, $id] = $this->newToken($account, 'reset_password', (int) config('alumni_auth.reset_ttl_minutes', 60) * 60, null, $ip);
        $this->email($account, 'Reset your alumni portal password',
            "Hello,\n\nUse this link to choose a new password:\n\n" . $this->link((int) $account->sub_institute_id, 'reset-password', $token)
            . "\n\nThe link works once and expires in " . (int) config('alumni_auth.reset_ttl_minutes', 60) . " minutes. If you did not ask for it, ignore this message - your password has not changed.", 'alumni-auth-reset-' . $id);
        $this->audit((int) $account->sub_institute_id, 'password_reset_requested', $account, [], $request, 'Alumnus');
    }

    /** @return array<string, array<int, string>> problems (empty on success) */
    public function resetPassword(string $token, string $password, ?Request $request = null): array
    {
        $row = $this->peekToken($token, 'reset_password');
        if (! $row) {
            return ['token' => ['This link is invalid or has expired. Ask for a new one.']];
        }
        $account = DB::table('alumni_accounts')->where('id', $row->account_id)->first();
        if ($problems = $this->passwordProblems($password, (string) ($account->email ?? ''))) {
            return $problems;   // checked BEFORE the token is used up, so a weak password does not burn the link
        }
        if (! $this->consumeToken($token, 'reset_password')) {
            return ['token' => ['This link is invalid or has expired. Ask for a new one.']];
        }
        // the link reached this mailbox, so it also proves the address
        $set = ['password_hash' => Hash::make($password), 'password_changed_at' => now(), 'failed_attempts' => 0, 'locked_until' => null, 'updated_at' => now()];
        if ($account->status === 'unverified') {
            $set += ['status' => 'active', 'email_verified_at' => $account->email_verified_at ?? now()];
        }
        DB::table('alumni_accounts')->where('id', $account->id)->update($set);
        $this->revokeSessions((int) $account->id);   // every device signs in again
        $this->audit((int) $account->sub_institute_id, 'password_reset', $account, [], $request, 'Alumnus');

        return [];
    }

    /** @return array<string, array<int, string>> problems (empty on success) */
    public function changePassword(object $account, int $sessionId, string $current, string $new, ?Request $request = null): array
    {
        if (! Hash::check($current, (string) $account->password_hash)) {
            return ['current_password' => ['That is not your current password.']];
        }
        if ($problems = $this->passwordProblems($new, (string) $account->email)) {
            return $problems;
        }
        DB::table('alumni_accounts')->where('id', $account->id)->update(['password_hash' => Hash::make($new), 'password_changed_at' => now(), 'updated_at' => now()]);
        $this->revokeSessions((int) $account->id, $sessionId);   // other devices are signed out; this one stays
        $this->audit((int) $account->sub_institute_id, 'password_changed', $account, [], $request, 'Alumnus');

        return [];
    }

    // ---------------------------------------------------------------- invitations

    /**
     * Staff invite an existing alumnus. Returns the link ONCE (it is not stored) and what happened to the email. The alumnus
     * still has to confirm their email address after choosing a password.
     *
     * @return array{link: string, email_status: string, email: string, expires_in_days: int}
     */
    public function invite(int $tenant, int $alumniId, ?int $staffUserId, ?string $ip, ?Request $request = null): array
    {
        $alumnus = $this->alumni->find($tenant, $alumniId);
        if (! $alumnus) {
            throw new AlumniRuleException('Alumni record not found.', 404);
        }
        $email = mb_strtolower(trim((string) $alumnus->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AlumniRuleException('Add a valid email address to this alumnus before inviting them.', 422, 'email');
        }
        if (in_array($alumnus->status, self::BLOCKED_ALUMNUS, true)) {
            throw new AlumniRuleException('This alumnus cannot be invited because their status is ' . str_replace('_', ' ', $alumnus->status) . '.', 409);
        }
        $account = DB::table('alumni_accounts')->where('sub_institute_id', $tenant)->where('alumni_id', $alumniId)->first();
        if ($account && $account->status !== 'invited') {
            throw new AlumniRuleException($account->status === 'disabled' ? 'This portal account is disabled. Enable it instead of inviting again.' : 'This alumnus already has a portal account.', 409);
        }
        if (! $account) {
            if (DB::table('alumni_accounts')->where('sub_institute_id', $tenant)->where('email', $email)->exists()) {
                throw new AlumniRuleException('Another alumnus already uses this email address for a portal account.', 409, 'email');
            }
            $id = (int) DB::table('alumni_accounts')->insertGetId(['sub_institute_id' => $tenant, 'alumni_id' => $alumniId, 'email' => $email, 'status' => 'invited', 'created_via' => 'invite',
                'created_by' => $staffUserId, 'created_at' => now(), 'updated_at' => now()]);
            $account = DB::table('alumni_accounts')->where('id', $id)->first();
        }
        $days = (int) config('alumni_auth.invite_ttl_days', 7);
        [$token, $tokenId] = $this->newToken($account, 'invite', $days * 86400, $staffUserId, $ip);
        $link = $this->link($tenant, 'invite', $token);
        $status = $this->email($account, 'You are invited to the alumni portal',
            "Hello " . ($alumnus->first_name ?? '') . ",\n\nYou have been invited to join the alumni portal. Choose a password here:\n\n{$link}\n\nThe link works once and expires in {$days} days.", 'alumni-auth-invite-' . $tokenId);
        $this->audit($tenant, 'invite', $account, ['email_status' => $status], $request, null, $staffUserId);

        return ['link' => $link, 'email_status' => $status, 'email' => $email, 'expires_in_days' => $days];
    }

    /** What the invitation page may show before a password is chosen: the institute and a masked address. */
    public function checkInvite(string $token): ?array
    {
        $row = $this->peekToken($token, 'invite');
        $account = $row ? DB::table('alumni_accounts')->where('id', $row->account_id)->where('status', 'invited')->first() : null;
        if (! $account) {
            return null;
        }
        [$local, $domain] = array_pad(explode('@', (string) $account->email, 2), 2, '');

        return ['institute_code' => $this->instituteCode((int) $account->sub_institute_id), 'email_hint' => mb_substr($local, 0, 1) . '***@' . $domain];
    }

    /** @return array<string, array<int, string>> problems (empty on success) */
    public function acceptInvite(string $token, string $password, ?string $ip, ?Request $request = null): array
    {
        $row = $this->peekToken($token, 'invite');
        $account = $row ? DB::table('alumni_accounts')->where('id', $row->account_id)->where('status', 'invited')->first() : null;
        if (! $account) {
            return ['token' => ['This invitation is invalid or has expired. Ask the institute to send a new one.']];
        }
        if ($problems = $this->passwordProblems($password, (string) $account->email)) {
            return $problems;
        }
        if (! $this->consumeToken($token, 'invite')) {
            return ['token' => ['This invitation is invalid or has expired. Ask the institute to send a new one.']];
        }
        DB::table('alumni_accounts')->where('id', $account->id)->update(['password_hash' => Hash::make($password), 'status' => 'unverified', 'password_changed_at' => now(), 'updated_at' => now()]);
        $fresh = DB::table('alumni_accounts')->where('id', $account->id)->first();
        [$verify, $id] = $this->newToken($fresh, 'verify_email', (int) config('alumni_auth.verify_email_ttl_hours', 24) * 3600, null, $ip);
        $this->sendVerification($fresh, $verify, $id);
        $this->audit((int) $account->sub_institute_id, 'invite_accepted', $fresh, [], $request, 'Alumnus');

        return [];
    }

    /**
     * Scheduled clean-up so the token and session tables do not grow forever: one-time tokens that were used or expired more
     * than 30 days ago, and login sessions revoked or expired more than 90 days ago. Nothing that is still usable is touched.
     *
     * @return array{tokens: int, sessions: int}
     */
    public function purge(): array
    {
        $tokens = DB::table('alumni_auth_tokens')->where(fn ($w) => $w->where('used_at', '<', now()->subDays(30))->orWhere('expires_at', '<', now()->subDays(30)))->delete();
        $sessions = DB::table('alumni_auth_sessions')->where(fn ($w) => $w->where('revoked_at', '<', now()->subDays(90))->orWhere('expires_at', '<', now()->subDays(90)))->delete();

        return ['tokens' => $tokens, 'sessions' => $sessions];
    }

    // ------------------------------------------------------------ staff operations

    public function setDisabled(int $tenant, int $accountId, bool $disable, ?string $reason, ?int $staffUserId, ?Request $request = null): object
    {
        $account = DB::table('alumni_accounts')->where('sub_institute_id', $tenant)->where('id', $accountId)->first();
        if (! $account) {
            throw new AlumniRuleException('Portal account not found.', 404);
        }
        if ($disable) {
            DB::table('alumni_accounts')->where('id', $accountId)->update(['status' => 'disabled', 'disabled_by' => $staffUserId, 'disabled_reason' => $reason ? mb_substr($reason, 0, 255) : null, 'updated_at' => now()]);
            $this->revokeSessions($accountId);
        } else {
            $restored = $account->password_hash === null ? 'invited' : ($account->email_verified_at === null ? 'unverified' : 'active');
            DB::table('alumni_accounts')->where('id', $accountId)->update(['status' => $restored, 'disabled_by' => null, 'disabled_reason' => null, 'failed_attempts' => 0, 'locked_until' => null, 'updated_at' => now()]);
        }
        $fresh = DB::table('alumni_accounts')->where('id', $accountId)->first();
        $this->audit($tenant, $disable ? 'account_disabled' : 'account_enabled', $fresh, ['reason' => $reason], $request, null, $staffUserId);

        return $fresh;
    }
}
