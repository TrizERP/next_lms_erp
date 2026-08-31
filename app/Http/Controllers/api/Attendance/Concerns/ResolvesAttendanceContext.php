<?php

namespace App\Http\Controllers\api\Attendance\Concerns;

use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;

/**
 * Shared request context resolution for the Attendance Management API.
 *
 * The legacy attendance screens live on stateful web routes and read
 * sub_institute_id / user_id out of the session. The endpoints under
 * /api/attendance are stateless, so sub_institute_id is resolved from the
 * verified JWT payload (G-SEC-29) rather than the session; user_id/syear are
 * still read from the request. Authenticated with the same JWT already used
 * by this app's own
 * login/session (App\Http\Controllers\api\ApiLoginController, and see
 * App\Http\Middleware\ApiSessionHydrator for the established precedent of
 * validating this exact token type on a stateless API route) - NOT a Laravel
 * Sanctum personal access token, which is a different token format this
 * codebase's real tokens never match.
 */
trait ResolvesAttendanceContext
{
    use GetsJwtToken;

    /**
     * @return array{sub_institute_id:int, user_id:int|null, syear:string|null}|\Illuminate\Http\JsonResponse
     */
    protected function attendanceContext(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['status' => 0, 'message' => 'Token not provided'], 401);
        }

        try {
            if (!$this->jwtToken($request)->validate()) {
                return response()->json(['status' => 0, 'message' => 'Invalid token'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Invalid token'], 401);
        }

        // G-SEC-29: the tenant comes from the verified JWT payload, never the
        // caller-suppliable request body/header.
        $subInstituteId = $this->jwtPayload('sub_institute_id', $request);

        if (!$subInstituteId || !is_numeric($subInstituteId)) {
            return response()->json(['status' => 0, 'message' => 'sub_institute_id is required'], 400);
        }

        return [
            'sub_institute_id' => (int) $subInstituteId,
            'user_id'          => is_numeric($request->input('user_id')) ? (int) $request->input('user_id') : null,
            'syear'            => $request->input('syear'),
        ];
    }

    /** Treat 'all', '0' and empty string as "no filter". */
    protected function activeFilter($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = array_values(array_filter($value, fn ($item) => $item !== null && $item !== '' && $item !== '0' && $item !== 'all'));

            return empty($value) ? null : (string) reset($value);
        }

        $value = trim((string) $value);

        return ($value === '' || $value === '0' || strtolower($value) === 'all') ? null : $value;
    }
}
