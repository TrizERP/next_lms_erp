<?php

namespace Tests\Unit;

use App\Http\Middleware\RequireLmsStaff;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The staff-only gate every new Fees Intelligence route relies on
 * (`routes/platform/fees_intelligence.php`) — not previously covered by a
 * dedicated test anywhere in this estate, despite already gating the
 * pre-existing `/events` routes. A student session reading tenant-wide
 * arrears/family data is exactly the leak this middleware exists to close.
 *
 * Extends the Laravel-aware `Tests\TestCase` rather than plain PHPUnit, since
 * the middleware's own 403 path calls the `response()` container helper.
 */
class RequireLmsStaffTest extends TestCase
{
    private function middleware(): RequireLmsStaff
    {
        return new RequireLmsStaff();
    }

    private function requestWithAuth(?array $auth): Request
    {
        $request = Request::create('/api/platform/workflow/fees-intelligence', 'GET');
        $request->attributes->set('lms_auth', $auth);

        return $request;
    }

    public function test_a_student_session_is_refused_with_403(): void
    {
        $response = $this->middleware()->handle(
            $this->requestWithAuth(['user_id' => 1, 'sub_institute_id' => 1, 'is_admin' => 0, 'is_student' => true]),
            fn ($request) => 'next-called'
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, $response->getData(true)['status_code']);
    }

    public function test_an_unidentified_caller_is_deferred_not_blocked(): void
    {
        $result = $this->middleware()->handle($this->requestWithAuth(null), fn ($request) => 'next-called');

        $this->assertSame('next-called', $result, 'no token means defer to the controller\'s own 401, never a 403 here');
    }

    public function test_a_staff_session_with_no_profile_id_passes_through(): void
    {
        $result = $this->middleware()->handle(
            $this->requestWithAuth(['user_id' => 1, 'sub_institute_id' => 1, 'is_admin' => 1, 'is_student' => false]),
            fn ($request) => 'next-called'
        );

        $this->assertSame('next-called', $result);
    }
}
