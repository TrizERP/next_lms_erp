<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniDonationService;
use App\Services\Alumni\AlumniRuleException;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Alumni donations (spec sections 21-24), offline-recorded - there is NO payment gateway. Every endpoint is behind the
 * `finance` gate (Admin-type profiles and Accountant), which is stricter than the `manage` gate used elsewhere.
 *
 *   GET  alumni/donations/campaigns | POST .../campaigns/store | .../campaigns/{id}/update
 *   GET  alumni/donations                       ?status= &campaign_id= &alumni_id= &q= &from= &to=
 *   GET  alumni/donations/summary               ?campaign_id= &from= &to=
 *   POST alumni/donations/store                 pledge, or money received (payment_mode + received_on)
 *   GET  alumni/donations/{id}                  donation, refunds
 *   POST alumni/donations/{id}/received | verify | reject | cancel | refund
 *   GET  alumni/donations/{id}/receipt          only once verified
 *   GET  alumni/{id}/donations                  one alumnus's gifts
 *
 * Verify, reject, cancel and refund each record who did it (audit trail + the row). Whether the person who recorded a
 * donation may also verify it is an OPEN DECISION and is not enforced here - see the plan, decision 7.
 */
class AlumniDonationApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniService $alumni, private AlumniDonationService $donations)
    {
    }

    // ------------------------------------------------------------------- campaigns

    public function campaigns(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $t = $this->tenant($request);
        $rows = DB::table('alumni_donation_campaigns')->where('sub_institute_id', $t)->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")->orderByDesc('id')->get()
            ->map(function ($c) use ($t) {
                $c->raised = $this->donations->campaignRaised($t, (int) $c->id);
                $c->percent = $c->goal_amount > 0 ? round(min(100, $c->raised / (float) $c->goal_amount * 100), 1) : null;

                return $c;
            });

        return $this->ok($rows);
    }

    public function saveCampaign(Request $request, $id = null): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $t = $this->tenant($request);
        $creating = $id === null;
        $v = Validator::make($request->all(), [
            'name' => ($creating ? 'required' : 'sometimes|required') . '|string|max:190', 'purpose' => 'nullable|string|max:190', 'description' => 'nullable|string|max:3000',
            'goal_amount' => 'nullable|numeric|min:0|max:' . AlumniDonationService::MAX_AMOUNT * 10, 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'status' => ['nullable', Rule::in(['draft', 'active', 'closed'])],
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $data = array_intersect_key($request->only(['name', 'purpose', 'description', 'goal_amount', 'starts_on', 'ends_on', 'status']), array_flip(['name', 'purpose', 'description', 'goal_amount', 'starts_on', 'ends_on', 'status']));
        if ($creating) {
            $newId = (int) DB::table('alumni_donation_campaigns')->insertGetId($data + ['sub_institute_id' => $t, 'status' => $data['status'] ?? 'draft', 'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now()]);
            AuditTrail::record($t, 'alumni', 'alumni.donations', 'campaign_create', ['entity_type' => 'alumni_donation_campaign', 'entity_id' => $newId, 'after' => ['name' => $data['name']]], $request);

            return $this->ok($this->donations->findCampaign($t, $newId), 'Campaign created.', 201);
        }
        $existing = $this->donations->findCampaign($t, (int) $id);
        if (! $existing) {
            return $this->notFound('Campaign');
        }
        DB::table('alumni_donation_campaigns')->where('sub_institute_id', $t)->where('id', (int) $id)->update($data + ['updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.donations', 'campaign_update', ['entity_type' => 'alumni_donation_campaign', 'entity_id' => (int) $id, 'before' => ['status' => $existing->status], 'after' => $data], $request);

        return $this->ok($this->donations->findCampaign($t, (int) $id), 'Campaign saved.');
    }

    // -------------------------------------------------------------------- donations

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pledged', 'pending_verification', 'verified', 'rejected', 'cancelled', 'partially_refunded', 'refunded'])],
            'campaign_id' => 'nullable|integer|min:1', 'alumni_id' => 'nullable|integer|min:1', 'from' => 'nullable|date', 'to' => 'nullable|date', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_donations as d')->join('alumni as a', function ($j) {
            $j->on('a.id', '=', 'd.alumni_id')->on('a.sub_institute_id', '=', 'd.sub_institute_id');
        })->leftJoin('alumni_donation_campaigns as c', 'c.id', '=', 'd.campaign_id')->where('d.sub_institute_id', $t);
        foreach (['status', 'campaign_id', 'alumni_id'] as $col) {
            if ($request->filled($col)) {
                $q->where('d.' . $col, $request->input($col));
            }
        }
        if ($request->filled('from')) {
            $q->whereRaw('COALESCE(d.received_on, d.pledged_on) >= ?', [$request->input('from')]);
        }
        if ($request->filled('to')) {
            $q->whereRaw('COALESCE(d.received_on, d.pledged_on) <= ?', [$request->input('to')]);
        }
        if ($request->filled('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';
            $q->where(fn ($w) => $w->where('a.full_name', 'like', $like)->orWhere('a.alumni_code', 'like', $like)->orWhere('d.receipt_no', 'like', $like)->orWhere('d.reference_no', 'like', $like));
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByDesc('d.id')->forPage($page, $per)->get(['d.*', 'a.full_name', 'a.alumni_code', 'c.name as campaign_name']);

        return $this->ok(['rows' => $rows, 'modes' => collect(AlumniDonationService::MODES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
            'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $t = $this->tenant($request);
        $d = $this->donations->find($t, (int) $id);
        if (! $d) {
            return $this->notFound('Donation');
        }
        $alumnus = DB::table('alumni')->where('sub_institute_id', $t)->where('id', $d->alumni_id)->first(['id', 'full_name', 'alumni_code']);

        return $this->ok(['donation' => $d, 'alumnus' => $alumnus,
            'refunds' => DB::table('alumni_donation_refunds')->where('sub_institute_id', $t)->where('donation_id', (int) $id)->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'alumni_id' => 'required|integer|min:1', 'campaign_id' => 'nullable|integer|min:1', 'amount' => 'required|numeric|gt:0|max:' . AlumniDonationService::MAX_AMOUNT,
            'purpose' => 'nullable|string|max:190', 'payment_mode' => ['nullable', Rule::in(array_keys(AlumniDonationService::MODES))], 'reference_no' => 'nullable|string|max:120',
            'received_on' => 'nullable|date|before_or_equal:today', 'pledged_on' => 'nullable|date', 'anonymous' => 'nullable|boolean', 'notes' => 'nullable|string|max:2000',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);

        return $this->guard(function () use ($request, $t) {
            $id = $this->donations->record($t, $request->all(), $this->userId($request));
            AuditTrail::record($t, 'alumni', 'alumni.donations', 'record', ['entity_type' => 'alumni_donation', 'entity_id' => $id, 'after' => ['amount' => (float) $request->input('amount'), 'alumni_id' => (int) $request->input('alumni_id')]], $request);

            return $this->ok($this->donations->find($t, $id), 'Donation recorded.', 201);
        });
    }

    public function update(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|gt:0|max:' . AlumniDonationService::MAX_AMOUNT, 'campaign_id' => 'nullable|integer|min:1', 'purpose' => 'nullable|string|max:190',
            'payment_mode' => ['nullable', Rule::in(array_keys(AlumniDonationService::MODES))], 'reference_no' => 'nullable|string|max:120', 'received_on' => 'nullable|date|before_or_equal:today',
            'anonymous' => 'nullable|boolean', 'notes' => 'nullable|string|max:2000',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->transition($request, $id, 'update', fn ($t) => $this->donations->update($t, (int) $id, $request->all()), 'Donation updated.', ['fields' => array_keys($request->except(['type']))]);
    }

    public function received(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['payment_mode' => ['required', Rule::in(array_keys(AlumniDonationService::MODES))], 'reference_no' => 'nullable|string|max:120', 'received_on' => 'required|date|before_or_equal:today']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->transition($request, $id, 'mark_received', fn ($t) => $this->donations->markReceived($t, (int) $id, (string) $request->input('payment_mode'), $request->input('reference_no'), (string) $request->input('received_on'), $this->userId($request)), 'Marked as received. It still needs to be verified.');
    }

    public function verify(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }

        return $this->transition($request, $id, 'verify', fn ($t) => $this->donations->verify($t, (int) $id, $this->userId($request)), 'Donation verified and receipt issued.');
    }

    public function reject(Request $request, $id): JsonResponse
    {
        return $this->withReason($request, $id, 'reject', 'Donation rejected.');
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        return $this->withReason($request, $id, 'cancel', 'Donation cancelled.');
    }

    public function refund(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['amount' => 'required|numeric|gt:0|max:' . AlumniDonationService::MAX_AMOUNT, 'reason' => 'required|string|min:3|max:500', 'refunded_on' => 'required|date|before_or_equal:today',
            'payment_mode' => ['nullable', Rule::in(array_keys(AlumniDonationService::MODES))], 'reference_no' => 'nullable|string|max:120']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->transition($request, $id, 'refund', fn ($t) => $this->donations->refund($t, (int) $id, (float) $request->input('amount'), (string) $request->input('reason'), (string) $request->input('refunded_on'), $request->input('payment_mode'), $request->input('reference_no'), $this->userId($request)), 'Refund recorded.',
            ['amount' => (float) $request->input('amount'), 'reason' => (string) $request->input('reason')]);
    }

    public function receipt(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $t = $this->tenant($request);

        return $this->guard(function () use ($t, $id, $request) {
            $data = $this->donations->receipt($t, (int) $id);
            AuditTrail::record($t, 'alumni', 'alumni.donations', 'receipt_view', ['entity_type' => 'alumni_donation', 'entity_id' => (int) $id, 'after' => ['receipt_no' => $data['receipt_no']]], $request);

            return $this->ok($data);
        });
    }

    public function summary(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['campaign_id' => 'nullable|integer|min:1', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->ok($this->donations->summary($this->tenant($request), $request->only(['campaign_id', 'from', 'to'])));
    }

    /**
     * Donor lookup for the "record a donation" form. The normal alumni search is behind the `manage` gate, which an
     * Accountant does not have, so finance gets its own: id, name, code and batch ONLY - never contact details, status
     * history or anything else from the profile. Needs two characters so it cannot be used to list everyone.
     */
    public function donors(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $text = trim((string) $request->input('q'));
        if (mb_strlen($text) < 2) {
            return $this->validationError(['q' => ['Type at least two characters.']]);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $text) . '%';

        return $this->ok(DB::table('alumni')->where('sub_institute_id', $this->tenant($request))->where('status', '!=', 'deceased')
            ->where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('alumni_code', 'like', $like))
            ->orderBy('full_name')->limit(8)->get(['id', 'full_name', 'alumni_code', 'batch']));
    }

    public function alumnusDonations(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }

        return $this->ok(DB::table('alumni_donations as d')->leftJoin('alumni_donation_campaigns as c', 'c.id', '=', 'd.campaign_id')->where('d.sub_institute_id', $t)->where('d.alumni_id', (int) $id)
            ->orderByDesc('d.id')->get(['d.id', 'd.amount', 'd.status', 'd.receipt_no', 'd.received_on', 'd.pledged_on', 'd.refunded_amount', 'c.name as campaign_name']));
    }

    // ---------------------------------------------------------------------- helpers

    private function withReason(Request $request, $id, string $action, string $message): JsonResponse
    {
        if ($denied = $this->gate($request, 'finance')) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['reason' => 'required|string|min:3|max:500']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $reason = (string) $request->input('reason');

        return $this->transition($request, $id, $action, fn ($t) => $this->donations->{$action}($t, (int) $id, $reason, $this->userId($request)), $message, ['reason' => $reason]);
    }

    /** Runs a state change, audits it, and turns a refused rule into the right HTTP answer. */
    private function transition(Request $request, $id, string $action, callable $do, string $message, array $detail = []): JsonResponse
    {
        $t = $this->tenant($request);

        return $this->guard(function () use ($request, $t, $id, $action, $do, $message, $detail) {
            $before = $this->donations->find($t, (int) $id);
            $row = $do($t);
            AuditTrail::record($t, 'alumni', 'alumni.donations', $action, ['entity_type' => 'alumni_donation', 'entity_id' => (int) $id,
                'before' => ['status' => $before->status ?? null], 'after' => ['status' => $row->status, 'receipt_no' => $row->receipt_no] + $detail], $request);

            return $this->ok($row, $message);
        });
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AlumniRuleException $e) {
            if ($e->status === 404) {
                return $this->notFound('Donation');
            }

            return $e->field
                ? response()->json(['status_code' => 0, 'message' => $e->getMessage(), 'errors' => [$e->field => [$e->getMessage()]]], $e->status)
                : response()->json(['status_code' => 0, 'message' => $e->getMessage()], $e->status);
        }
    }
}
