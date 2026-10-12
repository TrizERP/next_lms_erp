<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniEngagementService;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Job and internship opportunities - staff side (spec section 24).
 *
 * The kind of opportunity travels as `opportunity_type`, never `type`: the `api.session` middleware sets type=API on
 * every request, which silently replaces any field of that name.
 *
 *   GET  alumni/opportunities                   ?status=&type=&q=
 *   POST alumni/opportunities/store
 *   POST alumni/opportunities/{id}/update | review | close | delete
 *
 * APPROVAL. With the tenant setting `opportunity_approval_required` (default ON), an opportunity entered on behalf
 * of an alumnus starts as pending_approval and is only visible to students after a reviewer approves it; one the
 * institute posts itself (no alumni_id) is published straight away. With the setting OFF everything publishes.
 *
 * EXPIRY IS DERIVED. A published opportunity whose last_date has passed is reported as `expired` (effective_status)
 * and is never shown to students; nothing has to run for that to be true, so a stopped scheduler cannot leave a dead
 * listing on the board. (alumni:expire-opportunities only tidies the stored status.)
 *
 * LINKS. The application link must be http(s); javascript: and data: URLs are refused so a posting cannot run a script
 * on a student's screen.
 */
class AlumniOpportunityApiController extends Controller
{
    use AlumniApiSupport;

    public const TYPES = ['job', 'internship', 'apprenticeship', 'project', 'freelance'];

