<?php

namespace App\Http\Middleware;

use App\Services\Mcp\McpContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class McpContextHydrator
{
    public function __construct(private readonly McpContextResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            $auth = $request->attributes->get('mcp_auth', []);
            $context = $this->resolver->resolve($request, $auth);

            $request->attributes->set('mcp_context', $context);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid MCP context.',
                'data' => null,
                'errors' => $exception->errors(),
            ], 422);
        }

        return $next($request);
    }
}
