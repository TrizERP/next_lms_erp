<?php

namespace Tests\Feature;

use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;
use App\Mcp\AbstractMcpTool;
use App\Mcp\ConfirmableMcpToolInterface;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpAuditService;
use App\Services\Mcp\McpConfirmationService;
use App\Services\Mcp\McpRequestContext;
use Laravel\Mcp\Request as McpRequest;
use Tests\TestCase;

/**
 * A consequential tool must never be one call away from acting.
 *
 * The port to Laravel\Mcp\Server\Tool moved where a tool call *enters* the system —
 * AbstractMcpTool::handle() is now an entry point that did not exist before. The failure
 * this file guards against is that entry point reaching a write tool directly and
 * turning a two-step, human-gated action into a single tools/call.
 *
 * It does not go near the database: McpConfirmationService is mocked, so what is under
 * test is the wiring between the transport, the registry and the tool — which is the
 * part the migration actually touched. The service's own token properties (TTL,
 * single use, binding to user and institute) are unchanged and live in its own code.
 */
class McpConfirmationFlowTest extends TestCase
{
    /**
     * Write tools that are deliberately not confirmable, each with the reason.
     *
     * Anything else that declares read_only=false without implementing
     * ConfirmableMcpToolInterface fails the invariant below. Adding an entry here is a
     * conscious act with a name attached to it in the diff; forgetting to make a new
     * write tool confirmable is not.
     *
     * @var array<string, string>
     */
    private const UNCONFIRMED_WRITES = [
        'admissions.updateEnquiry' => 'Data entry on an unconfirmed enquiry. The consequential act in this '
            . 'flow is admissions.confirm, which creates the enrolment and carries its own admin gate and '
            . 'token; confirming each field edit would train users to click through confirmations.',
    ];

    public function test_every_write_tool_is_confirmable_or_explicitly_excused(): void
    {
        $unguarded = [];

        foreach (app(ToolRegistry::class)->tools() as $tool) {
            if ($tool->definition()['annotations']['read_only'] === true) {
                continue;
            }

            if ($tool instanceof ConfirmableMcpToolInterface) {
                continue;
            }

            if (array_key_exists($tool->name(), self::UNCONFIRMED_WRITES)) {
                continue;
            }

            $unguarded[] = $tool->name();
        }

        $this->assertSame(
            [],
            $unguarded,
            "These tools write but are one-shot callable over MCP:\n  " . implode("\n  ", $unguarded)
                . "\n\nImplement ConfirmableMcpToolInterface, or add the tool to UNCONFIRMED_WRITES with a reason."
        );
    }

    public function test_the_registered_admissions_confirm_tool_still_requires_confirmation(): void
    {
        // Named explicitly rather than left to the invariant above: this is the one tool
        // in the estate that creates a student enrolment.
        $tool = app(ToolRegistry::class)->tool('admissions.confirm');

        $this->assertNotNull($tool);
        $this->assertInstanceOf(ConfirmableMcpToolInterface::class, $tool);
        $this->assertFalse($tool->definition()['annotations']['read_only']);
        $this->assertTrue($tool->definition()['annotations']['requires_confirmation']);
    }

    public function test_a_tokenless_call_over_the_official_transport_previews_and_does_not_act(): void
    {
        $tool = new SpyConfirmableTool();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->expects($this->once())->method('create')
            ->willReturn(['token' => 'issued-token', 'expires_at' => 'later']);
        $confirmations->expects($this->never())->method('consume');

        $registry = new ToolRegistry([$tool], $confirmations);
        request()->attributes->set('mcp_context', $this->context());

        $result = $this->structured($tool->handle(new McpRequest(['enquiry_id' => 7]), $registry));

        $this->assertSame('preview', $result['mode']);
        $this->assertTrue($result['requires_confirmation']);
        $this->assertSame('issued-token', $result['confirmation']['token']);
        $this->assertSame(1, $tool->previews);
        $this->assertSame(0, $tool->executions, 'The tool acted without a confirmation token.');
    }

