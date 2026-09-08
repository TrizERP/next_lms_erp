<?php

namespace Tests\Feature;

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Mcp\AbstractMcpTool;
use App\Mcp\ConfirmableMcpToolInterface;
use App\Mcp\Servers\LmsMcpServer;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpAuditService;
use App\Services\Mcp\McpConfirmationService;
use App\Services\Mcp\McpContextResolver;
use App\Services\Mcp\McpRequestContext;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

/**
 * The module tool bindings and the tool registry have to agree — and both transports
 * have to agree with the registry.
 *
 * The first half exists because the bindings silently did not agree. `config/ai.php`
 * bound the fees module to `fees.get_pending`, and the registered tool is called
 * `fees.getPending` — so the binding matched nothing, the tool was unreachable from
 * every fees question, and nothing anywhere reported a problem. A name that does not
 * exist is not an error at runtime; it is a tool the caller quietly never has.
 *
 * The second half covers the two ways in: the official JSON-RPC endpoint and the
 * deprecated REST shim. They must publish the same tools and return the same results,
 * because the moment they can differ, "it works in the chat but not over MCP" becomes a
 * bug someone has to reproduce twice.
 *
 * Nothing here touches the database, which remains deliberate — this must keep passing
 * on a machine that cannot reach the estate. The HTTP tests get there by minting a real
 * JWT (so McpAuth is genuinely exercised) and then substituting the two collaborators
 * that would otherwise query: the context resolver and, where a tool is actually
 * called, the registry. Substituting the registry is also the point of several of these
 * tests — one binding changes what *both* transports serve, which is the single-registry
 * property stated as an executable claim.
 */
class McpToolBindingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The REST shim writes an audit row per call. Tests must not append to the
        // estate's audit table, so the writer is replaced with one that does nothing.
        $this->app->instance(McpAuditService::class, new class extends McpAuditService
        {
            public function log(array $payload): void
            {
                //
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function registeredToolNames(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $definition) => $definition['name'] ?? null,
            app(ToolRegistry::class)->definitions()
        ), 'is_string'));
    }

    public function test_every_module_binding_names_a_registered_tool(): void
    {
        $registered = $this->registeredToolNames();
        $problems = [];

        foreach ((array) config('ai.lifecycle.modules', []) as $module => $binding) {
            foreach ((array) ($binding['mcp_tools'] ?? []) as $tool) {
                if (! in_array($tool, $registered, true)) {
                    $problems[] = sprintf('%s binds "%s", which is not registered', $module, $tool);
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            "Module tool bindings reference tools that do not exist:\n  " . implode("\n  ", $problems)
                . "\n\nRegistered tools are:\n  " . implode("\n  ", $registered)
        );
    }

    public function test_tool_names_are_unique(): void
    {
        // The registry keys by name, so a duplicate silently replaces its predecessor
        // and one tool disappears without a word.
        $names = $this->registeredToolNames();

        $this->assertSame(
            array_values(array_unique($names)),
            $names,
            'Two MCP tools share a name; the registry keeps only the last one registered.'
        );
    }

    public function test_every_tool_declares_a_usable_definition(): void
    {
        foreach (app(ToolRegistry::class)->definitions() as $definition) {
            $name = $definition['name'] ?? '(unnamed)';

            $this->assertNotEmpty($definition['name'] ?? null, 'A tool has no name.');
            $this->assertNotEmpty(
                $definition['description'] ?? null,
                "{$name} has no description — the planner selects tools by reading these."
            );
            $this->assertIsArray(
                $definition['input_schema'] ?? null,
                "{$name} has no input schema, so a planner cannot fill its arguments."
            );
            $this->assertArrayHasKey(
                'allowed_roles',
                $definition['annotations'] ?? [],
                "{$name} declares no role gate."
            );
        }
    }

    public function test_a_module_without_an_agent_still_explains_its_depth(): void
    {
        // Stages 10-12 report not-reached for these modules, and a reader is owed a
        // reason rather than a blank row.
        $modules = app(ModuleRegistry::class)->all();

        foreach ($modules as $key => $module) {
            if ($module->hasAgent()) {
                continue;
            }

            $this->assertNotSame(
                '',
                trim($module->whyNoDepth()),
                "The {$key} module cannot reach stage 10 and gives no reason why."
            );
        }
    }

    public function test_consequential_tools_are_marked_so_the_planner_cannot_see_them(): void
    {
        // The LLM planner filters its catalogue on these annotations. A write tool that
        // forgets to declare itself would become model-selectable.
        $confirmable = [];

        foreach (app(ToolRegistry::class)->definitions() as $definition) {
            $annotations = $definition['annotations'] ?? [];

            if (($annotations['requires_confirmation'] ?? false) === true) {
                $confirmable[] = $definition['name'];
            }
        }

        $this->assertContains(
            'admissions.confirm',
            $confirmable,
            'admissions.confirm writes a record and must require confirmation.'
        );
    }

    // ------------------------------------------------------- the two transports

    public function test_the_json_rpc_handshake_returns_the_configured_server_identity(): void
    {
        $this->stubContext();

        $result = $this->rpc('initialize', [
            'protocolVersion' => (string) config('mcp.server.protocol_version', '2025-06-18'),
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ])->assertOk()->json('result');

        $this->assertSame(
            (string) config('mcp.server.name'),
            $result['serverInfo']['name'],
            'The handshake does not identify the configured server.'
        );
        $this->assertSame((string) config('mcp.server.version'), $result['serverInfo']['version']);
        $this->assertArrayHasKey('tools', $result['capabilities'], 'The server does not advertise tools.');
    }

    public function test_the_configured_protocol_versions_are_the_ones_the_server_speaks(): void
    {
        // The config used to hard-code 2025-06-18 while the package negotiated from
        // 2025-11-25, and only the REST shim read the setting — so /health advertised a
        // version the real endpoint had already moved past.
        $server = new LmsMcpServer(new FakeTransporter(), app(ToolRegistry::class));
        $supported = $server->createContext()->supportedProtocolVersions;

        $this->assertSame(
            array_values((array) config('mcp.server.protocol_versions')),
            array_values($supported),
            'The configured protocol versions are not the ones the MCP server offers.'
        );

        $this->assertSame(
            $supported[0],
            config('mcp.server.protocol_version'),
            'The singular protocol_version is not the version negotiated by default.'
        );

        // Config may narrow the package's list; it may never invent a version.
        $this->assertSame(
            [],
            array_diff($supported, ProtocolVersion::supported()),
            'A protocol version is advertised that laravel/mcp cannot actually speak.'
        );
    }

    public function test_the_handshake_negotiates_an_older_version_and_refuses_an_unknown_one(): void
    {
        $this->stubContext();

        // A client asking for a supported older version gets it back, not the newest.
        $negotiated = $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ])->assertOk()->json('result.protocolVersion');

        $this->assertSame('2025-06-18', $negotiated);

        // A version nobody implements is refused rather than echoed back.
        $error = $this->rpc('initialize', [
            'protocolVersion' => '1999-01-01',
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ])->assertOk()->json('error');

        $this->assertSame(-32602, $error['code']);
        $this->assertSame(
            array_values((array) config('mcp.server.protocol_versions')),
            array_values($error['data']['supported'])
        );
    }

    public function test_the_rest_shim_reports_the_same_protocol_versions_as_the_official_server(): void
    {
        $this->stubContext();

        $rpc = $this->rpc('initialize', [
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ])->assertOk()->json('result.protocolVersion');

        $rest = $this->postJson('/api/mcp/initialize', [], [
            'Authorization' => 'Bearer ' . $this->token(),
        ])->assertOk()->json('data.server');

        $this->assertSame($rpc, $rest['protocol_version'], 'The shim advertises a different protocol version.');
        $this->assertSame(
            array_values((array) config('mcp.server.protocol_versions')),
            array_values($rest['protocol_versions'])
        );
    }

    public function test_tools_list_over_json_rpc_publishes_every_registered_tool(): void
    {
        $this->stubContext();

        $published = $this->allToolsOverRpc();

        sort($published);
        $registered = $this->registeredToolNames();
        sort($registered);

        $this->assertSame(
            $registered,
            $published,
            'The JSON-RPC endpoint publishes a different tool list from the registry.'
        );
    }

    public function test_the_whole_catalogue_fits_in_one_tools_list_page(): void
    {
        // Laravel MCP paginates at 15 by default and the catalogue is larger, so a
        // client that reads the first page and stops used to lose every admissions and
        // fees tool without an error. LmsMcpServer sizes the page to the catalogue;
        // this fails if the catalogue ever outgrows it again.
        $this->stubContext();

        $first = $this->rpc('tools/list')->assertOk()->json('result');

        $this->assertCount(
            count($this->registeredToolNames()),
            $first['tools'],
            'The first tools/list page does not contain every tool.'
        );
        $this->assertArrayNotHasKey(
            'nextCursor',
            $first,
            'tools/list is paginated, so a client reading one page sees a partial catalogue.'
        );
    }

    public function test_tools_call_over_json_rpc_executes_through_the_registry(): void
    {
        $this->stubContext();
        $tool = $this->stubRegistry();

        $structured = $this->rpc('tools/call', [
            'name' => 'testing.echo',
            'arguments' => ['q' => 'grade 5'],
        ])->assertOk()->json('result.structuredContent');

        $this->assertSame('testing.echo', $structured['tool']);
        $this->assertSame('execute', $structured['result']['mode']);
        $this->assertSame(['echoed' => 'grade 5'], $structured['result']['result']);
        $this->assertSame(['q' => 'grade 5'], $tool->received);
        $this->assertSame(4242, $tool->scope?->userId, 'The tool was not scoped by the hydrated context.');
    }

    public function test_an_unknown_tool_is_refused_by_the_json_rpc_endpoint(): void
    {
        $this->stubContext();
        $this->stubRegistry();

        $response = $this->rpc('tools/call', ['name' => 'testing.nope', 'arguments' => []])->assertOk();

        $this->assertSame(-32602, $response->json('error.code'));
    }

    public function test_a_confirmation_round_trip_over_json_rpc_previews_then_executes(): void
    {
        $this->stubContext();

        $tool = new EchoConfirmableTool();
        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->method('create')->willReturn(['token' => 'round-trip-token', 'expires_at' => 'later']);
        $confirmations->method('consume')->willReturn([
            'token' => 'round-trip-token',
            'arguments' => ['enquiry_id' => 7],
            'preview' => [],
        ]);
        $this->app->instance(ToolRegistry::class, new ToolRegistry([$tool], $confirmations));

        // First call: no token. The tool must say what it would do and change nothing.
        $preview = $this->rpc('tools/call', [
            'name' => 'testing.confirmable',
            'arguments' => ['enquiry_id' => 7],
        ])->assertOk()->json('result.structuredContent.result');

        $this->assertSame('preview', $preview['mode']);
        $this->assertTrue($preview['requires_confirmation']);
        $this->assertSame('round-trip-token', $preview['confirmation']['token']);
        $this->assertSame(0, $tool->executions, 'The tool acted on the preview call.');

        // Second call: the token the preview handed back. Now it acts, exactly once.
        $confirmed = $this->rpc('tools/call', [
            'name' => 'testing.confirmable',
            'arguments' => ['enquiry_id' => 7, 'confirmation_token' => $preview['confirmation']['token']],
        ])->assertOk()->json('result.structuredContent.result');

        $this->assertSame('execute', $confirmed['mode']);
        $this->assertTrue($confirmed['confirmed']);
        $this->assertSame(['confirmed_enquiry' => 7], $confirmed['result']);
        $this->assertSame(1, $tool->executions);
    }

    public function test_a_cross_tenant_institute_is_refused_before_any_tool_runs(): void
    {
        // The real resolver, deliberately: this is the check that keeps a caller inside
        // the institutes its token grants, and it throws before a query is issued.
        $tool = $this->stubRegistry();

        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'testing.echo', 'arguments' => ['q' => 'anything']],
        ], [
            'Authorization' => 'Bearer ' . $this->token(['sub_institute_id' => '1,2', 'is_admin' => 1]),
            'X-MCP-Institute-Id' => '999',
        ])->assertStatus(422);

        $this->assertSame([], $tool->received, 'A cross-tenant call reached the tool.');
        $this->assertNull($tool->scope);
    }

    public function test_the_rate_limiter_rejects_a_caller_past_the_configured_burst(): void
    {
        config(['mcp.rate_limit.per_minute' => 3]);
        $this->stubContext();
        $this->stubRegistry();

        $call = fn (): TestResponse => $this->rpc('tools/call', [
            'name' => 'testing.echo',
            'arguments' => ['q' => 'x'],
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $call()->assertOk();
        }

        $limited = $call()->assertStatus(429);
        $this->assertNotNull($limited->headers->get('Retry-After'), 'A 429 gave the caller no Retry-After.');
    }

    public function test_rest_and_json_rpc_return_the_same_tool_result(): void
    {
        // The single-registry property, stated as a comparison. The one binding below
        // feeds both transports; if they could diverge, this is where it would show.
        $this->stubContext();
        $this->stubRegistry();

        $viaRpc = $this->rpc('tools/call', [
            'name' => 'testing.echo',
            'arguments' => ['q' => 'grade 5'],
        ])->assertOk()->json('result.structuredContent.result');

        $viaRest = $this->postJson('/api/mcp/tools/call', [
            'tool' => 'testing.echo',
            'arguments' => ['q' => 'grade 5'],
        ], ['Authorization' => 'Bearer ' . $this->token()])->assertOk()->json('data.result');

        $this->assertSame($viaRpc, $viaRest, 'The two transports disagree about the same call.');
        $this->assertSame(['echoed' => 'grade 5'], $viaRest['result']);
    }

    public function test_rest_and_json_rpc_publish_the_same_tools(): void
    {
        $this->stubContext();

        $viaRpc = collect($this->allToolsOverRpc())->sort()->values()->all();

        $viaRest = collect(
            $this->getJson('/api/mcp/tools', ['Authorization' => 'Bearer ' . $this->token()])
                ->assertOk()
                ->json('data.tools')
        )->pluck('name')->sort()->values()->all();

        $this->assertSame($viaRpc, $viaRest, 'The two transports publish different tool catalogues.');
        $this->assertCount(count($this->registeredToolNames()), $viaRest);
    }

    // ------------------------------------------------------ the deprecated shim

    public function test_the_rest_shim_announces_its_deprecation_and_removal_date(): void
    {
        $this->stubContext();

        $response = $this->getJson('/api/mcp/tools', ['Authorization' => 'Bearer ' . $this->token()])->assertOk();

        $this->assertSame('true', $response->headers->get('Deprecation'));
        $this->assertStringContainsString('rel="successor-version"', (string) $response->headers->get('Link'));
        $this->assertStringContainsString(
            (string) config('mcp.rest_shim.successor'),
            (string) $response->headers->get('Link')
        );

        $sunset = $response->headers->get('Sunset');
        $this->assertNotNull($sunset, 'The shim announces no removal date.');
        $this->assertNotFalse(strtotime((string) $sunset), 'Sunset is not an HTTP-date.');
        $this->assertGreaterThan(
            time(),
            strtotime((string) $sunset),
            'The advertised removal date has passed. Move MCP_REST_SUNSET, or retire the shim.'
        );

        $this->assertStringContainsString('deprecated', (string) $response->headers->get('Warning'));
    }

    public function test_the_official_endpoint_is_not_marked_deprecated(): void
    {
        $this->stubContext();

        $response = $this->rpc('tools/list')->assertOk();

        $this->assertNull($response->headers->get('Deprecation'));
        $this->assertNull($response->headers->get('Sunset'));
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A signed token for an admin caller in institute 1.
     *
     * Minted for real rather than stubbed so McpAuth is genuinely exercised: a change
     * that broke token validation would otherwise pass every test in this file.
     *
     * @param  array<string, mixed>  $claims
     */
    private function token(array $claims = []): string
    {
        return app(JwtToken::class)->createToken(array_merge([
            'id' => 4242,
            'sub_institute_id' => '1',
            'is_admin' => 1,
            'is_student' => false,
            'exp' => time() + 3600,
        ], $claims))->token();
    }

    /**
     * Every tool name the JSON-RPC endpoint publishes, following cursors.
     *
     * The server is configured so one page holds the whole catalogue, but this follows
     * `nextCursor` anyway: a list test that only ever reads page one would stop being a
     * list test the moment pagination came back.
     *
     * @return array<int, string>
     */
    private function allToolsOverRpc(): array
    {
        $names = [];
        $cursor = null;
        $pages = 0;

        do {
            $result = $this->rpc('tools/list', $cursor === null ? [] : ['cursor' => $cursor])
                ->assertOk()
                ->json('result');

            foreach ($result['tools'] ?? [] as $tool) {
                $names[] = $tool['name'];
            }

            $cursor = $result['nextCursor'] ?? null;
        } while ($cursor !== null && ++$pages < 20);

        return $names;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function rpc(string $method, array $params = []): TestResponse
    {
        return $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], [
            'Authorization' => 'Bearer ' . $this->token(),
            'Accept' => 'application/json, text/event-stream',
        ]);
    }

    /**
     * Replace the context resolver with one that answers without querying.
     *
     * The resolver's own behaviour — the allow-list check, the academic year lookup —
     * is covered in McpSecurityStackTest. Here it would only be a database dependency.
     */
    private function stubContext(): void
    {
        $this->app->instance(McpContextResolver::class, new class extends McpContextResolver
        {
            public function resolve(HttpRequest $request, array $auth): McpRequestContext
            {
                return new McpRequestContext(
                    userId: (int) ($auth['user_id'] ?? 0),
                    role: (string) ($auth['role'] ?? 'admin'),
                    selectedInstituteId: 1,
                    allowedInstituteIds: [1],
                    userProfileId: null,
                    clientId: null,
                    academicYear: 2026,
                    termId: null,
                    isAdmin: true,
                    isStudent: false,
                );
            }
        });
    }

    /**
     * Point the one registry at one deterministic tool.
     *
     * Both transports resolve ToolRegistry from the container, so this single binding
     * is what the JSON-RPC server publishes *and* what the REST controller calls.
     */
    private function stubRegistry(): EchoTool
    {
        $tool = new EchoTool();

        $this->app->instance(
            ToolRegistry::class,
            new ToolRegistry([$tool], $this->createMock(McpConfirmationService::class))
        );

        return $tool;
    }
}

