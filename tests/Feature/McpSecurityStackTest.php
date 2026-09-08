<?php

namespace Tests\Feature;

use App\Http\Middleware\McpAuth;
use App\Http\Middleware\McpContextHydrator;
use App\Http\Middleware\McpRateLimit;
use App\Mcp\AbstractMcpTool;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpConfirmationService;
use App\Services\Mcp\McpContextResolver;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request as McpRequest;
use Tests\TestCase;

/**
 * The security stack in front of every MCP surface, and where tenant scope comes from.
 *
 * The port to Laravel\Mcp added routes this application did not write — Mcp::web()
 * registers a GET and a DELETE alongside the POST — which is precisely the way a
 * middleware stack acquires a hole: nobody removed anything, a new door simply appeared
 * beside the guarded one.
 *
 * These assertions are over routing and object wiring, not the database, so they keep
 * passing on a machine that cannot reach the estate.
 */
class McpSecurityStackTest extends TestCase
{
    /**
     * @var array<int, class-string>
     */
    private const REQUIRED_MIDDLEWARE = [
        McpAuth::class,
        McpRateLimit::class,
        McpContextHydrator::class,
    ];

    public function test_every_mcp_route_runs_the_full_security_stack(): void
    {
        $prefix = trim((string) config('mcp.route_prefix', 'api/mcp'), '/');
        $problems = [];
        $checked = 0;

        foreach (Router::getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), $prefix)) {
                continue;
            }

            $checked++;
            $applied = $route->gatherMiddleware();

            foreach (self::REQUIRED_MIDDLEWARE as $required) {
                if (! in_array($required, $applied, true)) {
                    $problems[] = sprintf(
                        '%s /%s is missing %s',
                        implode('|', $route->methods()),
                        $route->uri(),
                        class_basename($required)
                    );
                }
            }
        }

        // Mcp::web() alone registers three (GET, DELETE, POST); the façade adds four.
        $this->assertGreaterThanOrEqual(7, $checked, 'Fewer MCP routes than expected were inspected.');
        $this->assertSame(
            [],
            $problems,
            "MCP routes are reachable without the full stack:\n  " . implode("\n  ", $problems)
        );
    }

    public function test_the_get_and_delete_verbs_the_package_registers_are_guarded_too(): void
    {
        // Named explicitly because these two are not ours: Mcp::web() creates them as
        // protocol 405 stubs and returns only the POST route, so chaining
        // ->middleware() onto its return value guarded one verb out of three.
        $prefix = trim((string) config('mcp.route_prefix', 'api/mcp'), '/');
        $byMethod = [];

        foreach (Router::getRoutes() as $route) {
            /** @var Route $route */
            if ($route->uri() !== $prefix) {
                continue;
            }

            foreach ($route->methods() as $method) {
                $byMethod[$method] = $route->gatherMiddleware();
            }
        }

        foreach (['GET', 'DELETE', 'POST'] as $method) {
            $this->assertArrayHasKey($method, $byMethod, "No {$method} route on /{$prefix}.");
            $this->assertContains(McpAuth::class, $byMethod[$method], "{$method} /{$prefix} is unauthenticated.");
            $this->assertContains(McpRateLimit::class, $byMethod[$method], "{$method} /{$prefix} is unthrottled.");
        }
    }

    public function test_the_official_endpoint_rejects_an_unauthenticated_call(): void
    {
        // McpAuth runs before the hydrator, so this never reaches the database.
        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'students.directory', 'arguments' => []],
        ])->assertStatus(401);
    }

    public function test_the_unauthenticated_rejection_covers_every_verb_and_surface(): void
    {
        $this->getJson('/api/mcp')->assertStatus(401);
        $this->deleteJson('/api/mcp')->assertStatus(401);
        $this->getJson('/api/mcp/tools')->assertStatus(401);
        $this->postJson('/api/mcp/tools/call', ['tool' => 'students.directory'])->assertStatus(401);
    }

    public function test_no_tool_accepts_a_tenant_argument(): void
    {
        // The invariant: scope is the hydrator's business. A tool that took an institute
        // id would let a caller pick a tenant by writing it in the payload, and the JWT
        // would be reduced to proof that *somebody* was logged in.
        $forbidden = [
            'institute_id', 'sub_institute_id', 'institute', 'tenant_id', 'tenant',
            'client_id', 'user_id', 'academic_year', 'syear', 'allowed_institute_ids',
        ];

        $problems = [];

        foreach (app(ToolRegistry::class)->tools() as $tool) {
            $published = (array) ($tool->toArray()['inputSchema']['properties'] ?? []);
            $declared = (array) ($tool->definition()['input_schema']['properties'] ?? []);

            foreach (array_keys($published + $declared) as $property) {
                if (in_array($property, $forbidden, true)) {
                    $problems[] = $tool->name() . ' accepts "' . $property . '"';
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            "These tools let a caller name its own tenant scope:\n  " . implode("\n  ", $problems)
        );
    }

    public function test_a_tool_is_scoped_by_the_hydrated_context_not_by_its_arguments(): void
    {
        $tool = new ScopeSpyTool();
        $registry = new ToolRegistry([$tool], $this->createMock(McpConfirmationService::class));

        $hydrated = new McpRequestContext(
            userId: 42,
            role: 'staff',
            selectedInstituteId: 7,
            allowedInstituteIds: [7],
            userProfileId: null,
            clientId: null,
            academicYear: 2026,
            termId: null,
            isAdmin: false,
            isStudent: false,
        );

        request()->attributes->set('mcp_context', $hydrated);

        // A caller doing its level best to name a different tenant in the payload.
        $tool->handle(new McpRequest([
            'sub_institute_id' => 999,
            'institute_id' => 999,
            'user_id' => 1,
            'academic_year' => 1999,
        ]), $registry);

        $this->assertSame($hydrated, $tool->scope, 'The tool was scoped by something other than the hydrator.');
        $this->assertSame(7, $tool->scope->selectedInstituteId);
        $this->assertSame(42, $tool->scope->userId);
        $this->assertSame([7], $tool->scope->allowedInstituteIds);
    }

    public function test_the_resolver_refuses_an_institute_outside_the_token_allow_list(): void
    {
        // The one legitimate way to select a tenant is a request-level override, and it
        // is checked against the institutes the JWT actually grants. This throws before
        // any query runs, so it is safe to assert here.
        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('X-MCP-Institute-Id', '999');

        $this->expectException(ValidationException::class);

        app(McpContextResolver::class)->resolve($request, [
            'user_id' => 1,
            'sub_institute_id' => '7,8',
            'is_admin' => 1,
            'is_student' => false,
            'role' => 'admin',
        ]);
    }

    public function test_the_selected_institute_is_always_inside_the_allowed_set(): void
    {
        // The invariant that keeps a request from contradicting itself. Scope filters
        // pin to the selection and then intersect it with the allowed set; a selection
        // outside that set makes StudentScope and EntityResolver return zero rows while
        // the tool services return the institute's data.
        $scope = new \ReflectionMethod(McpContextResolver::class, 'resolveInstituteScope');
        $scope->setAccessible(true);
        $resolver = app(McpContextResolver::class);

        $cases = [
            'super admin targeting outside their claim' => [
                'header' => '999',
                'auth' => ['sub_institute_id' => '7,8', 'is_admin' => 2],
                'expectSelected' => 999,
            ],
            'super admin with no claim at all' => [
                'header' => '999',
                'auth' => ['sub_institute_id' => '', 'is_admin' => 2],
                'expectSelected' => 999,
            ],
            'staff selecting inside their claim' => [
                'header' => '8',
                'auth' => ['sub_institute_id' => '7,8', 'is_admin' => 0],
                'expectSelected' => 8,
            ],
            'staff with no override' => [
                'header' => null,
                'auth' => ['sub_institute_id' => '7,8', 'is_admin' => 0],
                'expectSelected' => 7,
            ],
        ];

        foreach ($cases as $label => $case) {
            $request = Request::create('/api/mcp', 'POST');

            if ($case['header'] !== null) {
                $request->headers->set('X-MCP-Institute-Id', $case['header']);
            }

            [$selected, $allowed] = $scope->invoke($resolver, $request, $case['auth']);

            $this->assertSame($case['expectSelected'], $selected, $label);
            $this->assertContains($selected, $allowed, "{$label}: the selection is outside its own allowed set.");
        }
    }

    public function test_folding_the_selection_in_does_not_widen_an_ordinary_caller(): void
    {
        // The fold must be reachable only through the super-admin bypass. For everyone
        // else the allowed set stays exactly what the token granted.
        $scope = new \ReflectionMethod(McpContextResolver::class, 'resolveInstituteScope');
        $scope->setAccessible(true);

        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('X-MCP-Institute-Id', '8');

        [$selected, $allowed] = $scope->invoke(
            app(McpContextResolver::class),
            $request,
            ['sub_institute_id' => '7,8', 'is_admin' => 1]
        );

        $this->assertSame(8, $selected);
        $this->assertSame([7, 8], $allowed, 'An ordinary admin gained an institute the token did not grant.');
    }

    public function test_the_rate_limiter_keys_on_the_authenticated_user_not_the_payload(): void
    {
        // Keying on anything a caller controls would make the limit opt-out.
        $middleware = new \ReflectionMethod(McpRateLimit::class, 'resolveKey');
        $middleware->setAccessible(true);

        $request = Request::create('/api/mcp', 'POST', ['user_id' => 'spoofed']);
        $request->attributes->set('mcp_auth', ['user_id' => 42]);

        $this->assertSame('mcp:42', $middleware->invoke(app(McpRateLimit::class), $request));
    }
}

/**
 * Records the scope it was handed, so a test can prove where it came from.
 */
class ScopeSpyTool extends AbstractMcpTool
{
    public ?McpRequestContext $scope = null;

    public function name(): string
    {
        return 'testing.scope_spy';
    }

    public function description(): string
    {
        return 'Records the request context it was executed with.';
    }

    protected function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false];
    }

    protected function allowedRoles(): array
    {
        return ['admin', 'staff'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->scope = $context;

        return ['institute' => $context->selectedInstituteId];
    }
}
