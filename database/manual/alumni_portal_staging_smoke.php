<?php
/**
 * STAGING SMOKE TEST for the alumni portal against a REAL MariaDB with a production-equivalent structure.
 * Run only with the staging environment override (DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=staging_erp) and synthetic
 * JWT_SECRET / ALUMNI_JWT_SECRET values. It drives the real HTTP kernel (routes, middleware, throttles) in-process.
 * Refuses to run against anything but the staging server. Prints PASS/FAIL lines only: never a token, secret or password.
 */
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

if (config('database.connections.mysql.host') !== '127.0.0.1' || (int) config('database.connections.mysql.port') !== 3307 || DB::connection()->getDatabaseName() !== 'staging_erp') {
    fwrite(STDERR, "REFUSING: not the staging database.\n");
    exit(2);
}
config(['mail.default' => 'smtp', 'alumni_auth.portal_url' => 'https://portal.staging.test']);
Mail::swap(new class {
    public array $outbox = [];

    public function raw($body, $callback)
    {
        $m = new class {
            public $to;

            public $subject;

            public function to($a)
            {
                $this->to = is_array($a) ? reset($a) : $a;

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

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS  ' : 'FAIL  '), $name, ($ok ? '' : ($detail ? "  [$detail]" : '')), "\n";
}
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
function call(string $method, string $uri, array $data = [], ?string $bearer = null): array
{
    global $kernel;
    $server = ['HTTP_ACCEPT' => 'application/json'] + ($bearer !== null ? ['HTTP_AUTHORIZATION' => 'Bearer ' . $bearer] : []);
    $response = $kernel->handle(Request::create($uri, $method, $data, [], [], $server));

    return [$response->getStatusCode(), json_decode($response->getContent(), true) ?? []];
}
function mailToken(): ?string
{
    $box = Mail::getFacadeRoot()->outbox;
    $last = end($box);

    return $last && preg_match('/token=([a-f0-9]{64})/', $last['body'], $m) ? $m[1] : null;
}

// ---- synthetic fixtures (staging only)
$now = date('Y-m-d');
DB::table('school_setup')->updateOrInsert(['Id' => 1], ['SchoolName' => 'Staging School', 'ShortCode' => 'STG']);
DB::table('academic_year')->where('sub_institute_id', 1)->delete();
DB::table('academic_year')->insert(['sub_institute_id' => 1, 'term_id' => 1, 'syear' => date('Y'), 'title' => 'Current', 'sort_order' => 1, 'start_date' => (date('Y') - 1) . '-01-01', 'end_date' => (date('Y') + 1) . '-12-31']);
foreach ([900001 => 'Admin', 900002 => 'Student', 900003 => 'Teacher'] as $id => $name) {
    DB::table('tbluserprofilemaster')->updateOrInsert(['id' => $id], ['name' => $name, 'status' => 1, 'sub_institute_id' => 1]);
}
DB::table('tbluser')->updateOrInsert(['id' => 900001], ['user_name' => 'stage.admin', 'first_name' => 'Stage', 'last_name' => 'Admin', 'email' => 'stage.admin@staging.invalid', 'user_profile_id' => 900001, 'sub_institute_id' => 1, 'is_admin' => 0]);
$alumniSvc = app(App\Services\Alumni\AlumniService::class);
$alumnus = $alumniSvc->create(1, ['first_name' => 'Stage', 'last_name' => 'Invitee', 'email' => 'stage.invitee@staging.invalid', 'mobile' => '9000000042', 'batch' => '2017-18', 'passing_year' => 2018], 900001, 'manual');
$jwt = app(GenTux\Jwt\JwtToken::class);
$staffToken = $jwt->createToken(['id' => 900001, 'sub_institute_id' => 1, 'is_admin' => 0, 'client_id' => 1, 'user_profile_id' => 900001, 'is_student' => false]);
$studentToken = $jwt->createToken(['id' => 900002, 'sub_institute_id' => 1, 'is_admin' => 0, 'client_id' => 1, 'user_profile_id' => 900002, 'is_student' => true]);

echo "== existing staff and student authentication (unchanged behaviour) ==\n";
[$s] = call('GET', '/api/alumni/dashboard', [], $staffToken);
check('staff token reaches a staff endpoint (api.session hydrates as before)', $s === 200, "status $s");
[$s] = call('GET', '/api/alumni/dashboard', [], $studentToken);
check('student token is still refused by staff.only (403)', $s === 403, "status $s");
[$s] = call('GET', '/api/alumni/dashboard', [], null);
check('no token is still refused (401)', $s === 401, "status $s");
[$s] = call('GET', '/api/alumni/dashboard', [], 'not.a.token');
check('garbage token is still refused (401)', $s === 401, "status $s");

echo "== self-registration, confirmation, sign-in on MariaDB ==\n";
$body = ['institute_code' => 'STG', 'first_name' => 'Staging', 'last_name' => 'Registrant', 'email' => 'stage.reg@staging.invalid', 'password' => 'Staging-pass-2026', 'mobile' => '9000000043'];
[$s, $j] = call('POST', '/api/alumni-portal/register', $body);
check('register answers 202', $s === 202, "status $s");
$verify = mailToken();
check('a confirmation email was handed to the mail transport', $verify !== null);
check('the link token is stored only as a hash', DB::table('alumni_auth_tokens')->where('token_hash', hash('sha256', (string) $verify))->exists() && ! DB::table('alumni_auth_tokens')->where('token_hash', (string) $verify)->exists());
$leak = false;
foreach (['alumni_auth_tokens', 'alumni_message_log', 'platform_notification_log', 'platform_audit_log'] as $t) {
    $leak = $leak || str_contains(json_encode(DB::table($t)->get()), (string) $verify);
}
check('the live token appears in no table (notification log scrubbed)', ! $leak);
[$s2] = call('POST', '/api/alumni-portal/register', $body);
check('registering the same email again answers identically and creates nothing', $s2 === 202 && DB::table('alumni_accounts')->where('email', 'stage.reg@staging.invalid')->count() === 1);
[$s] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => $body['email'], 'password' => $body['password']]);
check('sign-in before confirming the email is refused (403)', $s === 403, "status $s");
[$s] = call('POST', '/api/alumni-portal/verify-email', ['token' => $verify]);
[$s2] = call('POST', '/api/alumni-portal/verify-email', ['token' => $verify]);
check('confirmation works once, a replay fails', $s === 200 && $s2 === 422, "$s/$s2");
[$s, $j] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'stg', 'email' => strtoupper($body['email']), 'password' => $body['password']]);
check('sign-in works (case-insensitive code and email)', $s === 200 && isset($j['data']['access_token']), "status $s");
$access = $j['data']['access_token'] ?? '';
$refresh = $j['data']['refresh_token'] ?? '';
[$s, $j] = call('GET', '/api/alumni-portal/me', [], $access);
check('me returns the caller and standing (pending staff verification)', $s === 200 && ($j['data']['standing']['features_enabled'] ?? null) === false, "status $s");
[$s] = call('GET', '/api/alumni-portal/events', [], $access);
check('features stay closed until staff verify (403)', $s === 403, "status $s");

