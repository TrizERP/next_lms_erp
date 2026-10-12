<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniMentorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Student-facing alumni features: the opportunity board and mentorship requests.
 *
 *   GET  alumni/student/opportunities | opportunities/{id}
 *   GET  alumni/student/mentorship/mentors
 *   GET  alumni/student/mentorship/requests
 *   POST alumni/student/mentorship/requests/store
 *   POST alumni/student/mentorship/requests/{id}/cancel
 *   POST alumni/student/mentorship/sessions/{sid}/feedback
 *
 * These routes sit behind `api.session` but NOT `staff.only`. Every action is bound to the student in the verified
 * JWT: their id is read from the session, never from the request, so a student can read and change only their own
 * requests and feedback. Alumni contact details are never returned - a student sees a mentor's name, batch, and the
 * profile the mentor wrote for mentoring, and reaches them through the coordinator.
 */
class AlumniStudentApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniMentorshipApiController $staff, private AlumniMentorshipService $mentorship)
    {
    }

    public function opportunities(Request $request): JsonResponse
    {
        if ($denied = $this->studentGate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['opportunity_type' => ['nullable', Rule::in(AlumniOpportunityApiController::TYPES)], 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:50']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $q = $this->board($request);
        if ($request->filled('opportunity_type')) {
            $q->where('o.type', $request->input('opportunity_type'));
        }
        if ($request->filled('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';
            $q->where(fn ($w) => $w->where('o.title', 'like', $like)->orWhere('o.company', 'like', $like)->orWhere('o.skills', 'like', $like));
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 20);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByRaw('CASE WHEN o.last_date IS NULL THEN 1 ELSE 0 END')->orderBy('o.last_date')->orderByDesc('o.id')->forPage($page, $per)->get($this->boardColumns());

        return $this->ok(['rows' => $rows, 'types' => AlumniOpportunityApiController::TYPES,
            'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function opportunityShow(Request $request, $id): JsonResponse
    {
        if ($denied = $this->studentGate($request)) {
            return $denied;
        }
        $row = $this->board($request)->where('o.id', (int) $id)->first($this->boardColumns());

        return $row ? $this->ok($row) : $this->notFound('Opportunity');
    }

    public function mentors(Request $request): JsonResponse
    {
        if ($denied = $this->studentGate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $rows = DB::table('alumni_mentor_profiles as m')
            ->join('alumni as a', function ($j) {
                $j->on('a.id', '=', 'm.alumni_id')->on('a.sub_institute_id', '=', 'm.sub_institute_id');
            })
            ->where('m.sub_institute_id', $t)->where('m.status', 'active')->whereIn('a.status', ['verified', 'active'])->orderBy('a.full_name')
            ->get(['m.alumni_id', 'm.expertise', 'm.industry', 'm.job_role', 'm.preferred_topics', 'm.available_days', 'm.mode', 'm.experience_years', 'm.capacity', 'a.full_name', 'a.batch'])
            ->map(function ($m) use ($t) {
                $m->open_slots = max(0, (int) $m->capacity - $this->mentorship->load($t, (int) $m->alumni_id));
                unset($m->capacity);

                return $m;
            })->filter(fn ($m) => $m->open_slots > 0)->values();

        return $this->ok($rows);
    }

    public function myRequests(Request $request): JsonResponse
    {
        if ($denied = $this->studentGate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $sid = (int) $request->session()->get('user_id');
        $reqs = DB::table('alumni_mentorship_requests as r')
            ->leftJoin('alumni as a', function ($j) {
                $j->on('a.id', '=', 'r.mentor_alumni_id')->on('a.sub_institute_id', '=', 'r.sub_institute_id');
            })
            ->where('r.sub_institute_id', $t)->where('r.student_id', $sid)->orderByDesc('r.id')
            ->get(['r.id', 'r.career_area', 'r.guidance_needed', 'r.preferred_date', 'r.status', 'r.created_at', 'a.full_name as mentor_name']);
        $sessions = DB::table('alumni_mentorship_sessions')->where('sub_institute_id', $t)->where('student_id', $sid)->whereIn('request_id', $reqs->pluck('id'))->orderBy('scheduled_at')
            ->get(['id', 'request_id', 'scheduled_at', 'duration_minutes', 'mode', 'location', 'status']);
        $fb = DB::table('alumni_mentorship_feedback')->where('sub_institute_id', $t)->where('from_role', 'student')->whereIn('session_id', $sessions->pluck('id'))->pluck('rating', 'session_id');

        return $this->ok($reqs->map(function ($r) use ($sessions, $fb) {
            $r->sessions = $sessions->where('request_id', $r->id)->values()->map(function ($s) use ($fb) {
                $s->my_rating = $fb[$s->id] ?? null;

                return $s;
            });

            return $r;
        }));
    }

    public function requestStore(Request $request): JsonResponse
    {
        if ($denied = $this->studentGate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'career_area' => 'required|string|max:190', 'guidance_needed' => 'nullable|string|max:3000',
            'preferred_mentor_alumni_id' => 'nullable|integer|min:1', 'preferred_date' => 'nullable|date|after_or_equal:today',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->staff->createRequest($request, $this->tenant($request), (int) $request->session()->get('user_id'), 'student');
    }

    public function requestCancel(Request $request, $id): JsonResponse
    {
        if ($denied = $this->studentGate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $req = DB::table('alumni_mentorship_requests')->where('sub_institute_id', $t)->where('student_id', (int) $request->session()->get('user_id'))->where('id', (int) $id)->first();
        if (! $req) {
            return $this->notFound('Request');
        }
        if (in_array($req->status, ['completed', 'cancelled'], true)) {
            return response()->json(['status_code' => 0, 'message' => 'This request is already ' . $req->status . '.'], 409);
        }

        return $this->staff->cancelRequest($request, $t, $req);
    }

    public function sessionFeedback(Request $request, $sid): JsonResponse
    {
        if ($denied = $this->studentGate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['rating' => 'required|integer|between:1,5', 'comments' => 'nullable|string|max:2000']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $s = DB::table('alumni_mentorship_sessions')->where('sub_institute_id', $t)->where('student_id', (int) $request->session()->get('user_id'))->where('id', (int) $sid)->first();

        return $s ? $this->staff->saveFeedback($request, $t, $s, 'student') : $this->notFound('Session');
    }

    // -------------------------------------------------------------------- helpers

    /** Published, unexpired opportunities of THIS institute. */
    private function board(Request $request)
    {
        $today = now()->toDateString();

        return DB::table('alumni_opportunities as o')
            ->leftJoin('alumni as a', function ($j) {
                $j->on('a.id', '=', 'o.alumni_id')->on('a.sub_institute_id', '=', 'o.sub_institute_id');
            })
            ->where('o.sub_institute_id', $this->tenant($request))->where('o.status', 'published')
            ->where(fn ($w) => $w->whereNull('o.last_date')->orWhere('o.last_date', '>=', $today));
    }

    /** Public-facing columns only: no posted_by, no reviewer, no alumni contact data. */
    private function boardColumns(): array
    {
        return ['o.id', 'o.type', 'o.company', 'o.title', 'o.location', 'o.experience', 'o.qualification', 'o.skills', 'o.salary', 'o.last_date',
            'o.application_url', 'o.contact_person', 'o.description', 'o.published_at', 'a.full_name as shared_by', 'a.batch as shared_by_batch'];
    }
}
