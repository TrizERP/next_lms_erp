<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniEngagementService;
use App\Services\Alumni\AlumniMentorshipService;
use App\Services\Alumni\AlumniMessaging;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Mentorship - staff side (coordinators run the workflow; mentors' answers are recorded here until alumni can
 * sign in themselves).
 *
 *   GET  alumni/mentorship/mentors                      mentor profiles with current load
 *   POST alumni/mentorship/mentors/store                register an alumnus as a mentor
 *   POST alumni/mentorship/mentors/{id}/update | delete
 *   GET  alumni/mentorship/requests                     ?status=
 *   POST alumni/mentorship/requests/store               log a request on behalf of a student
 *   GET  alumni/mentorship/requests/{id}                detail with sessions and feedback
 *   GET  alumni/mentorship/requests/{id}/matches        ranked, explainable suggestions
 *   POST alumni/mentorship/requests/{id}/match | respond | cancel | complete
 *   POST alumni/mentorship/requests/{id}/sessions/store
 *   POST alumni/mentorship/sessions/{sid}/update | feedback
 *
 * Completing a session records the mentor's engagement points exactly once (idempotent on the session id).
 */
class AlumniMentorshipApiController extends Controller
{
    use AlumniApiSupport;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function __construct(
        private AlumniService $alumni,
        private AlumniMentorshipService $mentorship,
        private AlumniEngagementService $engagement,
    ) {
    }

    // -------------------------------------------------------------------- mentors

    public function mentors(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $rows = DB::table('alumni_mentor_profiles as m')
            ->join('alumni as a', function ($j) {
                $j->on('a.id', '=', 'm.alumni_id')->on('a.sub_institute_id', '=', 'm.sub_institute_id');
            })
            ->where('m.sub_institute_id', $t)->orderBy('a.full_name')
            ->get(['m.*', 'a.full_name', 'a.alumni_code', 'a.batch', 'a.status as alumni_status'])
            ->map(function ($m) use ($t) {
                $m->load = $this->mentorship->load($t, (int) $m->alumni_id);

                return $m;
            });

        return $this->ok($rows);
    }

    public function mentorStore(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['alumni_id' => 'required|integer|min:1'] + $this->mentorRules());
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $alumnus = $this->alumni->find($t, (int) $request->input('alumni_id'));
        if (! $alumnus || ! in_array($alumnus->status, ['verified', 'active'], true)) {
            return $this->validationError(['alumni_id' => ['Choose a verified or active alumnus.']]);
        }
        if (DB::table('alumni_mentor_profiles')->where('sub_institute_id', $t)->where('alumni_id', $alumnus->id)->exists()) {
            return $this->validationError(['alumni_id' => ['This alumnus is already registered as a mentor.']]);
        }
        $id = DB::table('alumni_mentor_profiles')->insertGetId($this->mentorPayload($request) + [
            'sub_institute_id' => $t, 'alumni_id' => $alumnus->id, 'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->alumni->logActivity($t, (int) $alumnus->id, 'mentor_registered', 'Registered as a mentor', $this->userId($request));
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'mentor_create', ['entity_type' => 'alumni_mentor', 'entity_id' => $id, 'after' => ['alumni_id' => (int) $alumnus->id]], $request);

        return $this->ok(DB::table('alumni_mentor_profiles')->where('id', $id)->first(), 'Mentor registered.', 201);
    }