/**
 * A read-only tool with no dependencies, so a transport test measures the transport.
 */
class EchoTool extends AbstractMcpTool
{
    /** @var array<string, mixed> */
    public array $received = [];

    public ?McpRequestContext $scope = null;

    public function name(): string
    {
        return 'testing.echo';
    }

    public function description(): string
    {
        return 'Echoes its argument back, for transport tests.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['q' => ['type' => 'string']],
            'required' => ['q'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->received = $arguments;
        $this->scope = $context;

        return ['echoed' => $arguments['q'] ?? null];
    }
}

/**
 * A consequential tool, for the preview to token to execute round trip.
 */
class EchoConfirmableTool extends AbstractMcpTool implements ConfirmableMcpToolInterface
{
    public int $executions = 0;

    public function name(): string
    {
        return 'testing.confirmable';
    }

    public function description(): string
    {
        return 'A consequential tool used to exercise the confirmation round trip.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['enquiry_id' => ['type' => 'integer']],
            'required' => ['enquiry_id'],
            'additionalProperties' => false,
        ];
    }

    protected function isReadOnly(): bool
    {
        return false;
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        return $this->preview($arguments, $context);
    }

    public function preview(array $arguments, McpRequestContext $context): array
    {
        return ['would_confirm' => $arguments['enquiry_id'] ?? null];
    }

    public function executeConfirmed(array $arguments, McpRequestContext $context, array $confirmation): array
    {
        $this->executions++;
        $payload = $confirmation['arguments'] ?? $arguments;

        return ['confirmed_enquiry' => $payload['enquiry_id'] ?? null];
    }
}
