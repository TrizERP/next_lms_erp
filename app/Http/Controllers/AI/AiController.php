<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Shared base for the AI API.
 *
 * Mirrors the response envelope the MCP controllers already use
 * ({success, message, data, errors}) so the frontend has one shape to handle across
 * both surfaces rather than two.
 *
 * `scope()` is the important method: it returns the McpRequestContext that
 * McpContextHydrator put on the request. Controllers never build a context from
 * request input, which is what stops a caller naming someone else's school.
 */
abstract class AiController extends Controller
{
    protected function scope(Request $request): McpRequestContext
    {
        $context = $request->attributes->get('mcp_context');

        if (! $context instanceof McpRequestContext) {
            throw ValidationException::withMessages([
                'context' => ['The request scope could not be resolved.'],
            ]);
        }

        return $context;
    }

    protected function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    protected function failure(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Turns an exception into an honest response without leaking internals.
     *
     * Validation and authorisation failures keep their own status and message;
     * anything else is reported as a server error with the detail logged rather
     * than returned.
     */
    protected function handle(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return $this->failure('The request was not valid.', 422, $exception->errors());
        }

        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return $this->failure($exception->getMessage(), 403);
        }

        if ($exception instanceof \RuntimeException) {
            // Domain-level refusals — governance, decisions, workflow state — are
            // meaningful to the caller and safe to show.
            return $this->failure($exception->getMessage(), 422);
        }

        report($exception);

        return $this->failure('The request could not be completed.', 500);
    }

    /**
     * A bounded page size. Callers may ask for more, but not for everything.
     */
    protected function limit(Request $request, int $default = 50, int $max = 200): int
    {
        $limit = (int) $request->input('limit', $default);

        return max(1, min($limit, $max));
    }
}
