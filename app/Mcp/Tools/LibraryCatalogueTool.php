<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\LibraryService;
use App\Services\Mcp\McpRequestContext;

/**
 * Titles in the library catalogue, with how many physical copies each has.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class LibraryCatalogueTool extends AbstractMcpTool
{
    public function __construct(private readonly LibraryService $service)
    {
    }

    public function name(): string
    {
        return 'library.catalogue';
    }

    public function description(): string
    {
        return 'Titles in this institute\'s library catalogue, with the author, publisher, ISBN, classification, '
            .'the number of physical copies held and how many are currently on loan. One row is one TITLE and '
            .'not one book on the shelf. Withdrawn titles are excluded. No fine, reservation or renewal is '
            .'recorded anywhere. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'language' => ['type' => 'string'],
                'classification' => ['type' => 'string'],
                'search_text' => ['type' => 'string', 'description' => 'Title, author, ISBN or publisher.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'library.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->catalogue($context, $arguments);
    }
}