echo "== token isolation on the real stack ==\n";
foreach (['/api/alumni/dashboard', '/api/alumni/list', '/api/alumni/audit', '/api/alumni/donations'] as $u) {
    [$s] = call('GET', $u, [], $access);
    check("alumni token refused by staff endpoint $u", $s === 401, "status $s");
}
[$s] = call('GET', '/api/alumni-portal/me', [], $staffToken);
check('staff token refused by the portal (401)', $s === 401, "status $s");
[$s] = call('GET', '/api/alumni-portal/me', [], $studentToken);
check('student token refused by the portal (401)', $s === 401, "status $s");
$lookalike = JWT::encode(['id' => 900001, 'sub_institute_id' => 1, 'is_admin' => 1, 'user_profile_id' => 900001, 'aud' => 'alumni', 'typ' => 'alumni_access', 'exp' => time() + 600], (string) getenv('JWT_SECRET'), 'HS256');
[$s, $j] = call('GET', '/api/alumni/dashboard', [], $lookalike);
check('a staff-signed token marked as alumni is refused by the legacy hydrator', $s === 401 && ($j['message'] ?? '') === 'Invalid token payload', "status $s");
$claims = (array) JWT::decode($access, new Firebase\JWT\Key((string) config('alumni_auth.jwt_secret'), 'HS256'));
check('alumni access token carries no staff-style claims', ! array_intersect(['id', 'sub_institute_id', 'is_student', 'is_admin', 'user_profile_id'], array_keys($claims)));
check('alumni secret differs from the staff secret', config('alumni_auth.jwt_secret') !== getenv('JWT_SECRET'));

