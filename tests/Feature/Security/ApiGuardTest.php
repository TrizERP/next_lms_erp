<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\RequireApiJwt;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * V1 hardening: the `api/*` URLs that used to have no authentication at all
 * (config/api_guard.php `protect`) now need a JWT or a web login session, and
 * a caller may only act for its own school unless it is a multi-school admin.
 *
 * The phone-book test inspects middleware stacks instead of dispatching, and
 * the dispatched cases are read-only: this suite runs against whatever DB is
 * configured, so a regression must never be able to fire a real write.
 */
class ApiGuardTest extends TestCase
{
    private function token(array $claims = []): string
    {
        return JWT::encode(array_merge([
            'id' => 1,
            'sub_institute_id' => 7,
            'user_profile_id' => 1,
            'is_admin' => 0,
            'is_student' => false,
            'client_id' => null,
        ], $claims), env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    private function isProtected(string $method, string $uri, array $query = []): bool
    {
        return app(RequireApiJwt::class)->isProtected(Request::create('/' . $uri, $method, $query));
    }

    public function test_read_only_routes_that_were_open_now_return_401_without_a_token(): void
    {
        foreach ([
            'api/get-standard-list',          // web-group route (result.php)
            'api/get-exam-list',
            'api/question-paper/1',
            'api/online_admission_confirm/1',
            'api/admission_registration',
            'api/pal/pedagogy-engine',        // route file with no middleware group
        ] as $uri) {
            $this->getJson('/' . $uri . '?sub_institute_id=7')->assertStatus(401);
        }
    }

    public function test_every_route_the_guard_protects_runs_the_guard(): void
    {
        $router = app('router');
        $covered = 0;
        $missing = [];

        foreach ($router->getRoutes() as $route) {
            if (strpos($route->uri(), 'api/') !== 0) {
                continue;
            }
            $stack = $router->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());
            $hasGuard = in_array(RequireApiJwt::class, $stack, true);

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $uri = preg_replace('/\{[^}]+\??\}/', '1', $route->uri());
                if (! $this->isProtected($method, $uri)) {
                    continue;
                }
                $hasGuard ? $covered++ : $missing[] = "$method {$route->uri()}";
            }
        }

        $this->assertSame([], $missing, 'protected URLs whose route never runs api.jwt');
        $this->assertGreaterThan(100, $covered, 'the guard should cover well over 100 route/method pairs');
    }

    public function test_deliberately_public_routes_are_not_guarded(): void
    {
        foreach ([
            ['POST', 'api/api-login'],
            ['POST', 'api/login'],
            ['POST', 'api/online_admission_confirm'],
            ['POST', 'api/incoming-message'],
            ['GET', 'api/mobile/web-handoff/claims'],
            ['GET', 'api/g2g-lms/certifications-records/certificates/verify/ABC123'],
        ] as [$method, $uri]) {
            $this->assertFalse($this->isProtected($method, $uri), "$method $uri should stay public");
        }
        // Non-api web routes are never touched by this guard.
        $this->assertFalse($this->isProtected('GET', 'get-standard-list'));
    }

    public function test_public_webform_dropdowns_work_only_with_type_webform(): void
    {
        $this->assertFalse($this->isProtected('GET', 'api/get-standard-list', ['type' => 'webForm']));
        $this->assertFalse($this->isProtected('GET', 'api/get-division-list', ['type' => 'webForm']));
        $this->assertTrue($this->isProtected('GET', 'api/get-standard-list'));
        // The exception is per-URL: other dropdowns stay closed even with the flag.
        $this->assertTrue($this->isProtected('GET', 'api/get-exam-list', ['type' => 'webForm']));
    }

    public function test_tenant_rule_with_a_jwt(): void
    {
        Route::middleware('api.jwt')->post('/api/get-__probe', fn () => response()->json(['ok' => true]));

        $post = fn (string $token, array $body) => $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/get-__probe', $body);

        $post($this->token(), [])->assertOk();
        $post($this->token(), ['sub_institute_id' => 7])->assertOk();
        // A plain user of school 7 cannot name school 8.
        $post($this->token(), ['sub_institute_id' => 8])->assertStatus(403);
        // A school admin with no client cannot either.
        $post($this->token(['is_admin' => 1]), ['sub_institute_id' => 8])->assertStatus(403);
        // The multi-client super admin may.
        $post($this->token(['is_admin' => 2]), ['sub_institute_id' => 8])->assertOk();
    }