    public function test_a_token_bearing_call_consumes_the_token_for_that_exact_tool_and_caller(): void
    {
        $tool = new SpyConfirmableTool();
        $context = $this->context();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->expects($this->never())->method('create');
        $confirmations->expects($this->once())->method('consume')
            // The token alone is not the authority: it is only good for the tool, the
            // user and the institute it was issued against.
            ->with('issued-token', 'testing.spy_confirmable', $this->identicalTo($context))
            ->willReturn([
                'token' => 'issued-token',
                'arguments' => ['enquiry_id' => 7],
                'preview' => [],
            ]);

        $registry = new ToolRegistry([$tool], $confirmations);
        request()->attributes->set('mcp_context', $context);

        $result = $this->structured($tool->handle(
            new McpRequest(['enquiry_id' => 7, 'confirmation_token' => 'issued-token']),
            $registry
        ));

        $this->assertSame('execute', $result['mode']);
        $this->assertTrue($result['confirmed']);
        $this->assertSame(['acted' => true, 'enquiry_id' => 7], $result['result']);
        $this->assertSame(1, $tool->executions);
        $this->assertSame(0, $tool->previews);
    }

    public function test_all_three_registry_outcomes_are_enveloped_the_same_way(): void
    {
        // A caller switching on `mode` must be able to read every outcome, including the
        // one that wrote. The confirmed branch used to return the tool's array raw, which
        // made a completed admission indistinguishable from a call that never ran.
        $confirmable = new SpyConfirmableTool();
        $readOnly = new SpyReadOnlyTool();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->method('create')->willReturn(['token' => 't', 'expires_at' => 'later']);
        $confirmations->method('consume')->willReturn(['token' => 't', 'arguments' => ['enquiry_id' => 7], 'preview' => []]);

        $registry = new ToolRegistry([$confirmable, $readOnly], $confirmations);
        $context = $this->context();

        $read = $registry->execute('testing.spy_read_only', [], $context);
        $preview = $registry->execute('testing.spy_confirmable', ['enquiry_id' => 7], $context);
        $confirmed = $registry->execute('testing.spy_confirmable', ['enquiry_id' => 7], $context, 't');

        $this->assertSame('execute', $read['mode']);
        $this->assertSame(['rows' => []], $read['result']);
        $this->assertArrayNotHasKey('confirmed', $read);

        $this->assertSame('preview', $preview['mode']);
        $this->assertTrue($preview['requires_confirmation']);

        $this->assertSame('execute', $confirmed['mode']);
        $this->assertSame(['acted' => true, 'enquiry_id' => 7], $confirmed['result']);

        // False because it describes this response — the confirmation was already given
        // and consumed. `confirmed` is what separates a write from a read.
        $this->assertFalse($confirmed['requires_confirmation']);
        $this->assertTrue($confirmed['confirmed']);
    }

    public function test_a_confirmed_write_reaches_the_lifecycle_as_completed_with_its_real_payload(): void
    {
        // The consequence the envelope exists for, exercised through the real consumer
        // rather than a copy of its logic. McpToolCaller and AskService both switch on
        // `mode`; before the envelope a confirmed admission arrived as "unknown" with an
        // empty payload, so AdmissionsFlow published a generic success message in place
        // of the one AdmissionMcpService returned.
        $tool = new SpyConfirmableTool();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->method('consume')->willReturn([
            'token' => 't',
            'arguments' => ['enquiry_id' => 7],
            'preview' => [],
        ]);

        // The auditor is a real dependency now — every tool call leaves a row. Mocked
        // here so this test stays free of the database.
        $caller = new McpToolCaller(
            new ToolRegistry([$tool], $confirmations),
            $this->createMock(McpAuditService::class)
        );

        $stage = new StageContext(
            question: 'Confirm the admission.',
            scope: $this->context(),
            module: new ModuleCapability(
                key: 'admissions',
                label: 'Admissions',
                mcpTools: ['testing.spy_confirmable'],
            ),
        );

        $payload = $caller->call(
            $stage,
            'testing.spy_confirmable',
            ['enquiry_id' => 7],
            'Execute the confirmed admission.',
            confirmationToken: 't',
        );

        $this->assertNotNull($payload, 'A confirmed write returned nothing to the lifecycle.');
        $this->assertSame(7, $payload['enquiry_id']);
        $this->assertSame(1, $tool->executions);

        $traced = $stage->toolCalls();
        $this->assertCount(1, $traced);
        $this->assertSame(
            'completed',
            $traced[0]['status'],
            'A confirmed write was traced as something other than completed.'
        );
    }

