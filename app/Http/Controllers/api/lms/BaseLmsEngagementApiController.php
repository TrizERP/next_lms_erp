<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Shared plumbing for the LMS Engagement REST APIs (Leader Board and Social &
 * Collaborative) consumed by the K12 Next.js frontend.
 *
 * Authentication is the ERP's existing stateless JWT: the `api.session`
 * middleware validates the bearer token and hydrates the request session from
 * the verified payload. Identity and tenancy are therefore read from that
 * session ONLY - never from request parameters - so a caller cannot act as
 * another user, institute or academic year.
 *
 * The JSON envelope matches the rest of the K12 API surface
 * (see api\result\BaseResultApiController): {success, message, data, errors}
 * plus an optional `meta` block for pagination.
 */
abstract class BaseLmsEngagementApiController extends Controller
{
    /**
     * The verified caller context every endpoint works from.
     *
     * @return array{sub_institute_id:int,user_id:int,user_profile_id:mixed,user_profile_name:string,syear:int,is_student:bool,is_admin:int}
     */
    protected function context(Request $request): array
    {
        $session = $request->session();

        $profileName = (string) $session->get('user_profile_name', '');

        return [
            'sub_institute_id'  => (int) $session->get('sub_institute_id'),
            'user_id'           => (int) $session->get('user_id'),
            'user_profile_id'   => $session->get('user_profile_id'),
            'user_profile_name' => $profileName,
            'syear'             => (int) $session->get('syear'),
            'is_student'        => (bool) $session->get('is_student') || strtoupper($profileName) === 'STUDENT',
            'is_admin'          => (int) $session->get('is_admin'),
        ];
    }

    /** Run a handler, mapping every failure mode onto the standard envelope. */
    protected function run(callable $handler): JsonResponse
    {
        try {
            $result = $handler();

            return $result instanceof JsonResponse ? $result : $this->success($result);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (\Throwable $e) {
            Log::error('LMS Engagement API error: ' . $e->getMessage(), [
                'exception' => $e,
                'url'       => request()->fullUrl(),
            ]);

            return $this->error('Something went wrong. Please try again.');
        }
    }

    protected function success($data = null, string $message = 'Success', array $meta = [], int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message = 'Something went wrong.', int $status = 500, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $status);
    }

    protected function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], 422);
    }

    /** Page number and page size, clamped so a caller cannot ask for the world. */
    protected function pagination(Request $request, int $default = 15, int $max = 100): array
    {
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', $default);
        $perPage = $perPage > 0 ? min($perPage, $max) : $default;

        return [$page, $perPage];
    }
}
