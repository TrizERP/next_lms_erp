<?php

namespace App\Mcp;

use App\Services\Mcp\McpConfirmationService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The one place a tool is registered.
 *
 * Since the tools became official Laravel MCP tools, this registry holds the very
 * instances the MCP server publishes — `tools()` below is what LmsMcpServer registers.
 * That is the point of keeping it: the lifecycle and an external MCP client select from
 * the same list, so a tool cannot exist for one caller and not the other.
 *
 * It also owns the preview/confirm handshake, which the MCP protocol has no concept of.
 * Both entry points (the official transport and the compatibility façade) go through
 * execute(), so a consequential tool is gated the same way whoever calls it.
 */
class ToolRegistry
{
    /**
     * @var array<string, \App\Mcp\AbstractMcpTool>
     */
    private array $tools = [];

    /**
     * @param  iterable<\App\Mcp\AbstractMcpTool>  $tools
     */
    public function __construct(iterable $tools, private readonly McpConfirmationService $confirmationService)
    {
        foreach ($tools as $tool) {
            // Registering anything else would reintroduce a tool the MCP server cannot
            // publish — which is exactly the split this registry exists to prevent.
            if (! $tool instanceof AbstractMcpTool) {
                throw new InvalidArgumentException(sprintf(
                    'MCP tools must extend %s so they are publishable by the Laravel MCP server; got %s.',
                    AbstractMcpTool::class,
                    get_debug_type($tool)
                ));
            }

            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * The registered tool instances, for the Laravel MCP server to publish.
     *
     * @return array<int, \App\Mcp\AbstractMcpTool>
     */
    public function tools(): array
    {
        return array_values($this->tools);
    }

    public function tool(string $name): ?AbstractMcpTool
    {
        return $this->tools[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_values(array_map(
            static fn (AbstractMcpTool $tool) => $tool->definition(),
            $this->tools
        ));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(
        string $toolName,
        array $arguments,
        McpRequestContext $context,
        ?string $confirmationToken = null
    ): array {
        $tool = $this->tools[$toolName] ?? null;

        if (! $tool) {
            throw ValidationException::withMessages([
                'tool' => ['Unknown MCP tool requested.'],
            ]);
        }

        if ($tool instanceof ConfirmableMcpToolInterface) {
            if ($confirmationToken) {
                $confirmation = $this->confirmationService->consume($confirmationToken, $toolName, $context);

                // Enveloped like every other completed call. Returning the tool's array
                // raw here used to make a confirmed write the one outcome callers could
                // not read: McpToolCaller and AskService both switch on `mode`, so a
                // real admission was traced as status "unknown" with an empty payload
                // and AdmissionsFlow fell back to a generic success message instead of
                // the one the ERP service returned.
                //
                // `requires_confirmation` is false because it describes this response,
                // not the tool: the confirmation has already been supplied and consumed,
                // and nothing further is being asked of the caller. `confirmed` is what
                // distinguishes this from a plain read-only execution.
                return [
                    'mode' => 'execute',
                    'requires_confirmation' => false,
                    'confirmed' => true,
                    'result' => $tool->executeConfirmed($arguments, $context, $confirmation),
                ];
            }

            $preview = $tool->preview($arguments, $context);
            $confirmation = $this->confirmationService->create($toolName, $arguments, $context, $preview);

            return [
                'mode' => 'preview',
                'requires_confirmation' => true,
                'confirmation' => $confirmation,
                'preview' => $preview,
            ];
        }

        return [
            'mode' => 'execute',
            'requires_confirmation' => false,
            'result' => $tool->execute($arguments, $context),
        ];
    }
}
