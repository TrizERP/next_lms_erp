<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * G2G LMS migration — Package 2 (Assignments, Sessions & Calendar).
 *
 * Ported from G2G's `App\Http\Controllers\Api\LmsSessionController` (hp_erp).
 * Business logic, validation and response shapes are preserved as-is; only
 * identity/tenant resolution and route registration changed — see
 * `AssignmentsController`'s docblock for the full reasoning (same project,
 * same decision): identity is read directly from the session the
 * `api.session` middleware hydrates (`session('sub_institute_id')` /
 * `session('user_id')` / `session('user_profile_name')` /
 * `session('is_admin')`), rather than a not-yet-existing shared
 * `ResolvesLmsIdentity` trait; token/type=API validation is not re-checked
 * per method since the whole `g2g-lms` route group already runs behind
 * `['api.session', 'staff.only']`.
 *
 * Sessions live in `lms_virtual_classroom` — reused, not created — the only
 * existing table carrying `event_date` + `from_time`/`to_time`/`url`, same
 * as G2G's own choice. See this package's migration
 * `2026_09_05_230200_add_g2g_lms_columns_to_lms_virtual_classroom_table`
 * for what was added to it. Seats/attendees come from
 * `lms_session_registrations` (new table, this package's own migration).
 *
 * `deadlines()` differs from hp_erp in one respect: hp_erp's course-deadline
 * half read `lms_course_enroll.end_date`, a table that does not exist in
 * this project yet (Learning Dashboard/Catalog/My Learning package's
 * table). That half is guarded behind `Schema::hasTable()` and returns no
 * course deadlines until that table exists; the `calendar_events` half
 * (already guarded the same way in the source) is unchanged.
 */
class SessionsCalendarController extends Controller
{
    /** Profiles allowed to schedule sessions and manage attendees. */
    private function isAdminOrHr(): bool
    {
        $profile = strtolower((string) session()->get('user_profile_name'));
        $isAdmin = (bool) session()->get('is_admin');

        return $isAdmin || str_contains($profile, 'admin') || str_contains($profile, 'hr');
    }

    private function guardAdmin()
    {
        if ($this->isAdminOrHr()) {
            return null;
        }

        return response()->json([
            'status' => false,
            'message' => 'Your profile is not permitted to manage sessions.',
        ], 403);
    }

    private function tenantId(): ?int
    {
        $value = session()->get('sub_institute_id');

        return $value !== null ? (int) $value : null;
    }

    private function actingUserId(): ?int
    {
        $value = session()->get('user_id');

        return $value !== null ? (int) $value : null;
    }

    /** Seats consumed per session - only live registrations count. */
    private function registrationCounts()
    {
        return DB::table('lms_session_registrations')
            ->whereNull('deleted_at')
            ->whereIn('status', ['registered', 'attended'])
            ->select('session_id', DB::raw('COUNT(*) as registered_count'))
            ->groupBy('session_id');
    }

    /**
     * Open / Almost-full / Full is derived, never stored, so it can never
     * drift from the actual registration count.
     */
    private function decorate($session, $userId = null): object
    {
        $registered = (int) ($session->registered_count ?? 0);
        $total = $session->seats_total !== null ? (int) $session->seats_total : null;

        $session->registered_count = $registered;
        $session->seats_available = $total !== null ? max(0, $total - $registered) : null;

        if ($total === null || $total === 0) {
            $session->seat_status = 'open';
        } elseif ($registered >= $total) {
            $session->seat_status = 'full';
        } elseif ($registered / $total >= 0.8) {
            $session->seat_status = 'almost-full';
        } else {
            $session->seat_status = 'open';
        }

        $session->is_past = $session->event_date !== null && $session->event_date < now()->toDateString();
        if ($session->is_past) {
            $session->seat_status = 'closed';
        }

        $session->is_registered = $userId !== null && !empty($session->my_registration_status)
            && $session->my_registration_status !== 'cancelled';

        return $session;
    }

    /** GET api/g2g-lms/sessions-calendar — sessions in a date window. */
    public function index(Request $request)
    {
        $subInstituteId = $this->tenantId();
        $userId = $this->actingUserId();

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $from = $request->input('from') ?: now()->startOfMonth()->toDateString();
            $to = $request->input('to') ?: now()->endOfMonth()->toDateString();

            $query = DB::table('lms_virtual_classroom as v')
                ->leftJoinSub($this->registrationCounts(), 'r', fn ($join) => $join->on('r.session_id', '=', 'v.id'))
                ->leftJoin('lms_session_registrations as mine', function ($join) use ($userId) {
                    $join->on('mine.session_id', '=', 'v.id')
                        ->where('mine.user_id', '=', DB::raw((int) $userId))
                        ->whereNull('mine.deleted_at');
                })
                ->where('v.sub_institute_id', $subInstituteId)
                ->whereNull('v.deleted_at')
                ->whereBetween('v.event_date', [$from, $to]);

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('v.room_name', 'like', "%{$search}%")
                        ->orWhere('v.trainer_name', 'like', "%{$search}%")
                        ->orWhere('v.venue', 'like', "%{$search}%");
                });
            }

            if ($type = $request->input('session_type')) {
                $query->where('v.session_type', $type);
            }

            $sessions = $query
                ->orderBy('v.event_date')
                ->orderBy('v.from_time')
                ->get([
                    'v.id', 'v.room_name', 'v.session_type', 'v.description', 'v.notes',
                    'v.trainer_name', 'v.trainer_email', 'v.venue', 'v.seats_total',
                    'v.event_date', 'v.from_time', 'v.to_time', 'v.url', 'v.status',
                    'v.subject_id', 'v.standard_id',
                    DB::raw('COALESCE(r.registered_count, 0) as registered_count'),
                    'mine.status as my_registration_status',
                ])
                ->map(fn ($session) => $this->decorate($session, $userId));

            return response()->json([
                'status' => true,
                'data' => $sessions,
                'meta' => ['from' => $from, 'to' => $to],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load sessions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET api/g2g-lms/sessions-calendar/stats */
    public function stats(Request $request)
    {
        $subInstituteId = $this->tenantId();

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $today = now()->toDateString();
            $in30Days = now()->addDays(30)->toDateString();

            $base = fn () => DB::table('lms_virtual_classroom')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at');

            $upcoming = $base()->whereBetween('event_date', [$today, $in30Days])->count();
            $thisMonth = $base()
                ->whereBetween('event_date', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->count();

            $totalRegistrations = DB::table('lms_session_registrations')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->whereIn('status', ['registered', 'attended'])
                ->count();

            $upcomingSessions = DB::table('lms_virtual_classroom as v')
                ->leftJoinSub($this->registrationCounts(), 'r', fn ($join) => $join->on('r.session_id', '=', 'v.id'))
                ->where('v.sub_institute_id', $subInstituteId)
                ->whereNull('v.deleted_at')
                ->where('v.event_date', '>=', $today)
                ->get(['v.seats_total', DB::raw('COALESCE(r.registered_count, 0) as registered_count')]);

            $full = $upcomingSessions
                ->filter(fn ($s) => $s->seats_total !== null && $s->registered_count >= $s->seats_total)
                ->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'upcoming_sessions' => $upcoming,
                    'total_registrations' => $totalRegistrations,
                    'open_sessions' => $upcomingSessions->count() - $full,
                    'full_sessions' => $full,
                    'sessions_this_month' => $thisMonth,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load session stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET api/g2g-lms/sessions-calendar/deadlines - dated markers to overlay
     * on the calendar. See this class's docblock for the `lms_course_enroll`
     * guard (not yet present in this project).
     */
    public function deadlines(Request $request)
    {
        $subInstituteId = $this->tenantId();
        $userId = (int) $this->actingUserId();

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        $from = $request->input('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->input('to') ?: now()->endOfMonth()->toDateString();

        try {
            $isAdmin = $this->isAdminOrHr();

            $courseDeadlines = collect();

            if (Schema::hasTable('lms_course_enroll')) {
                $courseDeadlines = DB::table('lms_course_enroll as e')
                    ->join('sub_std_map as m', 'm.id', '=', 'e.course_id')
                    ->leftJoin('tbluser as u', 'u.id', '=', 'e.user_id')
                    ->where('e.sub_institute_id', $subInstituteId)
                    ->whereNull('e.deleted_at')
                    ->whereNotNull('e.end_date')
                    ->whereBetween('e.end_date', [$from, $to])
                    ->when(!$isAdmin && $userId, fn ($q) => $q->where('e.user_id', $userId))
                    ->orderBy('e.end_date')
                    ->limit(200)
                    ->get([
                        DB::raw('DATE(e.end_date) as date'),
                        DB::raw('m.display_name as title'),
                        'e.user_id',
                        DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
                        DB::raw("'course-deadline' as kind"),
                    ]);
            }

            $events = collect();

            if (Schema::hasTable('calendar_events')) {
                $events = DB::table('calendar_events')
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereBetween('school_date', [$from, $to])
                    ->orderBy('school_date')
                    ->limit(200)
                    ->get([
                        DB::raw('DATE(school_date) as date'),
                        'title',
                        DB::raw('NULL as user_id'),
                        DB::raw('NULL as learner_name'),
                        DB::raw("'event' as kind"),
                    ]);
            }

            return response()->json([
                'status' => true,
                'data' => $courseDeadlines->concat($events)->sortBy('date')->values(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load calendar deadlines',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** GET api/g2g-lms/sessions-calendar/{id}/attendees - who is on this session. */
    public function attendees(Request $request, $id)
    {
        $subInstituteId = $this->tenantId();

        $attendees = DB::table('lms_session_registrations as r')
            ->join('tbluser as u', 'u.id', '=', 'r.user_id')
            ->where('r.session_id', $id)
            ->when($subInstituteId, fn ($q) => $q->where('r.sub_institute_id', $subInstituteId))
            ->whereNull('r.deleted_at')
            ->orderBy('r.registered_at')
            ->get([
                'r.id', 'r.user_id', 'r.status', 'r.registered_at',
                'u.employee_no', 'u.email',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            ]);

        return response()->json(['status' => true, 'data' => $attendees]);
    }

    /** POST api/g2g-lms/sessions-calendar - schedule a session (admin/HR). */
    public function store(Request $request)
    {
        if ($roleError = $this->guardAdmin()) {
            return $roleError;
        }

        $subInstituteId = $this->tenantId();

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            $this->rules()
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $id = DB::table('lms_virtual_classroom')->insertGetId($this->payload($request) + [
                'sub_institute_id' => $subInstituteId,
                'created_by' => $this->actingUserId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Session scheduled',
                'data' => DB::table('lms_virtual_classroom')->find($id),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to schedule the session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** PUT api/g2g-lms/sessions-calendar/{id} */
    public function update(Request $request, $id)
    {
        if ($roleError = $this->guardAdmin()) {
            return $roleError;
        }

        $subInstituteId = $this->tenantId();

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            $this->rules()
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $session = DB::table('lms_virtual_classroom')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$session) {
            return response()->json(['status' => false, 'message' => 'Session not found'], 404);
        }

        $registered = DB::table('lms_session_registrations')
            ->where('session_id', $id)
            ->whereIn('status', ['registered', 'attended'])
            ->whereNull('deleted_at')
            ->count();

        if ($request->seats_total !== null && (int) $request->seats_total < $registered) {
            return response()->json([
                'status' => false,
                'message' => "This session already has {$registered} registration(s); seats cannot be set below that.",
            ], 422);
        }

        DB::table('lms_virtual_classroom')->where('id', $id)->update($this->payload($request) + [
            'updated_by' => $this->actingUserId(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Session updated',
            'data' => DB::table('lms_virtual_classroom')->find($id),
        ]);
    }

    /** DELETE api/g2g-lms/sessions-calendar/{id} - soft delete, registrations go with it. */
    public function destroy(Request $request, $id)
    {
        if ($roleError = $this->guardAdmin()) {
            return $roleError;
        }

        $subInstituteId = $this->tenantId();

        $session = DB::table('lms_virtual_classroom')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$session) {
            return response()->json(['status' => false, 'message' => 'Session not found'], 404);
        }

        $now = now();
        DB::table('lms_virtual_classroom')->where('id', $id)
            ->update(['deleted_at' => $now, 'deleted_by' => $this->actingUserId()]);
        DB::table('lms_session_registrations')->where('session_id', $id)->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'deleted_by' => $this->actingUserId()]);

        return response()->json([
            'status' => true,
            'message' => 'Session cancelled',
            'data' => ['id' => (int) $id],
        ]);
    }

    /**
     * POST api/g2g-lms/sessions-calendar/{id}/register
     * A learner takes a seat, or an admin puts someone on the session by
     * passing learner_id explicitly.
     */
    public function register(Request $request, $id)
    {
        $subInstituteId = $this->tenantId();
        $callerId = $this->actingUserId();
        $targetId = $request->input('learner_id', $callerId);

        if (!$targetId) {
            return response()->json(['status' => false, 'message' => 'user_id is required'], 422);
        }

        if ((string) $targetId !== (string) $callerId) {
            if ($roleError = $this->guardAdmin()) {
                return $roleError;
            }
        }

        $session = DB::table('lms_virtual_classroom')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$session) {
            return response()->json(['status' => false, 'message' => 'Session not found'], 404);
        }

        if ($session->event_date !== null && $session->event_date < now()->toDateString()) {
            return response()->json([
                'status' => false,
                'message' => 'That session has already taken place.',
            ], 422);
        }

        $registered = DB::table('lms_session_registrations')
            ->where('session_id', $id)
            ->whereIn('status', ['registered', 'attended'])
            ->whereNull('deleted_at')
            ->count();

        if ($session->seats_total !== null && $registered >= (int) $session->seats_total) {
            return response()->json(['status' => false, 'message' => 'This session is full.'], 422);
        }

        $existing = DB::table('lms_session_registrations')
            ->where('session_id', $id)
            ->where('user_id', $targetId)
            ->first();

        if ($existing && $existing->deleted_at === null && $existing->status !== 'cancelled') {
            return response()->json([
                'status' => false,
                'message' => 'Already registered for this session.',
            ], 422);
        }

        $now = now();

        if ($existing) {
            DB::table('lms_session_registrations')->where('id', $existing->id)->update([
                'status' => 'registered',
                'registered_at' => $now,
                'registered_by' => $callerId,
                'deleted_at' => null,
                'deleted_by' => null,
                'updated_by' => $callerId,
                'updated_at' => $now,
            ]);
            $registrationId = $existing->id;
        } else {
            $registrationId = DB::table('lms_session_registrations')->insertGetId([
                'session_id' => $id,
                'user_id' => $targetId,
                'status' => 'registered',
                'registered_at' => $now,
                'registered_by' => $callerId,
                'sub_institute_id' => $subInstituteId,
                'created_by' => $callerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Registered for the session',
            'data' => DB::table('lms_session_registrations')->find($registrationId),
        ], 201);
    }

    /**
     * DELETE api/g2g-lms/sessions-calendar/{id}/register
     * Give up a seat. Learners cancel their own; admins can remove anyone.
     */
    public function cancelRegistration(Request $request, $id)
    {
        $callerId = $this->actingUserId();
        $targetId = $request->input('learner_id', $callerId);

        if ((string) $targetId !== (string) $callerId) {
            if ($roleError = $this->guardAdmin()) {
                return $roleError;
            }
        }

        $registration = DB::table('lms_session_registrations')
            ->where('session_id', $id)
            ->where('user_id', $targetId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->first();

        if (!$registration) {
            return response()->json(['status' => false, 'message' => 'Registration not found'], 404);
        }

        DB::table('lms_session_registrations')->where('id', $registration->id)->update([
            'status' => 'cancelled',
            'updated_by' => $callerId,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Registration cancelled',
            'data' => ['id' => $registration->id],
        ]);
    }

    /** Shared validation for store/update. */
    private function rules(): array
    {
        return [
            'sub_institute_id' => 'required|integer',
            'room_name' => 'required|string|max:191',
            'session_type' => 'nullable|in:virtual,classroom',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'trainer_name' => 'nullable|string|max:191',
            'trainer_email' => 'nullable|email|max:191',
            'venue' => 'nullable|string|max:191',
            'seats_total' => 'nullable|integer|min:1|max:10000',
            'event_date' => 'required|date',
            'from_time' => 'required|date_format:H:i',
            'to_time' => 'required|date_format:H:i|after:from_time',
            'url' => 'nullable|string|max:2000',
            'status' => 'nullable|string|max:10',
            'subject_id' => 'nullable|integer',
        ];
    }

    /** Shared write payload for store/update. */
    private function payload(Request $request): array
    {
        return [
            'room_name' => $request->room_name,
            'session_type' => $request->input('session_type', 'virtual'),
            'description' => $request->description,
            'notes' => $request->notes,
            'trainer_name' => $request->trainer_name,
            'trainer_email' => $request->trainer_email,
            'venue' => $request->venue,
            'seats_total' => $request->seats_total,
            'event_date' => $request->event_date,
            'from_time' => $request->from_time,
            'to_time' => $request->to_time,
            'url' => $request->url,
            'status' => $request->input('status', '1'),
            'subject_id' => $request->subject_id,
        ];
    }
}
