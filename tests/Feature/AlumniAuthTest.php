<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniAccountApiController as Staff;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * Alumni portal sign-in: registration, invitations, email verification, passwords, tokens, ownership and isolation from the
 * staff/student/parent logins. Portal calls go through the real HTTP stack (routes, middleware, throttles); staff calls use the
 * controller helpers like the rest of the alumni tests. Emails are captured by a recording mail fake so the one-time links can be
 * used - and so the tests can prove those links are not left in the database. In-memory sqlite (see AlumniTestHelpers).
 */
class AlumniAuthTest extends TestCase
{
    use AlumniTestHelpers;

    private const PASSWORD = 'Str0ngPassw0rd';

    private string $secret;

    private ?string $previousStaffSecret = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
        $this->secret = str_repeat('a', 40);
        $this->previousStaffSecret = getenv('JWT_SECRET') === false ? null : (string) getenv('JWT_SECRET');
        putenv('JWT_SECRET=' . str_repeat('s', 40));   // the staff secret: deliberately different from the alumni one
        config(['alumni_auth.jwt_secret' => $this->secret, 'alumni_auth.staff_jwt_secret' => str_repeat('s', 40), 'mail.default' => 'smtp', 'hashing.bcrypt.rounds' => 4, 'alumni_auth.portal_url' => 'https://portal.example.test']);
        Mail::swap(new class {
            /** @var array<int, array{to: ?string, subject: ?string, body: string}> */
            public array $outbox = [];

            public function raw($body, $callback)
            {
                $m = new class {
                    public ?string $to = null;

                    public ?string $subject = null;

                    public function to($a)
                    {
                        $this->to = is_array($a) ? (string) reset($a) : (string) $a;

                        return $this;
                    }

                    public function subject($s)
                    {
                        $this->subject = $s;

                        return $this;
                    }
                };
                $callback($m);
                $this->outbox[] = ['to' => $m->to, 'subject' => $m->subject, 'body' => (string) $body];
            }
        });
    }

    protected function tearDown(): void
    {
        $this->previousStaffSecret === null ? putenv('JWT_SECRET') : putenv('JWT_SECRET=' . $this->previousStaffSecret);
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    private function api(string $path, array $data = [], ?string $bearer = null, string $method = 'POST')
    {
        // headers are passed PER REQUEST: withHeaders() would carry one request's token into the next, which can make a "no token"
        // assertion pass or fail for the wrong reason
        $headers = $bearer ? ['Authorization' => 'Bearer ' . $bearer] : [];

        return $method === 'GET' ? $this->getJson('/api/alumni-portal/' . $path, $headers) : $this->postJson('/api/alumni-portal/' . $path, $data, $headers);
    }

    /** @return array<int, array{to: ?string, subject: ?string, body: string}> */
    private function outbox(): array
    {
        return Mail::getFacadeRoot()->outbox;
    }

    /** The one-time token in the most recent email (or null). */
    private function lastToken(): ?string
    {
        $box = $this->outbox();
        $mail = end($box);

        return $mail && preg_match('/token=([a-f0-9]{64})/', $mail['body'], $m) ? $m[1] : null;
    }

    private function account(object $alumnus, string $status = 'active', string $password = self::PASSWORD, int $tenant = 7): object
    {
        $id = DB::table('alumni_accounts')->insertGetId([
            'sub_institute_id' => $tenant, 'alumni_id' => $alumnus->id, 'email' => mb_strtolower($alumnus->email), 'password_hash' => Hash::make($password), 'status' => $status,
            'email_verified_at' => $status === 'active' ? now() : null, 'created_via' => 'invite', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('alumni_accounts')->where('id', $id)->first();
    }

    /** @return array{access_token: string, refresh_token: string} */
    private function signIn(object $account, string $code = 'TST', string $password = self::PASSWORD): array
    {
        $r = $this->api('login', ['institute_code' => $code, 'email' => $account->email, 'password' => $password]);
        $this->assertSame(200, $r->getStatusCode(), $r->getContent());

        return $r->json('data');
    }

    private function signedInAlumnus(string $first = 'Portal', array $over = [], string $status = 'active', int $tenant = 7): array
    {
        $alumnus = $this->alum($first, $over, $tenant, $status);
        $account = $this->account($alumnus, 'active', self::PASSWORD, $tenant);
        $tokens = $this->signIn($account, $tenant === 7 ? 'TST' : 'OTH');

        return [$alumnus, $account, $tokens];
    }

    private function registerBody(array $over = []): array
    {
        return array_merge(['institute_code' => 'TST', 'first_name' => 'Newly', 'last_name' => 'Registered', 'email' => 'newly@example.org', 'password' => self::PASSWORD, 'mobile' => '9811111111'], $over);
    }

    // ------------------------------------------------------------ configuration

    public function test_portal_refuses_everything_when_the_secret_is_missing_weak_or_the_staff_secret(): void
    {
        $alumnus = $this->alum('Cfg');
        $account = $this->account($alumnus);
        foreach ([null, 'short', str_repeat('s', 40)] as $bad) {   // unset, too short, identical to the staff JWT_SECRET
            config(['alumni_auth.jwt_secret' => $bad]);
            $this->assertSame(503, $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => self::PASSWORD])->getStatusCode(), json_encode($bad));
            $this->assertSame(503, $this->api('register', $this->registerBody())->getStatusCode());
            $this->assertSame(503, $this->api('forgot-password', ['institute_code' => 'TST', 'email' => $account->email])->getStatusCode());
            $this->assertSame(503, $this->api('me', [], $this->mintToken($account, $this->secret), 'GET')->getStatusCode(), 'even a validly signed token is refused when the portal is not configured');
        }
        $this->assertSame(0, DB::table('alumni')->where('source', 'self')->count());
        $this->assertSame(0, DB::table('alumni_auth_tokens')->count());
    }

    /** A correctly built access token, signed with $secret, for a fresh session of this account. */
    private function mintToken(object $account, string $secret, array $over = []): string
    {
        $sid = $over['sid'] ?? DB::table('alumni_auth_sessions')->insertGetId(['sub_institute_id' => $account->sub_institute_id, 'account_id' => $account->id, 'refresh_hash' => hash('sha256', uniqid('', true)),
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);

        return JWT::encode(array_merge(['iss' => 'alumni-portal', 'aud' => 'alumni', 'typ' => 'alumni_access', 'sub' => (int) $account->id, 'aid' => (int) $account->alumni_id,
            'tid' => (int) $account->sub_institute_id, 'sid' => (int) $sid, 'iat' => time(), 'exp' => time() + 600], $over), $secret, 'HS256');
    }

    // ------------------------------------------------------------ self-registration

    public function test_self_registration_needs_email_confirmation_and_then_staff_verification(): void
    {
        $r = $this->api('register', $this->registerBody());
        $this->assertSame(202, $r->getStatusCode(), $r->getContent());

        $alumnus = DB::table('alumni')->where('email', 'newly@example.org')->first();
        $this->assertSame(['self', 'pending_verification', 7], [$alumnus->source, $alumnus->status, (int) $alumnus->sub_institute_id]);
        $account = DB::table('alumni_accounts')->where('alumni_id', $alumnus->id)->first();
        $this->assertSame(['unverified', 'self'], [$account->status, $account->created_via]);
        $this->assertNotSame(self::PASSWORD, $account->password_hash);
        $this->assertTrue(Hash::check(self::PASSWORD, $account->password_hash), 'stored as a hash that verifies');

        $this->assertCount(1, $this->outbox());
        $this->assertSame('newly@example.org', $this->outbox()[0]['to']);
        $token = $this->lastToken();
        $this->assertNotNull($token);

        // cannot sign in before confirming the email
        $early = $this->api('login', ['institute_code' => 'TST', 'email' => 'newly@example.org', 'password' => self::PASSWORD]);
        $this->assertSame(403, $early->getStatusCode());
        $this->assertStringContainsString('Confirm your email', $early->json('message'));
        $this->assertSame(0, DB::table('alumni_auth_sessions')->count());

        $this->assertSame(200, $this->api('verify-email', ['token' => $token])->getStatusCode());
        $this->assertSame('active', DB::table('alumni_accounts')->where('id', $account->id)->value('status'));
        $tokens = $this->signIn(DB::table('alumni_accounts')->where('id', $account->id)->first());

        // signed in, but staff have not verified the person yet: standing only, no features
        $me = $this->api('me', [], $tokens['access_token'], 'GET');
        $this->assertSame(200, $me->getStatusCode());
        $this->assertFalse($me->json('data.standing.features_enabled'));
        $this->assertStringContainsString('waiting for the institute', $me->json('data.standing.message'));
        $this->assertSame(403, $this->api('events', [], $tokens['access_token'], 'GET')->getStatusCode());
        $this->assertSame(403, $this->api('me/update', ['city' => 'Pune'], $tokens['access_token'])->getStatusCode());

        // staff verify the person with the existing alumni verification -> features open
        $this->svc->verify(7, (int) $alumnus->id, true, null, 1);
        $me2 = $this->api('me', [], $tokens['access_token'], 'GET');
        $this->assertTrue($me2->json('data.standing.features_enabled'));
        $this->assertSame(200, $this->api('events', [], $tokens['access_token'], 'GET')->getStatusCode());
    }

    public function test_the_emailed_link_is_never_left_in_the_database(): void
    {
        $this->api('register', $this->registerBody());
        $token = $this->lastToken();
        $this->assertNotNull($token);

        foreach (['alumni_auth_tokens', 'alumni_message_log', 'platform_notification_log', 'platform_audit_log'] as $table) {
            $this->assertStringNotContainsString($token, json_encode(DB::table($table)->get()), "{$table} must not contain the live token");
        }
        $this->assertSame(hash('sha256', $token), DB::table('alumni_auth_tokens')->value('token_hash'), 'only the hash is kept');
        $this->assertStringContainsString('removed from the log', (string) DB::table('platform_notification_log')->value('body'));
        // and the password is not in any log either
        $this->assertStringNotContainsString(self::PASSWORD, json_encode([DB::table('platform_audit_log')->get(), DB::table('platform_notification_log')->get(), DB::table('alumni_message_log')->get()]));
    }

    public function test_registration_answers_the_same_whether_or_not_the_person_already_exists(): void
    {
        $existing = $this->alum('Already', ['email' => 'already@example.org', 'mobile' => '9822222222']);
        $held = $this->alum('Holder', ['email' => 'holder@example.org']);
        $this->account($held);
        $before = ['alumni' => DB::table('alumni')->count(), 'accounts' => DB::table('alumni_accounts')->count()];

        $fresh = $this->api('register', $this->registerBody(['email' => 'brandnew@example.org', 'mobile' => '9833333333']));
        $emailTaken = $this->api('register', $this->registerBody(['email' => 'already@example.org']));        // an alumnus with this email exists, no account
        $mobileTaken = $this->api('register', $this->registerBody(['email' => 'other@example.org', 'mobile' => '9822222222']));
        $hasAccount = $this->api('register', $this->registerBody(['email' => 'holder@example.org']));
        $wrongInstitute = $this->api('register', $this->registerBody(['institute_code' => 'NOPE', 'email' => 'x@example.org']));
        $otherInstitute = $this->api('register', $this->registerBody(['institute_code' => 'OTH', 'email' => 'already@example.org']));   // same email, different institute: allowed

        foreach ([$fresh, $emailTaken, $mobileTaken, $hasAccount, $wrongInstitute, $otherInstitute] as $r) {
            $this->assertSame(202, $r->getStatusCode());
            $this->assertSame($fresh->json('message'), $r->json('message'), 'identical wording, so the reply cannot be used to find out who is an alumnus');
            $this->assertNull($r->json('data'));
        }
        $this->assertSame($before['alumni'] + 2, DB::table('alumni')->count(), 'only the genuinely new people (one per institute) were created');
        $this->assertSame($before['accounts'] + 2, DB::table('alumni_accounts')->count());
        $this->assertSame(0, DB::table('alumni')->where('id', $existing->id)->where('source', 'self')->count(), 'the existing record was not touched or linked');
        $this->assertNull(DB::table('alumni_accounts')->where('alumni_id', $existing->id)->first(), 'registering never attaches a login to an existing alumni record');
        $this->assertSame(1, DB::table('platform_audit_log')->where('action', 'register_existing_account')->count());
        $this->assertSame(3, DB::table('platform_audit_log')->whereIn('action', ['register_existing_account', 'register_duplicate'])->where('actor_name', 'Visitor (not signed in)')->count(), 'even an anonymous attempt says who it was');
        $this->assertSame(0, DB::table('platform_audit_log')->where('component', 'alumni.auth')->whereNull('actor_name')->whereNull('actor_user_id')->count());
    }

    public function test_registration_input_is_validated_and_weak_passwords_are_refused(): void
    {
        foreach ([['first_name' => ''], ['last_name' => ''], ['email' => 'not-an-email'], ['institute_code' => ''], ['password' => 'short1'], ['password' => 'onlyletterspassword'], ['password' => '1234567890123'], ['password' => 'x' . str_repeat('1', 80)]] as $i => $bad) {
            $r = $this->api('register', $this->registerBody($bad + ['email' => "v{$i}@example.org"]));   // a different email each time: registration is limited per email
            $this->assertSame(422, $r->getStatusCode(), json_encode($bad));
        }
        $this->assertSame(422, $this->api('register', $this->registerBody(['email' => 'same@example.org', 'password' => 'same@example.org']))->getStatusCode(), 'password equal to the email');
        $this->assertSame(0, DB::table('alumni_accounts')->count());
        $this->assertSame([], $this->outbox());
    }

    // -------------------------------------------------------- one-time tokens

    public function test_a_verification_link_works_once_and_expired_or_garbage_ones_never(): void
    {
        $this->api('register', $this->registerBody());
        $token = $this->lastToken();

        $this->assertSame(422, $this->api('verify-email', ['token' => 'garbage'])->getStatusCode());
        $this->assertSame(422, $this->api('verify-email', ['token' => str_repeat('0', 64)])->getStatusCode());
        $this->assertSame(200, $this->api('verify-email', ['token' => $token])->getStatusCode());
        $this->assertSame(422, $this->api('verify-email', ['token' => $token])->getStatusCode(), 'single use');

        $this->api('register', $this->registerBody(['email' => 'second@example.org', 'mobile' => '9844444444']));
        $expired = $this->lastToken();
        DB::table('alumni_auth_tokens')->where('token_hash', hash('sha256', $expired))->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(422, $this->api('verify-email', ['token' => $expired])->getStatusCode(), 'expired');
        $this->assertSame('unverified', DB::table('alumni_accounts')->where('email', 'second@example.org')->value('status'));
    }

    public function test_resending_the_confirmation_replaces_the_old_link_and_is_silent_about_who_has_an_account(): void
    {
        $this->api('register', $this->registerBody());
        $old = $this->lastToken();
        $known = $this->api('resend-verification', ['institute_code' => 'TST', 'email' => 'newly@example.org']);
        $unknown = $this->api('resend-verification', ['institute_code' => 'TST', 'email' => 'nobody@example.org']);
        $this->assertSame([202, 202], [$known->getStatusCode(), $unknown->getStatusCode()]);
        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertCount(2, $this->outbox(), 'only the real account got a new email');
        $new = $this->lastToken();
        $this->assertNotSame($old, $new);
        $this->assertSame(422, $this->api('verify-email', ['token' => $old])->getStatusCode(), 'the old link stopped working');
        $this->assertSame(200, $this->api('verify-email', ['token' => $new])->getStatusCode());
    }

    // ----------------------------------------------------------------- sign in

    public function test_sign_in_fails_the_same_way_for_every_wrong_detail(): void
    {
        $alumnus = $this->alum('Login');
        $account = $this->account($alumnus);
        $good = ['institute_code' => 'TST', 'email' => $account->email, 'password' => self::PASSWORD];

        $wrongPassword = $this->api('login', ['password' => 'Wrong-password1'] + $good);
        $unknownEmail = $this->api('login', ['email' => 'nobody@example.org'] + $good);
        $wrongInstitute = $this->api('login', ['institute_code' => 'NOPE'] + $good);
        $otherInstitute = $this->api('login', ['institute_code' => 'OTH'] + $good);   // right person, wrong institute
        foreach ([$wrongPassword, $unknownEmail, $wrongInstitute, $otherInstitute] as $r) {
            $this->assertSame(401, $r->getStatusCode());
            $this->assertSame(\App\Services\Alumni\AlumniAuthService::GENERIC_SIGN_IN_FAILURE, $r->json('message'));
        }
        $ok = $this->api('login', ['email' => strtoupper($account->email), 'institute_code' => 'tst'] + $good);   // email and institute code are case-insensitive
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame('Bearer', $ok->json('data.token_type'));
        $this->assertSame(1800, $ok->json('data.expires_in'));
        $this->assertSame(['access_token', 'expires_in', 'refresh_token', 'token_type'], collect($ok->json('data'))->keys()->sort()->values()->all(), 'no profile, no ids, no hash in the sign-in reply');
    }

    public function test_five_wrong_passwords_lock_the_account_for_a_while(): void
    {
        $account = $this->account($this->alum('Lock'));
        $try = fn (string $pw) => $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => $pw]);

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(401, $try('Wrong-password' . $i)->getStatusCode());
        }
        $this->assertNotNull(DB::table('alumni_accounts')->where('id', $account->id)->value('locked_until'));
        $locked = $try(self::PASSWORD);   // the RIGHT password, while locked
        $this->assertSame(401, $locked->getStatusCode());
        $this->assertSame(\App\Services\Alumni\AlumniAuthService::GENERIC_SIGN_IN_FAILURE, $locked->json('message'), 'a lock looks like any other failure');
        $this->assertSame(1, DB::table('platform_audit_log')->where('action', 'account_locked')->count());

        DB::table('alumni_accounts')->where('id', $account->id)->update(['locked_until' => now()->subMinute()]);
        $this->assertSame(200, $try(self::PASSWORD)->getStatusCode(), 'the lock expires');
        $this->assertSame(0, (int) DB::table('alumni_accounts')->where('id', $account->id)->value('failed_attempts'));
    }

    public function test_sign_in_is_rate_limited_per_email(): void
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->api('login', ['institute_code' => 'TST', 'email' => 'flood@example.org', 'password' => 'Whatever-1234'])->getStatusCode();
        }
        $this->assertSame(401, $codes[0]);
        $this->assertContains(429, $codes, 'more than 8 tries a minute for one email is refused outright');
        $this->assertSame(200, $this->api('login', ['institute_code' => 'TST', 'email' => $this->account($this->alum('Other'))->email, 'password' => self::PASSWORD])->getStatusCode(), 'another email is unaffected');
    }

    public function test_blocked_alumni_and_disabled_accounts_cannot_sign_in(): void
    {
        foreach (['blocked', 'deceased', 'opted_out'] as $status) {
            $alumnus = $this->alum('St' . $status, [], 7, $status);
            $account = $this->account($alumnus);
            $this->assertSame(401, $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => self::PASSWORD])->getStatusCode(), $status);
        }
        $disabled = $this->account($this->alum('Disabled'), 'disabled');
        $this->assertSame(401, $this->api('login', ['institute_code' => 'TST', 'email' => $disabled->email, 'password' => self::PASSWORD])->getStatusCode());
        $invited = DB::table('alumni_accounts')->insertGetId(['sub_institute_id' => 7, 'alumni_id' => $this->alum('Inv')->id, 'email' => 'inv@example.org', 'status' => 'invited', 'created_via' => 'invite', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(401, $this->api('login', ['institute_code' => 'TST', 'email' => 'inv@example.org', 'password' => self::PASSWORD])->getStatusCode(), 'no password set yet');
        $this->assertSame(0, DB::table('alumni_auth_sessions')->count());
    }

    // -------------------------------------------------------- access tokens

    public function test_access_tokens_are_checked_strictly(): void
    {
        [$alumnus, $account, $tokens] = $this->signedInAlumnus();
        $this->assertSame(200, $this->api('me', [], $tokens['access_token'], 'GET')->getStatusCode());

        $forgedStaffKey = $this->mintToken($account, str_repeat('s', 40));                                  // signed with the STAFF secret
        $wrongKey = $this->mintToken($account, str_repeat('z', 40));
        $expired = $this->mintToken($account, $this->secret, ['exp' => time() - 10, 'iat' => time() - 700]);
        $wrongAud = $this->mintToken($account, $this->secret, ['aud' => 'staff']);
        $wrongType = $this->mintToken($account, $this->secret, ['typ' => 'alumni_refresh']);
        $wrongTenant = $this->mintToken($account, $this->secret, ['tid' => 8]);
        $wrongAlumnus = $this->mintToken($account, $this->secret, ['aid' => (int) $alumnus->id + 1]);
        $noSession = $this->mintToken($account, $this->secret, ['sid' => 999999]);
        $hs512 = JWT::encode(['iss' => 'alumni-portal', 'aud' => 'alumni', 'typ' => 'alumni_access', 'sub' => (int) $account->id, 'aid' => (int) $account->alumni_id, 'tid' => 7, 'sid' => 1, 'exp' => time() + 600], $this->secret, 'HS512');
        $none = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=') . '.' . rtrim(strtr(base64_encode(json_encode(['aud' => 'alumni', 'typ' => 'alumni_access', 'sub' => (int) $account->id, 'aid' => (int) $account->alumni_id, 'tid' => 7, 'sid' => 1, 'exp' => time() + 600])), '+/', '-_'), '=') . '.';
        foreach (['staff secret' => $forgedStaffKey, 'wrong secret' => $wrongKey, 'expired' => $expired, 'wrong audience' => $wrongAud, 'wrong type' => $wrongType, 'wrong tenant' => $wrongTenant,
            'wrong alumnus' => $wrongAlumnus, 'no such session' => $noSession, 'HS512' => $hs512, 'alg none' => $none, 'garbage' => 'abc.def.ghi'] as $why => $bad) {
            $this->assertSame(401, $this->api('me', [], $bad, 'GET')->getStatusCode(), $why);
        }
        $this->assertSame(401, $this->api('me', [], null, 'GET')->getStatusCode(), 'no token');
    }

    public function test_changes_take_effect_immediately_not_when_the_token_expires(): void
    {
        [$alumnus, $account, $tokens] = $this->signedInAlumnus();
        $me = fn () => $this->api('me', [], $tokens['access_token'], 'GET')->getStatusCode();
        $this->assertSame(200, $me());

        DB::table('alumni')->where('id', $alumnus->id)->update(['status' => 'blocked']);
        $this->assertSame(401, $me(), 'blocked alumnus');
        DB::table('alumni')->where('id', $alumnus->id)->update(['status' => 'active']);
        $this->assertSame(200, $me());
        DB::table('alumni_accounts')->where('id', $account->id)->update(['status' => 'disabled']);
        $this->assertSame(401, $me(), 'disabled account');
        DB::table('alumni_accounts')->where('id', $account->id)->update(['status' => 'active']);
        $this->assertSame(200, $me());
        DB::table('alumni_auth_sessions')->where('account_id', $account->id)->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(401, $me(), 'session past its absolute end');
    }

    public function test_logout_ends_the_session_at_once(): void
    {
        [, , $tokens] = $this->signedInAlumnus();
        $this->assertSame(200, $this->api('logout', [], $tokens['access_token'])->getStatusCode());
        $this->assertSame(401, $this->api('me', [], $tokens['access_token'], 'GET')->getStatusCode());
        $this->assertSame(401, $this->api('refresh', ['refresh_token' => $tokens['refresh_token']])->getStatusCode(), 'the refresh token died with the session');
    }

    // ---------------------------------------------------------- refresh tokens

    public function test_refresh_rotates_and_reuse_of_an_old_token_revokes_the_login(): void
    {
        [, $account, $first] = $this->signedInAlumnus();

        $second = $this->api('refresh', ['refresh_token' => $first['refresh_token']]);
        $this->assertSame(200, $second->getStatusCode());
        $this->assertNotSame($first['refresh_token'], $second->json('data.refresh_token'));
        $this->assertSame(200, $this->api('me', [], $second->json('data.access_token'), 'GET')->getStatusCode());
        $this->assertSame(hash('sha256', $second->json('data.refresh_token')), DB::table('alumni_auth_sessions')->where('account_id', $account->id)->value('refresh_hash'));

        // the first refresh token is presented again (a copy was stolen): the whole login is revoked
        $replay = $this->api('refresh', ['refresh_token' => $first['refresh_token']]);
        $this->assertSame(401, $replay->getStatusCode());
        $this->assertNotNull(DB::table('alumni_auth_sessions')->where('account_id', $account->id)->value('revoked_at'));
        $this->assertSame(401, $this->api('refresh', ['refresh_token' => $second->json('data.refresh_token')])->getStatusCode(), 'the legitimate holder must sign in again too');
        $this->assertSame(401, $this->api('me', [], $second->json('data.access_token'), 'GET')->getStatusCode());
        $this->assertSame(1, DB::table('platform_audit_log')->where('action', 'refresh_token_reuse')->count());
        foreach (['', 'nonsense', str_repeat('a', 64)] as $bad) {
            $this->assertContains($this->api('refresh', ['refresh_token' => $bad])->getStatusCode(), [401, 422]);
        }
    }

    // ---------------------------------------------------------------- passwords

    public function test_forgot_and_reset_password_flow(): void
    {
        [$alumnus, $account, $tokens] = $this->signedInAlumnus();
        $known = $this->api('forgot-password', ['institute_code' => 'TST', 'email' => $account->email]);
        $unknown = $this->api('forgot-password', ['institute_code' => 'TST', 'email' => 'nobody@example.org']);
        $this->assertSame([202, 202], [$known->getStatusCode(), $unknown->getStatusCode()]);
        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertCount(1, $this->outbox(), 'only the real account is emailed');
        $first = $this->lastToken();

        $this->api('forgot-password', ['institute_code' => 'TST', 'email' => $account->email]);
        $second = $this->lastToken();
        $this->assertSame(422, $this->api('reset-password', ['token' => $first, 'password' => 'Brand-new-pass1'])->getStatusCode(), 'a newer request replaced the older link');

        $weak = $this->api('reset-password', ['token' => $second, 'password' => 'short']);
        $this->assertSame(422, $weak->getStatusCode());
        $this->assertNotNull($weak->json('errors.password'));
        $this->assertNotNull(DB::table('alumni_auth_tokens')->where('token_hash', hash('sha256', $second))->whereNull('used_at')->first(), 'a weak password did not burn the link');

        $ok = $this->api('reset-password', ['token' => $second, 'password' => 'Brand-new-pass1']);
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame(422, $this->api('reset-password', ['token' => $second, 'password' => 'Another-pass-22'])->getStatusCode(), 'single use');

        $this->assertSame(401, $this->api('me', [], $tokens['access_token'], 'GET')->getStatusCode(), 'every existing sign-in ended');
        $this->assertSame(401, $this->api('refresh', ['refresh_token' => $tokens['refresh_token']])->getStatusCode());
        $this->assertSame(401, $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => self::PASSWORD])->getStatusCode(), 'old password no longer works');
        $this->assertSame(200, $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => 'Brand-new-pass1'])->getStatusCode());
        $this->assertSame(2, DB::table('platform_audit_log')->where('action', 'password_reset_requested')->count());
    }

    public function test_an_expired_reset_link_is_refused_and_a_reset_also_confirms_an_unconfirmed_mailbox(): void
    {
        $account = $this->account($this->alum('Unconfirmed'), 'unverified');
        $this->api('forgot-password', ['institute_code' => 'TST', 'email' => $account->email]);
        $token = $this->lastToken();
        DB::table('alumni_auth_tokens')->where('token_hash', hash('sha256', $token))->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(422, $this->api('reset-password', ['token' => $token, 'password' => 'Brand-new-pass1'])->getStatusCode());

        $this->api('forgot-password', ['institute_code' => 'TST', 'email' => $account->email]);
        $this->assertSame(200, $this->api('reset-password', ['token' => $this->lastToken(), 'password' => 'Brand-new-pass1'])->getStatusCode());
        $fresh = DB::table('alumni_accounts')->where('id', $account->id)->first();
        $this->assertSame('active', $fresh->status, 'the link reached the mailbox, so it proved it');
        $this->assertNotNull($fresh->email_verified_at);
    }

    public function test_change_password_checks_the_old_one_and_signs_out_other_devices_only(): void
    {
        [, $account, $here] = $this->signedInAlumnus();
        $there = $this->signIn($account);   // a second device

        $this->assertSame(422, $this->api('change-password', ['current_password' => 'Not-my-password1', 'password' => 'Brand-new-pass1'], $here['access_token'])->getStatusCode());
        $this->assertSame(422, $this->api('change-password', ['current_password' => self::PASSWORD, 'password' => 'short'], $here['access_token'])->getStatusCode());
        $this->assertSame(200, $this->api('change-password', ['current_password' => self::PASSWORD, 'password' => 'Brand-new-pass1'], $here['access_token'])->getStatusCode());

        $this->assertSame(200, $this->api('me', [], $here['access_token'], 'GET')->getStatusCode(), 'this device stays signed in');
        $this->assertSame(401, $this->api('me', [], $there['access_token'], 'GET')->getStatusCode(), 'the other one is signed out');
        $this->assertSame(401, $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => self::PASSWORD])->getStatusCode());
    }

    // -------------------------------------------------------------- invitations

    public function test_staff_invite_then_the_alumnus_sets_a_password_and_confirms_their_email(): void
    {
        $alumnus = $this->alum('Invitee', [], 7, 'registered');

        [$s, $b] = $this->go(Staff::class, 'invite', [], 7, 'Admin', [$alumnus->id]);
        $this->assertSame(201, $s, json_encode($b));
        $link = $b['data']['link'];
        $this->assertStringStartsWith('https://portal.example.test/alumni-portal/invite?token=', $link);
        $this->assertStringContainsString('i=TST', $link);
        $this->assertSame('sent', $b['data']['email_status']);
        $token = $this->lastToken();
        $this->assertStringContainsString($token, $link);
        $this->assertSame($alumnus->email, $this->outbox()[0]['to']);
        $this->assertNull(DB::table('alumni_accounts')->where('alumni_id', $alumnus->id)->value('password_hash'));

        $check = $this->api('invite/check', ['token' => $token]);
        $this->assertSame(200, $check->getStatusCode());
        $this->assertSame('TST', $check->json('data.institute_code'));
        $this->assertStringContainsString('***@', $check->json('data.email_hint'));
        $this->assertStringNotContainsString($alumnus->email, $check->getContent(), 'the invitation page never shows the full address');

        $this->assertSame(422, $this->api('invite/accept', ['token' => $token, 'password' => 'weak'])->getStatusCode());
        $this->assertSame(200, $this->api('invite/check', ['token' => $token])->getStatusCode(), 'a weak password did not burn the invitation');
        $this->assertSame(200, $this->api('invite/accept', ['token' => $token, 'password' => self::PASSWORD])->getStatusCode());
        $this->assertSame(422, $this->api('invite/accept', ['token' => $token, 'password' => self::PASSWORD])->getStatusCode(), 'single use');
        $this->assertSame(422, $this->api('invite/check', ['token' => $token])->getStatusCode());

        // choosing a password does not prove the mailbox: the person must still confirm the email
        $this->assertSame('unverified', DB::table('alumni_accounts')->where('alumni_id', $alumnus->id)->value('status'));
        $this->assertSame(403, $this->api('login', ['institute_code' => 'TST', 'email' => $alumnus->email, 'password' => self::PASSWORD])->getStatusCode());
        $this->assertSame(200, $this->api('verify-email', ['token' => $this->lastToken()])->getStatusCode());
        $tokens = $this->api('login', ['institute_code' => 'TST', 'email' => $alumnus->email, 'password' => self::PASSWORD]);
        $this->assertSame(200, $tokens->getStatusCode());
        $me = $this->api('me', [], $tokens->json('data.access_token'), 'GET');
        $this->assertTrue($me->json('data.standing.features_enabled'), 'a registered alumnus (staff-created) needs no further verification');
    }

    public function test_a_new_invitation_replaces_the_old_link_and_invitations_are_refused_when_they_make_no_sense(): void
    {
        $alumnus = $this->alum('Twice', [], 7, 'registered');
        $this->go(Staff::class, 'invite', [], 7, 'Admin', [$alumnus->id]);
        $first = $this->lastToken();
        $this->go(Staff::class, 'invite', [], 7, 'Admin', [$alumnus->id]);
        $second = $this->lastToken();
        $this->assertSame(422, $this->api('invite/check', ['token' => $first])->getStatusCode(), 'the old link stopped working');
        $this->assertSame(200, $this->api('invite/check', ['token' => $second])->getStatusCode());
        $this->assertSame(1, DB::table('alumni_accounts')->where('alumni_id', $alumnus->id)->count(), 're-inviting does not create a second account');

        DB::table('alumni_auth_tokens')->where('token_hash', hash('sha256', $second))->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(422, $this->api('invite/accept', ['token' => $second, 'password' => self::PASSWORD])->getStatusCode(), 'expired invitation');

        $noEmail = $this->alum('NoMail');
        DB::table('alumni')->where('id', $noEmail->id)->update(['email' => null]);
        $this->assertSame(422, $this->go(Staff::class, 'invite', [], 7, 'Admin', [$noEmail->id])[0]);
        foreach (['blocked', 'deceased', 'opted_out'] as $status) {
            $this->assertSame(409, $this->go(Staff::class, 'invite', [], 7, 'Admin', [$this->alum('S' . $status, [], 7, $status)->id])[0], $status);
        }
        $has = $this->account($this->alum('HasOne'));
        $this->assertSame(409, $this->go(Staff::class, 'invite', [], 7, 'Admin', [$has->alumni_id])[0], 'already has an account');
        $dup = $this->alum('SameMail', ['email' => 'shared@example.org']);
        $this->account($this->alum('Holder2', ['email' => 'holder2@example.org']));
        DB::table('alumni_accounts')->where('email', 'holder2@example.org')->update(['email' => 'shared@example.org']);
        $this->assertSame(409, $this->go(Staff::class, 'invite', [], 7, 'Admin', [$dup->id])[0], 'another account already uses this email at this institute');
        $this->assertSame(404, $this->go(Staff::class, 'invite', [], 8, 'Admin', [$alumnus->id])[0], 'another institute cannot invite this alumnus');
    }

    public function test_only_alumni_managers_may_invite_or_control_accounts(): void
    {
        $alumnus = $this->alum('Gate', [], 7, 'registered');
        $account = $this->account($this->alum('Acct'));
        foreach (['Teacher', 'Accountant', 'Peon'] as $profile) {
            $this->assertSame(403, $this->go(Staff::class, 'invite', [], 7, $profile, [$alumnus->id])[0], $profile);
            $this->assertSame(403, $this->go(Staff::class, 'index', [], 7, $profile)[0], $profile);
            $this->assertSame(403, $this->go(Staff::class, 'disable', [], 7, $profile, [$account->id])[0], $profile);
            $this->assertSame(403, $this->go(Staff::class, 'show', [], 7, $profile, [$alumnus->id])[0], $profile);
        }
        $this->assertSame(0, DB::table('alumni_auth_tokens')->count());
        $this->assertSame('active', DB::table('alumni_accounts')->where('id', $account->id)->value('status'));
        $this->assertSame(201, $this->go(Staff::class, 'invite', [], 7, 'Alumni Coordinator', [$alumnus->id])[0]);
    }

    // ----------------------------------------------------------- staff control

    public function test_staff_can_disable_enable_and_sign_out_an_account_and_never_see_secrets(): void
    {
        [$alumnus, $account, $tokens] = $this->signedInAlumnus();

        [$s, $b] = $this->go(Staff::class, 'show', [], 7, 'Admin', [$alumnus->id]);
        $this->assertSame(200, $s);
        $this->assertSame('active', $b['data']['status']);
        $this->assertStringNotContainsString('password', json_encode($b));
        $this->assertStringNotContainsString($account->password_hash, json_encode($b));

        $this->assertSame(200, $this->go(Staff::class, 'revokeSessions', [], 7, 'Admin', [$account->id])[0]);
        $this->assertSame(401, $this->api('me', [], $tokens['access_token'], 'GET')->getStatusCode(), 'signed out everywhere');
        $again = $this->signIn($account);

        [$ds, $db] = $this->go(Staff::class, 'disable', ['reason' => 'Requested by the alumnus'], 7, 'Admin', [$account->id]);
        $this->assertSame(200, $ds, json_encode($db));
        $this->assertSame('disabled', $db['data']['status']);
        $this->assertSame(401, $this->api('me', [], $again['access_token'], 'GET')->getStatusCode(), 'disabling takes effect at once');
        $this->assertSame(401, $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => self::PASSWORD])->getStatusCode());
        $this->assertSame(0, DB::table('alumni_auth_sessions')->where('account_id', $account->id)->whereNull('revoked_at')->count());

        $this->assertSame('active', $this->go(Staff::class, 'enable', [], 7, 'Admin', [$account->id])[1]['data']['status']);
        $this->assertSame(200, $this->api('login', ['institute_code' => 'TST', 'email' => $account->email, 'password' => self::PASSWORD])->getStatusCode());
        foreach (['account_disabled', 'account_enabled', 'sessions_revoked'] as $action) {
            $this->assertSame(1, DB::table('platform_audit_log')->where('component', 'alumni.auth')->where('action', $action)->count(), $action);
        }
        $this->assertSame(55, (int) DB::table('platform_audit_log')->where('action', 'account_disabled')->value('actor_user_id'), 'the staff member is recorded');
    }

    public function test_staff_see_only_their_own_institutes_accounts(): void
    {
        $mine = $this->account($this->alum('Mine'));
        $theirs = $this->account($this->alum('Theirs', [], 8), 'active', self::PASSWORD, 8);

        [, $list] = $this->go(Staff::class, 'index', [], 7, 'Admin');
        $this->assertSame([(int) $mine->id], collect($list['data']['rows'])->pluck('id')->map(fn ($i) => (int) $i)->all());
        foreach (['disable', 'enable', 'revokeSessions', 'resendVerification'] as $m) {
            $this->assertSame(404, $this->go(Staff::class, $m, [], 7, 'Admin', [$theirs->id])[0], $m);
        }
        $this->assertSame(404, $this->go(Staff::class, 'show', [], 7, 'Admin', [$theirs->alumni_id])[0]);
        $this->assertSame('active', DB::table('alumni_accounts')->where('id', $theirs->id)->value('status'), 'untouched');
        $this->assertSame(422, $this->go(Staff::class, 'index', ['status' => 'weird'], 7, 'Admin')[0]);

        $unverified = $this->account($this->alum('Unv'), 'unverified');
        $this->assertSame(200, $this->go(Staff::class, 'resendVerification', [], 7, 'Admin', [$unverified->id])[0]);
        $this->assertSame(409, $this->go(Staff::class, 'resendVerification', [], 7, 'Admin', [$mine->id])[0], 'only an unconfirmed account');
    }

    // ----------------------------------------------------- ownership and scope

    public function test_an_alumnus_can_only_see_and_change_their_own_record(): void
    {
        [$a, $accountA, $tokensA] = $this->signedInAlumnus('Alpha', ['city' => 'Pune']);
        [$b] = $this->signedInAlumnus('Bravo', ['city' => 'Surat']);

        $me = $this->api('me', [], $tokensA['access_token'], 'GET');
        $this->assertSame($a->alumni_code, $me->json('data.profile.alumni_code'));
        $this->assertStringNotContainsString($b->alumni_code, $me->getContent());

        // naming someone else, another institute, or protected columns in the body changes nothing but the allowed fields
        $r = $this->api('me/update', [
            'alumni_id' => $b->id, 'id' => $b->id, 'account_id' => 999, 'sub_institute_id' => 8, 'tid' => 8, 'status' => 'verified', 'first_name' => 'Hijacked', 'last_name' => 'Name',
            'email' => 'attacker@example.org', 'batch' => '1999-00', 'dob' => '1990-01-01', 'student_id' => 12345, 'alumni_code' => 'ALM-FAKE', 'city' => 'Mumbai', 'mobile' => '9877777777',
        ], $tokensA['access_token']);
        $this->assertSame(200, $r->getStatusCode(), $r->getContent());

        $aFresh = DB::table('alumni')->where('id', $a->id)->first();
        $this->assertSame(['Mumbai', '9877777777'], [$aFresh->city, $aFresh->mobile], 'the allowed fields changed');
        $this->assertSame([$a->first_name, $a->last_name, $a->email, $a->batch, $a->status, 7], [$aFresh->first_name, $aFresh->last_name, $aFresh->email, $aFresh->batch, $aFresh->status, (int) $aFresh->sub_institute_id], 'the protected ones did not');
        $this->assertSame('Surat', DB::table('alumni')->where('id', $b->id)->value('city'), 'the other alumnus was not touched');
        $this->assertSame($accountA->email, DB::table('alumni_accounts')->where('id', $accountA->id)->value('email'), 'sign-in email cannot be changed here');
        $this->assertSame(422, $this->api('me/update', ['linkedin_url' => 'not a url'], $tokensA['access_token'])->getStatusCode());
        $this->assertSame(422, $this->api('me/update', ['first_name' => 'Only protected'], $tokensA['access_token'])->getStatusCode(), 'nothing editable was sent');
        $this->assertSame(1, DB::table('platform_audit_log')->where('component', 'alumni.portal')->where('action', 'self_update')->count());
    }

    public function test_privacy_and_consent_choices_apply_to_the_signed_in_alumnus_only(): void
    {
        [$a, , $tokensA] = $this->signedInAlumnus('Priv');
        [$b] = $this->signedInAlumnus('Other');

        $before = $this->api('me/privacy', [], $tokensA['access_token'], 'GET');
        $this->assertSame(200, $before->getStatusCode());
        $this->assertContains('email', array_column($before->json('data.fields'), 'key'));

        $save = $this->api('me/privacy', ['fields' => ['email' => 'private', 'location' => 'public'], 'consents' => ['consent_email' => true], 'alumni_id' => $b->id], $tokensA['access_token']);
        $this->assertSame(200, $save->getStatusCode(), $save->getContent());
        $this->assertSame('private', DB::table('alumni_privacy')->where('alumni_id', $a->id)->where('field_key', 'email')->value('visibility'));
        $this->assertSame(1, (int) DB::table('alumni')->where('id', $a->id)->value('consent_email'));
        $this->assertSame(0, DB::table('alumni_privacy')->where('alumni_id', $b->id)->count(), 'the other alumnus has no stored choices');
        $this->assertSame(0, (int) DB::table('alumni')->where('id', $b->id)->value('consent_email'));
        $this->assertSame(422, $this->api('me/privacy', ['fields' => ['password' => 'private']], $tokensA['access_token'])->getStatusCode(), 'unknown setting');
        $this->assertSame(422, $this->api('me/privacy', ['fields' => ['email' => 'everyone']], $tokensA['access_token'])->getStatusCode(), 'unknown level');
        $this->assertSame(422, $this->api('me/privacy', ['consents' => ['is_admin' => true]], $tokensA['access_token'])->getStatusCode(), 'only the four channel consents');
    }

    public function test_events_self_service_respects_ownership_audience_capacity_and_payment(): void
    {
        [$a, , $tokensA] = $this->signedInAlumnus('Evt');
        [$b, , $tokensB] = $this->signedInAlumnus('Evo');
        $make = fn (array $over = []) => (int) DB::table('alumni_events')->insertGetId(array_merge(['sub_institute_id' => 7, 'name' => 'Reunion', 'event_type' => 'alumni_meet', 'event_date' => now()->addDays(5)->toDateString(),
            'status' => 'published', 'mode' => 'offline', 'registration_required' => 1, 'auto_confirm' => 1, 'fee_amount' => 0, 'created_at' => now(), 'updated_at' => now()], $over));
        $open = $make();
        $paid = $make(['name' => 'Gala', 'fee_amount' => 500]);
        $tiny = $make(['name' => 'Tiny', 'capacity' => 1]);
        $draft = $make(['name' => 'Draft', 'status' => 'draft']);
        $past = $make(['name' => 'Past', 'event_date' => now()->subDay()->toDateString()]);
        $foreign = (int) DB::table('alumni_events')->insertGetId(['sub_institute_id' => 8, 'name' => 'Elsewhere', 'event_type' => 'alumni_meet', 'event_date' => now()->addDays(5)->toDateString(), 'status' => 'published',
            'mode' => 'offline', 'fee_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $names = collect($this->api('events', [], $tokensA['access_token'], 'GET')->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Reunion', 'Gala', 'Tiny'], $names, 'published, upcoming, this institute only');

        $this->assertSame(201, $this->api("events/{$open}/register", ['participants' => 2], $tokensA['access_token'])->getStatusCode());
        $this->assertSame(409, $this->api("events/{$open}/register", [], $tokensA['access_token'])->getStatusCode(), 'already registered');
        $claim = $this->api("events/{$paid}/register", ['payment_status' => 'paid'], $tokensA['access_token']);
        $this->assertSame(201, $claim->getStatusCode());
        $this->assertSame('pending', DB::table('alumni_event_registrations')->where('event_id', $paid)->where('alumni_id', $a->id)->value('payment_status'), 'a portal registration can never claim to have paid');
        foreach ([$draft, $past] as $closed) {
            $this->assertSame(409, $this->api("events/{$closed}/register", [], $tokensA['access_token'])->getStatusCode(), 'closed to registration');
        }
        $this->assertSame(404, $this->api("events/{$foreign}/register", [], $tokensA['access_token'])->getStatusCode(), 'another institute\'s event');

        $this->assertSame(201, $this->api("events/{$tiny}/register", [], $tokensA['access_token'])->getStatusCode());
        $waitlisted = $this->api("events/{$tiny}/register", [], $tokensB['access_token']);
        $this->assertSame('waitlisted', $waitlisted->json('data.status'));

        // ownership: B cannot cancel A's registration (there is no way to name it - it is found by event + the signed-in alumnus)
        $this->assertSame(404, $this->api("events/{$open}/cancel", [], $tokensB['access_token'])->getStatusCode());
        $this->assertSame('confirmed', DB::table('alumni_event_registrations')->where('event_id', $open)->where('alumni_id', $a->id)->value('status'));
        $this->assertSame(200, $this->api("events/{$tiny}/cancel", [], $tokensA['access_token'])->getStatusCode());
        $this->assertSame('confirmed', DB::table('alumni_event_registrations')->where('event_id', $tiny)->where('alumni_id', $b->id)->value('status'), 'cancelling frees the seat for the waiting list');
        $this->assertSame(409, $this->api("events/{$tiny}/cancel", [], $tokensA['access_token'])->getStatusCode(), 'already cancelled');
    }

    // -------------------------------------------- isolation from other logins

    public function test_alumni_tokens_do_not_work_on_staff_endpoints_and_staff_tokens_do_not_work_on_the_portal(): void
    {
        [$alumnus, $account, $tokens] = $this->signedInAlumnus();

        // an alumni token against staff routes behind api.session / api.jwt: refused
        foreach (['/api/alumni/dashboard', '/api/alumni/list', '/api/alumni/reports', '/api/alumni/audit', '/api/alumni/donations'] as $url) {
            $this->assertSame(401, $this->getJson($url, ['Authorization' => 'Bearer ' . $tokens['access_token']])->getStatusCode(), $url);
        }

        // a staff-style token (staff secret, staff claims) against the portal: refused
        $staff = JWT::encode(['id' => 5, 'sub_institute_id' => 7, 'is_admin' => 1, 'user_profile_id' => 1, 'is_student' => false, 'exp' => time() + 600], str_repeat('s', 40), 'HS256');
        $this->assertSame(401, $this->api('me', [], $staff, 'GET')->getStatusCode());

        // even a token that someone signs with the STAFF secret but marks as an alumni token is refused by the legacy hydrator
        $lookalike = JWT::encode(['id' => 5, 'sub_institute_id' => 7, 'is_admin' => 1, 'user_profile_id' => 1, 'aud' => 'alumni', 'typ' => 'alumni_access', 'exp' => time() + 600], str_repeat('s', 40), 'HS256');
        $r = $this->getJson('/api/alumni/dashboard', ['Authorization' => 'Bearer ' . $lookalike]);
        $this->assertSame(401, $r->getStatusCode());
        $this->assertSame('Invalid token payload', $r->json('message'), 'rejected by the hardening in the legacy hydrator, not by luck');
        $this->assertSame(['sub', 'aid', 'tid', 'sid'], array_values(array_intersect(['sub', 'aid', 'tid', 'sid'], array_keys((array) JWT::decode($tokens['access_token'], new \Firebase\JWT\Key($this->secret, 'HS256'))))));
        $claims = (array) JWT::decode($tokens['access_token'], new \Firebase\JWT\Key($this->secret, 'HS256'));
        foreach (['id', 'sub_institute_id', 'is_student', 'is_admin', 'user_profile_id'] as $legacy) {
            $this->assertArrayNotHasKey($legacy, $claims, "an alumni token carries no '{$legacy}' claim that a staff stack could misread");
        }
    }

    public function test_the_same_email_at_two_institutes_gives_two_unrelated_accounts(): void
    {
        $a = $this->alum('Twin', ['email' => 'twin@example.org'], 7);
        $b = $this->alum('Twin', ['email' => 'twin@example.org'], 8);
        $accA = $this->account($a, 'active', 'Passw0rd-for-A1', 7);
        $accB = $this->account($b, 'active', 'Passw0rd-for-B2', 8);

        $this->assertSame(200, $this->api('login', ['institute_code' => 'TST', 'email' => 'twin@example.org', 'password' => 'Passw0rd-for-A1'])->getStatusCode());
        $this->assertSame(401, $this->api('login', ['institute_code' => 'TST', 'email' => 'twin@example.org', 'password' => 'Passw0rd-for-B2'])->getStatusCode(), 'B\'s password does not open A\'s account');
        $tokB = $this->api('login', ['institute_code' => 'OTH', 'email' => 'twin@example.org', 'password' => 'Passw0rd-for-B2'])->json('data.access_token');
        $this->assertSame($b->alumni_code, $this->api('me', [], $tokB, 'GET')->json('data.profile.alumni_code'));
        $this->assertNotSame($accA->id, $accB->id);
    }

    // ------------------------------------------------------------------- audit

    public function test_security_events_are_audited_without_secrets(): void
    {
        $this->api('register', $this->registerBody());
        $this->api('verify-email', ['token' => $this->lastToken()]);
        $account = DB::table('alumni_accounts')->first();
        $this->signIn($account);
        $this->api('forgot-password', ['institute_code' => 'TST', 'email' => $account->email]);
        $reset = $this->lastToken();
        $this->api('reset-password', ['token' => $reset, 'password' => 'Brand-new-pass1']);

        $actions = DB::table('platform_audit_log')->where('component', 'alumni.auth')->pluck('action')->all();
        foreach (['register', 'verify_email', 'login', 'password_reset_requested', 'password_reset'] as $expected) {
            $this->assertContains($expected, $actions, $expected);
        }
        $everything = json_encode(DB::table('platform_audit_log')->get());
        foreach ([self::PASSWORD, 'Brand-new-pass1', $reset, $account->password_hash] as $secret) {
            $this->assertStringNotContainsString($secret, $everything, 'no password, hash or token in the audit trail');
        }
        $this->assertSame(0, DB::table('platform_audit_log')->where('component', 'alumni.auth')->whereNull('actor_name')->whereNull('actor_user_id')->count(), 'every entry says who');
    }

    public function test_the_portal_route_table_is_exactly_what_was_reviewed(): void
    {
        $public = [];
        $guarded = [];
        foreach (\Illuminate\Support\Facades\Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/alumni-portal/')) {
                continue;
            }
            $mw = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
            foreach (['api.session', 'api.jwt', 'staff.only', 'lms.auth'] as $legacy) {
                $this->assertNotContains($legacy, $mw, "{$route->uri()} must not sit behind the staff stack ({$legacy})");
            }
            $throttles = array_filter($mw, fn ($m) => str_starts_with($m, 'throttle:portal-'));
            $this->assertNotEmpty($throttles, "{$route->uri()} needs a named portal rate limit");
            foreach ($mw as $m) {
                $this->assertFalse(str_starts_with($m, 'throttle:') && ! str_starts_with($m, 'throttle:portal-') && $m !== 'throttle:api', "bare throttle on {$route->uri()}: {$m}");
            }
            in_array('alumni.auth', $mw, true) ? $guarded[] = $route->methods()[0] . ' ' . $route->uri() : $public[] = $route->methods()[0] . ' ' . $route->uri();
        }
        sort($public);
        $this->assertSame([
            'POST api/alumni-portal/forgot-password', 'POST api/alumni-portal/invite/accept', 'POST api/alumni-portal/invite/check', 'POST api/alumni-portal/login', 'POST api/alumni-portal/refresh',
            'POST api/alumni-portal/register', 'POST api/alumni-portal/resend-verification', 'POST api/alumni-portal/reset-password', 'POST api/alumni-portal/verify-email',
        ], $public, 'the public (no-token) routes are exactly the reviewed list; a new one needs review');
        $this->assertCount(9, $guarded, 'every other portal route runs alumni.auth');
    }

    public function test_the_purge_removes_only_stale_tokens_and_sessions(): void
    {
        [, $account, $tokens] = $this->signedInAlumnus();
        $row = fn (array $o) => DB::table('alumni_auth_tokens')->insert(array_merge(['sub_institute_id' => 7, 'account_id' => $account->id, 'purpose' => 'verify_email', 'token_hash' => hash('sha256', uniqid('', true)),
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()], $o));
        $row(['used_at' => now()->subDays(40)]);                        // used long ago: go
        $row(['expires_at' => now()->subDays(40)]);                     // expired long ago: go
        $row(['used_at' => now()->subDays(2)]);                         // used recently: keep (evidence)
        $row(['expires_at' => now()->addHour()]);                       // still usable: keep
        $sess = fn (array $o) => DB::table('alumni_auth_sessions')->insert(array_merge(['sub_institute_id' => 7, 'account_id' => $account->id, 'refresh_hash' => hash('sha256', uniqid('', true)),
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()], $o));
        $sess(['revoked_at' => now()->subDays(100)]);                   // go
        $sess(['expires_at' => now()->subDays(100)]);                   // go
        $sess(['revoked_at' => now()->subDays(3)]);                     // keep

        $r = app(\App\Services\Alumni\AlumniAuthService::class)->purge();

        $this->assertSame(['tokens' => 2, 'sessions' => 2], $r);
        $this->assertSame(2, DB::table('alumni_auth_tokens')->count());
        $this->assertSame(200, $this->api('me', [], $tokens['access_token'], 'GET')->getStatusCode(), 'a live sign-in is untouched');
        $this->artisan('alumni:run', ['task' => 'auth-purge'])->assertExitCode(0);
    }

    public function test_password_rules(): void
    {
        $svc = app(\App\Services\Alumni\AlumniAuthService::class);
        $this->assertSame([], $svc->passwordProblems('Valid-pass-123', 'a@b.co'));
        $this->assertNotSame([], $svc->passwordProblems('short1A', 'a@b.co'));
        $this->assertNotSame([], $svc->passwordProblems('NoDigitsHereAtAll', 'a@b.co'));
        $this->assertNotSame([], $svc->passwordProblems('1234567890123456', 'a@b.co'));
        $this->assertNotSame([], $svc->passwordProblems('a@b.com1234', 'A@B.COM1234'), 'not the email');
        $this->assertNotSame([], $svc->passwordProblems(str_repeat('a1', 40), 'a@b.co'), 'over bcrypt\'s 72 bytes');
    }
}