    public function test_a_forged_or_garbage_token_is_rejected(): void
    {
        Route::middleware('api.jwt')->post('/api/get-__probe2', fn () => response()->json(['ok' => true]));

        $this->withHeaders(['Authorization' => 'Bearer not-a-jwt'])
            ->postJson('/api/get-__probe2')->assertStatus(401);

        $forged = JWT::encode(['id' => 1, 'sub_institute_id' => 7], 'wrong-secret', 'HS256');
        $this->withHeaders(['Authorization' => 'Bearer ' . $forged])
            ->postJson('/api/get-__probe2')->assertStatus(401);

        // A bad token is not rescued by a valid session.
        $this->withSession(['user_id' => 1, 'sub_institute_id' => 7])
            ->withHeaders(['Authorization' => 'Bearer not-a-jwt'])
            ->postJson('/api/get-__probe2')->assertStatus(401);
    }

    public function test_a_logged_in_blade_session_is_accepted_and_scoped_to_its_school(): void
    {
        Route::middleware('web')->get('/api/get-__probe3', fn () => response()->json(['ok' => true]));

        $session = ['user_id' => 5, 'sub_institute_id' => 7, 'is_admin' => 0];

        $this->getJson('/api/get-__probe3')->assertStatus(401);
        $this->withSession($session)->getJson('/api/get-__probe3')->assertOk();
        $this->withSession($session)->getJson('/api/get-__probe3?sub_institute_id=7')->assertOk();
        $this->withSession($session)->getJson('/api/get-__probe3?sub_institute_id=8')->assertStatus(403);
    }

