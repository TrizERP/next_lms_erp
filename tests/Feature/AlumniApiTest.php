<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Alumni module (Phase B): master CRUD, ID uniqueness, duplicates, verification,
 * child records, conversion idempotency, tenant isolation, role gate, completion.
 *
 * RUNS ON IN-MEMORY SQLITE, FORCED IN setUp (phpunit.xml does not force it, so without this
 * the test would hit the live shared database). setUp refuses to continue otherwise.
 * Middleware is not exercised; the session `api.session` would hydrate is attached by hand,
 * and a separate test asserts the route table puts the middleware in front of the controller.
 */
class AlumniApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');

        (require base_path('database/migrations/2026_10_10_100000_create_alumni_core_tables.php'))->up();

        Schema::create('tblstudent', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->string('enrollment_no')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('gender')->nullable();
            $t->date('dob')->nullable();
            $t->string('email')->nullable();
            $t->string('mobile')->nullable();
            $t->string('city')->nullable();
            $t->integer('student_inactive')->nullable();
        });
        Schema::create('tblstudent_tc_details', function ($t) {
            $t->increments('id');
            $t->integer('sub_institute_id');
            $t->integer('student_id');
        });
        Schema::create('tblstudent_enrollment', function ($t) {
            $t->increments('id');
            $t->integer('sub_institute_id');
            $t->integer('student_id');
            $t->string('syear');
            $t->integer('standard_id')->nullable();
            $t->integer('section_id')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('enrollment_code')->nullable();
        });
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
        });
        Schema::create('division', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
        });
    }

    private function act(string $method, array $input = [], int $tenant = 7, string $profile = 'Admin', array $args = [], bool $student = false): array
    {
        $request = Request::create('/x', 'POST', $input);
        $request->merge(['type' => 'API']);   // what the api.session middleware does to every request
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', 55);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);

        $response = app(AlumniApiController::class)->{$method}($request, ...$args);

        return [$response->getStatusCode(), $response->getData(true)];
    }

    private function person(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Asha', 'last_name' => 'Patel', 'gender' => 'Female', 'dob' => '2004-05-12',
            'email' => 'asha@example.org', 'mobile' => '9000000001', 'passing_year' => 2022,
            'city' => 'Ahmedabad', 'country' => 'India',
        ], $over);
    }

    public function test_manual_create_generates_unique_sequential_ids_per_tenant_and_year(): void
    {
        [$s1, $b1] = $this->act('store', $this->person());
        [$s2, $b2] = $this->act('store', $this->person(['first_name' => 'Ravi', 'email' => 'r@example.org', 'mobile' => '9000000002', 'dob' => '2003-01-01']));
        [, $other] = $this->act('store', $this->person(), 8);   // same person, other institute: allowed

        $this->assertSame(201, $s1);
        $this->assertSame('ALM-2022-00001', $b1['data']['alumni_code']);
        $this->assertSame('ALM-2022-00002', $b2['data']['alumni_code']);
        $this->assertSame('ALM-2022-00001', $other['data']['alumni_code']);   // counter is per tenant
        $this->assertSame('Asha Patel', $b1['data']['full_name']);
        $this->assertSame('2021-22', $b1['data']['batch']);
    }

    public function test_duplicate_on_a_hard_identifier_is_refused_and_cannot_be_overridden(): void
    {
        $this->act('store', $this->person());
        [$status, $body] = $this->act('store', $this->person(['first_name' => 'Different', 'dob' => '1999-01-01', 'allow_duplicate' => true]));

        $this->assertSame(409, $status);   // same email + mobile
        $this->assertContains('email', $body['matches'][0]['matched_on']);
        $this->assertSame(1, DB::table('alumni')->count());
    }

    public function test_name_and_dob_only_match_can_be_overridden_by_staff(): void
    {
        $this->act('store', $this->person());
        $twin = $this->person(['email' => 'other@example.org', 'mobile' => '9111111111']);

        [$refused] = $this->act('store', $twin);
        [$allowed] = $this->act('store', $twin + ['allow_duplicate' => true]);

        $this->assertSame(409, $refused);
        $this->assertSame(201, $allowed);
    }

    public function test_validation_errors_come_back_per_field(): void
    {
        [$status, $body] = $this->act('store', ['email' => 'not-an-email', 'dob' => '2999-01-01']);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('first_name', $body['errors']);
        $this->assertArrayHasKey('email', $body['errors']);
        $this->assertArrayHasKey('dob', $body['errors']);
    }

    public function test_tenants_cannot_read_or_change_each_others_alumni(): void
    {
        [, $b] = $this->act('store', $this->person());
        $id = $b['data']['id'];

        [$show] = $this->act('show', [], 8, 'Admin', [$id]);
        [$upd] = $this->act('update', ['city' => 'Hacked'], 8, 'Admin', [$id]);
        [$st] = $this->act('status', ['status' => 'blocked'], 8, 'Admin', [$id]);
        [, $list] = $this->act('index', [], 8);

        $this->assertSame(404, $show);
        $this->assertSame(404, $upd);
        $this->assertSame(404, $st);
        $this->assertSame(0, $list['data']['pagination']['total']);
        $this->assertSame('Ahmedabad', DB::table('alumni')->where('id', $id)->value('city'));
    }

    public function test_role_gate_blocks_teachers_and_students_but_allows_coordinator(): void
    {
        [$teacher] = $this->act('index', [], 7, 'Teacher');
        [$student] = $this->act('index', [], 7, 'Admin', [], true);
        [$coord] = $this->act('index', [], 7, 'Alumni Coordinator');
        [$write] = $this->act('store', $this->person(), 7, 'Teacher');

        $this->assertSame(403, $teacher);
        $this->assertSame(403, $student);
        $this->assertSame(200, $coord);
        $this->assertSame(403, $write);
        $this->assertSame(0, DB::table('alumni')->count());
    }

    public function test_status_change_and_verification_workflow(): void
    {
        $id = DB::table('alumni')->insertGetId([
            'sub_institute_id' => 7, 'alumni_code' => 'ALM-2024-00001', 'first_name' => 'Self', 'full_name' => 'Self',
            'status' => 'pending_verification', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$noReason, $err] = $this->act('verify', ['decision' => 'reject'], 7, 'Admin', [$id]);
        [$ok, $body] = $this->act('verify', ['decision' => 'approve'], 7, 'Admin', [$id]);
        [$again] = $this->act('verify', ['decision' => 'approve'], 7, 'Admin', [$id]);
        [$bad] = $this->act('status', ['status' => 'nonsense'], 7, 'Admin', [$id]);

        $this->assertSame(422, $noReason);
        $this->assertArrayHasKey('remarks', $err['errors']);
        $this->assertSame(200, $ok);
        $this->assertSame('verified', $body['data']['status']);
        $this->assertSame(55, (int) $body['data']['verified_by']);
        $this->assertSame(409, $again);    // not pending any more
        $this->assertSame(422, $bad);
    }

    public function test_career_write_updates_current_job_snapshot_and_completion(): void
    {
        [, $b] = $this->act('store', $this->person());
        $id = $b['data']['id'];
        $before = DB::table('alumni')->where('id', $id)->value('profile_completion');

        [$s, $row] = $this->act('childStore', [
            'company_name' => 'TCS', 'industry' => 'IT', 'job_title' => 'Engineer', 'is_current' => true,
            'employment_status' => 'employed', 'start_date' => '2023-01-01',
        ], 7, 'Admin', [$id, 'career']);
        $this->act('childStore', ['skill' => 'Python', 'level' => 'advanced'], 7, 'Admin', [$id, 'skills']);
        [$dup] = $this->act('childStore', ['skill' => 'python'], 7, 'Admin', [$id, 'skills']);

        $a = DB::table('alumni')->where('id', $id)->first();
        $this->assertSame(201, $s);
        $this->assertSame('TCS', $a->current_company);
        $this->assertSame('IT', $a->current_industry);
        $this->assertSame('employed', $a->employment_status);
        $this->assertGreaterThan((int) $before, (int) $a->profile_completion);
        $this->assertSame(422, $dup);   // case-insensitive duplicate skill

        // another tenant cannot touch the child row
        [$del] = $this->act('childDelete', [], 8, 'Admin', [$id, 'career', $row['data']['id']]);
        $this->assertSame(404, $del);
        $this->assertSame(1, DB::table('alumni_career')->count());
    }

    public function test_skill_without_a_chosen_level_defaults_instead_of_failing(): void
    {
        [, $b] = $this->act('store', $this->person());
        $id = $b['data']['id'];

        [$status, $row] = $this->act('childStore', ['skill' => 'Chess', 'level' => null], 7, 'Admin', [$id, 'skills']);
        [$s2, $row2] = $this->act('childStore', ['skill' => 'Go', 'level' => ''], 7, 'Admin', [$id, 'skills']);

        $this->assertSame(201, $status);
        $this->assertSame('intermediate', $row['data']['level']);
        $this->assertSame(201, $s2);
        $this->assertSame('intermediate', $row2['data']['level']);
    }

    public function test_conversion_is_rule_based_scoped_and_idempotent(): void
    {
        DB::table('tblstudent')->insert([
            ['id' => 1, 'sub_institute_id' => 7, 'enrollment_no' => 'E1', 'first_name' => 'Leaver', 'last_name' => 'One', 'email' => 'l1@x.org'],
            ['id' => 2, 'sub_institute_id' => 7, 'enrollment_no' => 'E2', 'first_name' => 'Still', 'last_name' => 'Studying', 'email' => null],
            ['id' => 3, 'sub_institute_id' => 8, 'enrollment_no' => 'E3', 'first_name' => 'Other', 'last_name' => 'Tenant', 'email' => null],
        ]);
        DB::table('tblstudent_tc_details')->insert([
            ['sub_institute_id' => 7, 'student_id' => 1],
            ['sub_institute_id' => 8, 'student_id' => 3],
        ]);

        [, $preview] = $this->act('conversionPreview');
        $this->assertSame(1, $preview['data']['total']);                    // only the LC-issued student of tenant 7
        $this->assertSame(1, $preview['data']['candidates'][0]['student_id']);

        [$noChoice] = $this->act('conversionRun', []);
        $this->assertSame(422, $noChoice);                                  // must choose or confirm_all

        [, $run1] = $this->act('conversionRun', ['confirm_all' => true]);
        [, $run2] = $this->act('conversionRun', ['confirm_all' => true]);
        [, $explicit] = $this->act('conversionRun', ['student_ids' => [1, 2]]);   // 2 is not eligible

        $this->assertSame(1, $run1['data']['created']);
        $this->assertSame(0, $run2['data']['created']);
        $this->assertSame(0, $explicit['data']['created']);
        $this->assertSame(1, DB::table('alumni')->where('sub_institute_id', 7)->count());
        $this->assertSame(0, DB::table('alumni')->where('sub_institute_id', 8)->count());
        $this->assertSame('auto', DB::table('alumni')->value('source'));
    }

    public function test_default_rule_is_leaving_certificate_or_ended_final_standard_enrollment(): void
    {
        \Illuminate\Support\Facades\Schema::table('standard', function ($t) {
            $t->integer('sort_order')->default(0);
            $t->integer('sub_institute_id')->default(0);
        });
        DB::table('standard')->insert([['id' => 10, 'name' => '10', 'sort_order' => 10, 'sub_institute_id' => 7], ['id' => 12, 'name' => '12', 'sort_order' => 12, 'sub_institute_id' => 7],
            ['id' => 20, 'name' => 'Other 14', 'sort_order' => 14, 'sub_institute_id' => 8]]);
        $past = now()->subMonths(2)->toDateString();
        $future = now()->addMonths(2)->toDateString();
        $students = [1 => 'Lc', 2 => 'FinalEnded', 3 => 'FinalOngoing', 4 => 'EarlierStandardEnded', 5 => 'NoEnrollment', 6 => 'OtherTenantFinal'];
        foreach ($students as $id => $name) {
            DB::table('tblstudent')->insert(['id' => $id, 'sub_institute_id' => $id === 6 ? 8 : 7, 'enrollment_no' => 'E' . $id, 'first_name' => $name, 'last_name' => 'S', 'email' => null]);
        }
        DB::table('tblstudent_tc_details')->insert(['sub_institute_id' => 7, 'student_id' => 1]);
        $enrol = fn (int $student, int $standard, ?string $end, int $tenant = 7) => DB::table('tblstudent_enrollment')->insert(
            ['sub_institute_id' => $tenant, 'student_id' => $student, 'syear' => '2025', 'standard_id' => $standard, 'section_id' => 1, 'end_date' => $end]);
        $enrol(2, 12, $past);
        $enrol(3, 12, $future);
        $enrol(4, 10, $past);
        $enrol(6, 20, $past, 8);

        [, $preview] = $this->act('conversionPreview');
        $this->assertSame('lc_or_final_enrollment_ended', $preview['data']['rule'], 'the owner-approved rule is the default');
        $found = collect($preview['data']['candidates'])->pluck('student_id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        $this->assertSame([1, 2], $found, 'leaving certificate, or an ended enrollment in the final standard - not an ongoing one, an earlier standard, or another institute');

        // a tenant can still choose the stricter rule
        $this->act('saveSettings', ['eligibility_rule' => 'lc_issued']);
        [, $strict] = $this->act('conversionPreview');
        $this->assertSame([1], collect($strict['data']['candidates'])->pluck('student_id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_academic_history_is_read_from_enrollment_not_copied(): void
    {
        DB::table('standard')->insert(['id' => 10, 'name' => '12']);
        DB::table('division')->insert(['id' => 1, 'name' => 'A']);
        DB::table('tblstudent_enrollment')->insert([
            ['sub_institute_id' => 7, 'student_id' => 1, 'syear' => '2021', 'standard_id' => 10, 'section_id' => 1],
            ['sub_institute_id' => 8, 'student_id' => 1, 'syear' => '2099', 'standard_id' => 10, 'section_id' => 1],
        ]);
        [, $b] = $this->act('store', $this->person(['student_id' => 1]));

        [, $h] = $this->act('academicHistory', [], 7, 'Admin', [$b['data']['id']]);

        $this->assertTrue($h['data']['linked']);
        $this->assertCount(1, $h['data']['rows']);                          // tenant 8's row excluded
        $this->assertSame('12', $h['data']['rows'][0]['class_name']);
    }

    public function test_list_filters_sort_and_paginate_in_the_database(): void
    {
        foreach ([['A', 'Surat', 2020], ['B', 'Surat', 2021], ['C', 'Pune', 2021]] as $i => [$n, $city, $y]) {
            $this->act('store', $this->person(['first_name' => $n, 'email' => "$n@x.org", 'mobile' => '90000100' . $i, 'dob' => '2003-0' . ($i + 1) . '-01', 'city' => $city, 'passing_year' => $y]));
        }

        [, $f] = $this->act('index', ['city' => 'Surat', 'sort' => 'full_name', 'dir' => 'asc', 'per_page' => 1, 'page' => 2]);
        [, $q] = $this->act('index', ['q' => 'C Patel']);
        [$badSort] = $this->act('index', ['sort' => 'password; drop table alumni']);

        $this->assertSame(2, $f['data']['pagination']['total']);
        $this->assertSame('B Patel', $f['data']['rows'][0]['full_name']);
        $this->assertSame(1, count($q['data']['rows']));
        $this->assertSame(422, $badSort);
    }

    public function test_settings_pattern_must_contain_a_counter_and_is_used_for_ids(): void
    {
        [$bad] = $this->act('saveSettings', ['id_pattern' => 'ALUMNI']);
        [$ok] = $this->act('saveSettings', ['id_pattern' => 'SCH/{YYYY}/{SEQ:4}']);
        [, $b] = $this->act('store', $this->person());

        $this->assertSame(422, $bad);
        $this->assertSame(200, $ok);
        $this->assertSame('SCH/2022/0001', $b['data']['alumni_code']);
    }

    public function test_dashboard_numbers_come_from_the_tenants_rows(): void
    {
        $this->act('store', $this->person());
        $this->act('store', $this->person(['email' => 'b@x.org', 'mobile' => '9333333333', 'first_name' => 'B', 'dob' => '2001-01-01']), 8);

        [, $d] = $this->act('dashboard');

        $this->assertSame(1, $d['data']['totals']['total']);
        $this->assertSame('Ahmedabad', $d['data']['by_city'][0]['label']);
    }

    public function test_rate_limits_are_per_caller_and_per_action_not_one_shared_ip_bucket(): void
    {
        $limit = fn (string $name, string $token) => \Illuminate\Support\Facades\RateLimiter::limiter($name)(
            Request::create('/x', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'REMOTE_ADDR' => '10.0.0.9'])
        );

        $a = $limit('alumni-export', 'token-a');
        $b = $limit('alumni-export', 'token-b');
        $other = $limit('alumni-upload', 'token-a');

        $this->assertNotSame($a->key, $b->key);        // two users at one IP do not share a counter
        $this->assertNotSame($a->key, $other->key);    // one user's exports do not eat their upload allowance
        $this->assertSame(20, $a->maxAttempts);
        $this->assertSame(30, $other->maxAttempts);

        $throttles = [];
        foreach (\Illuminate\Support\Facades\Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/alumni/')) {   // the staff/student API; portal limiters are named portal-* and tested in AlumniAuthTest
                foreach ($route->gatherMiddleware() as $m) {
                    if (is_string($m) && str_starts_with($m, 'throttle:')) {
                        $throttles[] = $m;
                    }
                }
            }
        }
        foreach ($throttles as $m) {
            $this->assertStringStartsWith('throttle:alumni-', $m, 'a bare throttle:N,1 shares one IP-wide counter: ' . $m);
        }
        $this->assertNotEmpty($throttles);
    }

    public function test_routes_sit_behind_api_session_and_staff_only(): void
    {
        $staff = $student = 0;
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/alumni/')) {   // the portal (api/alumni-portal) deliberately uses its own alumni.auth, see AlumniAuthTest
                continue;
            }
            $this->assertContains('api.session', $route->gatherMiddleware(), $uri);   // every route verifies the JWT
            if (str_starts_with($uri, 'api/alumni/student/')) {
                // student-facing: bound to the student in the token by the controller, so they are NOT staff-only ...
                $this->assertNotContains('staff.only', $route->gatherMiddleware(), $uri);
                $student++;
            } else {
                // ... and everything else keeps students and parents out
                $this->assertContains('staff.only', $route->gatherMiddleware(), $uri);
                $staff++;
            }
        }
        $this->assertGreaterThan(10, $staff);
        $this->assertGreaterThan(3, $student);
    }
}
