<?php

namespace App\Mcp;

use App\Services\Mcp\McpConfirmationService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Validation\ValidationException;

class ToolRegistry
{
    /**
     * @var array<string, \App\Mcp\McpToolInterface>
     */
    private array $tools = [];

    public function __construct(iterable $tools, private readonly McpConfirmationService $confirmationService)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->definition()['name']] = $tool;
        }
    }

    public function definitions(): array
    {
        return array_values(array_map(
            static fn (McpToolInterface $tool) => $tool->definition(),
            $this->tools
        ));
    }

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

                return $tool->executeConfirmed($arguments, $context, $confirmation);
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
