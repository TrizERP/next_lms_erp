<?php

namespace Tests\Feature\Security;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Track D / Issue 1 — "Remove auth bypass in middleware".
 *
 * Both `session` (App\Http\Middleware\SessionMiddleware) and `api.session`
 * (App\Http\Middleware\ApiSessionHydrator) exist to attach a verified,
 * JWT-derived identity to the request before any session()-reading
 * controller/middleware runs. Previously SessionMiddleware's auth check was
 * gated on `type !== "API"` — meaning any request sent the way the Next.js
 * frontend (and every mobile client) actually sends requests, with
 * `type=API`, skipped authentication entirely and fell straight through to
 * the controller. This test proves both middleware now reject an
 * unauthenticated `type=API` request instead of letting it through.
 *
 * These routes are defined ad hoc in each test rather than reused from
 * routes/*.php so the assertion is only about the middleware itself, not
 * about downstream permission/business logic (checkPermission, menu, etc.)
 * that also happens to run on the real fees/API routes.
 */
class SessionMiddlewareAuthBypassTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('session')->post('/__test/session-middleware-probe', function () {
            return response()->json(['ok' => true, 'user_id' => session()->get('user_id')]);
        });

        Route::middleware('api.session')->post('/__test/api-session-hydrator-probe', function () {
            return response()->json(['ok' => true, 'user_id' => session()->get('user_id')]);
        });
    }

    private function tokenFor(int $userId, int $subInstituteId): string
    {
        $payload = [
            'id' => $userId,
            'sub_institute_id' => $subInstituteId,
            'user_profile_id' => 1,
            'is_admin' => 1,
            'is_student' => false,
            'client_id' => null,
        ];

        return JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    public function test_session_middleware_rejects_an_api_mode_request_with_no_token(): void
    {
        $response = $this->postJson('/__test/session-middleware-probe', ['type' => 'API']);

        $response->assertStatus(401);
    }

    public function test_session_middleware_rejects_an_api_mode_request_with_a_garbage_token(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer not-a-real-jwt'])
            ->postJson('/__test/session-middleware-probe', ['type' => 'API']);

        $response->assertStatus(401);
    }

    public function test_session_middleware_accepts_an_api_mode_request_with_a_valid_token(): void
    {
        $instituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Session MW Test School',
            'ShortCode' => 'SMW' . random_int(1000, 9999),
            'ContactPerson' => 'Test',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        DB::table('academic_year')->insert([
            'sub_institute_id' => $instituteId,
            'syear' => 2026,
            'term_id' => 1,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'sort_order' => 1,
        ]);

        $userId = (int) DB::table('tbluser')->insertGetId([
            'user_name' => 'smwuser' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Session',
            'last_name' => 'Probe',
            'email' => 'smw' . random_int(10000, 99999) . '@example.com',
            'mobile' => '9999999999',
            'user_profile_id' => 1,
            'join_year' => '2026',
            'sub_institute_id' => $instituteId,
            'is_admin' => 1,
            'status' => 1,
        ]);

        $token = $this->tokenFor($userId, $instituteId);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/__test/session-middleware-probe', ['type' => 'API']);

        $response->assertStatus(200)->assertJson(['user_id' => $userId]);
    }

    public function test_api_session_hydrator_rejects_a_request_with_no_token(): void
    {
        $response = $this->postJson('/__test/api-session-hydrator-probe');

        $response->assertStatus(401);
    }

    public function test_api_session_hydrator_rejects_a_request_with_a_garbage_token(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer not-a-real-jwt'])
            ->postJson('/__test/api-session-hydrator-probe');

        $response->assertStatus(401);
    }

    /**
     * Pre-fix, ApiSessionHydrator fell back to
     * `$request->input('sub_institute_id')` whenever the token's own
     * `sub_institute_id` claim was missing — so a request carrying any
     * *valid* token (for any tenant) plus a spoofed `sub_institute_id` body
     * field could hydrate a session for a school the token was never issued
     * for. The fix takes the tenant exclusively from the verified payload.
     */
    public function test_api_session_hydrator_does_not_trust_a_client_supplied_sub_institute_id(): void
    {
        $payload = [
            'id' => 123456,
            // no sub_institute_id claim in the token itself
            'user_profile_id' => 1,
            'is_admin' => 1,
            'is_student' => false,
            'client_id' => null,
        ];
        $token = JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/__test/api-session-hydrator-probe', ['sub_institute_id' => 999]);

        $response->assertStatus(401);
    }
}
