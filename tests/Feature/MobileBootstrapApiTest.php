<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GET /api/mobile/bootstrap is the native app's single "who am I + which menu" call.
 * These cases need no database rows: phpunit.xml points at the shared live DB, so the
 * suite only asserts routing and that identity can come from nowhere but the token.
 */
class MobileBootstrapApiTest extends TestCase
{
    public function test_route_is_registered_behind_api_session(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/mobile/bootstrap', 'GET'));

        $this->assertContains('api.session', $route->gatherMiddleware());
    }

    public function test_it_rejects_requests_without_a_token(): void
    {
        $this->getJson('/api/mobile/bootstrap')->assertStatus(401);
    }

    public function test_it_rejects_a_garbage_token_even_if_body_claims_a_role(): void
    {
        // The legacy /api/homescreen trusts user_profile_id from the body; this must not.
        $this->getJson('/api/mobile/bootstrap?user_profile_id=1&sub_institute_id=1&user_profile_name=Super+Admin', [
            'Authorization' => 'Bearer not-a-real-token',
        ])->assertStatus(401);
    }

    public function test_controller_never_reads_identity_from_the_request(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/api/MobileBootstrapApiController.php'));

        $this->assertDoesNotMatchRegularExpression('/\$request->(input|get|query|post)\(/', $src);
    }
}
