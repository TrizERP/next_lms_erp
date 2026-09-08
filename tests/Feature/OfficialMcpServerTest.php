<?php

namespace Tests\Feature;

use App\Mcp\AbstractMcpTool;
use App\Mcp\ConfirmableMcpToolInterface;
use App\Mcp\Servers\LmsMcpServer;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpConfirmationService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Container\Container;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

/**
 * The tools and the official MCP server must be the same thing.
 *
 * Before the migration these were two populations — App\Mcp tools on one side, a
 * Laravel MCP adapter on the other — and the failure mode that arrangement invites is
 * a tool the chat lifecycle can call and an external MCP client cannot, or the reverse.
 * These assertions exist to make that split impossible to reintroduce quietly.
 *
 * Nothing here touches the database. The tool that exercises handle() is a stub, so
 * this keeps passing on a machine that cannot reach the estate.
 */
class OfficialMcpServerTest extends TestCase
{
    public function test_the_official_server_is_registered_on_the_configured_mcp_endpoint(): void
    {
        $route = app(Registrar::class)->getWebServer(trim((string) config('mcp.route_prefix', 'api/mcp'), '/'));

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
    }

    public function test_every_registered_tool_is_an_official_laravel_mcp_tool(): void
    {
        $tools = app(ToolRegistry::class)->tools();

        $this->assertNotEmpty($tools);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(
                Tool::class,
                $tool,
                $tool->name() . ' is not a Laravel MCP tool, so the server cannot publish it.'
            );
        }
    }

    public function test_the_server_publishes_the_registry_instances_and_nothing_else(): void
    {
        $registry = app(ToolRegistry::class);
        $server = new LmsMcpServer(new FakeTransporter(), $registry);

        $published = $server->createContext()->tools()->all();
        $registered = $registry->tools();

        $this->assertSame(
            array_map(static fn (Tool $tool) => $tool->name(), $registered),
            array_map(static fn (Tool $tool) => $tool->name(), $published),
            'The MCP server publishes a different tool list from the registry.'
        );

        // Same objects, not equivalent copies: an adapter layer would satisfy the name
        // comparison above while still being a second place a tool can be defined.
        foreach ($published as $index => $tool) {
            $this->assertSame($registered[$index], $tool);
        }
    }

    public function test_the_published_schemas_match_each_tool_definition(): void
    {
        $registry = app(ToolRegistry::class);
        $server = new LmsMcpServer(new FakeTransporter(), $registry);

        $official = $server->createContext()->tools()
            ->mapWithKeys(static fn (Tool $tool) => [$tool->name() => $tool->toArray()])
            ->all();

        foreach ($registry->definitions() as $definition) {
            $name = $definition['name'];

            $this->assertArrayHasKey($name, $official);
            $this->assertSame($definition['description'], $official[$name]['description']);

            $schema = $official[$name]['inputSchema'];
            $properties = (array) $schema['properties'];

            $this->assertArrayHasKey(
                'confirmation_token',
                $properties,
                "{$name} does not accept a confirmation token over the official transport."
            );

            unset($properties['confirmation_token']);
            $schema['properties'] = (object) $properties;

            $this->assertJsonStringEqualsJsonString(
                json_encode($definition['input_schema'], JSON_THROW_ON_ERROR),
                json_encode($schema, JSON_THROW_ON_ERROR),
                "{$name} publishes a different schema over MCP than the one it declares."
            );

            $this->assertSame(
                $definition['annotations']['read_only'],
                $official[$name]['annotations']['readOnlyHint'],
                "{$name} misreports whether it writes."
            );
        }
    }

    public function test_handle_runs_the_tool_through_the_registry_and_strips_the_confirmation_token(): void
    {
        $tool = new RecordingMcpTool();
        $registry = new ToolRegistry([$tool], $this->confirmationService());

        $context = $this->context();
        request()->attributes->set('mcp_context', $context);

        $response = $tool->handle(
            new McpRequest(['q' => 'grade 5', 'confirmation_token' => 'ffffffff-ffff-4fff-8fff-ffffffffffff']),
            $registry
        );

        $structured = $response->mergeStructuredContent([])['structuredContent'] ?? [];

        $this->assertSame('testing.recording', $structured['tool']);
        $this->assertSame('execute', $structured['result']['mode']);
        $this->assertSame(['ok' => true, 'q' => 'grade 5'], $structured['result']['result']);

        // The token authorises a call; it must never reach a service as business input.
        $this->assertSame(['q' => 'grade 5'], $tool->received);
        $this->assertSame($context, $tool->receivedContext);
    }

    public function test_the_container_can_invoke_handle_the_way_the_tool_invoker_does(): void
    {
        $tool = new RecordingMcpTool();

        $this->app->instance(ToolRegistry::class, new ToolRegistry([$tool], $this->confirmationService()));
        $this->app->instance(McpRequest::class, new McpRequest(['q' => 'invoked']));
        request()->attributes->set('mcp_context', $this->context());

        $response = Container::getInstance()->call([$tool, 'handle']);
        $structured = $response->mergeStructuredContent([])['structuredContent'] ?? [];

        $this->assertSame(['ok' => true, 'q' => 'invoked'], $structured['result']['result']);
    }

    public function test_a_consequential_tool_previews_instead_of_acting_when_no_token_is_given(): void
    {
        $tool = new RecordingConfirmableMcpTool();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->method('create')->willReturn(['token' => 'a-token', 'expires_at' => 'later']);

        $registry = new ToolRegistry([$tool], $confirmations);
        request()->attributes->set('mcp_context', $this->context());

        $response = $tool->handle(new McpRequest(['q' => 'confirm me']), $registry);
        $structured = $response->mergeStructuredContent([])['structuredContent'] ?? [];

        $this->assertSame('preview', $structured['result']['mode']);
        $this->assertTrue($structured['result']['requires_confirmation']);
        $this->assertSame('a-token', $structured['result']['confirmation']['token']);
        $this->assertFalse(
            $tool->confirmed,
            'The official transport executed a consequential tool without a confirmation token.'
        );
    }

    public function test_the_registry_refuses_a_tool_the_mcp_server_could_not_publish(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ToolRegistry([new \stdClass()], $this->confirmationService());
    }

    private function confirmationService(): McpConfirmationService
    {
        return $this->createMock(McpConfirmationService::class);
    }

    private function context(): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1,
            role: 'admin',
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
}

class RecordingMcpTool extends AbstractMcpTool
{
    /** @var array<string, mixed> */
    public array $received = [];

    public ?McpRequestContext $receivedContext = null;

    public function name(): string
    {
        return 'testing.recording';
    }

    public function description(): string
    {
        return 'Records the arguments it was handed.';
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
        $this->receivedContext = $context;

        return ['ok' => true, 'q' => $arguments['q'] ?? null];
    }
}

class RecordingConfirmableMcpTool extends AbstractMcpTool implements ConfirmableMcpToolInterface
{
    public bool $confirmed = false;

    public function name(): string
    {
        return 'testing.confirmable';
    }

    public function description(): string
    {
        return 'A consequential tool that must be confirmed before it acts.';
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
        return ['would' => 'act on ' . ($arguments['q'] ?? '')];
    }

    public function executeConfirmed(array $arguments, McpRequestContext $context, array $confirmation): array
    {
        $this->confirmed = true;

        return ['acted' => true];
    }
}
