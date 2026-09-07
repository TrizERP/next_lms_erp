<?php

namespace App\Mcp;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Every LMS tool is an official Laravel MCP tool.
 *
 * The base class extends Laravel\Mcp\Server\Tool, so the package's own transport,
 * tools/list and tools/call machinery reaches the same object the lifecycle calls.
 * There is no adapter and no second breed of tool: one class is simultaneously the
 * thing App\Mcp\ToolRegistry registers and the thing the MCP server publishes.
 *
 * What stays ours is the part MCP has no opinion about — the institute-scoped request
 * context, the role gate, and the preview/confirm handshake for consequential writes.
 * Those live in ToolRegistry, and handle() below routes the official transport through
 * it rather than around it, so an external MCP client cannot reach a write tool by a
 * path that skips the confirmation the chat UI has to go through.
 */
abstract class AbstractMcpTool extends Tool implements McpToolInterface
{
    /**
     * The tool's JSON Schema, hand-written per tool and published verbatim by toArray().
     *
     * Note also what is *not* declared here: every tool overrides the public name() and
     * description() that Laravel\Mcp\Server\Primitive derives from the class name. They
     * cannot be re-declared abstract in this class, because PHP forbids making an
     * inherited concrete method abstract — so McpToolBindingsTest is what holds tools to
     * giving both a real value, which matters because the registry keys on name().
     *
     * @return array<string, mixed>
     */
    abstract protected function inputSchema(): array;

    /**
     * Governance metadata for this platform — risk class, required permission.
     *
     * Deliberately not called annotations(): that name belongs to Laravel MCP's
     * HasAnnotations trait, which publishes the protocol's own client hints. Keeping
     * the two apart means our risk vocabulary never has to pretend to be an MCP hint.
     *
     * @return array<string, mixed>
     */
    protected function toolAnnotations(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        return ['admin', 'staff'];
    }

    protected function isReadOnly(): bool
    {
        return true;
    }

    public function title(): string
    {
        return $this->name();
    }

    /**
     * The registry-facing definition, unchanged from before the migration.
     *
     * The lifecycle planner, the module bindings test and the compatibility façade all
     * read this shape, so it keeps snake_case `input_schema` and the platform's own
     * annotation keys. toArray() below is the protocol-facing view of the same tool.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'input_schema' => $this->inputSchema(),
            'annotations' => array_merge(
                [
                    'read_only' => $this->isReadOnly(),
                    'allowed_roles' => $this->allowedRoles(),
                    'requires_confirmation' => $this instanceof ConfirmableMcpToolInterface,
                ],
                $this->toolAnnotations()
            ),
        ];
    }

    /**
     * The official MCP entry point for this tool.
     *
     * ToolRegistry is injected as a method argument because Laravel MCP invokes
     * handlers through the container. Resolving it here rather than in the constructor
     * also avoids a cycle: the registry is built from the tools it registers.
     */
    public function handle(Request $request, ToolRegistry $registry): ResponseFactory
    {
        $context = request()->attributes->get('mcp_context');

        if (! $context instanceof McpRequestContext) {
            throw new AuthorizationException('MCP request context was not established.');
        }

        $arguments = $request->all();

        // The token authorises a call; it is never business input, so it is taken off
        // before the arguments reach a service and its validator.
        $confirmationToken = $arguments['confirmation_token'] ?? null;
        unset($arguments['confirmation_token']);

        return Response::structured([
            'tool' => $this->name(),
            'result' => $registry->execute(
                $this->name(),
                $arguments,
                $context,
                is_string($confirmationToken) ? $confirmationToken : null,
            ),
        ]);
    }

    /**
     * Publish the tool's own JSON Schema verbatim.
     *
     * Laravel MCP normally builds inputSchema from schema(JsonSchema), but these tools
     * already carry hand-written schemas whose `required` lists and
     * additionalProperties=false are what stop a planner inventing arguments.
     * Re-expressing them through the fluent builder would be a rewrite of 25 contracts
     * for no gain, so toArray() hands the package the existing schema instead.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = $this->inputSchema();

        if (! isset($schema['type'])) {
            $schema['type'] = 'object';
        }

        $properties = $schema['properties'] ?? [];
        $properties = is_object($properties) ? get_object_vars($properties) : (array) $properties;
        $properties['confirmation_token'] = [
            'type' => 'string',
            'format' => 'uuid',
            'description' => 'Confirmation token returned by a previous preview for a consequential tool.',
        ];
        // An object, as Laravel MCP normalises its own schemas: a properties map that
        // happens to be empty must still serialise as {} rather than [].
        $schema['properties'] = (object) $properties;

        $readOnly = $this->isReadOnly();

        return $this->mergeMeta($this->mergeIcons([
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $schema,
            'annotations' => [
                'readOnlyHint' => $readOnly,
                'destructiveHint' => ! $readOnly,
                'openWorldHint' => false,
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        // toArray() publishes inputSchema() directly; nothing to build here.
        return [];
    }

    protected function authorize(McpRequestContext $context): void
    {
        if (! in_array($context->role, $this->allowedRoles(), true)) {
            throw new AuthorizationException('You do not have permission to use this MCP tool.');
        }
    }
}
