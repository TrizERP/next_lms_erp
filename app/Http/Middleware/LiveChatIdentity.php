<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Live chat identity guard. Run after `api.session`, which has verified the
 * token and filled the session.
 *
 * The controller reads who is calling (user id, role, school, sender) from the
 * request body, so a caller could act as anyone, including an admin. This
 * middleware replaces those fields with the verified values, limits staff-only
 * actions to admins, and checks that the chat session belongs to the caller.
 */
class LiveChatIdentity
{
    /** Routes (method + path suffix) only an admin may call. */
    private const ADMIN_ONLY = [
        'POST presence', 'POST slots', 'DELETE slots', 'POST meet',
    ];

    public function handle(Request $request, Closure $next)
    {
        $userId = (string) session()->get('user_id');
        $tenant = (string) session()->get('sub_institute_id');
        if ($userId === '' || $tenant === '') {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $profile = strtolower((string) session()->get('user_profile_name'));
        $isStudent = (bool) session()->get('is_student') || in_array($profile, ['student', 'parent'], true);
        $isAdmin = !$isStudent && str_contains($profile, 'admin');

        $key = strtoupper($request->method()) . ' ' . basename($request->path());
        $adminOnly = in_array($key, self::ADMIN_ONLY, true)
            || ($request->isMethod('GET') && basename($request->path()) === 'session' && $request->query('adminQueue'));
        if ($adminOnly && !$isAdmin) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Chat sessions are owned by their student; admins may only touch their own school's.
        $sessionId = $request->input('sessionId', $request->query('sessionId'));
        if ($sessionId) {
            $row = DB::table('ai_live_chat_sessions')->where('id', (string) $sessionId)->first();
            if ($row) {
                $sameTenant = (string) ($row->sub_institute_id ?? '') === $tenant;
                $owns = (string) $row->user_id === $userId;
                if (!($isAdmin ? $sameTenant : $owns)) {
                    return response()->json(['error' => 'Forbidden'], 403);
                }
            }
        }

        $identity = [
            'userId' => $userId,
            'subInstituteId' => $tenant,
        ];

        // Query string
        $request->query->set('subInstituteId', $tenant);
        if (!$isAdmin) {
            $request->query->set('userId', $userId);
        }

        // Body
        $body = $request->json()->all() ?: $request->request->all();
        $body['subInstituteId'] = $tenant;
        if (isset($body['user']) && is_array($body['user'])) {
            $body['user'] = array_merge($body['user'], $identity, ['userRole' => $isAdmin ? 'Admin' : 'Student']);
        }
        if (isset($body['student']) && is_array($body['student'])) {
            $body['student'] = array_merge($body['student'], $identity);
        }
        if ($isAdmin) {
            foreach (['adminId' => $userId] as $k => $v) {
                $body[$k] = $v;
            }
        } else {
            $body['senderId'] = $userId;
            $body['senderRole'] = 'user';
            $body['role'] = 'user';
        }
        if ($request->isJson()) {
            $request->json()->replace($body);
        }
        $request->request->replace($body);

        return $next($request);
    }
}
