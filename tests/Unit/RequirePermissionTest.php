<?php

namespace Tests\Unit;

use App\Http\Middleware\RequirePermission;
use App\Services\Rbac\PermissionService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regression cover for the warn-only rollout switch.
 *
 * NO DATABASE - PermissionService is substituted, so nothing touches the live shared
 * vivek_erp that phpunit.xml points at.
 *
 * WHY THIS EXISTS
 * The first version of this middleware warn-skipped only when the request had NO verified
 * identity. A caller presenting a valid JWT was therefore permission-checked for real
 * while `LMS_API_AUTH_ENFORCE` still said warn-only - so the three legacy write routes
 * began returning 403 to every token-bearing client the moment the middleware shipped.
 * Measured blast radius at the time: 59 (profile, tenant) pairs hold rights on menu 270
 * with no row on menu 236 at all, and 30 of 148 menu-270 rows have can_add=0.
 *
 * Warn-only has to mean warn-only for everybody, or it is not a rollout switch.
 */
class RequirePermissionTest extends TestCase
{
    private function middleware(bool $allowed): RequirePermission
    {
        $permissions = new class($allowed) extends PermissionService {
            public function __construct(private bool $allowed)
            {
            }

            public function check(int $userId, ?int $profileId, int|string $subInstituteId, string $module, string $action): bool
            {
                return $this->allowed;
            }
        };

        return new RequirePermission($permissions);
    }

    /** @param array<string,mixed>|null $auth */
    private function request(?array $auth): Request
    {
        $request = Request::create('/api/lms-create-content', 'POST');
        $request->attributes->set('lms_auth', $auth);

        return $request;
    }

    private const AUTHED = ['user_id' => 1, 'user_profile_id' => 1, 'sub_institute_id' => 1];

    public function test_warn_only_lets_an_UNGRANTED_but_AUTHENTICATED_caller_through(): void
    {
        // The regression. A valid token plus no grant must NOT 403 while warn-only.
        config(['lms_content.api_auth_enforce' => false]);

        $reached = false;
        $response = $this->middleware(false)->handle(
            $this->request(self::AUTHED),
            function () use (&$reached) {
                $reached = true;

                return response('ok');
            },
            'lms.content',
            'create'
        );

        $this->assertTrue($reached, 'Warn-only must pass an ungranted authenticated caller through to the controller.');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_warn_only_lets_a_TOKENLESS_caller_through(): void
    {
        config(['lms_content.api_auth_enforce' => false]);

        $reached = false;
        $this->middleware(false)->handle($this->request(null), function () use (&$reached) {
            $reached = true;

            return response('ok');
        }, 'lms.content', 'create');

        $this->assertTrue($reached);
    }

    public function test_enforcing_denies_an_ungranted_authenticated_caller_with_403(): void
    {
        config(['lms_content.api_auth_enforce' => true]);

        $reached = false;
        $response = $this->middleware(false)->handle($this->request(self::AUTHED), function () use (&$reached) {
            $reached = true;

            return response('ok');
        }, 'lms.content', 'create');

        $this->assertFalse($reached, 'The controller must not be reached when enforcing and ungranted.');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_enforcing_denies_a_tokenless_caller_with_401(): void
    {
        config(['lms_content.api_auth_enforce' => true]);

        $response = $this->middleware(true)->handle($this->request(null), fn () => response('ok'), 'lms.content', 'create');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_a_granted_caller_is_always_allowed_in_either_mode(): void
    {
        foreach ([false, true] as $enforcing) {
            config(['lms_content.api_auth_enforce' => $enforcing]);

            $reached = false;
            $this->middleware(true)->handle($this->request(self::AUTHED), function () use (&$reached) {
                $reached = true;

                return response('ok');
            }, 'lms.content', 'create');

            $this->assertTrue($reached, 'A granted caller must pass in both modes.');
        }
    }
}
