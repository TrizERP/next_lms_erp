<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;

/**
 * Pins the caller of a legacy student/teacher mobile API to the identity in their token.
 *
 * These endpoints (`notificationHubAPI`, `teacherStudentListAPI`, `studentParentcommunicationListAPI`
 * ...) only checked that the token was valid, then used `student_id`, `teacher_id` and
 * `sub_institute_id` from the request, so one parent's token could read another school's
 * students or write a reply as any teacher.
 *
 * Usage: `mobile.identity:student` or `mobile.identity:teacher`. The token must be of the
 * matching kind; the matching id and the school in the request are then replaced with the
 * token's own.
 */
class PinMobileIdentity
{
    use GetsJwtToken;

    public function handle(Request $request, Closure $next, string $kind = 'student')
    {
        try {
            if (!$this->jwtToken($request)->validate()) {
                return $this->deny('Token Auth Failed', 401);
            }
            $payload = $this->jwtPayload(null, $request);
        } catch (\Exception $e) {
            return $this->deny('Token Auth Failed', 401);
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return $this->deny('Token expired', 401);
        }

        $school = (int) ($payload['sub_institute_id'] ?? 0);
        $isStudentToken = !empty($payload['is_student']) || !empty($payload['student_id']);

        if ($kind === 'student') {
            if (!$isStudentToken) {
                return $this->deny('Not available to this account', 403);
            }
            $id = (int) ($payload['student_id'] ?? $payload['id'] ?? 0);
            $param = 'student_id';
        } else {
            if ($isStudentToken) {
                return $this->deny('Not available to this account', 403);
            }
            $id = (int) ($payload['teacher_id'] ?? $payload['user_id'] ?? $payload['id'] ?? 0);
            $param = 'teacher_id';
        }

        if ($school <= 0 || $id <= 0) {
            return $this->deny('Invalid token payload', 401);
        }

        $pins = ['sub_institute_id' => $school, $param => $id];
        $request->merge($pins);
        foreach ($pins as $key => $value) {
            // Some of these controllers read $_REQUEST / $_POST rather than the request object.
            $_REQUEST[$key] = $value;
            if (isset($_POST[$key])) {
                $_POST[$key] = $value;
            }
        }
        $request->attributes->set('verified_user_id', $id);

        return $next($request);
    }

    private function deny(string $message, int $status)
    {
        return response()->json(['status' => '2', 'message' => $message, 'data' => []], $status);
    }
}