    public function __construct(private AlumniService $alumni, private AlumniEngagementService $engagement)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pending_approval', 'published', 'rejected', 'closed', 'expired'])],
            'opportunity_type' => ['nullable', Rule::in(self::TYPES)], 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $today = now()->toDateString();
        $q = DB::table('alumni_opportunities as o')
            ->leftJoin('alumni as a', function ($j) {
                $j->on('a.id', '=', 'o.alumni_id')->on('a.sub_institute_id', '=', 'o.sub_institute_id');
            })
            ->where('o.sub_institute_id', $t);
        if ($request->filled('opportunity_type')) {
            $q->where('o.type', $request->input('opportunity_type'));
        }
        if ($request->filled('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';
            $q->where(fn ($w) => $w->where('o.title', 'like', $like)->orWhere('o.company', 'like', $like)->orWhere('o.skills', 'like', $like));
        }
        if ($request->filled('status')) {
            $s = $request->input('status');
            if ($s === 'expired') {
                // stored by the nightly task, or already past its date and not yet swept
                $q->where(fn ($w) => $w->where('o.status', 'expired')
                    ->orWhere(fn ($x) => $x->where('o.status', 'published')->whereNotNull('o.last_date')->where('o.last_date', '<', $today)));
            } elseif ($s === 'published') {
                $q->where('o.status', 'published')->where(fn ($w) => $w->whereNull('o.last_date')->orWhere('o.last_date', '>=', $today));
            } else {
                $q->where('o.status', $s);
            }
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByRaw("CASE o.status WHEN 'pending_approval' THEN 0 ELSE 1 END")->orderByDesc('o.id')->forPage($page, $per)
            ->get(['o.*', 'a.full_name as alumni_name'])
            ->map(function ($r) use ($today) {
                $r->effective_status = ($r->status === 'published' && $r->last_date && $r->last_date < $today) ? 'expired' : $r->status;

                return $r;
            });

        return $this->ok(['rows' => $rows, 'types' => self::TYPES, 'approval_required' => $this->approvalRequired($t),
            'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), $this->rules(true));
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        if ($request->filled('alumni_id') && ! $this->alumni->find($t, (int) $request->input('alumni_id'))) {
            return $this->validationError(['alumni_id' => ['That alumnus was not found.']]);
        }
        $onBehalfOfAlumnus = $request->filled('alumni_id');
        $publish = ! ($this->approvalRequired($t) && $onBehalfOfAlumnus);

        $id = DB::table('alumni_opportunities')->insertGetId($this->payload($request) + [
            'sub_institute_id' => $t, 'alumni_id' => $onBehalfOfAlumnus ? (int) $request->input('alumni_id') : null,
            'posted_by' => $this->userId($request), 'status' => $publish ? 'published' : 'pending_approval',
            'published_at' => $publish ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($publish) {
            $this->creditAlumnus($t, $id, $this->userId($request));
        }
        AuditTrail::record($t, 'alumni', 'alumni.opportunities', 'create', ['entity_type' => 'alumni_opportunity', 'entity_id' => $id, 'after' => ['type' => $request->input('opportunity_type'), 'status' => $publish ? 'published' : 'pending_approval']], $request);

        return $this->ok(DB::table('alumni_opportunities')->where('id', $id)->first(), $publish ? 'Opportunity published.' : 'Opportunity saved. It will be shown to students once approved.', 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), $this->rules(false));
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $row = DB::table('alumni_opportunities')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $row) {
            return $this->notFound('Opportunity');
        }
        if ($row->status === 'closed') {
            return response()->json(['status_code' => 0, 'message' => 'A closed opportunity cannot be edited.'], 409);
        }
        $data = $this->payload($request, true);
        // an edit to a PUBLISHED listing goes back through approval when approval is required and an alumnus owns it
        $upd = $data + ['updated_at' => now()];
        if ($row->status === 'published' && $row->alumni_id && $this->approvalRequired($t)) {
            $upd += ['status' => 'pending_approval', 'review_remarks' => 'Edited after publication - needs review again.'];
        }
        DB::table('alumni_opportunities')->where('id', $row->id)->update($upd);
        AuditTrail::record($t, 'alumni', 'alumni.opportunities', 'update', ['entity_type' => 'alumni_opportunity', 'entity_id' => (int) $id, 'after' => $data], $request);

        return $this->ok(DB::table('alumni_opportunities')->where('id', $row->id)->first(), 'Opportunity updated.');
    }

    public function review(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['decision' => 'required|in:approve,reject', 'remarks' => 'nullable|string|max:500']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        if ($request->input('decision') === 'reject' && trim((string) $request->input('remarks')) === '') {
            return $this->validationError(['remarks' => ['Give a reason when rejecting an opportunity.']]);
        }
        $t = $this->tenant($request);
        $row = DB::table('alumni_opportunities')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $row) {
            return $this->notFound('Opportunity');
        }
        if ($row->status !== 'pending_approval') {
            return response()->json(['status_code' => 0, 'message' => 'Only an opportunity that is waiting for approval can be reviewed.'], 409);
        }
        $approve = $request->input('decision') === 'approve';
        DB::table('alumni_opportunities')->where('id', $row->id)->update([
            'status' => $approve ? 'published' : 'rejected', 'reviewed_by' => $this->userId($request), 'reviewed_at' => now(),
            'review_remarks' => $request->input('remarks'), 'published_at' => $approve ? now() : null, 'updated_at' => now(),
        ]);
        if ($approve) {
            $this->creditAlumnus($t, (int) $row->id, $this->userId($request));
        }
        AuditTrail::record($t, 'alumni', 'alumni.opportunities', $approve ? 'approve' : 'reject', ['entity_type' => 'alumni_opportunity', 'entity_id' => (int) $id], $request);

        return $this->ok(DB::table('alumni_opportunities')->where('id', $row->id)->first(), $approve ? 'Opportunity approved and published.' : 'Opportunity rejected.');
    }

    public function close(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_opportunities')->where('sub_institute_id', $t)->where('id', (int) $id);
        if (! (clone $q)->exists()) {
            return $this->notFound('Opportunity');
        }
        $q->update(['status' => 'closed', 'updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.opportunities', 'close', ['entity_type' => 'alumni_opportunity', 'entity_id' => (int) $id], $request);

        return $this->ok(DB::table('alumni_opportunities')->where('id', (int) $id)->first(), 'Opportunity closed.');
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_opportunities')->where('sub_institute_id', $t)->where('id', (int) $id);
        $row = (clone $q)->first();
        if (! $row) {
            return $this->notFound('Opportunity');
        }
        $q->delete();
        AuditTrail::record($t, 'alumni', 'alumni.opportunities', 'delete', ['entity_type' => 'alumni_opportunity', 'entity_id' => (int) $id, 'before' => ['title' => $row->title, 'company' => $row->company]], $request);

        return $this->ok(null, 'Opportunity deleted.');
    }

    // -------------------------------------------------------------------- helpers

    public function approvalRequired(int $t): bool
    {
        return (bool) $this->alumni->setting($t, 'opportunity_approval_required', true);
    }

    /** Engagement credit for the alumnus who offered it, once. */
    private function creditAlumnus(int $t, int $opportunityId, ?int $userId): void
    {
        $o = DB::table('alumni_opportunities')->where('id', $opportunityId)->first();
        if ($o && $o->alumni_id) {
            $type = $o->type === 'internship' ? 'internship_support' : 'job_placement_support';
            $this->engagement->record($t, (int) $o->alumni_id, $type, ucfirst($o->type) . ' opportunity shared: ' . $o->title, $userId, null, null, 'opportunity', $opportunityId);
        }
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';

        return [
            'opportunity_type' => [$creating ? 'required' : 'sometimes', Rule::in(self::TYPES)], 'company' => $req . '|string|max:190', 'title' => $req . '|string|max:190',
            'alumni_id' => 'nullable|integer|min:1', 'location' => 'nullable|string|max:160', 'experience' => 'nullable|string|max:80',
            'qualification' => 'nullable|string|max:190', 'skills' => 'nullable|string|max:500', 'salary' => 'nullable|string|max:80',
            'last_date' => 'nullable|date' . ($creating ? '|after_or_equal:today' : ''), 'contact_person' => 'nullable|string|max:190',
            'description' => 'nullable|string|max:5000',
            'application_url' => ['nullable', 'string', 'max:500', 'regex:#^https?://[^\s]+$#i'],
        ];
    }

    private function payload(Request $request, bool $partial = false): array
    {
        $out = [];
        foreach (['type', 'company', 'title', 'location', 'experience', 'qualification', 'skills', 'salary', 'last_date', 'application_url', 'contact_person', 'description'] as $f) {
            $in = $f === 'type' ? 'opportunity_type' : $f;   // `type` itself is overwritten by the session middleware (type=API)
            if ($request->has($in) || ! $partial) {
                $val = $request->input($in);
                $out[$f] = is_string($val) && trim($val) === '' ? null : $val;
            }
        }

        return $out;
    }
}
