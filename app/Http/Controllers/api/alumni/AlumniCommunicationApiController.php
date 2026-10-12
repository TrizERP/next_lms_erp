<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Jobs\SendAlumniCampaignJob;
use App\Services\Alumni\AlumniMessaging;
use App\Services\Alumni\AlumniRuleException;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Alumni communication - staff side (spec sections 15, 16 and 41).
 *
 *   GET  alumni/communication/meta                types, channels and what each can really do today, placeholders
 *   GET  alumni/communication/templates           (+ POST store | {id}/update | {id}/delete)
 *   POST alumni/communication/preview             who a campaign would reach, per channel, after consent
 *   POST alumni/communication/send                create a campaign: send now, schedule, or keep as a draft
 *   GET  alumni/communication/campaigns           campaigns with delivery counts
 *   GET  alumni/communication/campaigns/{id}      one campaign with a per-channel breakdown
 *   POST alumni/communication/campaigns/{id}/send | cancel
 *   GET  alumni/communication/history             every message attempt (?campaign_id= &alumni_id= &channel= &status= &comm_type=)
 *   GET  alumni/{id}/communication                one alumnus's history
 *
 * Statuses are the platform sender's own: sent, skipped (with the reason), failed. SMS, WhatsApp and mobile-app messages
 * are recorded as skipped until a provider is configured, and the screen says so before you send - nothing here pretends
 * to have delivered.
 */
class AlumniCommunicationApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniService $alumni, private AlumniMessaging $messaging)
    {
    }

    public function meta(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);

        return $this->ok([
            'types' => collect(AlumniMessaging::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'channels' => collect($this->messaging->channelStatus($t))->map(fn ($v, $key) => ['key' => $key] + $v)->values(),
            'placeholders' => ['name', 'first_name', 'last_name', 'batch', 'passing_year', 'institute', 'alumni_code'],
        ]);
    }

    // ------------------------------------------------------------------ templates

    public function templates(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $this->messaging->ensureDefaultTemplates($t, $this->userId($request));

        return $this->ok(DB::table('alumni_message_templates')->where('sub_institute_id', $t)->orderBy('comm_type')->orderBy('name')->get());
    }

    public function templateStore(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), $this->templateRules());
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        if (DB::table('alumni_message_templates')->where('sub_institute_id', $t)->where('name', trim((string) $request->input('name')))->exists()) {
            return $this->validationError(['name' => ['A template with this name already exists.']]);
        }
        $id = DB::table('alumni_message_templates')->insertGetId([
            'sub_institute_id' => $t, 'name' => trim((string) $request->input('name')), 'comm_type' => $request->input('comm_type'),
            'subject' => $request->input('subject'), 'body' => $request->input('body'), 'is_system' => 0,
            'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditTrail::record($t, 'alumni', 'alumni.communication', 'template_create', ['entity_type' => 'alumni_template', 'entity_id' => $id], $request);

        return $this->ok(DB::table('alumni_message_templates')->where('id', $id)->first(), 'Template saved.', 201);
    }

    public function templateUpdate(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), $this->templateRules());
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_message_templates')->where('sub_institute_id', $t)->where('id', (int) $id);
        if (! (clone $q)->exists()) {
            return $this->notFound('Template');
        }
        if (DB::table('alumni_message_templates')->where('sub_institute_id', $t)->where('name', trim((string) $request->input('name')))->where('id', '!=', (int) $id)->exists()) {
            return $this->validationError(['name' => ['A template with this name already exists.']]);
        }
        $q->update(['name' => trim((string) $request->input('name')), 'comm_type' => $request->input('comm_type'), 'subject' => $request->input('subject'), 'body' => $request->input('body'), 'updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.communication', 'template_update', ['entity_type' => 'alumni_template', 'entity_id' => (int) $id], $request);

        return $this->ok(DB::table('alumni_message_templates')->where('id', (int) $id)->first(), 'Template saved.');
    }

    public function templateDelete(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $row = DB::table('alumni_message_templates')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $row) {
            return $this->notFound('Template');
        }
        if ($row->is_system) {
            return response()->json(['status_code' => 0, 'message' => 'Built-in templates can be edited but not deleted.'], 409);
        }
        DB::table('alumni_message_templates')->where('id', $row->id)->delete();
        AuditTrail::record($t, 'alumni', 'alumni.communication', 'template_delete', ['entity_type' => 'alumni_template', 'entity_id' => (int) $id], $request);

        return $this->ok(null, 'Template deleted.');
    }

    // ------------------------------------------------------------------ campaigns

    public function preview(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['channels' => 'required|array|min:1', 'channels.*' => ['string', Rule::in(array_keys(AlumniMessaging::CHANNELS))], 'filters' => 'nullable|array']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $filters = $this->messaging->cleanFilters((array) $request->input('filters', []));

        return $this->ok($this->messaging->preview($t, $filters, (array) $request->input('channels')) + ['audience_label' => $this->messaging->describeAudience($filters)]);
    }

    public function send(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'name' => 'nullable|string|max:190', 'comm_type' => ['required', Rule::in(array_keys(AlumniMessaging::TYPES))],
            'subject' => 'required|string|max:255', 'body' => 'required|string|max:5000',
            'channels' => 'required|array|min:1', 'channels.*' => ['string', Rule::in(array_keys(AlumniMessaging::CHANNELS))],
            'filters' => 'nullable|array', 'scheduled_at' => 'nullable|date|after:now', 'save_as_draft' => 'nullable|boolean',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $draft = $request->boolean('save_as_draft');
        try {
            $campaign = $this->messaging->createCampaign($t, [
                'name' => $request->input('name'), 'comm_type' => (string) $request->input('comm_type'), 'subject' => (string) $request->input('subject'),
                'body' => (string) $request->input('body'), 'channels' => (array) $request->input('channels'), 'filters' => (array) $request->input('filters', []),
            ], $this->userId($request), $draft, $request->filled('scheduled_at') ? (string) $request->input('scheduled_at') : null);
        } catch (AlumniRuleException $e) {
            return $this->validationError([($e->field ?? 'filters') => [$e->getMessage()]]);
        }
        $id = (int) $campaign->id;
        $status = $campaign->status;
        AuditTrail::record($t, 'alumni', 'alumni.communication', 'campaign_create', ['entity_type' => 'alumni_campaign', 'entity_id' => $id, 'after' => ['status' => $status, 'channels' => $campaign->channels, 'audience' => $campaign->audience_label]], $request);

        return $this->ok($this->campaign($t, $id), match ($status) { 'draft' => 'Draft saved.', 'scheduled' => 'Campaign scheduled.', default => 'Campaign sent to the queue.' }, 201);
    }

    public function sendDraft(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $c = DB::table('alumni_campaigns')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $c) {
            return $this->notFound('Campaign');
        }
        if (! in_array($c->status, ['draft', 'failed'], true)) {
            return response()->json(['status_code' => 0, 'message' => 'Only a draft or a failed campaign can be sent.'], 409);
        }
        DB::table('alumni_campaigns')->where('id', $c->id)->update(['status' => 'queued', 'updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.communication', 'campaign_send', ['entity_type' => 'alumni_campaign', 'entity_id' => (int) $id], $request);
        SendAlumniCampaignJob::dispatch((int) $c->id);

        return $this->ok($this->campaign($t, (int) $id), 'Campaign sent to the queue.');
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_campaigns')->where('sub_institute_id', $t)->where('id', (int) $id);
        $c = (clone $q)->first();
        if (! $c) {
            return $this->notFound('Campaign');
        }
        if (! in_array($c->status, ['draft', 'scheduled'], true)) {
            return response()->json(['status_code' => 0, 'message' => 'Only a draft or a scheduled campaign can be cancelled; ' . $c->status . ' campaigns have already started.'], 409);
        }
        $q->update(['status' => 'cancelled', 'updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.communication', 'campaign_cancel', ['entity_type' => 'alumni_campaign', 'entity_id' => (int) $id], $request);

        return $this->ok($this->campaign($t, (int) $id), 'Campaign cancelled.');
    }

    public function campaigns(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $per = max(1, min(100, (int) ($request->input('per_page') ?: 25)));
        $page = max(1, (int) ($request->input('page') ?: 1));
        $q = DB::table('alumni_campaigns')->where('sub_institute_id', $t);
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage($page, $per)->get(['id', 'name', 'comm_type', 'subject', 'channels', 'audience_label', 'status', 'scheduled_at', 'recipient_count', 'sent_count', 'skipped_count', 'failed_count', 'no_consent_count', 'created_at', 'finished_at']);

        return $this->ok(['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function campaignShow(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $c = $this->campaign($t, (int) $id);
        if (! $c) {
            return $this->notFound('Campaign');
        }
        $breakdown = DB::table('alumni_message_log')->where('sub_institute_id', $t)->where('campaign_id', (int) $id)
            ->groupBy('channel', 'status')->selectRaw('channel, status, COUNT(*) as total')->get();
        $reasons = DB::table('alumni_message_log')->where('sub_institute_id', $t)->where('campaign_id', (int) $id)->whereNotNull('reason')
            ->groupBy('channel', 'reason')->selectRaw('channel, reason, COUNT(*) as total')->get();

        return $this->ok(['campaign' => $c, 'breakdown' => $breakdown, 'reasons' => $reasons, 'channel_status' => $this->messaging->channelStatus($t)]);
    }

    // -------------------------------------------------------------------- history

    public function history(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['queued', 'sent', 'skipped', 'failed'])], 'channel' => ['nullable', Rule::in(array_keys(AlumniMessaging::CHANNELS))],
            'campaign_id' => 'nullable|integer|min:1', 'alumni_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);

        return $this->ok($this->historyRows($t, $request->only(['status', 'channel', 'campaign_id', 'alumni_id', 'comm_type']), (int) ($request->input('page') ?: 1), (int) ($request->input('per_page') ?: 25)));
    }

    public function alumnusHistory(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }

        return $this->ok($this->historyRows($t, ['alumni_id' => (int) $id], 1, 50));
    }

    // -------------------------------------------------------------------- helpers

    private function historyRows(int $t, array $f, int $page, int $per): array
    {
        $q = DB::table('alumni_message_log as m')->join('alumni as a', function ($j) {
            $j->on('a.id', '=', 'm.alumni_id')->on('a.sub_institute_id', '=', 'm.sub_institute_id');
        })->leftJoin('alumni_campaigns as c', 'c.id', '=', 'm.campaign_id')->where('m.sub_institute_id', $t);
        foreach (['status', 'channel', 'comm_type'] as $col) {
            if (! empty($f[$col])) {
                $q->where('m.' . $col, $f[$col]);
            }
        }
        foreach (['campaign_id', 'alumni_id'] as $col) {
            if (! empty($f[$col])) {
                $q->where('m.' . $col, (int) $f[$col]);
            }
        }
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('m.id')->forPage($page, $per)->get([
            'm.id', 'm.alumni_id', 'a.full_name', 'a.alumni_code', 'm.campaign_id', 'c.name as campaign_name', 'c.audience_label as recipient_group',
            'm.comm_type', 'm.channel', 'm.subject', 'm.status', 'm.reason', 'm.read_at', 'm.response', 'm.is_transactional', 'm.created_at',
        ]);

        return ['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]];
    }

    private function campaign(int $t, int $id): ?object
    {
        return DB::table('alumni_campaigns')->where('sub_institute_id', $t)->where('id', $id)->first();
    }

    private function templateRules(): array
    {
        return ['name' => 'required|string|max:150', 'comm_type' => ['required', Rule::in(array_keys(AlumniMessaging::TYPES))], 'subject' => 'required|string|max:255', 'body' => 'required|string|max:5000'];
    }
}