echo "== staff verification, refresh rotation and reuse detection ==\n";
$reg = DB::table('alumni')->where('email', $body['email'])->first();
$alumniSvc->verify(1, (int) $reg->id, true, null, 900001);
[$s] = call('GET', '/api/alumni-portal/events', [], $access);
check('after staff verification the same session gets features (200)', $s === 200, "status $s");
[$s, $j] = call('POST', '/api/alumni-portal/refresh', ['refresh_token' => $refresh]);
check('refresh rotates the pair', $s === 200 && ($j['data']['refresh_token'] ?? $refresh) !== $refresh, "status $s");
$newRefresh = $j['data']['refresh_token'] ?? '';
$newAccess = $j['data']['access_token'] ?? '';
[$s] = call('POST', '/api/alumni-portal/refresh', ['refresh_token' => $refresh]);
check('replaying the old refresh token fails and revokes the login', $s === 401 && DB::table('alumni_auth_sessions')->whereNotNull('revoked_at')->count() >= 1, "status $s");
[$s] = call('POST', '/api/alumni-portal/refresh', ['refresh_token' => $newRefresh]);
[$s2] = call('GET', '/api/alumni-portal/me', [], $newAccess);
check('the legitimate holder is signed out too (stolen-token alarm)', $s === 401 && $s2 === 401, "$s/$s2");

echo "== lockout, reset, change password ==\n";
for ($i = 0; $i < 5; $i++) {
    call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => $body['email'], 'password' => 'Wrong-password-' . $i]);
}
[$s] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => $body['email'], 'password' => $body['password']]);
check('five wrong passwords lock the account even for the right password', $s === 401 && DB::table('alumni_accounts')->where('email', $body['email'])->whereNotNull('locked_until')->exists(), "status $s");
DB::table('alumni_accounts')->where('email', $body['email'])->update(['locked_until' => date('Y-m-d H:i:s', time() - 60)]);
app('cache')->flush();   // the per-email rate limit (8 a minute) is tested separately below; here only the lockout logic is under test
[$s, $j] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => $body['email'], 'password' => $body['password']]);
check('the lock expires', $s === 200, "status $s");
$access2 = $j['data']['access_token'] ?? '';
call('POST', '/api/alumni-portal/forgot-password', ['institute_code' => 'STG', 'email' => $body['email']]);
$reset = mailToken();
[$s, $j] = call('POST', '/api/alumni-portal/reset-password', ['token' => $reset, 'password' => 'short']);
check('a weak password is refused without using the link up', $s === 422 && DB::table('alumni_auth_tokens')->where('token_hash', hash('sha256', (string) $reset))->whereNull('used_at')->exists(), "status $s");
[$s] = call('POST', '/api/alumni-portal/reset-password', ['token' => $reset, 'password' => 'Brand-new-pass-77']);
[$s2] = call('POST', '/api/alumni-portal/reset-password', ['token' => $reset, 'password' => 'Brand-new-pass-88']);
[$s3] = call('GET', '/api/alumni-portal/me', [], $access2);
check('reset works once, and signs every device out', $s === 200 && $s2 === 422 && $s3 === 401, "$s/$s2/$s3");
app('cache')->flush();
[$s, $j] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => $body['email'], 'password' => 'Brand-new-pass-77']);
check('sign-in works with the new password', $s === 200, "status $s");

echo "== staff invitation end to end ==\n";
[$s, $j] = call('POST', "/api/alumni/{$alumnus->id}/invite", [], $staffToken);
check('staff invite returns a one-time link (201)', $s === 201 && str_contains($j['data']['link'] ?? '', '/alumni-portal/invite?token='), "status $s");
$inviteToken = mailToken();
[$s] = call('POST', '/api/alumni-portal/invite/accept', ['token' => $inviteToken, 'password' => 'Invited-pass-2026']);
[$s2] = call('POST', '/api/alumni-portal/invite/accept', ['token' => $inviteToken, 'password' => 'Invited-pass-2026']);
check('invitation is accepted once', $s === 200 && $s2 === 422, "$s/$s2");
[$s] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => 'stage.invitee@staging.invalid', 'password' => 'Invited-pass-2026']);
check('invited person must still confirm their email (403)', $s === 403, "status $s");
call('POST', '/api/alumni-portal/verify-email', ['token' => mailToken()]);
[$s, $j] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => 'stage.invitee@staging.invalid', 'password' => 'Invited-pass-2026']);
check('then sign-in works and features are on (staff-created record)', $s === 200, "status $s");
[$s, $j] = call('GET', '/api/alumni-portal/me', [], $j['data']['access_token'] ?? '');
check('own profile only', $s === 200 && ($j['data']['standing']['features_enabled'] ?? false) === true && ($j['data']['profile']['first_name'] ?? '') === 'Stage', "status $s");