    public function test_the_confirmed_call_acts_on_the_previewed_payload_not_the_resent_one(): void
    {
        // The property that makes the gate real. A caller that previews enquiry 7, shows
        // the user what confirming 7 would do, then resends the token with enquiry 99
        // must not enrol student 99.
        $tool = new SpyConfirmableTool();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->method('consume')->willReturn([
            'token' => 'issued-token',
            'arguments' => ['enquiry_id' => 7],
            'preview' => [],
        ]);

        $registry = new ToolRegistry([$tool], $confirmations);
        request()->attributes->set('mcp_context', $this->context());

        $result = $this->structured($tool->handle(
            new McpRequest(['enquiry_id' => 99, 'confirmation_token' => 'issued-token']),
            $registry
        ));

        $this->assertSame(7, $result['result']['enquiry_id'], 'A resent argument overrode the previewed payload.');
        $this->assertSame(['enquiry_id' => 7], $tool->confirmedWith);
    }

    public function test_a_malformed_token_falls_back_to_preview_rather_than_acting(): void
    {
        // A non-string token is not a token. The interesting case is that it degrades to
        // the preview branch instead of being coerced into one.
        $tool = new SpyConfirmableTool();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->expects($this->never())->method('consume');
        $confirmations->method('create')->willReturn(['token' => 'issued-token', 'expires_at' => 'later']);

        $registry = new ToolRegistry([$tool], $confirmations);
        request()->attributes->set('mcp_context', $this->context());

        $result = $this->structured($tool->handle(
            new McpRequest(['enquiry_id' => 7, 'confirmation_token' => ['not', 'a', 'string']]),
            $registry
        ));

        $this->assertSame('preview', $result['mode']);
        $this->assertSame(0, $tool->executions);
    }

    public function test_the_token_never_reaches_the_tool_as_business_input(): void
    {
        // Every confirmable tool's schema declares additionalProperties=false, so a token
        // left in the payload would be rejected by the tool's own validator — or worse,
        // silently persisted by one that is more forgiving.
        $tool = new SpyConfirmableTool();

        $confirmations = $this->createMock(McpConfirmationService::class);
        $confirmations->method('create')->willReturn(['token' => 'issued-token', 'expires_at' => 'later']);

        $registry = new ToolRegistry([$tool], $confirmations);
        request()->attributes->set('mcp_context', $this->context());

        $tool->handle(new McpRequest(['enquiry_id' => 7, 'confirmation_token' => 'issued-token-x']), $registry);

        $this->assertArrayNotHasKey('confirmation_token', $tool->previewedWith);
    }

    public function test_the_transport_refuses_a_call_with_no_scoped_context(): void
    {
        // Without the hydrated context there is no user or institute to bind a token to,
        // so the call must not reach the registry at all.
        $tool = new SpyConfirmableTool();
        $registry = new ToolRegistry([$tool], $this->createMock(McpConfirmationService::class));

        request()->attributes->remove('mcp_context');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $tool->handle(new McpRequest(['enquiry_id' => 7]), $registry);
    }

    /**
     * @return array<string, mixed>
     */
    private function structured(\Laravel\Mcp\ResponseFactory $response): array
    {
        return ($response->mergeStructuredContent([])['structuredContent'] ?? [])['result'] ?? [];
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

/**
 * A read-only tool, for comparing the registry's three return shapes.
 */
class SpyReadOnlyTool extends AbstractMcpTool
{
    public function name(): string
    {
        return 'testing.spy_read_only';
    }

    public function description(): string
    {
        return 'A read-only tool used to contrast the registry envelope shapes.';
    }

    protected function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        return ['rows' => []];
    }
}

/**
 * Stands in for AdmissionsConfirmTool, counting what it was asked to do.
 */
class SpyConfirmableTool extends AbstractMcpTool implements ConfirmableMcpToolInterface
{
    public int $previews = 0;

    public int $executions = 0;

    /** @var array<string, mixed> */
    public array $previewedWith = [];

    /** @var array<string, mixed> */
    public array $confirmedWith = [];

    public function name(): string
    {
        return 'testing.spy_confirmable';
    }

    public function description(): string
    {
        return 'A consequential tool that counts previews and executions.';
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
        $this->previews++;
        $this->previewedWith = $arguments;

        return ['would_confirm' => $arguments['enquiry_id'] ?? null];
    }

    public function executeConfirmed(array $arguments, McpRequestContext $context, array $confirmation): array
    {
        $this->executions++;

        // Mirrors AdmissionsConfirmTool: act on what was previewed and stored, falling
        // back to the passed arguments only if the record carried none.
        $payload = $confirmation['arguments'] ?? $arguments;
        $this->confirmedWith = $payload;

        return ['acted' => true, 'enquiry_id' => $payload['enquiry_id'] ?? null];
    }
}
