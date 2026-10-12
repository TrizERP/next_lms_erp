<?php

namespace Tests\Feature;

use App\Services\Alumni\AlumniAuthService;
use Firebase\JWT\JWT;
use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * The alumni portal added code next to the existing logins (a new middleware, a new route file, one refusal in the shared legacy
 * session hydrator). This pins that STAFF and STUDENT authentication behave exactly as before, through the real HTTP stack, and
 * that an alumni token can never stand in for either. In-memory sqlite (see AlumniTestHelpers); tokens are minted with the same
 * GenTux class and claim shape ApiLoginController uses.
 */
class AlumniStaffAuthUnaffectedTest extends TestCase
{
    use AlumniTestHelpers;

    private ?string $previousSecret = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
        $this->previousSecret = getenv('JWT_SECRET') === false ? null : (string) getenv('JWT_SECRET');
        putenv('JWT_SECRET=' . str_repeat('s', 40));
        config(['alumni_auth.jwt_secret' => str_repeat('a', 40), 'alumni_auth.staff_jwt_secret' => str_repeat('s', 40), 'hashing.bcrypt.rounds' => 4]);

        // what the legacy session hydrator reads
        Schema::create('academic_year', function ($t) {
            $t->increments('id');
            $t->integer('sub_institute_id');
            $t->integer('term_id')->default(1);
            $t->string('syear');
            $t->string('title')->nullable();
            $t->integer('sort_order')->default(1);
            $t->date('start_date');
            $t->date('end_date');
        });
        Schema::create('tbluserprofilemaster', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
            $t->integer('status')->default(1);
            $t->integer('sub_institute_id');
            $t->integer('parent_id')->nullable();
        });
        Schema::create('class_teacher', function ($t) {   // the hydrator scopes a Teacher profile by class
            $t->increments('id');
            $t->integer('teacher_id');
            $t->integer('sub_institute_id');
            $t->string('syear')->nullable();
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id')->nullable();
            $t->integer('division_id')->nullable();
        });
        Schema::create('tbluser', function ($t) {
            $t->integer('id')->primary();
            $t->string('user_name')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('email')->nullable();
        });
        DB::table('academic_year')->insert(['sub_institute_id' => 7, 'syear' => date('Y'), 'start_date' => (date('Y') - 1) . '-01-01', 'end_date' => (date('Y') + 1) . '-12-31']);
        DB::table('tbluserprofilemaster')->insert([['id' => 1, 'name' => 'Admin', 'sub_institute_id' => 7], ['id' => 2, 'name' => 'Teacher', 'sub_institute_id' => 7], ['id' => 3, 'name' => 'Student', 'sub_institute_id' => 7]]);
        DB::table('tbluser')->insert(['id' => 55, 'user_name' => 'admin1', 'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.org']);
    }

    protected function tearDown(): void
    {
        $this->previousSecret === null ? putenv('JWT_SECRET') : putenv('JWT_SECRET=' . $this->previousSecret);
        parent::tearDown();
    }

    /** A token with the same claim shape as ApiLoginController's. */
    private function legacyToken(int $profileId, bool $isStudent = false, array $extra = []): string
    {
        return app(JwtToken::class)->createToken(array_merge(['id' => 55, 'sub_institute_id' => 7, 'is_admin' => 0, 'client_id' => 1, 'user_profile_id' => $profileId, 'is_student' => $isStudent], $extra));
    }

    private function bearerGet(string $url, ?string $token)
    {
        return $this->getJson($url, $token ? ['Authorization' => 'Bearer ' . $token] : []);   // per-request headers: withHeaders() would carry a previous request's token into the next one
    }

    public function test_staff_login_tokens_work_exactly_as_before(): void
    {
        $this->alum('Visible');
        $r = $this->bearerGet('/api/alumni/dashboard', $this->legacyToken(1));
        $this->assertSame(200, $r->getStatusCode(), $r->getContent());
        $this->assertSame(1, $r->json('data.totals.total'), 'the staff session was hydrated for the right institute');
        $this->assertSame(200, $this->bearerGet('/api/alumni/list', $this->legacyToken(1))->getStatusCode());
        // a legitimate staff token that merely carries some other audience claim must not be caught by the alumni refusal
        $this->assertSame(200, $this->bearerGet('/api/alumni/dashboard', $this->legacyToken(1, false, ['aud' => 'mobile-app', 'typ' => 'staff']))->getStatusCode());
    }

    public function test_student_and_other_profiles_are_still_refused_as_before(): void
    {
        $this->assertSame(403, $this->bearerGet('/api/alumni/dashboard', $this->legacyToken(3, true))->getStatusCode(), 'student token: staff.only');
        $this->assertSame(403, $this->bearerGet('/api/alumni/dashboard', $this->legacyToken(2))->getStatusCode(), 'teacher: the alumni role gate');
        $this->assertSame(401, $this->bearerGet('/api/alumni/dashboard', null)->getStatusCode());
        $this->assertSame(401, $this->bearerGet('/api/alumni/dashboard', 'garbage.token.value')->getStatusCode());
        $this->assertSame(401, $this->bearerGet('/api/alumni/dashboard', JWT::encode(['id' => 55, 'sub_institute_id' => 7, 'user_profile_id' => 1], str_repeat('z', 40), 'HS256'))->getStatusCode(), 'wrong signature');
    }

    public function test_an_alumni_token_cannot_stand_in_for_staff_or_student(): void
    {
        $alumnus = $this->alum('Portal');
        $accountId = DB::table('alumni_accounts')->insertGetId(['sub_institute_id' => 7, 'alumni_id' => $alumnus->id, 'email' => $alumnus->email, 'password_hash' => bcrypt('Str0ngPassw0rd'), 'status' => 'active',
            'email_verified_at' => now(), 'created_via' => 'invite', 'created_at' => now(), 'updated_at' => now()]);
        $login = $this->postJson('/api/alumni-portal/login', ['institute_code' => 'TST', 'email' => $alumnus->email, 'password' => 'Str0ngPassw0rd']);
        $this->assertSame(200, $login->getStatusCode(), $login->getContent());
        $alumniToken = $login->json('data.access_token');
        $this->assertSame(200, $this->bearerGet('/api/alumni-portal/me', $alumniToken)->getStatusCode(), 'it does work on the portal');

        foreach (['/api/alumni/dashboard', '/api/alumni/list', '/api/alumni/reports', '/api/alumni/audit', '/api/alumni/accounts'] as $url) {
            $this->assertSame(401, $this->bearerGet($url, $alumniToken)->getStatusCode(), "staff endpoint {$url}");
        }
        // and the other way round
        $this->assertSame(401, $this->bearerGet('/api/alumni-portal/me', $this->legacyToken(1))->getStatusCode(), 'a staff token on the portal');
        $this->assertSame(401, $this->bearerGet('/api/alumni-portal/me', $this->legacyToken(3, true))->getStatusCode(), 'a student token on the portal');

        // the worst case: someone signs an alumni-marked token with the STAFF secret. The legacy hydrator refuses it by name.
        $r = $this->bearerGet('/api/alumni/dashboard', $this->legacyToken(1, false, ['aud' => 'alumni', 'typ' => 'alumni_access']));
        $this->assertSame(401, $r->getStatusCode());
        $this->assertSame('Invalid token payload', $r->json('message'));
        $this->assertSame($accountId, (int) DB::table('alumni_accounts')->value('id'));
    }

    public function test_the_portal_refuses_to_run_with_the_staff_secret_even_after_config_is_built(): void
    {
        // the staff secret is read through config (not env()), so `php artisan config:cache` cannot switch this check off
        config(['alumni_auth.jwt_secret' => str_repeat('s', 40)]);
        $this->assertFalse(app(AlumniAuthService::class)->isConfigured());
        $this->assertSame(503, $this->postJson('/api/alumni-portal/login', ['institute_code' => 'TST', 'email' => 'a@b.co', 'password' => 'x'])->getStatusCode());
        $this->assertSame(200, $this->bearerGet('/api/alumni/dashboard', $this->legacyToken(1))->getStatusCode(), 'staff sign-in is untouched either way');
    }
}
