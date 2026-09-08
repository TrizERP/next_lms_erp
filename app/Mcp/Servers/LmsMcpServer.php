<?php

namespace App\Mcp\Servers;

use App\Mcp\ToolRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;

/**
 * The standards-compliant MCP surface for the LMS.
 *
 * The tools it publishes are the registry's own instances — each one an official
 * Laravel\Mcp\Server\Tool — so there is nothing to adapt and nothing that can drift.
 * Adding a tool to App\Providers\McpServiceProvider::TOOLS is all it takes for the
 * lifecycle and every external MCP client to see it at once.
 */
class LmsMcpServer extends Server
{
    public function __construct(Transport $transport, ToolRegistry $registry)
    {
        parent::__construct($transport);

        $this->name = (string) config('mcp.server.name', config('app.name', 'Laravel') . ' MCP Server');
        $this->version = (string) config('mcp.server.version', '1.0.0');

        // Read from config so the REST shim's /health and /initialize report the same
        // versions this endpoint negotiates. Config can only narrow the package's own
        // supported list — see config/mcp.php — so this can never advertise a version
        // Laravel MCP cannot actually speak. An empty list falls back to the package.
        $configured = (array) config('mcp.server.protocol_versions', []);

        if ($configured !== []) {
            $this->supportedProtocolVersion = array_values(array_map('strval', $configured));
        }
        $this->instructions = 'Use the listed LMS tools to retrieve scoped ERP data. '
            . 'Tool results are authoritative; do not invent business records or values.';

        $this->tools = $registry->tools();

        // One page, always.
        //
        // Laravel MCP paginates tools/list at 15 by default, and the catalogue is 25.
        // A client that reads the first page and stops — which is most of them, and was
        // this application's own REST façade behaviour — saw fifteen tools and silently
        // lost every admissions and fees tool. Cursor pagination is spec-correct and
        // still works; sizing the page to the catalogue just means there is never a
        // second page to miss. The catalogue is bounded by McpServiceProvider::TOOLS,
        // so this cannot grow unbounded without someone editing that list.
        $this->defaultPaginationLength = max($this->defaultPaginationLength, count($this->tools));
    }
}
