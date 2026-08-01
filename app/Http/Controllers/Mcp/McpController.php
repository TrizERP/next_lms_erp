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