echo "== staff control, ownership, events on MariaDB ==\n";
$acct = DB::table('alumni_accounts')->where('email', 'stage.invitee@staging.invalid')->first();
[$s, $j] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => 'stage.invitee@staging.invalid', 'password' => 'Invited-pass-2026']);
$inviteeAccess = $j['data']['access_token'] ?? '';
[$s] = call('POST', "/api/alumni/accounts/{$acct->id}/disable", ['reason' => 'staging check'], $staffToken);
[$s2] = call('GET', '/api/alumni-portal/me', [], $inviteeAccess);
check('staff disable signs the person out at once', $s === 200 && $s2 === 401, "$s/$s2");
call('POST', "/api/alumni/accounts/{$acct->id}/enable", [], $staffToken);
[$s, $j] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => 'stage.invitee@staging.invalid', 'password' => 'Invited-pass-2026']);
$inviteeAccess = $j['data']['access_token'] ?? '';
check('enable restores sign-in', $s === 200, "status $s");
$before = DB::table('alumni')->where('id', $reg->id)->first();
[$s] = call('POST', '/api/alumni-portal/me/update', ['city' => 'Stagingville', 'alumni_id' => $reg->id, 'sub_institute_id' => 99, 'first_name' => 'Hijack', 'status' => 'blocked'], $inviteeAccess);
$mine = DB::table('alumni')->where('id', $alumnus->id)->first();
$theirs = DB::table('alumni')->where('id', $reg->id)->first();
check('update changes only the caller, only allowed fields', $s === 200 && $mine->city === 'Stagingville' && $mine->first_name === 'Stage' && $mine->status !== 'blocked' && $theirs->city === $before->city && (int) $mine->sub_institute_id === 1, "status $s");
$eid = DB::table('alumni_events')->insertGetId(['sub_institute_id' => 1, 'name' => 'Staging Reunion', 'event_type' => 'alumni_meet', 'event_date' => date('Y-m-d', strtotime('+5 days')), 'status' => 'published', 'mode' => 'offline',
    'registration_required' => 1, 'auto_confirm' => 1, 'fee_amount' => 0, 'capacity' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
[$s, $j] = call('POST', "/api/alumni-portal/events/{$eid}/register", ['participants' => 1], $inviteeAccess);
check('event registration works under a row lock (201)', $s === 201, "status $s");
[$s] = call('POST', "/api/alumni-portal/events/{$eid}/cancel", [], $inviteeAccess);
check('own registration can be cancelled', $s === 200, "status $s");
[$s] = call('POST', '/api/alumni-portal/me/privacy', ['fields' => ['email' => 'private'], 'consents' => ['consent_email' => true]], $inviteeAccess);
check('privacy and consent save for the caller', $s === 200 && (int) DB::table('alumni')->where('id', $alumnus->id)->value('consent_email') === 1 && DB::table('alumni_privacy')->where('alumni_id', $alumnus->id)->count() === 1, "status $s");

echo "== rate limiting ==\n";
app('cache')->flush();
$codes = [];
for ($i = 0; $i < 10; $i++) {
    [$c] = call('POST', '/api/alumni-portal/login', ['institute_code' => 'STG', 'email' => 'flood@staging.invalid', 'password' => 'Whatever-pass-1']);
    $codes[] = $c;
}
check('sign-in is rate-limited per email (429 after 8 a minute)', $codes[0] === 401 && in_array(429, $codes, true), implode(',', $codes));
app('cache')->flush();

echo "== database-level guarantees ==\n";
$dupe = false;
try {
    DB::table('alumni_accounts')->insert(['sub_institute_id' => 1, 'alumni_id' => 999999, 'email' => $acct->email, 'status' => 'unverified', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    $dupe = true;
}
check('MariaDB enforces one account per email per institute', $dupe);
$dupe = false;
try {
    $h = DB::table('alumni_auth_tokens')->value('token_hash');
    DB::table('alumni_auth_tokens')->insert(['sub_institute_id' => 1, 'account_id' => 1, 'purpose' => 'invite', 'token_hash' => $h, 'expires_at' => date('Y-m-d H:i:s', time() + 60)]);
} catch (Throwable $e) {
    $dupe = true;
}
check('MariaDB enforces unique token hashes', $dupe);
check('audit trail has an actor on every alumni.auth entry', DB::table('platform_audit_log')->where('component', 'alumni.auth')->whereNull('actor_user_id')->whereNull('actor_name')->count() === 0);
$secretsInAudit = false;
foreach (['Staging-pass-2026', 'Brand-new-pass-77', 'Invited-pass-2026'] as $pw) {
    $secretsInAudit = $secretsInAudit || str_contains(json_encode(DB::table('platform_audit_log')->get()), $pw);
}
check('no password in the audit trail', ! $secretsInAudit);
$r = app(App\Services\Alumni\AlumniAuthService::class)->purge();
check('scheduled purge runs on MariaDB', is_array($r) && isset($r['tokens'], $r['sessions']));

echo "\nstaging smoke: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
