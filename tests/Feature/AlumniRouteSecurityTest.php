<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * A route-by-route security net for the whole alumni API, so a new endpoint cannot be added without its protection.
 *
 * It walks EVERY registered `api/alumni/*` route and proves:
 *   1. it runs behind `api.session` (the verified-JWT tenant/user), and staff routes also behind `staff.only`;
 *   2. every staff endpoint refuses a profile with no alumni role (Teacher) - the role gate is in the controller, because the
 *      `perm:` middleware is warn-only in this codebase - and does so BEFORE doing any work or touching any record;
 *   3. money and audit endpoints also refuse an Alumni Coordinator (who manages alumni but may not see donations or the log);
 *   4. every student endpoint refuses a staff profile;
 *   5. a staff route can never be reached with a request-supplied tenant: sub_institute_id in the body is ignored.
 *
 * In-memory sqlite (see AlumniTestHelpers). The calls pass dummy ids, so a gate that was missing would reach a controller body
 * and fail loudly (404/422/500 instead of 403).
 */
class AlumniRouteSecurityTest extends TestCase
{
    use AlumniTestHelpers;

    /** Controller methods that are not a gate-then-work endpoint of their own. */
    private const MONEY_OR_AUDIT = ['AlumniDonationApiController', 'AlumniAuditApiController'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
        \Illuminate\Support\Facades\Schema::create('tblstudent', function ($t) {   // studentGate checks the caller is a real student of the institute
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
        });
    }

