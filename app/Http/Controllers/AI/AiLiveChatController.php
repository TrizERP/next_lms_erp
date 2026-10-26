<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

class AiLiveChatController extends Controller
{
    public const INITIAL_ADMIN_GREETING = 'Hello! I can help you with that right now.';
    public const DEFAULT_MEETING_DURATION = 15;
    public const DEFAULT_GRACE_PERIOD = 2;

    public const DEFAULT_TIMES = [
        ['start' => '10:00', 'end' => '10:15'],
        ['start' => '10:30', 'end' => '10:45'],
        ['start' => '11:15', 'end' => '11:30'],
        ['start' => '13:00', 'end' => '13:15'],
        ['start' => '13:30', 'end' => '13:45'],
        ['start' => '14:00', 'end' => '14:15'],
        ['start' => '14:30', 'end' => '14:45'],
        ['start' => '15:15', 'end' => '15:30'],
        ['start' => '16:00', 'end' => '16:15'],
        ['start' => '16:30', 'end' => '16:45'],
        ['start' => '17:00', 'end' => '17:15'],
    ];

    // ==========================================
    // 1. SESSION MANAGEMENT
    // ==========================================

    public function sessionGet(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->query('sessionId');
            $adminQueue = $request->query('adminQueue');
            $userId = $request->query('userId');
            $subInstituteId = $request->query('subInstituteId');

            $presence = $this->resolveAdminPresence($subInstituteId);

            if ($sessionId) {
                $session = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
                if (!$session) {
                    return response()->json(['error' => 'Session not found'], 404);
                }
                return response()->json([
                    'session' => $this->formatSession($session),
                    'adminOnline' => $presence['isOnline'],
                ]);
            }

            if ($adminQueue === 'true') {
                $query = DB::table('ai_live_chat_sessions')
                    ->whereIn('status', ['waiting', 'active'])
                    ->whereNotIn('user_role', ['Admin', 'Administrator']);

                if ($subInstituteId && trim((string)$subInstituteId) !== '' && $subInstituteId !== '0') {
                    $query->where(function ($q) use ($subInstituteId) {
                        $q->where('sub_institute_id', (string)$subInstituteId)
                          ->orWhereNull('sub_institute_id');
                    });
                }

                $rows = $query->orderBy('updated_at', 'desc')->get();
                $sessions = $rows->map(fn($r) => $this->formatSession($r))->values()->all();

                return response()->json([
                    'sessions' => $sessions,
                    'adminOnline' => $presence['isOnline'],
                ]);
            }

            if ($userId) {
                $row = DB::table('ai_live_chat_sessions')
                    ->where('user_id', (string)$userId)
                    ->whereIn('status', ['waiting', 'active'])
                    ->orderBy('updated_at', 'desc')
                    ->first();

                return response()->json([
                    'session' => $row ? $this->formatSession($row) : null,
                    'adminOnline' => $presence['isOnline'],
                ]);
            }

            $all = DB::table('ai_live_chat_sessions')->orderBy('updated_at', 'desc')->get();
            return response()->json([
                'sessions' => $all->map(fn($r) => $this->formatSession($r))->values()->all(),
                'adminOnline' => $presence['isOnline'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve session', 'detail' => $e->getMessage()], 500);
        }
    }

    public function sessionPost(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $action = $data['action'] ?? 'create_or_get';

            if ($action === 'create_or_get') {
                $user = $data['user'] ?? [];
                $userId = (string)($user['userId'] ?? '');
                if ($userId === '') {
                    return response()->json(['error' => 'User details are required'], 400);
                }

                $userName = (string)($user['userName'] ?? 'Student');
                $userRole = (string)($user['userRole'] ?? 'Student');
                $subInstituteId = (string)($user['subInstituteId'] ?? '1');
                $clientId = isset($user['clientId']) ? (string)$user['clientId'] : null;
                $pagePath = (string)($data['pagePath'] ?? '/dashboard');
                $pageTitle = isset($data['pageTitle']) ? (string)$data['pageTitle'] : null;
                $initialMessage = isset($data['initialMessage']) ? trim((string)$data['initialMessage']) : null;

                $presence = $this->resolveAdminPresence($subInstituteId);

                // Look for existing waiting or active session for this user
                $existing = DB::table('ai_live_chat_sessions')
                    ->where('user_id', $userId)
                    ->whereIn('status', ['waiting', 'active'])
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($existing) {
                    $messages = DB::table('ai_live_chat_messages')
                        ->where('session_id', $existing->id)
                        ->orderBy('created_at', 'asc')
                        ->get()
                        ->map(fn($m) => $this->formatMessage($m))
                        ->all();

                    return response()->json([
                        'session' => $this->formatSession($existing),
                        'messages' => $messages,
                        'adminOnline' => $presence['isOnline'],
                    ]);
                }

                // Create new session
                $sessionId = 'chat-' . (string)Str::uuid();
                $now = Carbon::now();

                $sessionData = [
                    'id' => $sessionId,
                    'sub_institute_id' => $subInstituteId,
                    'client_id' => $clientId,
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'user_role' => $userRole,
                    'status' => 'waiting',
                    'admin_id' => null,
                    'admin_name' => null,
                    'page_path' => $pagePath,
                    'page_title' => $pageTitle,
                    'initial_message' => $initialMessage,
                    'initial_greeting_sent' => false,
                    'unread_count_for_admin' => 0,
                    'unread_count_for_user' => 0,
                    'messages_exchanged_count' => 0,
                    'meet_suggested' => false,
                    'last_message_text' => null,
                    'last_message_time' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                DB::table('ai_live_chat_sessions')->insert($sessionData);

                // Insert welcome system message
                $statusNote = $presence['isOnline']
                    ? 'Connected to Live Support. An administrator is currently online and will reply shortly.'
                    : 'Administrator is currently offline. You can leave a message or book a Google Meet session for later.';

                $welcomeMsg = [
                    'id' => (string)Str::uuid(),
                    'session_id' => $sessionId,
                    'sender_id' => 'system',
                    'sender_name' => 'System',
                    'sender_role' => 'system',
                    'type' => 'system',
                    'text' => $statusNote,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                DB::table('ai_live_chat_messages')->insert($welcomeMsg);

                // Insert initial message if provided
                if ($initialMessage !== null && $initialMessage !== '') {
                    $userMsg = [
                        'id' => (string)Str::uuid(),
                        'session_id' => $sessionId,
                        'sender_id' => $userId,
                        'sender_name' => $userName,
                        'sender_role' => 'user',
                        'type' => 'text',
                        'text' => $initialMessage,
                        'created_at' => Carbon::now()->addMilliseconds(100),
                        'updated_at' => Carbon::now()->addMilliseconds(100),
                    ];
                    DB::table('ai_live_chat_messages')->insert($userMsg);

                    DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update([
                        'last_message_text' => $initialMessage,
                        'last_message_time' => $now,
                        'messages_exchanged_count' => 1,
                        'unread_count_for_admin' => 1,
                        'updated_at' => Carbon::now(),
                    ]);
                }

                // If Admin is currently online: send automatic initial greeting
                if ($presence['isOnline'] && !empty($presence['primaryAdmin'])) {
                    $admin = $presence['primaryAdmin'];
                    $greetingMsg = [
                        'id' => (string)Str::uuid(),
                        'session_id' => $sessionId,
                        'sender_id' => (string)$admin['adminId'],
                        'sender_name' => (string)$admin['adminName'],
                        'sender_role' => 'admin',
                        'type' => 'text',
                        'text' => self::INITIAL_ADMIN_GREETING,
                        'created_at' => Carbon::now()->addMilliseconds(200),
                        'updated_at' => Carbon::now()->addMilliseconds(200),
                    ];
                    DB::table('ai_live_chat_messages')->insert($greetingMsg);

                    DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update([
                        'initial_greeting_sent' => true,
                        'status' => 'active',
                        'admin_id' => (string)$admin['adminId'],
                        'admin_name' => (string)$admin['adminName'],
                        'last_message_text' => self::INITIAL_ADMIN_GREETING,
                        'last_message_time' => Carbon::now(),
                        'unread_count_for_user' => 1,
                        'updated_at' => Carbon::now(),
                    ]);
                }

                $freshSession = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
                $allMessages = DB::table('ai_live_chat_messages')
                    ->where('session_id', $sessionId)
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(fn($m) => $this->formatMessage($m))
                    ->all();

                return response()->json([
                    'session' => $this->formatSession($freshSession),
                    'messages' => $allMessages,
                    'adminOnline' => $presence['isOnline'],
                ]);
            }

            if ($action === 'assign_admin') {
                $sessionId = (string)($data['sessionId'] ?? '');
                $adminId = (string)($data['adminId'] ?? '');
                $adminName = (string)($data['adminName'] ?? 'Admin Staff');

                $session = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
                if (!$session) {
                    return response()->json(['error' => 'Session not found'], 404);
                }

                $updates = [
                    'status' => 'active',
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'updated_at' => Carbon::now(),
                ];

                if (!$session->initial_greeting_sent) {
                    $updates['initial_greeting_sent'] = true;
                    $updates['last_message_text'] = self::INITIAL_ADMIN_GREETING;
                    $updates['last_message_time'] = Carbon::now();

                    DB::table('ai_live_chat_messages')->insert([
                        'id' => (string)Str::uuid(),
                        'session_id' => $sessionId,
                        'sender_id' => $adminId,
                        'sender_name' => $adminName,
                        'sender_role' => 'admin',
                        'type' => 'text',
                        'text' => self::INITIAL_ADMIN_GREETING,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                } else {
                    DB::table('ai_live_chat_messages')->insert([
                        'id' => (string)Str::uuid(),
                        'session_id' => $sessionId,
                        'sender_id' => 'system',
                        'sender_name' => 'System',
                        'sender_role' => 'system',
                        'type' => 'system',
                        'text' => "{$adminName} (Administrator) has joined the chat.",
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }

                DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update($updates);
                $updated = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
                return response()->json(['session' => $this->formatSession($updated)]);
            }

            if ($action === 'close_session') {
                $sessionId = (string)($data['sessionId'] ?? '');
                $closedBy = $data['closedBy'] ?? ['name' => 'Admin'];
                $closerName = (string)($closedBy['name'] ?? 'Admin');

                $session = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
                if (!$session) {
                    return response()->json(['error' => 'Session not found'], 404);
                }

                DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update([
                    'status' => 'closed',
                    'updated_at' => Carbon::now(),
                ]);

                DB::table('ai_live_chat_messages')->insert([
                    'id' => (string)Str::uuid(),
                    'session_id' => $sessionId,
                    'sender_id' => 'system',
                    'sender_name' => 'System',
                    'sender_role' => 'system',
                    'type' => 'system',
                    'text' => "Live chat ended by {$closerName}.",
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $updated = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
                return response()->json(['session' => $this->formatSession($updated)]);
            }

            if ($action === 'mark_read') {
                $sessionId = (string)($data['sessionId'] ?? '');
                $role = (string)($data['role'] ?? 'user');

                if ($sessionId !== '') {
                    $field = ($role === 'user') ? 'unread_count_for_user' : 'unread_count_for_admin';
                    DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update([$field => 0]);
                }

                return response()->json(['success' => true]);
            }

            return response()->json(['error' => 'Unknown action'], 400);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to process session', 'detail' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. MESSAGES MANAGEMENT
    // ==========================================

    public function messagesGet(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->query('sessionId');
            if (!$sessionId) {
                return response()->json(['error' => 'sessionId is required'], 400);
            }

            $query = DB::table('ai_live_chat_messages')
                ->where('session_id', $sessionId)
                ->orderBy('created_at', 'asc');

            if ($after = $request->query('afterTimestamp')) {
                $query->where('created_at', '>', Carbon::parse($after));
            }

            $messages = $query->get()->map(fn($m) => $this->formatMessage($m))->all();
            return response()->json(['messages' => $messages]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve messages', 'detail' => $e->getMessage()], 500);
        }
    }

    public function messagesPost(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $sessionId = (string)($data['sessionId'] ?? '');
            if (!$sessionId) {
                return response()->json(['error' => 'sessionId is required'], 400);
            }

            $session = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
            if (!$session) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            $now = Carbon::now();
            $senderRole = (string)($data['senderRole'] ?? 'user');
            $text = (string)($data['text'] ?? '');
            $type = (string)($data['type'] ?? 'text');
            $meetUrl = isset($data['meetUrl']) ? (string)$data['meetUrl'] : null;
            $bookingDetails = isset($data['bookingDetails']) ? json_encode($data['bookingDetails']) : null;

            $messageId = (string)Str::uuid();
            $messageRecord = [
                'id' => $messageId,
                'session_id' => $sessionId,
                'sender_id' => (string)($data['senderId'] ?? 'user'),
                'sender_name' => (string)($data['senderName'] ?? ($senderRole === 'admin' ? 'Admin' : 'Student')),
                'sender_role' => $senderRole,
                'type' => $type,
                'text' => $text,
                'meet_url' => $meetUrl,
                'booking_details' => $bookingDetails,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            DB::table('ai_live_chat_messages')->insert($messageRecord);

            // Update session
            $sessionUpdates = [
                'updated_at' => $now,
                'last_message_text' => $text,
                'last_message_time' => $now,
            ];

            if ($senderRole === 'user') {
                $sessionUpdates['unread_count_for_admin'] = DB::raw('unread_count_for_admin + 1');
                $sessionUpdates['messages_exchanged_count'] = DB::raw('messages_exchanged_count + 1');
            } else if ($senderRole === 'admin') {
                $sessionUpdates['unread_count_for_user'] = DB::raw('unread_count_for_user + 1');
            }

            DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update($sessionUpdates);

            // Check if meet suggestion should be triggered
            $refreshed = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
            if ($refreshed && $refreshed->messages_exchanged_count >= 3 && !$refreshed->meet_suggested && $senderRole === 'user') {
                $suggestionMsg = [
                    'id' => (string)Str::uuid(),
                    'session_id' => $sessionId,
                    'sender_id' => 'system',
                    'sender_name' => 'System',
                    'sender_role' => 'system',
                    'type' => 'meet_suggestion',
                    'text' => 'Would you like to schedule a 1:1 Google Meet session with the administrator to resolve this quickly?',
                    'created_at' => Carbon::now()->addMilliseconds(300),
                    'updated_at' => Carbon::now()->addMilliseconds(300),
                ];
                DB::table('ai_live_chat_messages')->insert($suggestionMsg);
                DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update(['meet_suggested' => true]);
            }

            $inserted = DB::table('ai_live_chat_messages')->where('id', $messageId)->first();
            return response()->json(['message' => $this->formatMessage($inserted)]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to send message', 'detail' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 3. ADMIN PRESENCE TRACKING
    // ==========================================

    public function presenceGet(Request $request): JsonResponse
    {
        $subInstituteId = $request->query('subInstituteId');
        $result = $this->resolveAdminPresence($subInstituteId);
        return response()->json($result);
    }

    public function presencePost(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $adminId = (string)($data['adminId'] ?? '');
            if (!$adminId) {
                return response()->json(['error' => 'adminId is required'], 400);
            }

            $adminName = (string)($data['adminName'] ?? 'Admin Staff');
            $subInstituteId = (string)($data['subInstituteId'] ?? '1');
            $status = (string)($data['status'] ?? 'available');
            $now = Carbon::now();

            DB::table('ai_live_admin_presence')->updateOrInsert(
                ['admin_id' => $adminId, 'sub_institute_id' => $subInstituteId],
                [
                    'admin_name' => $adminName,
                    'status' => $status,
                    'last_seen' => $now,
                    'updated_at' => $now,
                ]
            );

            // If newly available, automatically assign any waiting sessions without greeting
            if ($status === 'available') {
                $waiting = DB::table('ai_live_chat_sessions')
                    ->where('status', 'waiting')
                    ->where('initial_greeting_sent', false)
                    ->get();

                foreach ($waiting as $w) {
                    DB::table('ai_live_chat_sessions')->where('id', $w->id)->update([
                        'status' => 'active',
                        'admin_id' => $adminId,
                        'admin_name' => $adminName,
                        'initial_greeting_sent' => true,
                        'last_message_text' => self::INITIAL_ADMIN_GREETING,
                        'last_message_time' => $now,
                        'unread_count_for_user' => 1,
                        'updated_at' => $now,
                    ]);

                    DB::table('ai_live_chat_messages')->insert([
                        'id' => (string)Str::uuid(),
                        'session_id' => $w->id,
                        'sender_id' => $adminId,
                        'sender_name' => $adminName,
                        'sender_role' => 'admin',
                        'type' => 'text',
                        'text' => self::INITIAL_ADMIN_GREETING,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            return response()->json([
                'presence' => [
                    'adminId' => $adminId,
                    'adminName' => $adminName,
                    'subInstituteId' => $subInstituteId,
                    'status' => $status,
                    'lastSeen' => $now->toISOString(),
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to update presence', 'detail' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 4. MEETING SLOTS & BOOKINGS
    // ==========================================

    public function slotsGet(Request $request): JsonResponse
    {
        try {
            $date = (string)($request->query('date') ?? Carbon::today()->format('Y-m-d'));
            $subInstituteId = (string)($request->query('subInstituteId') ?? '1');
            $includeBooked = $request->query('includeBooked') === 'true';

            $this->ensureSlotsForDate($date, $subInstituteId);

            $query = DB::table('ai_live_meeting_slots')
                ->where('date', $date)
                ->where('sub_institute_id', $subInstituteId);

            if (!$includeBooked) {
                $query->where('is_booked', false);
            }

            $slots = $query->orderBy('start_time', 'asc')->get()->map(fn($s) => $this->formatSlot($s))->all();

            $defaultMeetUrl = DB::table('ai_live_chat_settings')
                ->where('setting_key', 'default_meet_url')
                ->value('setting_value');

            return response()->json([
                'slots' => $slots,
                'defaultMeetUrl' => $defaultMeetUrl ?: 'https://meet.google.com/qme-irws-tko',
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve slots', 'detail' => $e->getMessage()], 500);
        }
    }

    public function slotsPost(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $adminId = (string)($data['adminId'] ?? 'admin-1');
            $adminName = (string)($data['adminName'] ?? 'Academic Administrator');
            $subInstituteId = (string)($data['subInstituteId'] ?? '1');
            $date = (string)($data['date'] ?? Carbon::today()->format('Y-m-d'));
            $slotsList = $data['slots'] ?? [];
            $defaultMeetUrl = isset($data['defaultMeetUrl']) ? trim((string)$data['defaultMeetUrl']) : null;
            $overwrite = !empty($data['overwrite']);

            if ($defaultMeetUrl !== null && $defaultMeetUrl !== '') {
                DB::table('ai_live_chat_settings')->updateOrInsert(
                    ['setting_key' => 'default_meet_url'],
                    ['setting_value' => $defaultMeetUrl, 'updated_at' => Carbon::now()]
                );
            }

            if ($overwrite) {
                DB::table('ai_live_meeting_slots')
                    ->where('date', $date)
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('is_booked', false)
                    ->delete();
            }

            $now = Carbon::now();
            foreach ($slotsList as $s) {
                $start = (string)($s['startTime'] ?? '');
                $end = (string)($s['endTime'] ?? '');
                if ($start === '') continue;

                $slotId = "slot-{$date}-{$start}";
                DB::table('ai_live_meeting_slots')->updateOrInsert(
                    ['id' => $slotId],
                    [
                        'admin_id' => $adminId,
                        'admin_name' => $adminName,
                        'sub_institute_id' => $subInstituteId,
                        'date' => $date,
                        'start_time' => $start,
                        'end_time' => $end ?: Carbon::createFromFormat('H:i', $start)->addMinutes(15)->format('H:i'),
                        'duration_minutes' => (int)($s['durationMinutes'] ?? 15),
                        'is_booked' => false,
                        'updated_at' => $now,
                    ]
                );
            }

            $allSlots = DB::table('ai_live_meeting_slots')
                ->where('date', $date)
                ->where('sub_institute_id', $subInstituteId)
                ->orderBy('start_time', 'asc')
                ->get()
                ->map(fn($s) => $this->formatSlot($s))
                ->all();

            return response()->json(['slots' => $allSlots, 'success' => true]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to configure slots', 'detail' => $e->getMessage()], 500);
        }
    }

    public function slotsDelete(Request $request): JsonResponse
    {
        try {
            $slotId = (string)($request->query('slotId') ?? $request->input('slotId'));
            if (!$slotId) {
                return response()->json(['error' => 'slotId is required'], 400);
            }

            DB::table('ai_live_meeting_slots')->where('id', $slotId)->where('is_booked', false)->delete();
            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to delete slot', 'detail' => $e->getMessage()], 500);
        }
    }

    public function bookingsGet(Request $request): JsonResponse
    {
        try {
            $this->expireMissedBookings();

            $sessionId = $request->query('sessionId');
            $date = $request->query('date');
            $subInstituteId = $request->query('subInstituteId');

            $query = DB::table('ai_live_meeting_bookings')->orderBy('date', 'desc')->orderBy('start_time', 'asc');

            if ($sessionId) {
                $query->where('session_id', $sessionId);
            }
            if ($date) {
                $query->where('date', $date);
            }
            if ($subInstituteId) {
                $query->where('sub_institute_id', $subInstituteId);
            }

            $bookings = $query->get()->map(fn($b) => $this->formatBooking($b))->all();
            return response()->json(['bookings' => $bookings]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to retrieve bookings', 'detail' => $e->getMessage()], 500);
        }
    }

    public function bookingsPost(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $action = (string)($data['action'] ?? 'book');

            if ($action === 'book') {
                $slotId = (string)($data['slotId'] ?? '');
                $sessionId = (string)($data['sessionId'] ?? '');
                $student = $data['student'] ?? [];
                $studentId = (string)($student['userId'] ?? 'student');
                $studentName = (string)($student['userName'] ?? 'Student');

                $slot = DB::table('ai_live_meeting_slots')->where('id', $slotId)->first();
                if (!$slot || $slot->is_booked) {
                    return response()->json(['error' => 'Slot is no longer available or already booked.'], 400);
                }

                $session = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
                $adminId = $session?->admin_id ?: $slot->admin_id;
                $adminName = $session?->admin_name ?: $slot->admin_name;

                $defaultMeetUrl = DB::table('ai_live_chat_settings')->where('setting_key', 'default_meet_url')->value('setting_value');
                $meetUrl = $defaultMeetUrl ?: 'https://meet.google.com/' . $this->generateMeetCode();

                $bookingId = 'book-' . (string)Str::uuid();
                $now = Carbon::now();

                $bookingRecord = [
                    'id' => $bookingId,
                    'slot_id' => $slotId,
                    'session_id' => $sessionId,
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'sub_institute_id' => $slot->sub_institute_id,
                    'date' => $slot->date,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_minutes' => $slot->duration_minutes,
                    'status' => 'scheduled',
                    'meet_url' => $meetUrl,
                    'student_joined' => false,
                    'admin_joined' => false,
                    'grace_period_minutes' => 2,
                    'reminder_sent' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                DB::table('ai_live_meeting_bookings')->insert($bookingRecord);

                DB::table('ai_live_meeting_slots')->where('id', $slotId)->update([
                    'is_booked' => true,
                    'booking_id' => $bookingId,
                    'meet_url' => $meetUrl,
                    'updated_at' => $now,
                ]);

                // Insert booking card message into chat session
                $confirmationMsg = [
                    'id' => (string)Str::uuid(),
                    'session_id' => $sessionId,
                    'sender_id' => 'system',
                    'sender_name' => 'System',
                    'sender_role' => 'system',
                    'type' => 'booking_confirmation',
                    'text' => "Google Meet scheduled for {$slot->date} at {$slot->start_time} ({$slot->duration_minutes} mins).",
                    'meet_url' => $meetUrl,
                    'booking_details' => json_encode($bookingRecord),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                DB::table('ai_live_chat_messages')->insert($confirmationMsg);

                DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update([
                    'active_booking_id' => $bookingId,
                    'active_meet_url' => $meetUrl,
                    'last_message_text' => "1:1 Google Meet booked for {$slot->date} at {$slot->start_time}.",
                    'last_message_time' => $now,
                    'updated_at' => $now,
                ]);

                $inserted = DB::table('ai_live_meeting_bookings')->where('id', $bookingId)->first();
                return response()->json(['booking' => $this->formatBooking($inserted)]);
            }

            if ($action === 'join') {
                $bookingId = (string)($data['bookingId'] ?? '');
                $role = (string)($data['role'] ?? 'user');
                $now = Carbon::now();

                $booking = DB::table('ai_live_meeting_bookings')->where('id', $bookingId)->first();
                if (!$booking) {
                    return response()->json(['error' => 'Booking not found'], 404);
                }

                $updates = ['updated_at' => $now];
                if ($role === 'user') {
                    $updates['student_joined'] = true;
                    $updates['student_joined_at'] = $now;
                } else {
                    $updates['admin_joined'] = true;
                    $updates['admin_joined_at'] = $now;
                }

                if ($booking->status === 'scheduled') {
                    $updates['status'] = 'in_progress';
                }

                DB::table('ai_live_meeting_bookings')->where('id', $bookingId)->update($updates);
                $updated = DB::table('ai_live_meeting_bookings')->where('id', $bookingId)->first();
                return response()->json(['booking' => $this->formatBooking($updated)]);
            }

            if ($action === 'cancel') {
                $bookingId = (string)($data['bookingId'] ?? '');
                $cancelledBy = (string)($data['cancelledBy'] ?? 'User');

                $booking = DB::table('ai_live_meeting_bookings')->where('id', $bookingId)->first();
                if (!$booking) {
                    return response()->json(['error' => 'Booking not found'], 404);
                }

                DB::table('ai_live_meeting_bookings')->where('id', $bookingId)->update([
                    'status' => 'cancelled',
                    'updated_at' => Carbon::now(),
                ]);

                DB::table('ai_live_meeting_slots')->where('id', $booking->slot_id)->update([
                    'is_booked' => false,
                    'booking_id' => null,
                    'meet_url' => null,
                    'updated_at' => Carbon::now(),
                ]);

                DB::table('ai_live_chat_messages')->insert([
                    'id' => (string)Str::uuid(),
                    'session_id' => $booking->session_id,
                    'sender_id' => 'system',
                    'sender_name' => 'System',
                    'sender_role' => 'system',
                    'type' => 'system',
                    'text' => "Meeting on {$booking->date} at {$booking->start_time} was cancelled by {$cancelledBy}.",
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $updated = DB::table('ai_live_meeting_bookings')->where('id', $bookingId)->first();
                return response()->json(['booking' => $this->formatBooking($updated)]);
            }

            return response()->json(['error' => 'Unknown booking action'], 400);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to process booking', 'detail' => $e->getMessage()], 500);
        }
    }

    public function meetPost(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $sessionId = (string)($data['sessionId'] ?? '');
            if (!$sessionId) {
                return response()->json(['error' => 'sessionId is required'], 400);
            }

            $session = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
            if (!$session) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            $customMeetUrl = isset($data['customMeetUrl']) ? trim((string)$data['customMeetUrl']) : null;
            $defaultMeetUrl = DB::table('ai_live_chat_settings')->where('setting_key', 'default_meet_url')->value('setting_value');
            $meetUrl = $customMeetUrl ?: ($defaultMeetUrl ?: 'https://meet.google.com/' . $this->generateMeetCode());

            $now = Carbon::now();
            $senderRole = (string)($data['senderRole'] ?? 'admin');
            $senderName = (string)($data['senderName'] ?? 'Admin Staff');
            $senderId = (string)($data['senderId'] ?? 'admin');

            DB::table('ai_live_chat_sessions')->where('id', $sessionId)->update([
                'active_meet_url' => $meetUrl,
                'last_message_text' => 'Join 1:1 Google Meet session',
                'last_message_time' => $now,
                'unread_count_for_user' => DB::raw('unread_count_for_user + 1'),
                'updated_at' => $now,
            ]);

            $messageId = (string)Str::uuid();
            $messageData = [
                'id' => $messageId,
                'session_id' => $sessionId,
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'sender_role' => $senderRole,
                'type' => 'meet',
                'text' => 'Administrator has started a live Google Meet session. Click Join Meeting to connect directly.',
                'meet_url' => $meetUrl,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            DB::table('ai_live_chat_messages')->insert($messageData);

            $freshSession = DB::table('ai_live_chat_sessions')->where('id', $sessionId)->first();
            $freshMsg = DB::table('ai_live_chat_messages')->where('id', $messageId)->first();

            return response()->json([
                'session' => $this->formatSession($freshSession),
                'message' => $this->formatMessage($freshMsg),
                'meetUrl' => $meetUrl,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Failed to create meet', 'detail' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // HELPERS & FORMATTERS
    // ==========================================

    private function resolveAdminPresence(?string $subInstituteId): array
    {
        $cutoff = Carbon::now()->subSeconds(60);
        $query = DB::table('ai_live_admin_presence')
            ->where('status', 'available')
            ->where('last_seen', '>=', $cutoff);

        if ($subInstituteId && trim((string)$subInstituteId) !== '' && $subInstituteId !== '0') {
            $query->where(function ($q) use ($subInstituteId) {
                $q->where('sub_institute_id', (string)$subInstituteId)
                  ->orWhere('sub_institute_id', '1');
            });
        }

        $admins = $query->orderBy('last_seen', 'desc')->get();
        $isOnline = $admins->count() > 0;

        $primary = $admins->first();
        $primaryAdmin = $primary ? [
            'adminId' => (string)$primary->admin_id,
            'adminName' => (string)$primary->admin_name,
            'subInstituteId' => (string)$primary->sub_institute_id,
            'status' => (string)$primary->status,
        ] : null;

        return [
            'isOnline' => $isOnline,
            'activeAdmins' => $admins->map(fn($a) => [
                'adminId' => (string)$a->admin_id,
                'adminName' => (string)$a->admin_name,
                'subInstituteId' => (string)$a->sub_institute_id,
                'status' => (string)$a->status,
                'lastSeen' => Carbon::parse($a->last_seen)->toISOString(),
            ])->all(),
            'primaryAdmin' => $primaryAdmin,
        ];
    }

    private function ensureSlotsForDate(string $date, string $subInstituteId): void
    {
        $count = DB::table('ai_live_meeting_slots')
            ->where('date', $date)
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        if ($count > 0) return;

        $now = Carbon::now();
        foreach (self::DEFAULT_TIMES as $t) {
            $slotId = "slot-{$date}-{$t['start']}";
            DB::table('ai_live_meeting_slots')->insertOrIgnore([
                'id' => $slotId,
                'admin_id' => 'admin-default',
                'admin_name' => 'Academic Administrator',
                'sub_institute_id' => $subInstituteId,
                'date' => $date,
                'start_time' => $t['start'],
                'end_time' => $t['end'],
                'duration_minutes' => self::DEFAULT_MEETING_DURATION,
                'is_booked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function expireMissedBookings(): void
    {
        $now = Carbon::now();
        $todayStr = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        $scheduled = DB::table('ai_live_meeting_bookings')
            ->where('status', 'scheduled')
            ->where('student_joined', false)
            ->where(function ($q) use ($todayStr, $currentTime) {
                $q->where('date', '<', $todayStr)
                  ->orWhere(function ($sub) use ($todayStr, $currentTime) {
                      $sub->where('date', $todayStr)
                          ->where('end_time', '<', $currentTime);
                  });
            })
            ->get();

        foreach ($scheduled as $b) {
            DB::table('ai_live_meeting_bookings')->where('id', $b->id)->update([
                'status' => 'missed',
                'updated_at' => $now,
            ]);

            DB::table('ai_live_meeting_slots')->where('id', $b->slot_id)->update([
                'is_booked' => false,
                'booking_id' => null,
                'updated_at' => $now,
            ]);

            DB::table('ai_live_chat_messages')->insert([
                'id' => (string)Str::uuid(),
                'session_id' => $b->session_id,
                'sender_id' => 'system',
                'sender_name' => 'System',
                'sender_role' => 'system',
                'type' => 'booking_missed',
                'text' => "Scheduled Google Meet on {$b->date} at {$b->start_time} was missed as student did not join within the meeting window.",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function generateMeetCode(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $p1 = substr(str_shuffle($chars), 0, 3);
        $p2 = substr(str_shuffle($chars), 0, 4);
        $p3 = substr(str_shuffle($chars), 0, 3);
        return "{$p1}-{$p2}-{$p3}";
    }

    private function formatSession(object $row): array
    {
        return [
            'id' => (string)$row->id,
            'subInstituteId' => (string)($row->sub_institute_id ?? '1'),
            'clientId' => $row->client_id ? (string)$row->client_id : null,
            'userId' => (string)$row->user_id,
            'userName' => (string)$row->user_name,
            'userRole' => (string)$row->user_role,
            'status' => (string)$row->status,
            'adminId' => $row->admin_id ? (string)$row->admin_id : null,
            'adminName' => $row->admin_name ? (string)$row->admin_name : null,
            'pagePath' => (string)($row->page_path ?? '/dashboard'),
            'pageTitle' => $row->page_title ? (string)$row->page_title : null,
            'initialGreetingSent' => (bool)$row->initial_greeting_sent,
            'unreadCountForAdmin' => (int)$row->unread_count_for_admin,
            'unreadCountForUser' => (int)$row->unread_count_for_user,
            'messagesExchangedCount' => (int)$row->messages_exchanged_count,
            'meetSuggested' => (bool)$row->meet_suggested,
            'lastMessageText' => $row->last_message_text ? (string)$row->last_message_text : null,
            'lastMessageTime' => $row->last_message_time ? Carbon::parse($row->last_message_time)->toISOString() : null,
            'activeMeetUrl' => $row->active_meet_url ? (string)$row->active_meet_url : null,
            'activeBookingId' => $row->active_booking_id ? (string)$row->active_booking_id : null,
            'createdAt' => Carbon::parse($row->created_at)->toISOString(),
            'updatedAt' => Carbon::parse($row->updated_at)->toISOString(),
        ];
    }

    private function formatMessage(object $row): array
    {
        $bookingDetails = null;
        if (!empty($row->booking_details)) {
            $bookingDetails = is_string($row->booking_details) ? json_decode($row->booking_details, true) : (array)$row->booking_details;
        }

        return [
            'id' => (string)$row->id,
            'sessionId' => (string)$row->session_id,
            'senderId' => (string)$row->sender_id,
            'senderName' => (string)$row->sender_name,
            'senderRole' => (string)$row->sender_role,
            'type' => (string)$row->type,
            'text' => (string)$row->text,
            'meetUrl' => $row->meet_url ? (string)$row->meet_url : null,
            'bookingDetails' => $bookingDetails,
            'createdAt' => Carbon::parse($row->created_at)->toISOString(),
        ];
    }

    private function formatSlot(object $row): array
    {
        return [
            'id' => (string)$row->id,
            'adminId' => (string)$row->admin_id,
            'adminName' => (string)$row->admin_name,
            'subInstituteId' => (string)$row->sub_institute_id,
            'date' => (string)$row->date,
            'startTime' => (string)$row->start_time,
            'endTime' => (string)$row->end_time,
            'durationMinutes' => (int)$row->duration_minutes,
            'isBooked' => (bool)$row->is_booked,
            'bookingId' => $row->booking_id ? (string)$row->booking_id : null,
            'meetUrl' => $row->meet_url ? (string)$row->meet_url : null,
        ];
    }

    private function formatBooking(object $row): array
    {
        return [
            'id' => (string)$row->id,
            'slotId' => (string)$row->slot_id,
            'sessionId' => (string)$row->session_id,
            'studentId' => (string)$row->student_id,
            'studentName' => (string)$row->student_name,
            'adminId' => (string)$row->admin_id,
            'adminName' => (string)$row->admin_name,
            'subInstituteId' => (string)$row->sub_institute_id,
            'date' => (string)$row->date,
            'startTime' => (string)$row->start_time,
            'endTime' => (string)$row->end_time,
            'durationMinutes' => (int)$row->duration_minutes,
            'status' => (string)$row->status,
            'meetUrl' => (string)$row->meet_url,
            'studentJoined' => (bool)$row->student_joined,
            'adminJoined' => (bool)$row->admin_joined,
            'studentJoinedAt' => $row->student_joined_at ? Carbon::parse($row->student_joined_at)->toISOString() : null,
            'adminJoinedAt' => $row->admin_joined_at ? Carbon::parse($row->admin_joined_at)->toISOString() : null,
            'gracePeriodMinutes' => (int)$row->grace_period_minutes,
            'reminderSent' => (bool)$row->reminder_sent,
            'createdAt' => Carbon::parse($row->created_at)->toISOString(),
            'updatedAt' => Carbon::parse($row->updated_at)->toISOString(),
        ];
    }
}