    public function test_staff_urls_refuse_students_and_parents_but_student_urls_do_not(): void
    {
        Route::middleware('api.jwt')->post('/api/interactions/__probe', fn () => response()->json(['ok' => true]));
        Route::middleware('api.jwt')->post('/api/get-__probe4', fn () => response()->json(['ok' => true]));

        $call = fn (string $uri, array $claims) => $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($claims + ['user_profile_id' => null])])
            ->postJson($uri);

        // A staff URL: the student flag is refused, anyone else is let through.
        $call('/api/interactions/__probe', ['is_student' => true])->assertStatus(403);
        $call('/api/interactions/__probe', ['is_student' => false])->assertOk();
        $call('/api/interactions/__probe', ['is_admin' => 1, 'is_student' => false])->assertOk();

        // A student-facing URL is open to any logged-in user, students included.
        $call('/api/get-__probe4', ['is_student' => true])->assertOk();
    }

    public function test_teacher_only_writes_are_staff_only_but_the_matching_reads_are_not(): void
    {
        $req = fn (string $method, string $uri) => Request::create('/' . $uri, $method);
        $guard = app(RequireApiJwt::class);
        $isStaffOnly = function (string $method, string $uri) use ($req) {
            $request = $req($method, $uri);
            foreach ((array) config('api_guard.staff') as $pattern) {
                if (preg_match('/^([A-Z]+)\s+(.+)$/', $pattern, $m)) {
                    if ($m[1] !== $method) { continue; }
                    $pattern = $m[2];
                }
                if (fnmatch($pattern, substr($request->path(), 4))) { return true; }
            }
            return false;
        };

        foreach ([
            ['DELETE', 'api/question-paper/1'], ['POST', 'api/question-paper'], ['PUT', 'api/question-paper/1'],
            ['POST', 'api/lms-homework/store'], ['POST', 'api/lms-homework/delete/1'],
            ['POST', 'api/lms-question-bank/delete'], ['DELETE', 'api/admission_enquiry/1'],
            ['POST', 'api/students-dashboard/summary'], ['POST', 'api/exam-evaluation/batches'],
        ] as [$m, $u]) {
            $this->assertTrue($isStaffOnly($m, $u), "$m $u should be staff-only");
        }
        foreach ([
            ['POST', 'api/lms-homework/list'], ['POST', 'api/lms-homework/submission-store'],
            ['GET', 'api/question-paper/1'], ['POST', 'api/question-paper/search'],
            ['GET', 'api/get-standard-list'], ['POST', 'api/menu-rights'], ['POST', 'api/lms-courses'],
        ] as [$m, $u]) {
            $this->assertFalse($isStaffOnly($m, $u), "$m $u must stay open to students");
        }
        $this->assertInstanceOf(RequireApiJwt::class, $guard);
    }

    /**
     * The safety net for the future: every `api/*` route must be guarded by config/api_guard.php,
     * sit behind an auth middleware, be served by a controller that validates the JWT itself, or be
     * named below as deliberately public. A new route that is none of these fails here, so an
     * anonymous endpoint cannot be added by accident again.
     */
    public function test_every_api_route_is_guarded_authenticated_or_deliberately_public(): void
    {
        // Method + URI of routes that are meant to be reachable without a token. Keep this short.
        $deliberatelyPublic = [
            'GET api/crm-whatsapp', 'GET api/crm-whatsapp-update', 'POST api/incoming-message',
            'POST api/update-message', 'POST api/whats-send-app', 'POST api/whats-comming-app',
            'GET api/g2g-lms/certifications-records/certificates/verify/{code}',
            'POST api/online_admission_confirm',
            'POST api/api-login',
            'GET api/testkey', // only registered in local/testing, see routes/api.php
        ];

        $router = app('router');
        $guard = app(RequireApiJwt::class);
        $sources = [];
        $open = [];

        foreach ($router->getRoutes() as $route) {
            if (strpos($route->uri(), 'api/') !== 0) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $request = Request::create('/' . preg_replace('/\{[^}]+\??\}/', '1', $route->uri()), $method);
                if ($guard->isProtected($request) || in_array("$method {$route->uri()}", $deliberatelyPublic, true)) {
                    continue;
                }
                $stack = $router->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());
                $hasAuthMiddleware = collect($stack)->contains(
                    fn ($m) => is_string($m) && preg_match('/Auth|Session|Permission|Brain|Mcp|Staff|Hydrator|Rights|Task/i', $m)
                );
                if ($hasAuthMiddleware) {
                    continue;
                }
                $action = $route->getActionName();
                if ($action !== 'Closure' && strpos($action, '@') !== false) {
                    [$class] = explode('@', $action);
                    if (class_exists($class)) {
                        // The controller's own file plus the traits it uses: several controllers
                        // authenticate through a shared trait (ResolvesLeaveContext, ...).
                        $files = [(new \ReflectionClass($class))->getFileName()];
                        foreach (class_uses_recursive($class) as $trait) {
                            $files[] = (new \ReflectionClass($trait))->getFileName();
                        }
                        $code = '';
                        foreach ($files as $file) {
                            $sources[$file] ??= file_get_contents($file);
                            $code .= $sources[$file];
                        }
                        if (preg_match('/jwtToken\(|->validate\(\)|hydrateSession/', $code)) {
                            continue; // the controller checks the token itself
                        }
                    }
                }
                $open[] = "$method {$route->uri()} => $action";
            }
        }

        sort($open);
        $this->assertSame([], $open, "api routes with no authentication and not listed as public:
" . implode("
", $open));
    }

    public function test_url_encoding_cannot_slip_past_the_guard(): void
    {
        // The router decodes the path before matching, so each of these reaches the real route.
        foreach (['/api/get%2Dexam%2Dlist', '/api/get-exam%2Dlist', '/api/%67et-exam-list'] as $uri) {
            $this->getJson($uri)->assertStatus(401);
        }
    }

    public function test_an_array_valued_school_id_is_refused(): void
    {
        Route::middleware('api.jwt')->post('/api/get-__probe5', fn () => response()->json(['ok' => true]));

        // (int) of an array is 1; it must not be read as "school 1" (or as the caller's school).
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token(['sub_institute_id' => 1])])
            ->postJson('/api/get-__probe5', ['sub_institute_id' => [8]])
            ->assertStatus(403);
    }
}
