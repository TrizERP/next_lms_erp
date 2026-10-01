<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Services\Mcp\McpAuditService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

abstract class McpController extends Controller
{
    /**
     * Some tools sweep a cohort rather than answering about one record — `fees.arrears`
     * makes one `FeesPendingService::getPending()` call per student in scope, sequentially,
     * and 25 students of legacy per-student fee logic has been measured past PHP's default
     * 60-second limit, turning a slow-but-honest answer into a fatal timeout instead.
     *
     * Raised here rather than in php.ini so the limit travels with the code that needs it —
     * the same reasoning `AskController::allowTimeForACohortSweep()` already applies for the
     * conversational route. This one covers every tool called through this MCP surface,
     * since any of them could grow the same shape. It is a ceiling, not a target: nothing
     * here is expected to take three minutes, and a call that does is a performance bug
     * this does not excuse.
     */
    protected function allowTimeForACohortSweep(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
    }

    protected function success(Request $request, string $message, array $data, int $status = 200): JsonResponse
    {
        $response = response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);

        $this->audit($request, $status, 'success', $data);

        return $response;
    }

    protected function handleFailure(Request $request, Throwable $exception, ?string $toolName = null): JsonResponse
    {
        [$status, $message, $errors, $errorCode] = match (true) {
            $exception instanceof TooManyRequestsHttpException => [429, 'Too many requests.', null, 'rate_limited'],
            $exception instanceof ValidationException => [422, 'Invalid tool parameters.', $exception->errors(), 'invalid_parameters'],
            $exception instanceof AuthorizationException => [403, 'User does not have permission.', null, 'forbidden'],
            $exception instanceof NotFoundHttpException => [404, 'Record not found.', null, 'not_found'],
            default => [500, 'Internal Laravel failure.', null, 'internal_error'],
        };

        $response = response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);

        $this->audit($request, $status, 'error', null, $toolName, $errorCode, $exception->getMessage());

        return $response;
    }

    protected function audit(
        Request $request,
        int $statusCode,
        string $outcome,
        ?array $responsePayload = null,
        ?string $toolName = null,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): void {
        /** @var McpRequestContext|null $context */
        $context = $request->attributes->get('mcp_context');

        app(McpAuditService::class)->log([
            'request_id' => $request->headers->get('X-Request-Id'),
            'endpoint' => $request->path(),
            'tool_name' => $toolName ?: $request->input('tool'),
            'user_id' => $context?->userId,
            'sub_institute_id' => $context?->selectedInstituteId,
            'status_code' => $statusCode,
            'outcome' => $outcome,
            'input_payload' => $request->except(['confirmation_token']),
            'response_payload' => $responsePayload,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }
}
