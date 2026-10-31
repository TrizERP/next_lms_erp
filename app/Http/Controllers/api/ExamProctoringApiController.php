<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Http\Controllers\lms\onlineExamController;

class ExamProctoringApiController extends Controller
{
    private const EVENT_TYPES = [
        'tab_hidden',
        'tab_visible',
        'window_blur',
        'window_focus',
        'fullscreen_exit',
        'fullscreen_enter',
        'copy',
        'cut',
        'paste',
        'context_menu',
        'page_refresh',
        'network_disconnect',
        'network_reconnect',
        'inactivity_warning',
        'auto_submit',
        'screenshot_attempt',
        'select_all',
    ];

    private const VIOLATION_TYPES = [
        'tab_hidden',
        'window_blur',
        'fullscreen_exit',
        'copy',
        'cut',
        'paste',
        'context_menu',
        'inactivity_warning',
        'screenshot_attempt',
        'select_all',
    ];

    public function start(Request $request): JsonResponse
    {
        if ($response = $this->requireStudent($request)) {
            return $response;
        }

        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'min:1'],
            'client_session_key' => ['required', 'uuid'],
        ]);
        $studentId = (int) $request->session()->get('user_id');
        $tenantId = (int) $request->session()->get('sub_institute_id');

        $paper = DB::table('question_paper')
            ->where('id', $validated['exam_id'])
            ->where('sub_institute_id', $tenantId)
            ->first();
        if (! $paper) {
            return response()->json([
                'success' => false,
                'message' => 'The exam was not found for this school.',
            ], 404);
        }

        $identity = [
            'sub_institute_id' => $tenantId,
            'student_id' => $studentId,
            'client_session_key' => $validated['client_session_key'],
        ];
        $session = DB::transaction(function () use ($identity, $validated, $tenantId, $studentId, $paper) {
            $session = DB::table('lms_exam_proctoring_sessions')
                ->where($identity)
                ->lockForUpdate()
                ->first();
            if ($session) {
                if ((int) $session->exam_id === (int) $validated['exam_id'] && $session->status === 'interrupted') {
                    $expiresAt = $session->expires_at
                        ? Carbon::parse($session->expires_at)
                        : Carbon::parse($session->started_at)->addMinutes(max(1, (int) ($paper->time_allowed ?: 30)));
                    if ($expiresAt->isFuture()) {
                        DB::table('lms_exam_proctoring_sessions')->where('id', $session->id)->update([
                            'status' => 'active',
                            'ended_at' => null,
                            'expires_at' => $expiresAt,
                            'last_heartbeat_at' => now(),
                            'updated_at' => now(),
                        ]);
                        return DB::table('lms_exam_proctoring_sessions')->where('id', $session->id)->first();
                    }
                }
                if ((int) $session->exam_id === (int) $validated['exam_id'] && ! $session->expires_at) {
                    $expiresAt = Carbon::parse($session->started_at)
                        ->addMinutes(max(1, (int) ($paper->time_allowed ?: 30)));
                    DB::table('lms_exam_proctoring_sessions')->where('id', $session->id)->update([
                        'expires_at' => $expiresAt,
                        'updated_at' => now(),
                    ]);
                    return DB::table('lms_exam_proctoring_sessions')->where('id', $session->id)->first();
                }
                return $session;
            }

            $activeSession = DB::table('lms_exam_proctoring_sessions')
                ->where('sub_institute_id', $tenantId)
                ->where('student_id', $studentId)
                ->where('exam_id', (int) $validated['exam_id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();
            if ($activeSession) {
                $expiresAt = $activeSession->expires_at
                    ? Carbon::parse($activeSession->expires_at)
                    : Carbon::parse($activeSession->started_at)
                        ->addMinutes(max(1, (int) ($paper->time_allowed ?: 30)));
                if (! $activeSession->expires_at) {
                    DB::table('lms_exam_proctoring_sessions')->where('id', $activeSession->id)->update([
                        'expires_at' => $expiresAt,
                        'updated_at' => now(),
                    ]);
                }
                if ($expiresAt->isPast()) {
                    DB::table('lms_exam_proctoring_sessions')->where('id', $activeSession->id)->update([
                        'auto_submit_reason' => $activeSession->auto_submit_reason ?: 'time_expired',
                        'updated_at' => now(),
                    ]);
                    return DB::table('lms_exam_proctoring_sessions')->where('id', $activeSession->id)->first();
                }

                $heartbeatTimeout = max(45, (int) config('exam_proctoring.heartbeat_seconds', 15) * 3);
                $lastActivityAt = collect([
                    $activeSession->started_at,
                    $activeSession->last_event_at,
                    $activeSession->last_heartbeat_at,
                ])->filter()
                    ->map(fn ($timestamp) => Carbon::parse($timestamp))
                    ->max();
                if ($lastActivityAt && $lastActivityAt->greaterThan(now()->subSeconds($heartbeatTimeout))) {
                    return null;
                }

                DB::table('lms_exam_proctoring_sessions')->where('id', $activeSession->id)->update([
                    'client_session_key' => $validated['client_session_key'],
                    'last_heartbeat_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->insertProctoringEvent(
                    $activeSession,
                    'page_refresh',
                    'info',
                    false,
                    null,
                    json_encode(['source' => 'stale_session_recovery'], JSON_THROW_ON_ERROR),
                    (string) Str::uuid(),
                    now()
                );
                return DB::table('lms_exam_proctoring_sessions')->where('id', $activeSession->id)->first();
            }

            $now = now();
            $minutes = max(1, (int) ($paper->time_allowed ?: 30));
            $sessionId = (string) Str::uuid();
            DB::table('lms_exam_proctoring_sessions')->insert([
                ...$identity,
                'id' => $sessionId,
                'exam_id' => (int) $validated['exam_id'],
                'status' => 'active',
                'started_at' => $now,
                'expires_at' => $now->copy()->addMinutes($minutes),
                'last_heartbeat_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('lms_exam_proctoring_sessions')->where('id', $sessionId)->first();
        }, 3);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'This student already has an active session for this exam. Resume it in the original browser.',
                'code' => 'active_session_exists',
            ], 409);
        }
        if ((int) $session->exam_id !== (int) $validated['exam_id']) {
            return response()->json([
                'success' => false,
                'message' => 'This browser session is already bound to another exam.',
            ], 409);
        }

        $now = now();
        $expiresAt = $session->expires_at
            ? Carbon::parse($session->expires_at)
            : Carbon::parse($session->started_at)->addMinutes(max(1, (int) ($paper->time_allowed ?: 30)));
        if ($session->status === 'active' && ($expiresAt->isPast() || $session->auto_submit_reason)) {
            $submission = $this->submit($request, (string) $session->id);
            if (! $submission->isSuccessful()) {
                return $submission;
            }
            $session = DB::table('lms_exam_proctoring_sessions')->where('id', $session->id)->first();
        }
        $draft = $this->decodeDraft($session->answer_draft ?? null);
        $warningHistory = DB::table('lms_exam_proctoring_events')
            ->where('session_id', $session->id)
            ->where('is_violation', true)
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get(['event_type', 'occurred_at'])
            ->map(fn ($event) => [
                'event_type' => $event->event_type,
                'occurred_at' => $event->occurred_at,
                'message' => $this->warningMessage($event->event_type),
            ])
            ->all();
        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at,
                'server_time' => $now->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'remaining_seconds' => max(0, $now->diffInSeconds($expiresAt, false)),
                'draft' => $draft,
                'tab_switch_count' => (int) $session->tab_switch_count,
                'violation_count' => (int) $session->violation_count,
                'critical_violation_count' => (int) $session->critical_violation_count,
                'settings' => $this->settings(),
                'online_exam_id' => $session->online_exam_id,
                'auto_submit_reason' => $session->auto_submit_reason,
                'warning_history' => $warningHistory,
            ],
        ]);
    }

    public function recordEvent(Request $request, string $sessionId): JsonResponse
    {
        if ($response = $this->requireStudent($request)) {
            return $response;
        }

        $validated = $request->validate([
            'event_type' => ['required', Rule::in(self::EVENT_TYPES)],
            'client_session_key' => ['required', 'uuid'],
            'event_key' => ['required', 'uuid'],
            'details' => ['nullable', 'array', 'max:10'],
            'details.*' => ['nullable', 'string', 'max:200'],
        ]);
        $occurredAt = now();
        $details = isset($validated['details'])
            ? json_encode($validated['details'], JSON_THROW_ON_ERROR)
            : null;
        $result = DB::transaction(function () use ($request, $sessionId, $validated, $occurredAt, $details) {
            $session = $this->studentSession($request, $sessionId, true);
            if (! $session) {
                return ['state' => 'missing'];
            }
            if (! hash_equals((string) $session->client_session_key, $validated['client_session_key'])) {
                return ['state' => 'stale_client'];
            }
            if ($session->status !== 'active') {
                return ['state' => 'ended'];
            }
            $existingEvent = DB::table('lms_exam_proctoring_events')
                ->where('event_key', $validated['event_key'])
                ->first(['session_id']);
            if ($existingEvent) {
                if ($existingEvent->session_id !== $sessionId) {
                    return ['state' => 'duplicate_key'];
                }
                return [
                    'state' => 'recorded',
                    'data' => [
                        'event_type' => $validated['event_type'],
                        'is_violation' => in_array($validated['event_type'], self::VIOLATION_TYPES, true),
                        'warning_message' => null,
                        'tab_switch_count' => (int) $session->tab_switch_count,
                        'violation_count' => (int) $session->violation_count,
                        'critical_violation_count' => (int) $session->critical_violation_count,
                        'tab_limit_exceeded' => false,
                        'should_auto_submit' => (bool) $session->auto_submit_reason,
                        'auto_submit_reason' => $session->auto_submit_reason,
                        'duplicate' => true,
                    ],
                ];
            }

            $violation = in_array($validated['event_type'], self::VIOLATION_TYPES, true);
            $severity = in_array(
                $validated['event_type'],
                (array) config('exam_proctoring.critical_event_types', []),
                true
            ) ? 'critical' : ($violation ? 'warning' : 'info');
            $tabSwitchCount = (int) $session->tab_switch_count
                + ($validated['event_type'] === 'tab_hidden' ? 1 : 0);
            $violationCount = (int) $session->violation_count + ($violation ? 1 : 0);
            $criticalViolationCount = (int) $session->critical_violation_count
                + ($violation && $severity === 'critical' ? 1 : 0);
            $settings = $this->settings();
            $autoSubmitReason = $session->auto_submit_reason;
            if (
                ! $autoSubmitReason
                && $validated['event_type'] === 'tab_hidden'
                && config('exam_proctoring.auto_submit_on_tab_switch', true)
                && $tabSwitchCount >= $settings['tab_switch_limit']
            ) {
                $autoSubmitReason = 'tab_switch';
            } elseif (! $autoSubmitReason && $criticalViolationCount >= $settings['critical_violation_limit']) {
                $autoSubmitReason = 'critical_violation_threshold';
            }
            $this->insertProctoringEvent(
                $session,
                $validated['event_type'],
                $severity,
                $violation,
                $violation ? $violationCount : null,
                $details,
                $validated['event_key'],
                $occurredAt
            );
            DB::table('lms_exam_proctoring_sessions')->where('id', $sessionId)->update([
                'last_event_at' => $occurredAt,
                'tab_switch_count' => $tabSwitchCount,
                'violation_count' => $violationCount,
                'critical_violation_count' => $criticalViolationCount,
                'auto_submit_reason' => $autoSubmitReason,
                'last_heartbeat_at' => $occurredAt,
                'updated_at' => now(),
            ]);

            $warningMessage = $this->warningMessage($validated['event_type']);
            $tabLimitExceeded = $validated['event_type'] === 'tab_hidden'
                && $tabSwitchCount >= $settings['tab_switch_limit'];

            return [
                'state' => 'recorded',
                'data' => [
                    'event_type' => $validated['event_type'],
                    'is_violation' => $violation,
                    'severity' => $severity,
                    'warning_message' => $warningMessage,
                    'tab_switch_count' => $tabSwitchCount,
                    'violation_count' => $violationCount,
                    'critical_violation_count' => $criticalViolationCount,
                    'tab_limit_exceeded' => $tabLimitExceeded,
                    'should_auto_submit' => (bool) $autoSubmitReason,
                    'auto_submit_reason' => $autoSubmitReason,
                ],
            ];
        });

        if ($result['state'] === 'missing') {
            return response()->json([
                'success' => false,
                'message' => 'The proctoring session was not found.',
            ], 404);
        }
        if ($result['state'] === 'ended') {
            return response()->json([
                'success' => false,
                'message' => 'The proctoring session is no longer active.',
            ], 409);
        }
        if ($result['state'] === 'duplicate_key') {
            return response()->json([
                'success' => false,
                'message' => 'The event identifier has already been used by another session.',
            ], 409);
        }
        if ($result['state'] === 'stale_client') {
            return response()->json([
                'success' => false,
                'message' => 'This examination session was resumed in another browser. Reload the examination to continue.',
                'code' => 'stale_client',
            ], 409);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    public function saveDraft(Request $request, string $sessionId): JsonResponse
    {
        if ($response = $this->requireStudent($request)) {
            return $response;
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.mcq' => ['nullable', 'array', 'max:500'],
            'answers.mcq.*' => ['array', 'max:50'],
            'answers.mcq.*.*' => ['string', 'max:100'],
            'answers.narrative' => ['nullable', 'array', 'max:500'],
            'answers.narrative.*' => ['string', 'max:20000'],
        ]);
        $result = DB::transaction(function () use ($request, $sessionId, $validated) {
            $session = $this->studentSession($request, $sessionId, true);
            if (! $session) {
                return 'missing';
            }
            if ($session->status !== 'active') {
                return 'ended';
            }
            if ($session->expires_at && Carbon::parse($session->expires_at)->isPast()) {
                return 'expired';
            }

            DB::table('lms_exam_proctoring_sessions')->where('id', $sessionId)->update([
                'answer_draft' => json_encode($validated['answers'], JSON_THROW_ON_ERROR),
                'last_heartbeat_at' => now(),
                'updated_at' => now(),
            ]);

            return 'saved';
        });

        if ($result === 'expired') {
            return response()->json([
                'success' => true,
                'data' => ['saved' => false, 'expired' => true],
            ]);
        }
        if ($result !== 'saved') {
            return response()->json([
                'success' => false,
                'message' => match ($result) {
                    'missing' => 'The proctoring session was not found.',
                    'expired' => 'The examination time has expired.',
                    default => 'The proctoring session has already ended.',
                },
                'code' => $result,
            ], $result === 'missing' ? 404 : 409);
        }

        return response()->json(['success' => true, 'data' => ['saved' => true]]);
    }

    public function heartbeat(Request $request, string $sessionId): JsonResponse
    {
        if ($response = $this->requireStudent($request)) {
            return $response;
        }

        $session = DB::transaction(function () use ($request, $sessionId) {
            $session = $this->studentSession($request, $sessionId, true);
            if ($session && $session->status === 'active') {
                DB::table('lms_exam_proctoring_sessions')->where('id', $sessionId)->update([
                    'last_heartbeat_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return $session;
        });
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'The proctoring session was not found.',
            ], 404);
        }

        $now = now();
        $expiresAt = $session->expires_at ? Carbon::parse($session->expires_at) : $now;
        if (
            $session->status === 'active'
            && ($expiresAt->isPast() || $session->auto_submit_reason)
        ) {
            return $this->submit($request, $sessionId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $session->status,
                'server_time' => $now->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'remaining_seconds' => max(0, $now->diffInSeconds($expiresAt, false)),
                'expired' => $session->status === 'active' && $expiresAt->isPast(),
                'auto_submit_reason' => $session->auto_submit_reason,
                'online_exam_id' => $session->online_exam_id,
            ],
        ]);
    }

    public function submit(Request $request, string $sessionId): JsonResponse
    {
        if ($response = $this->requireStudent($request)) {
            return $response;
        }

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.mcq' => ['nullable', 'array', 'max:500'],
            'answers.mcq.*' => ['array', 'max:50'],
            'answers.mcq.*.*' => ['string', 'max:100'],
            'answers.narrative' => ['nullable', 'array', 'max:500'],
            'answers.narrative.*' => ['string', 'max:20000'],
        ]);

        $result = DB::transaction(function () use ($request, $sessionId, $validated) {
            $session = $this->studentSession($request, $sessionId, true);
            if (! $session) {
                return ['state' => 'missing'];
            }
            if ($session->status === 'submitted' && $session->online_exam_id) {
                return ['state' => 'submitted', 'online_exam_id' => $session->online_exam_id];
            }
            if ($session->status !== 'active') {
                return ['state' => 'ended'];
            }

            $expired = $session->expires_at && Carbon::parse($session->expires_at)->isPast();
            $autoSubmitReason = $session->auto_submit_reason;
            if (! $expired && ! $autoSubmitReason) {
                $draft = $validated['answers'] ?? $this->decodeDraft($session->answer_draft ?? null);
            } else {
                $draft = $this->decodeDraft($session->answer_draft ?? null);
            }
            $autoSubmitReason = $autoSubmitReason ?: ($expired ? 'time_expired' : null);
            if ($autoSubmitReason && empty($draft)) {
                $draft = ['mcq' => [], 'narrative' => []];
            }
            if (! $autoSubmitReason && ! isset($validated['answers'])) {
                return ['state' => 'draft_missing'];
            }

            $answers = $draft;
            $answerPayload = [
                'type' => 'API',
                'questionpaper_id' => $session->exam_id,
                'hid_session_quiz' => Carbon::parse($session->started_at)->toDateTimeString(),
                'answer_multiple' => $answers['mcq'] ?? [],
                'answer_narrative' => $answers['narrative'] ?? [],
                'sub_institute_id' => $session->sub_institute_id,
                'user_id' => $session->student_id,
                'syear' => $request->session()->get('syear'),
            ];
            $submissionRequest = Request::create('/lms/online_exam', 'POST', $answerPayload);
            $submissionRequest->setLaravelSession($request->session());
            $submissionRequest->attributes->set('proctoring_authorized', true);

            $submission = app(onlineExamController::class)->store($submissionRequest);
            $submissionData = $submission instanceof JsonResponse ? $submission->getData(true) : [];
            $onlineExamId = $submissionData['online_exam_id'] ?? null;
            if (! $onlineExamId || ($submissionData['status_code'] ?? 0) !== 1) {
                throw new \RuntimeException('The examination submission could not be completed.');
            }

            DB::table('lms_exam_proctoring_sessions')->where('id', $sessionId)->update([
                'answer_draft' => json_encode($answers, JSON_THROW_ON_ERROR),
                'status' => 'submitted',
                'ended_at' => now(),
                'auto_submit_reason' => $autoSubmitReason,
                'online_exam_id' => (int) $onlineExamId,
                'updated_at' => now(),
            ]);
            if ($autoSubmitReason) {
                $this->insertProctoringEvent(
                    $session,
                    'auto_submit',
                    'info',
                    false,
                    null,
                    json_encode(['reason' => $autoSubmitReason], JSON_THROW_ON_ERROR),
                    (string) Str::uuid(),
                    now()
                );
            }

            return [
                'state' => 'submitted',
                'online_exam_id' => (int) $onlineExamId,
                'result' => $submissionData,
            ];
        });

        if ($result['state'] !== 'submitted') {
            $message = match ($result['state']) {
                'missing' => 'The proctoring session was not found.',
                'draft_missing' => 'Save the latest answers before submitting.',
                default => 'The proctoring session has already ended.',
            };
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $result['state'] === 'missing' ? 404 : 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'online_exam_id' => $result['online_exam_id'],
                'result' => $result['result'] ?? null,
            ],
        ]);
    }

    public function complete(Request $request, string $sessionId): JsonResponse
    {
        if ($response = $this->requireStudent($request)) {
            return $response;
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['interrupted'])],
        ]);
        $sessionState = DB::transaction(function () use ($request, $sessionId, $validated) {
            $session = $this->studentSession($request, $sessionId, true);
            if (! $session) {
                return 'missing';
            }
            if ($session->status === $validated['status']) {
                return 'unchanged';
            }
            if ($session->status !== 'active') {
                return 'ended';
            }

            DB::table('lms_exam_proctoring_sessions')->where('id', $sessionId)->update([
                'status' => $validated['status'],
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

            return 'updated';
        });
        if ($sessionState === 'missing') {
            return response()->json([
                'success' => false,
                'message' => 'The proctoring session was not found.',
            ], 404);
        }
        if ($sessionState === 'ended') {
            return response()->json([
                'success' => false,
                'message' => 'The proctoring session has already ended.',
            ], 409);
        }

        return response()->json(['success' => true, 'data' => ['status' => $validated['status']]]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = Validator::make($request->query(), [
            'exam_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $tenantId = (int) $request->session()->get('sub_institute_id');

        $eventCounts = DB::table('lms_exam_proctoring_events as events')
            ->join('lms_exam_proctoring_sessions as scoped_sessions', 'scoped_sessions.id', '=', 'events.session_id')
            ->where('scoped_sessions.sub_institute_id', $tenantId)
            ->select('events.session_id')
            ->selectRaw('COUNT(*) as event_count')
            ->selectRaw('SUM(CASE WHEN is_violation = 1 THEN 1 ELSE 0 END) as violation_count')
            ->groupBy('events.session_id');

        $query = DB::table('lms_exam_proctoring_sessions as sessions')
            ->leftJoin('question_paper as papers', function ($join) {
                $join->on('papers.id', '=', 'sessions.exam_id')
                    ->on('papers.sub_institute_id', '=', 'sessions.sub_institute_id');
            })
            ->leftJoinSub($eventCounts, 'event_counts', function ($join) {
                $join->on('event_counts.session_id', '=', 'sessions.id');
            })
            ->where('sessions.sub_institute_id', $tenantId)
            ->select([
                'sessions.id as session_id',
                'sessions.student_id',
                'sessions.exam_id',
                'papers.paper_name as exam_name',
                'sessions.status',
                'sessions.started_at',
                'sessions.ended_at',
                'sessions.last_event_at',
                'sessions.tab_switch_count',
                'sessions.critical_violation_count',
                'sessions.auto_submit_reason',
                DB::raw('COALESCE(event_counts.event_count, 0) as event_count'),
                DB::raw('COALESCE(event_counts.violation_count, 0) as violation_count'),
            ])
            ->orderByDesc('sessions.started_at')
            ->limit(100);

        if (! empty($validated['exam_id'])) {
            $query->where('sessions.exam_id', (int) $validated['exam_id']);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function events(Request $request, string $sessionId): JsonResponse
    {
        $session = DB::table('lms_exam_proctoring_sessions')
            ->where('id', $sessionId)
            ->where('sub_institute_id', (int) $request->session()->get('sub_institute_id'))
            ->first();
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'The proctoring session was not found.',
            ], 404);
        }

        $events = DB::table('lms_exam_proctoring_events as events')
            ->leftJoin('question_paper as papers', 'papers.id', '=', 'events.question_paper_id')
            ->where('events.session_id', $sessionId)
            ->orderByDesc('events.occurred_at')
            ->limit(200)
            ->get([
                'events.id',
                'events.exam_id',
                'events.question_paper_id',
                'papers.paper_name as question_paper_name',
                'events.student_id',
                'events.session_id',
                'events.event_type',
                'events.severity',
                'events.is_violation',
                'events.event_details',
                'events.violation_count',
                'events.occurred_at',
                'events.created_at',
            ])
            ->reverse()
            ->values();

        return response()->json(['success' => true, 'data' => $events]);
    }

    private function requireStudent(Request $request): ?JsonResponse
    {
        if (! $request->session()->get('is_student')) {
            return response()->json([
                'success' => false,
                'message' => 'Proctoring sessions are available to student accounts only.',
            ], 403);
        }

        return null;
    }

    private function studentSession(Request $request, string $sessionId, bool $forUpdate = false): ?object
    {
        $query = DB::table('lms_exam_proctoring_sessions')
            ->where('id', $sessionId)
            ->where('student_id', (int) $request->session()->get('user_id'))
            ->where('sub_institute_id', (int) $request->session()->get('sub_institute_id'));

        return ($forUpdate ? $query->lockForUpdate() : $query)->first();
    }

    private function settings(): array
    {
        return [
            'tab_switch_limit' => max(1, (int) config('exam_proctoring.tab_switch_limit', 5)),
            'auto_submit_on_tab_switch' => (bool) config('exam_proctoring.auto_submit_on_tab_switch', true),
            'critical_violation_limit' => max(1, (int) config('exam_proctoring.critical_violation_limit', 5)),
            'inactivity_seconds' => max(60, (int) config('exam_proctoring.inactivity_seconds', 300)),
            'heartbeat_seconds' => max(5, (int) config('exam_proctoring.heartbeat_seconds', 15)),
        ];
    }

    private function decodeDraft(mixed $draft): array
    {
        if (is_array($draft)) {
            return $draft;
        }
        if (! is_string($draft) || $draft === '') {
            return ['mcq' => [], 'narrative' => []];
        }
        $decoded = json_decode($draft, true);
        return is_array($decoded) ? $decoded : ['mcq' => [], 'narrative' => []];
    }

    private function warningMessage(string $eventType): ?string
    {
        return match ($eventType) {
            'tab_hidden' => 'Leaving the examination tab has been recorded.',
            'window_blur' => 'The examination window lost focus. This has been recorded.',
            'fullscreen_exit' => 'Exiting fullscreen has been recorded.',
            'copy', 'cut', 'paste' => 'Clipboard actions are disabled and have been recorded.',
            'context_menu' => 'The context menu is disabled and this attempt has been recorded.',
            'inactivity_warning' => 'Please continue the examination. Your inactivity has been recorded.',
            'screenshot_attempt' => 'A screenshot shortcut was detected. Browser controls cannot prevent every capture method.',
            'select_all' => 'Selecting all examination content is disabled and has been recorded.',
            default => null,
        };
    }

    private function insertProctoringEvent(
        object $session,
        string $eventType,
        string $severity,
        bool $isViolation,
        ?int $violationCount,
        ?string $details,
        string $eventKey,
        Carbon $occurredAt
    ): void {
        DB::table('lms_exam_proctoring_events')->insert([
            'exam_id' => $session->exam_id,
            'question_paper_id' => $session->exam_id,
            'student_id' => $session->student_id,
            'session_id' => $session->id,
            'event_type' => $eventType,
            'severity' => $severity,
            'is_violation' => $isViolation,
            'event_details' => $details,
            'details' => $details,
            'violation_count' => $violationCount,
            'event_key' => $eventKey,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }
}