    public function mentorUpdate(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), $this->mentorRules());
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_mentor_profiles')->where('sub_institute_id', $t)->where('id', (int) $id);
        if (! (clone $q)->exists()) {
            return $this->notFound('Mentor');
        }
        $q->update($this->mentorPayload($request, true) + ['updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'mentor_update', ['entity_type' => 'alumni_mentor', 'entity_id' => (int) $id, 'after' => $request->only(['status', 'capacity'])], $request);

        return $this->ok(DB::table('alumni_mentor_profiles')->where('id', (int) $id)->first(), 'Mentor updated.');
    }

    public function mentorDelete(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $m = DB::table('alumni_mentor_profiles')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $m) {
            return $this->notFound('Mentor');
        }
        if ($this->mentorship->load($t, (int) $m->alumni_id) > 0) {
            return response()->json(['status_code' => 0, 'message' => 'This mentor has open requests. Pause the profile instead, or reassign the requests first.'], 409);
        }
        DB::table('alumni_mentor_profiles')->where('id', $m->id)->delete();
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'mentor_delete', ['entity_type' => 'alumni_mentor', 'entity_id' => (int) $id], $request);

        return $this->ok(null, 'Mentor removed.');
    }

    // ------------------------------------------------------------------- requests

    /** Find a student of this institute by name or enrollment number (id, name and enrollment number only). */
    public function studentSearch(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $s = trim((string) $request->input('q'));
        if (mb_strlen($s) < 2) {
            return $this->ok([]);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
        $rows = DB::table('tblstudent')->where('sub_institute_id', $this->tenant($request))
            ->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('enrollment_no', 'like', $like))
            ->orderBy('first_name')->limit(10)->get(['id', 'first_name', 'last_name', 'enrollment_no']);

        return $this->ok($rows);
    }

    public function requests(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['status' => ['nullable', Rule::in(['open', 'matched', 'accepted', 'completed', 'cancelled'])], 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_mentorship_requests as r')
            ->leftJoin('tblstudent as s', function ($j) {
                $j->on('s.id', '=', 'r.student_id')->on('s.sub_institute_id', '=', 'r.sub_institute_id');
            })
            ->leftJoin('alumni as a', function ($j) {
                $j->on('a.id', '=', 'r.mentor_alumni_id')->on('a.sub_institute_id', '=', 'r.sub_institute_id');
            })
            ->where('r.sub_institute_id', $t);
        if ($request->filled('status')) {
            $q->where('r.status', $request->input('status'));
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByRaw("CASE r.status WHEN 'open' THEN 0 WHEN 'matched' THEN 1 WHEN 'accepted' THEN 2 ELSE 3 END")->orderByDesc('r.id')
            ->forPage($page, $per)
            ->get(['r.id', 'r.student_id', 'r.career_area', 'r.status', 'r.preferred_date', 'r.mentor_alumni_id', 'r.created_at', 'r.created_by_role',
                's.first_name as student_first', 's.last_name as student_last', 'a.full_name as mentor_name']);

        return $this->ok(['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function requestStore(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'student_id' => 'required|integer|min:1', 'career_area' => 'required|string|max:190', 'guidance_needed' => 'nullable|string|max:3000',
            'preferred_mentor_alumni_id' => 'nullable|integer|min:1', 'preferred_date' => 'nullable|date|after_or_equal:today',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        if (! DB::table('tblstudent')->where('id', (int) $request->input('student_id'))->where('sub_institute_id', $t)->exists()) {
            return $this->validationError(['student_id' => ['That student was not found.']]);
        }

        return $this->createRequest($request, $t, (int) $request->input('student_id'), 'staff');
    }

    /** Shared by the staff and the student endpoint. */
    public function createRequest(Request $request, int $t, int $studentId, string $role): JsonResponse
    {
        $preferred = $request->filled('preferred_mentor_alumni_id') ? (int) $request->input('preferred_mentor_alumni_id') : null;
        if ($preferred !== null && ! DB::table('alumni_mentor_profiles')->where('sub_institute_id', $t)->where('alumni_id', $preferred)->where('status', 'active')->exists()) {
            return $this->validationError(['preferred_mentor_alumni_id' => ['That mentor is not available.']]);
        }
        $open = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $t)->where('student_id', $studentId)
            ->whereIn('status', ['open', 'matched', 'accepted'])->count();
        if ($open >= 3) {
            return response()->json(['status_code' => 0, 'message' => 'There are already three requests in progress for this student. Finish or cancel one first.'], 409);
        }
        $id = DB::table('alumni_mentorship_requests')->insertGetId([
            'sub_institute_id' => $t, 'student_id' => $studentId, 'career_area' => trim((string) $request->input('career_area')),
            'guidance_needed' => $request->input('guidance_needed'), 'preferred_mentor_alumni_id' => $preferred,
            'preferred_date' => $request->input('preferred_date'), 'status' => 'open', 'created_by_role' => $role,
            'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'request_create', ['entity_type' => 'alumni_mentorship_request', 'entity_id' => $id, 'after' => ['student_id' => $studentId, 'by' => $role]], $request);

        return $this->ok(DB::table('alumni_mentorship_requests')->where('id', $id)->first(), 'Mentorship request submitted.', 201);
    }

    public function requestShow(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $req) {
            return $this->notFound('Request');
        }
        $sessions = DB::table('alumni_mentorship_sessions')->where('sub_institute_id', $t)->where('request_id', $req->id)->orderBy('scheduled_at')->get();
        $feedback = DB::table('alumni_mentorship_feedback')->where('sub_institute_id', $t)->whereIn('session_id', $sessions->pluck('id'))->get();

        return $this->ok([
            'request' => $req,
            'student' => DB::table('tblstudent')->where('id', $req->student_id)->where('sub_institute_id', $t)->first(['id', 'first_name', 'last_name', 'enrollment_no']),
            'mentor' => $req->mentor_alumni_id ? DB::table('alumni')->where('sub_institute_id', $t)->where('id', $req->mentor_alumni_id)->first(['id', 'full_name', 'alumni_code', 'current_company']) : null,
            'sessions' => $sessions->map(function ($s) use ($feedback) {
                $s->feedback = $feedback->where('session_id', $s->id)->values();

                return $s;
            }),
        ]);
    }

    public function matches(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $this->tenant($request))->where('id', (int) $id)->first();

        return $req ? $this->ok($this->mentorship->suggest($this->tenant($request), $req)) : $this->notFound('Request');
    }

    public function match(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['mentor_alumni_id' => 'required|integer|min:1']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->transition($request, fn ($t) => $this->mentorship->match($t, (int) $id, (int) $request->input('mentor_alumni_id'), $this->userId($request)), 'match', (int) $id, 'Mentor matched. Waiting for the mentor to accept.');
    }

    public function respond(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['decision' => 'required|in:accept,decline', 'note' => 'nullable|string|max:500']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        if ($request->input('decision') === 'decline' && trim((string) $request->input('note')) === '') {
            return $this->validationError(['note' => ['Give a reason when the mentor declines.']]);
        }
        $accept = $request->input('decision') === 'accept';

        return $this->transition($request, fn ($t) => $this->mentorship->respond($t, (int) $id, $accept, $request->input('note')), $accept ? 'accept' : 'decline', (int) $id,
            $accept ? 'Mentor accepted the request.' : 'Mentor declined. The request is open again.');
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $req) {
            return $this->notFound('Request');
        }
        if (in_array($req->status, ['completed', 'cancelled'], true)) {
            return response()->json(['status_code' => 0, 'message' => 'This request is already ' . $req->status . '.'], 409);
        }

        return $this->cancelRequest($request, $t, $req);
    }

    public function cancelRequest(Request $request, int $t, object $req): JsonResponse
    {
        DB::transaction(function () use ($t, $req) {
            DB::table('alumni_mentorship_requests')->where('id', $req->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('alumni_mentorship_sessions')->where('sub_institute_id', $t)->where('request_id', $req->id)->where('status', 'scheduled')->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'request_cancel', ['entity_type' => 'alumni_mentorship_request', 'entity_id' => (int) $req->id, 'before' => ['status' => $req->status]], $request);

        return $this->ok(DB::table('alumni_mentorship_requests')->where('id', $req->id)->first(), 'Request cancelled.');
    }

    public function complete(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $req) {
            return $this->notFound('Request');
        }
        if ($req->status !== 'accepted') {
            return response()->json(['status_code' => 0, 'message' => 'Only an accepted request can be completed.'], 409);
        }
        if (! DB::table('alumni_mentorship_sessions')->where('sub_institute_id', $t)->where('request_id', $req->id)->where('status', 'completed')->exists()) {
            return response()->json(['status_code' => 0, 'message' => 'Hold at least one session before completing the request.'], 409);
        }
        DB::table('alumni_mentorship_requests')->where('id', $req->id)->update(['status' => 'completed', 'updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'request_complete', ['entity_type' => 'alumni_mentorship_request', 'entity_id' => (int) $req->id], $request);

        return $this->ok(DB::table('alumni_mentorship_requests')->where('id', $req->id)->first(), 'Mentorship completed.');
    }

    // ------------------------------------------------------------ sessions, feedback

    public function sessionStore(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'scheduled_at' => 'required|date|after:now', 'duration_minutes' => 'nullable|integer|between:10,480',
            'mode' => 'nullable|in:online,offline', 'location' => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $req) {
            return $this->notFound('Request');
        }
        if ($req->status !== 'accepted') {
            return response()->json(['status_code' => 0, 'message' => 'Sessions can be scheduled once the mentor has accepted.'], 409);
        }
        $sid = DB::table('alumni_mentorship_sessions')->insertGetId([
            'sub_institute_id' => $t, 'request_id' => $req->id, 'mentor_alumni_id' => $req->mentor_alumni_id, 'student_id' => $req->student_id,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime((string) $request->input('scheduled_at'))), 'duration_minutes' => (int) ($request->input('duration_minutes') ?: 45),
            'mode' => $request->input('mode', 'online'), 'location' => $request->input('location'), 'status' => 'scheduled',
            'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'session_create', ['entity_type' => 'alumni_mentorship_session', 'entity_id' => $sid, 'after' => ['request_id' => (int) $req->id]], $request);

        return $this->ok(DB::table('alumni_mentorship_sessions')->where('id', $sid)->first(), 'Session scheduled.', 201);
    }

    public function sessionUpdate(Request $request, $sid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['status' => 'required|in:completed,cancelled,no_show', 'notes' => 'nullable|string|max:3000']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $s = DB::table('alumni_mentorship_sessions')->where('sub_institute_id', $t)->where('id', (int) $sid)->first();
        if (! $s) {
            return $this->notFound('Session');
        }
        if ($s->status !== 'scheduled') {
            return response()->json(['status_code' => 0, 'message' => 'This session is already ' . str_replace('_', ' ', $s->status) . '.'], 409);
        }
        DB::table('alumni_mentorship_sessions')->where('id', $s->id)->update(['status' => $request->input('status'), 'notes' => $request->input('notes'), 'updated_at' => now()]);
        if ($request->input('status') === 'completed') {
            // exactly once per session, however many times this is retried
            $this->engagement->record($t, (int) $s->mentor_alumni_id, 'mentoring', 'Mentoring session completed', $this->userId($request), null, null, 'mentorship_session', (int) $s->id);
        }
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'session_' . $request->input('status'), ['entity_type' => 'alumni_mentorship_session', 'entity_id' => (int) $s->id], $request);

        return $this->ok(DB::table('alumni_mentorship_sessions')->where('id', $s->id)->first(), 'Session updated.');
    }

    public function feedback(Request $request, $sid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['from_role' => 'required|in:student,mentor', 'rating' => 'required|integer|between:1,5', 'comments' => 'nullable|string|max:2000']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $s = DB::table('alumni_mentorship_sessions')->where('sub_institute_id', $t)->where('id', (int) $sid)->first();
        if (! $s) {
            return $this->notFound('Session');
        }

        return $this->saveFeedback($request, $t, $s, (string) $request->input('from_role'));
    }

    public function saveFeedback(Request $request, int $t, object $s, string $role): JsonResponse
    {
        if ($s->status !== 'completed') {
            return response()->json(['status_code' => 0, 'message' => 'Feedback can be given once the session is completed.'], 409);
        }
        if (DB::table('alumni_mentorship_feedback')->where('session_id', $s->id)->where('from_role', $role)->exists()) {
            return response()->json(['status_code' => 0, 'message' => 'Feedback has already been given for this session.'], 409);
        }
        $id = DB::table('alumni_mentorship_feedback')->insertGetId([
            'sub_institute_id' => $t, 'session_id' => $s->id, 'from_role' => $role, 'rating' => (int) $request->input('rating'),
            'comments' => $request->input('comments'), 'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'feedback', ['entity_type' => 'alumni_mentorship_feedback', 'entity_id' => $id, 'after' => ['session_id' => (int) $s->id, 'from_role' => $role]], $request);

        return $this->ok(DB::table('alumni_mentorship_feedback')->where('id', $id)->first(), 'Feedback saved.', 201);
    }

    // -------------------------------------------------------------------- helpers

    private function transition(Request $request, callable $do, string $audit, int $id, string $message): JsonResponse
    {
        $t = $this->tenant($request);
        try {
            $row = $do($t);
        } catch (\DomainException $e) {
            return response()->json(['status_code' => 0, 'message' => $e->getMessage()], str_contains($e->getMessage(), 'not found') ? 404 : 409);
        }
        if ($row->mentor_alumni_id && $audit === 'match') {
            // tell the mentor (transactional). The career area only: the student's name is shared by the coordinator, not by email.
            try {
                app(AlumniMessaging::class)->notify($t, (int) $row->mentor_alumni_id, 'mentorship_request', 'A student has asked for your guidance',
                    "Dear {{name}},\n\nA student at {{institute}} would like your guidance on: {{career_area}}.\nThe alumni office will contact you to confirm. Thank you for supporting our students.",
                    'mentorship_match:' . $row->id . ':' . $row->mentor_alumni_id, true, ['career_area' => (string) $row->career_area]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($row->mentor_alumni_id && in_array($audit, ['match', 'accept'], true)) {
            $this->alumni->logActivity($t, (int) $row->mentor_alumni_id, 'mentorship_' . $audit, $audit === 'match' ? 'Matched with a student for mentoring' : 'Accepted a mentoring request', $this->userId($request));
        }
        AuditTrail::record($t, 'alumni', 'alumni.mentorship', 'request_' . $audit, ['entity_type' => 'alumni_mentorship_request', 'entity_id' => $id, 'after' => ['status' => $row->status, 'mentor_alumni_id' => $row->mentor_alumni_id]], $request);

        return $this->ok($row, $message);
    }

    private function mentorRules(): array
    {
        return [
            'expertise' => 'nullable|string|max:500', 'industry' => 'nullable|string|max:120', 'job_role' => 'nullable|string|max:190',
            'preferred_topics' => 'nullable|string|max:2000', 'available_days' => 'nullable|array', 'available_days.*' => ['string', Rule::in(self::DAYS)],
            'mode' => 'nullable|in:online,offline,both', 'experience_years' => 'nullable|integer|between:0,70',
            'capacity' => 'nullable|integer|between:1,50', 'status' => 'nullable|in:active,paused',
        ];
    }

    private function mentorPayload(Request $request, bool $partial = false): array
    {
        $out = [];
        foreach (['expertise', 'industry', 'job_role', 'preferred_topics', 'mode', 'experience_years', 'capacity', 'status'] as $f) {
            if ($request->has($f) || ! $partial) {
                $val = $request->input($f);
                $out[$f] = ($val === '' || $val === null) ? ($f === 'mode' ? 'both' : ($f === 'capacity' ? 3 : ($f === 'status' ? 'active' : null))) : $val;
            }
        }
        if ($request->has('available_days') || ! $partial) {
            $out['available_days'] = implode(',', array_values(array_intersect(self::DAYS, (array) $request->input('available_days', []))));
        }

        return $out;
    }
}
