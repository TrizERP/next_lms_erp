<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\CertificateService;

/**
 * The certificate layouts this institute can issue from.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class CertificateTemplatesTool extends AbstractMcpTool
{
    public function __construct(private readonly CertificateService $service)
    {
    }

    public function name(): string
    {
        return 'certificate.templates';
    }

    public function description(): string
    {
        return 'The certificate layouts this institute can issue from, with the type each serves and whether '
            .'it is active. Use it to resolve a certificate type named in a question. Only certificate '
            .'layouts are listed; fee receipts and salary slips belong to other modules. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'certificate_type' => ['type' => 'string'],
                'only_active' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'certificate.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->templates($context, $arguments);
    }
}
