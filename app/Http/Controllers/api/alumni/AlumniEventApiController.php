<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniEventService;
use App\Services\Alumni\AlumniMessaging;
use App\Services\Alumni\AlumniRuleException;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Alumni events - staff side (spec sections 17-20). The rules (capacity, waitlist, deadline, audience, idempotent
 * attendance) are in AlumniEventService; this class validates input, scopes by institute and answers in the usual envelope.
 *
 *   GET  alumni/events                              ?status= &event_type= &q= &from= &to=
 *   POST alumni/events/store | {id}/update
 *   GET  alumni/events/{id}                         event, live seat counts, files
 *   POST alumni/events/{id}/publish | cancel | complete | invite
 *   GET  alumni/events/{id}/registrations           ?status=
 *   POST alumni/events/{id}/registrations/store     register an alumnus (on their behalf until alumni can sign in)
 *   POST alumni/events/{id}/registrations/{rid}/confirm | cancel | payment
 *   POST alumni/events/{id}/attendance              method: manual | qr | mobile | registration_no | alumni_id
 *   POST alumni/events/{id}/feedback | GET alumni/events/{id}/report
 *   POST alumni/events/{id}/files/upload | {fid}/download | {fid}/delete
 *   GET  alumni/{id}/events                         one alumnus's registrations and attendance
 *
 * The kind of event travels as `event_type`, never `type`: the `api.session` middleware sets type=API on every request.
 * Event files use the same private-disk, content-sniffed, random-name handling as alumni documents.
 */
class AlumniEventApiController extends Controller
{
    use AlumniApiSupport;

    private const MAX_KB = 5120;

    private const FILE_MIMES = 'pdf,jpg,jpeg,png,doc,docx';

    public function __construct(private AlumniService $alumni, private AlumniEventService $events, private AlumniMessaging $messaging)
    {
    }

