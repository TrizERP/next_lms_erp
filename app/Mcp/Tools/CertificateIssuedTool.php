<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\CertificateService;

/**
 * Certificates issued, with a breakdown by type. The printed text is never returned.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class CertificateIssuedTool extends AbstractMcpTool
{
    public function __construct(private readonly CertificateService $service)
    {
    }

    public function name(): string
    {
        return 'certificate.issued';
    }

    public function description(): string
    {
        return 'Certificates issued by this institute in this academic year, with the type, number, student '
            .'and issue date, and a breakdown by type across the whole year. The printed certificate text '
            .'is deliberately not returned. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'certificate_type' => ['type' => 'string', 'description' => 'Match part of the type, e.g. Transfer or Bonafide.'],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'certificate_number' => ['type' => 'string'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->issued($context, $arguments);
    }
}