    /** @return array<int, LaravelRoute> */
    private function alumniRoutes(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), fn (LaravelRoute $r) => str_starts_with($r->uri(), 'api/alumni/')));   // the staff and student API; the portal (api/alumni-portal) has its own tests in AlumniAuthTest
    }

    private function middleware(LaravelRoute $r): array
    {
        return array_values(array_filter($r->gatherMiddleware(), 'is_string'));
    }

    /** Call a route's controller method the way the framework would, with a session for the given profile. */
    private function invoke(LaravelRoute $r, string $profile, bool $student = false, array $input = []): int
    {
        [$class, $method] = explode('@', $r->getActionName());
        $request = Request::create('/x', 'POST', $input);
        $request->merge(['type' => 'API']);
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', 7);
        $store->put('user_id', 55);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);
        // dummy route parameters, in the route's own order: ids are numeric, a report key / child kind needs a real-looking word
        $args = array_map(fn (string $p) => match ($p) {
            'key' => 'master', 'kind' => 'career', default => '1',
        }, $r->parameterNames());

        return app($class)->{$method}($request, ...$args)->getStatusCode();
    }

    public function test_every_alumni_route_sits_behind_the_verified_session(): void
    {
        $routes = $this->alumniRoutes();
        $this->assertGreaterThan(100, count($routes), 'the route walk found the alumni API');
        $missing = [];
        $studentOnly = 0;
        foreach ($routes as $r) {
            $mw = $this->middleware($r);
            if (! in_array('api.session', $mw, true)) {
                $missing[] = 'no api.session: ' . $r->uri();
            }
            if (str_starts_with($r->uri(), 'api/alumni/student')) {
                $studentOnly++;
                if (in_array('staff.only', $mw, true)) {
                    $missing[] = 'student route wrongly staff.only: ' . $r->uri();
                }
            } elseif (! in_array('staff.only', $mw, true)) {
                $missing[] = 'staff route without staff.only: ' . $r->uri();
            }
        }
        $this->assertSame([], $missing);
        $this->assertGreaterThan(0, $studentOnly);
    }

    public function test_every_staff_endpoint_refuses_a_profile_with_no_alumni_role(): void
    {
        $failures = [];
        $checked = 0;
        foreach ($this->alumniRoutes() as $r) {
            if (str_starts_with($r->uri(), 'api/alumni/student')) {
                continue;
            }
            $checked++;
            try {
                $code = $this->invoke($r, 'Teacher');
            } catch (\Throwable $e) {
                $code = 'threw ' . get_class($e);
            }
            if ($code !== 403) {
                $failures[] = $r->methods()[0] . ' ' . $r->uri() . ' -> ' . $code;
            }
        }
        $this->assertGreaterThan(100, $checked);
        $this->assertSame([], $failures, 'every staff endpoint must refuse a Teacher with 403 before doing anything');
    }

    public function test_money_and_audit_endpoints_also_refuse_an_alumni_coordinator(): void
    {
        $failures = [];
        $checked = 0;
        foreach ($this->alumniRoutes() as $r) {
            $class = class_basename(explode('@', $r->getActionName())[0]);
            $isReport = $class === 'AlumniReportApiController';
            if (! in_array($class, self::MONEY_OR_AUDIT, true) && ! $isReport) {
                continue;
            }
            // reports: a coordinator may open most, but never the donation report
            $input = [];
            $params = $r->parameterNames();
            if ($isReport && $params === []) {
                continue;   // the catalog itself is filtered per role and is tested in AlumniReportsTest
            }
            $checked++;
            $code = $isReport ? $this->invokeReport($r) : $this->invoke($r, 'Alumni Coordinator');
            if ($code !== 403) {
                $failures[] = $r->uri() . ' -> ' . $code;
            }
        }
        $this->assertGreaterThan(10, $checked);
        $this->assertSame([], $failures);
    }

    /** The donation report, show and export, as a coordinator. */
    private function invokeReport(LaravelRoute $r): int
    {
        [$class, $method] = explode('@', $r->getActionName());
        $request = Request::create('/x', 'POST', ['format' => 'csv']);
        $request->merge(['type' => 'API']);
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', 7);
        $store->put('user_id', 55);
        $store->put('user_profile_name', 'Alumni Coordinator');
        $store->put('is_student', false);

        return app($class)->{$method}($request, 'donation')->getStatusCode();
    }

    public function test_student_endpoints_refuse_staff_and_unlinked_callers(): void
    {
        $failures = [];
        $checked = 0;
        foreach ($this->alumniRoutes() as $r) {
            if (! str_starts_with($r->uri(), 'api/alumni/student')) {
                continue;
            }
            $checked++;
            $code = $this->invoke($r, 'Admin', false);   // staff, not a student
            if ($code !== 403) {
                $failures[] = $r->uri() . ' (staff) -> ' . $code;
            }
            $code = $this->invoke($r, 'Student', true);   // a student id that does not exist in tblstudent here
            if ($code !== 403) {
                $failures[] = $r->uri() . ' (unknown student) -> ' . $code;
            }
        }
        $this->assertGreaterThan(5, $checked);
        $this->assertSame([], $failures);
    }

    public function test_a_tenant_in_the_request_body_is_ignored(): void
    {
        // Institute 8 has an alumnus; an Admin of institute 7 names institute 8 in every field a client could try.
        $this->alum('Mine', ['city' => 'Pune'], 7);
        $foreign = $this->alum('Theirs', ['city' => 'Pune'], 8);
        $spoof = ['sub_institute_id' => 8, 'tenant' => 8, 'institute_id' => 8, 'school_id' => 8];

        $list = $this->go(\App\Http\Controllers\api\alumni\AlumniApiController::class, 'index', $spoof, 7);
        $names = array_column($list[1]['data']['rows'] ?? [], 'full_name');
        $this->assertContains('Mine Alum', $names);
        $this->assertNotContains('Theirs Alum', $names);
        $this->assertSame(404, $this->go(\App\Http\Controllers\api\alumni\AlumniApiController::class, 'show', $spoof, 7, 'Admin', [$foreign->id])[0], 'reading another institute\'s record');
        $this->assertSame(404, $this->go(\App\Http\Controllers\api\alumni\AlumniApiController::class, 'update', $spoof + ['first_name' => 'Hijacked'], 7, 'Admin', [$foreign->id])[0], 'writing it');
        $this->assertSame('Theirs', DB::table('alumni')->where('id', $foreign->id)->value('first_name'));

        $report = $this->go(\App\Http\Controllers\api\alumni\AlumniReportApiController::class, 'show', $spoof, 7, 'Admin', ['master']);
        $this->assertNotContains('Theirs Alum', array_column($report[1]['data']['rows'], 'full_name'));
    }
}