    // ---------------------------------------------------------------------- events

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])], 'event_type' => ['nullable', Rule::in(array_keys(AlumniEventService::TYPES))],
            'from' => 'nullable|date', 'to' => 'nullable|date', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_events')->where('sub_institute_id', $t);
        foreach (['status', 'event_type'] as $c) {
            if ($request->filled($c)) {
                $q->where($c, $request->input($c));
            }
        }
        if ($request->filled('from')) {
            $q->where('event_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $q->where('event_date', '<=', $request->input('to'));
        }
        if ($request->filled('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('venue', 'like', $like));
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByDesc('event_date')->orderByDesc('id')->forPage($page, $per)->get();
        $seats = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->whereIn('event_id', $rows->pluck('id'))->whereIn('status', ['registered', 'confirmed', 'attended'])
            ->groupBy('event_id')->selectRaw('event_id, SUM(participants) as used')->pluck('used', 'event_id');
        $rows = $rows->map(function ($e) use ($seats) {
            $e->seats_used = (int) ($seats[$e->id] ?? 0);

            return $e;
        });

        return $this->ok(['rows' => $rows, 'types' => collect(AlumniEventService::TYPES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(), 'methods' => AlumniEventService::METHODS,
            'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $e = $this->events->find($t, (int) $id);
        if (! $e) {
            return $this->notFound('Event');
        }
        $e->seats_used = $this->events->usedSeats($t, (int) $id);

        return $this->ok(['event' => $e, 'audience' => $e->audience ? json_decode((string) $e->audience, true) : [],
            'files' => DB::table('alumni_event_files')->where('sub_institute_id', $t)->where('event_id', (int) $id)->orderByDesc('id')->get(['id', 'original_name', 'mime', 'size_bytes', 'created_at'])]);
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
        $id = DB::table('alumni_events')->insertGetId($this->payload($request) + ['sub_institute_id' => $t, 'status' => 'draft', 'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.events', 'create', ['entity_type' => 'alumni_event', 'entity_id' => $id, 'after' => ['name' => $request->input('name'), 'event_type' => $request->input('event_type')]], $request);

        return $this->ok($this->events->find($t, $id), 'Event saved as a draft.', 201);
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
        $e = $this->events->find($t, (int) $id);
        if (! $e) {
            return $this->notFound('Event');
        }
        if (in_array($e->status, ['cancelled', 'completed'], true)) {
            return response()->json(['status_code' => 0, 'message' => 'A ' . $e->status . ' event cannot be edited.'], 409);
        }
        $data = $this->payload($request, true);
        if (isset($data['capacity']) && $data['capacity'] !== null && (int) $data['capacity'] < $this->events->usedSeats($t, (int) $id)) {
            return $this->validationError(['capacity' => ['Capacity cannot be lower than the ' . $this->events->usedSeats($t, (int) $id) . ' seats already taken.']]);
        }
        DB::table('alumni_events')->where('id', $e->id)->update($data + ['updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.events', 'update', ['entity_type' => 'alumni_event', 'entity_id' => (int) $id, 'after' => $data], $request);
        // a larger capacity may let waitlisted people in
        $this->events->promoteWaitlist($t, (int) $id);

        return $this->ok($this->events->find($t, (int) $id), 'Event updated.');
    }

    public function publish(Request $request, $id): JsonResponse
    {
        return $this->rule($request, fn ($t) => $this->events->publish($t, (int) $id), 'publish', (int) $id, 'Event published.');
    }

    public function complete(Request $request, $id): JsonResponse
    {
        return $this->rule($request, function ($t) use ($id) {
            $r = $this->events->complete($t, (int) $id);

            return $r['event'];
        }, 'complete', (int) $id, 'Event completed. Anyone who held a seat and never checked in is now a no-show.');
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $v = Validator::make($request->all(), ['reason' => 'nullable|string|max:500']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->rule($request, fn ($t) => $this->events->cancelEvent($t, (int) $id, $request->input('reason')), 'cancel', (int) $id, 'Event cancelled. Everyone who was due to attend has been told.');
    }

    /** Invite the event's audience by creating a campaign with the event details filled in. */
    public function invite(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['channels' => 'required|array|min:1', 'channels.*' => ['string', Rule::in(array_keys(AlumniMessaging::CHANNELS))], 'filters' => 'nullable|array']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $e = $this->events->find($t, (int) $id);
        if (! $e) {
            return $this->notFound('Event');
        }
        if ($e->status !== 'published') {
            return response()->json(['status_code' => 0, 'message' => 'Publish the event before inviting anyone.'], 409);
        }
        [$subject, $body] = $this->messaging->templateFor($t, 'event_invitation');
        $when = date('j M Y', strtotime((string) $e->event_date)) . ($e->start_time ? ', ' . substr((string) $e->start_time, 0, 5) : '');
        $fill = fn (string $text) => strtr($text, ['{{event_name}}' => $e->name, '{{event_date}}' => $when, '{{event_venue}}' => $e->mode === 'online' ? 'online' : (string) $e->venue,
            '{{event_link}}' => $e->online_link ? 'Join online: ' . $e->online_link : '']);
        $filters = $request->filled('filters') ? (array) $request->input('filters') : ($e->audience ? (array) json_decode((string) $e->audience, true) : []);
        try {
            $campaign = $this->messaging->createCampaign($t, ['name' => 'Invitation: ' . $e->name, 'comm_type' => 'event_invitation', 'subject' => $fill($subject), 'body' => $fill($body),
                'channels' => (array) $request->input('channels'), 'filters' => $filters], $this->userId($request));
        } catch (AlumniRuleException $x) {
            return $this->validationError([($x->field ?? 'filters') => [$x->getMessage()]]);
        }
        AuditTrail::record($t, 'alumni', 'alumni.events', 'invite', ['entity_type' => 'alumni_event', 'entity_id' => (int) $id, 'after' => ['campaign_id' => (int) $campaign->id]], $request);

        return $this->ok(DB::table('alumni_campaigns')->where('id', $campaign->id)->first(), 'Invitations sent to the queue.', 201);
    }

    // ----------------------------------------------------------------- registrations

    public function registrations(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->events->find($t, (int) $id)) {
            return $this->notFound('Event');
        }
        $q = DB::table('alumni_event_registrations as r')->join('alumni as a', function ($j) {
            $j->on('a.id', '=', 'r.alumni_id')->on('a.sub_institute_id', '=', 'r.sub_institute_id');
        })->where('r.sub_institute_id', $t)->where('r.event_id', (int) $id);
        if ($request->filled('status')) {
            $q->where('r.status', $request->input('status'));
        }
        // the QR token is shown to staff (they print or send tickets); it is never part of any alumni-wide listing
        $rows = $q->orderBy('r.id')->get(['r.id', 'r.alumni_id', 'a.full_name', 'a.alumni_code', 'a.batch', 'r.registration_no', 'r.participants', 'r.guest_details',
            'r.payment_status', 'r.status', 'r.qr_token', 'r.registered_at', 'r.attended_at', 'r.attendance_method']);

        return $this->ok($rows);
    }

    public function register(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['alumni_id' => 'required|integer|min:1', 'participants' => 'nullable|integer|between:1,20', 'guest_details' => 'nullable|string|max:2000', 'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'waived'])]]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        try {
            [$reg, $outcome] = $this->events->register($t, (int) $id, (int) $request->input('alumni_id'), (int) ($request->input('participants') ?: 1), $request->input('guest_details'), $request->input('payment_status'), $this->userId($request));
        } catch (AlumniRuleException $e) {
            return $this->ruleResponse($e);
        }
        AuditTrail::record($t, 'alumni', 'alumni.events', 'register', ['entity_type' => 'alumni_event_registration', 'entity_id' => (int) $reg->id, 'after' => ['event_id' => (int) $id, 'alumni_id' => (int) $reg->alumni_id, 'status' => $outcome]], $request);

        return $this->ok($reg, match ($outcome) { 'waitlisted' => 'The event is full, so this alumnus is on the waiting list.', 'confirmed' => 'Registered and confirmed.', default => 'Registered. Waiting for confirmation.' }, 201);
    }

    public function confirm(Request $request, $id, $rid): JsonResponse
    {
        return $this->regAction($request, fn ($t) => $this->events->confirm($t, (int) $id, (int) $rid), 'confirm', (int) $rid, 'Registration confirmed.');
    }

    public function cancelRegistration(Request $request, $id, $rid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        try {
            $promoted = $this->events->cancelRegistration($t, (int) $id, (int) $rid);
        } catch (AlumniRuleException $e) {
            return $this->ruleResponse($e);
        }
        AuditTrail::record($t, 'alumni', 'alumni.events', 'registration_cancel', ['entity_type' => 'alumni_event_registration', 'entity_id' => (int) $rid, 'after' => ['promoted' => count($promoted)]], $request);

        return $this->ok(['promoted' => count($promoted)], 'Registration cancelled.' . (count($promoted) ? ' ' . count($promoted) . ' alumnus(i) moved off the waiting list.' : ''));
    }

    public function payment(Request $request, $id, $rid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;   // before validating: an unauthorised caller learns nothing about the payload rules
        }
        $v = Validator::make($request->all(), ['payment_status' => ['required', Rule::in(['pending', 'paid', 'waived'])]]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->regAction($request, fn ($t) => $this->events->setPayment($t, (int) $id, (int) $rid, (string) $request->input('payment_status')), 'payment', (int) $rid, 'Payment status updated.');
    }

    // -------------------------------------------------------- attendance, feedback, report

    public function attendance(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['method' => ['required', Rule::in(AlumniEventService::METHODS)], 'value' => 'nullable|string|max:120', 'registration_id' => 'nullable|integer|min:1']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        try {
            [$reg, $already] = $this->events->checkIn($t, (int) $id, (string) $request->input('method'), $request->input('value'), $request->filled('registration_id') ? (int) $request->input('registration_id') : null, $this->userId($request));
        } catch (AlumniRuleException $e) {
            return $this->ruleResponse($e);
        }
        if (! $already) {
            AuditTrail::record($t, 'alumni', 'alumni.events', 'check_in', ['entity_type' => 'alumni_event_registration', 'entity_id' => (int) $reg->id, 'after' => ['method' => $request->input('method')]], $request);
        }
        $alumnus = $this->alumni->find($t, (int) $reg->alumni_id);

        return $this->ok(['registration' => $reg, 'alumnus' => $alumnus ? ['id' => (int) $alumnus->id, 'full_name' => $alumnus->full_name, 'alumni_code' => $alumnus->alumni_code] : null, 'already_checked_in' => $already],
            $already ? 'Already checked in.' : 'Checked in.');
    }

    public function feedback(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['alumni_id' => 'required|integer|min:1', 'rating' => 'required|integer|between:1,5', 'comments' => 'nullable|string|max:2000']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        if (! $this->events->find($t, (int) $id)) {
            return $this->notFound('Event');
        }
        try {
            $row = $this->events->feedback($t, (int) $id, (int) $request->input('alumni_id'), (int) $request->input('rating'), $request->input('comments'), $this->userId($request));
        } catch (AlumniRuleException $e) {
            return $this->ruleResponse($e);
        }

        return $this->ok($row, 'Feedback saved.', 201);
    }

    public function report(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        try {
            $report = $this->events->report($t, (int) $id);
        } catch (AlumniRuleException $e) {
            return $this->ruleResponse($e);
        }
        $report['feedback'] = DB::table('alumni_event_feedback as f')->join('alumni as a', function ($j) {
            $j->on('a.id', '=', 'f.alumni_id')->on('a.sub_institute_id', '=', 'f.sub_institute_id');
        })->where('f.sub_institute_id', $t)->where('f.event_id', (int) $id)->orderByDesc('f.id')->get(['a.full_name', 'a.alumni_code', 'f.rating', 'f.comments', 'f.created_at']);

        return $this->ok($report);
    }

    public function alumnusEvents(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }

        return $this->ok(DB::table('alumni_event_registrations as r')->join('alumni_events as e', 'e.id', '=', 'r.event_id')->where('r.sub_institute_id', $t)->where('r.alumni_id', (int) $id)
            ->orderByDesc('e.event_date')->get(['e.id as event_id', 'e.name', 'e.event_type', 'e.event_date', 'r.registration_no', 'r.status', 'r.participants', 'r.attended_at']));
    }

    // ------------------------------------------------------------------------ files

    public function fileUpload(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['file' => ['required', 'file', 'max:' . self::MAX_KB, 'mimes:' . self::FILE_MIMES]],
            ['file.max' => 'The file must be 5 MB or smaller.', 'file.mimes' => 'Upload a PDF, JPG, PNG or Word document.']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        if (! $this->events->find($t, (int) $id)) {
            return $this->notFound('Event');
        }
        $file = $request->file('file');
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs("alumni-events/{$t}/{$id}", bin2hex(random_bytes(16)) . '.' . $ext, 'local');
        $name = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'file', 0, 200);
        $fid = DB::table('alumni_event_files')->insertGetId(['sub_institute_id' => $t, 'event_id' => (int) $id, 'original_name' => $name, 'file_path' => $path, 'mime' => $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(), 'uploaded_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.events', 'file_upload', ['entity_type' => 'alumni_event_file', 'entity_id' => $fid, 'after' => ['event_id' => (int) $id]], $request);

        return $this->ok(DB::table('alumni_event_files')->where('id', $fid)->first(['id', 'original_name', 'mime', 'size_bytes', 'created_at']), 'File uploaded.', 201);
    }

    public function fileDownload(Request $request, $id, $fid)
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $f = DB::table('alumni_event_files')->where('sub_institute_id', $t)->where('event_id', (int) $id)->where('id', (int) $fid)->first();
        if (! $f || ! Storage::disk('local')->exists($f->file_path)) {
            return $this->notFound('File');
        }

        return Storage::disk('local')->download($f->file_path, $f->original_name, ['Content-Type' => $f->mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function fileDelete(Request $request, $id, $fid): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $f = DB::table('alumni_event_files')->where('sub_institute_id', $t)->where('event_id', (int) $id)->where('id', (int) $fid)->first();
        if (! $f) {
            return $this->notFound('File');
        }
        DB::table('alumni_event_files')->where('id', $f->id)->delete();
        Storage::disk('local')->delete($f->file_path);
        AuditTrail::record($t, 'alumni', 'alumni.events', 'file_delete', ['entity_type' => 'alumni_event_file', 'entity_id' => (int) $fid], $request);

        return $this->ok(null, 'File removed.');
    }

    // ---------------------------------------------------------------------- helpers

    /** Run a lifecycle action, turning a rule refusal into the right response and auditing a success. */
    private function rule(Request $request, callable $do, string $audit, int $id, string $message): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        try {
            $row = $do($t);
        } catch (AlumniRuleException $e) {
            return $this->ruleResponse($e);
        }
        AuditTrail::record($t, 'alumni', 'alumni.events', $audit, ['entity_type' => 'alumni_event', 'entity_id' => $id, 'after' => ['status' => $row->status ?? null]], $request);

        return $this->ok($row, $message);
    }

    private function regAction(Request $request, callable $do, string $audit, int $rid, string $message): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        try {
            $row = $do($t);
        } catch (AlumniRuleException $e) {
            return $this->ruleResponse($e);
        }
        AuditTrail::record($t, 'alumni', 'alumni.events', 'registration_' . $audit, ['entity_type' => 'alumni_event_registration', 'entity_id' => $rid, 'after' => ['status' => $row->status ?? null]], $request);

        return $this->ok($row, $message);
    }

    private function ruleResponse(AlumniRuleException $e): JsonResponse
    {
        if ($e->status === 422 && $e->field) {
            return $this->validationError([$e->field => [$e->getMessage()]]);
        }

        return response()->json(['status_code' => 0, 'message' => $e->getMessage()] + ($e->field ? ['errors' => [$e->field => [$e->getMessage()]]] : []), $e->status);
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';

        return [
            'name' => $req . '|string|max:190', 'description' => 'nullable|string|max:5000', 'event_type' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(AlumniEventService::TYPES))],
            'event_date' => $req . '|date' . ($creating ? '|after_or_equal:today' : ''), 'start_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'], 'end_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/', 'after:start_time'],
            'venue' => 'nullable|string|max:255', 'mode' => ['nullable', Rule::in(['offline', 'online', 'hybrid'])],
            'online_link' => ['nullable', 'string', 'max:500', 'regex:#^https?://[^\s]+$#i'], 'capacity' => 'nullable|integer|min:1|max:100000',
            'registration_required' => 'nullable|boolean', 'auto_confirm' => 'nullable|boolean', 'registration_deadline' => 'nullable|date',
            'organizer' => 'nullable|string|max:190', 'contact_person' => 'nullable|string|max:190', 'contact_phone' => 'nullable|string|max:40', 'contact_email' => 'nullable|email|max:190',
            'fee_amount' => 'nullable|numeric|min:0|max:10000000', 'filters' => 'nullable|array',
        ];
    }

    private function payload(Request $request, bool $partial = false): array
    {
        $out = [];
        foreach (['name', 'description', 'event_type', 'event_date', 'start_time', 'end_time', 'venue', 'mode', 'online_link', 'capacity', 'organizer', 'contact_person', 'contact_phone', 'contact_email', 'registration_deadline'] as $f) {
            if ($request->has($f) || ! $partial) {
                $val = $request->input($f);
                $out[$f] = (is_string($val) && trim($val) === '') ? null : $val;
            }
        }
        if (($request->has('mode') || ! $partial) && empty($out['mode'])) {
            $out['mode'] = 'offline';
        }
        foreach (['registration_required' => 1, 'auto_confirm' => 1] as $f => $default) {
            if ($request->has($f) || ! $partial) {
                $out[$f] = $request->has($f) ? ($request->boolean($f) ? 1 : 0) : $default;
            }
        }
        if ($request->has('fee_amount') || ! $partial) {
            $out['fee_amount'] = (float) ($request->input('fee_amount') ?: 0);
        }
        if ($request->has('filters') || ! $partial) {
            $filters = $this->messaging->cleanFilters((array) $request->input('filters', []));
            $out['audience'] = $filters === [] ? null : json_encode($filters);
            $out['audience_label'] = $filters === [] ? null : $this->messaging->describeAudience($filters);
        }

        return $out;
    }
}
