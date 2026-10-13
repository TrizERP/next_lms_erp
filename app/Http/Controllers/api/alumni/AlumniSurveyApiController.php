<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniMessaging;
use App\Services\Alumni\AlumniRuleException;
use App\Services\Alumni\AlumniService;
use App\Services\Alumni\AlumniSurveyService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Alumni surveys - staff side (spec section 39). The rules are in AlumniSurveyService.
 *
 *   GET  alumni/surveys                        ?status= &survey_type= &q=
 *   POST alumni/surveys/store | {id}/update    (questions may be sent with either; they are locked once responses exist)
 *   GET  alumni/surveys/{id}                   survey, questions, response count
 *   POST alumni/surveys/{id}/publish | close | delete | send
 *   GET  alumni/surveys/{id}/results           per-question results, response rate
 *   GET  alumni/surveys/{id}/participation     who has and has not responded (never links to answers)
 *   POST alumni/surveys/{id}/respond           record a response on an alumnus's behalf (phone / paper)
 *
 * The kind of survey travels as `survey_type`, never `type` (the api.session middleware overwrites that). Alumni answering
 * for themselves needs alumni sign-in, which is a pending product decision; the same service will serve that screen.
 */
class AlumniSurveyApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniService $alumni, private AlumniSurveyService $surveys, private AlumniMessaging $messaging)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['status' => ['nullable', Rule::in(['draft', 'published', 'closed'])], 'survey_type' => ['nullable', Rule::in(array_keys(AlumniSurveyService::TYPES))], 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_surveys')->where('sub_institute_id', $t);
        foreach (['status', 'survey_type'] as $c) {
            if ($request->filled($c)) {
                $q->where($c, $request->input($c));
            }
        }
        if ($request->filled('q')) {
            $q->where('title', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%');
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByDesc('id')->forPage($page, $per)->get();
        $counts = DB::table('alumni_survey_submissions')->where('sub_institute_id', $t)->whereIn('survey_id', $rows->pluck('id'))->groupBy('survey_id')->selectRaw('survey_id, COUNT(*) as n')->pluck('n', 'survey_id');
        $qs = DB::table('alumni_survey_questions')->where('sub_institute_id', $t)->whereIn('survey_id', $rows->pluck('id'))->groupBy('survey_id')->selectRaw('survey_id, COUNT(*) as n')->pluck('n', 'survey_id');
        $rows = $rows->map(function ($s) use ($counts, $qs) {
            $s->response_count = (int) ($counts[$s->id] ?? 0);
            $s->question_count = (int) ($qs[$s->id] ?? 0);
            $s->is_open = $this->surveys->isOpen($s);

            return $s;
        });

        return $this->ok(['rows' => $rows, 'types' => collect(AlumniSurveyService::TYPES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
            'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $s = $this->surveys->find($t, (int) $id);
        if (! $s) {
            return $this->notFound('Survey');
        }
        $s->is_open = $this->surveys->isOpen($s);

        return $this->ok(['survey' => $s, 'audience' => $s->audience ? json_decode((string) $s->audience, true) : [], 'questions' => $this->surveys->questions($t, (int) $id),
            'response_count' => $this->surveys->responseCount($t, (int) $id), 'locked' => $this->surveys->responseCount($t, (int) $id) > 0]);
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
        try {
            $questions = $request->has('questions') ? $this->surveys->normaliseQuestions((array) $request->input('questions')) : [];
        } catch (AlumniRuleException $e) {
            return $this->validationError([($e->field ?? 'questions') => [$e->getMessage()]]);
        }
        $id = DB::transaction(function () use ($request, $t, $questions) {
            $id = DB::table('alumni_surveys')->insertGetId($this->payload($request) + ['sub_institute_id' => $t, 'status' => 'draft', 'created_by' => $this->userId($request), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($questions as $q) {
                DB::table('alumni_survey_questions')->insert($q + ['sub_institute_id' => $t, 'survey_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
        AuditTrail::record($t, 'alumni', 'alumni.surveys', 'create', ['entity_type' => 'alumni_survey', 'entity_id' => $id, 'after' => ['title' => $request->input('title'), 'questions' => count($questions)]], $request);

        return $this->ok($this->surveys->find($t, $id), 'Survey saved as a draft.', 201);
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
        $s = $this->surveys->find($t, (int) $id);
        if (! $s) {
            return $this->notFound('Survey');
        }
        if ($s->status === 'closed') {
            return response()->json(['status_code' => 0, 'message' => 'A closed survey cannot be edited.'], 409);
        }
        try {
            if ($request->has('questions')) {
                $this->surveys->saveQuestions($t, (int) $id, (array) $request->input('questions'));
            }
        } catch (AlumniRuleException $e) {
            return $e->status === 422 ? $this->validationError([($e->field ?? 'questions') => [$e->getMessage()]]) : response()->json(['status_code' => 0, 'message' => $e->getMessage()], $e->status);
        }
        $data = $this->payload($request, true);
        if ($this->surveys->responseCount($t, (int) $id) > 0 && array_key_exists('anonymous', $data) && (int) $data['anonymous'] !== (int) $s->anonymous) {
            return response()->json(['status_code' => 0, 'message' => 'Anonymity cannot change once responses exist.'], 409);
        }
        DB::table('alumni_surveys')->where('id', $s->id)->update($data + ['updated_at' => now()]);
        AuditTrail::record($t, 'alumni', 'alumni.surveys', 'update', ['entity_type' => 'alumni_survey', 'entity_id' => (int) $id, 'after' => array_diff_key($data, ['audience' => 1])], $request);

        return $this->ok($this->surveys->find($t, (int) $id), 'Survey updated.');
    }

    public function publish(Request $request, $id): JsonResponse
    {
        return $this->lifecycle($request, fn ($t) => $this->surveys->publish($t, (int) $id), 'publish', (int) $id, 'Survey published.');
    }

    public function close(Request $request, $id): JsonResponse
    {
        return $this->lifecycle($request, fn ($t) => $this->surveys->close($t, (int) $id), 'close', (int) $id, 'Survey closed.');
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $s = $this->surveys->find($t, (int) $id);
        if (! $s) {
            return $this->notFound('Survey');
        }
        if ($this->surveys->responseCount($t, (int) $id) > 0) {
            return response()->json(['status_code' => 0, 'message' => 'This survey has responses. Close it instead of deleting it, so the results are kept.'], 409);
        }
        DB::transaction(function () use ($t, $s) {
            DB::table('alumni_survey_questions')->where('sub_institute_id', $t)->where('survey_id', $s->id)->delete();
            DB::table('alumni_surveys')->where('id', $s->id)->delete();
        });
        AuditTrail::record($t, 'alumni', 'alumni.surveys', 'delete', ['entity_type' => 'alumni_survey', 'entity_id' => (int) $id, 'before' => ['title' => $s->title]], $request);

        return $this->ok(null, 'Survey deleted.');
    }

    public function results(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        try {
            return $this->ok($this->surveys->results($this->tenant($request), (int) $id));
        } catch (AlumniRuleException $e) {
            return response()->json(['status_code' => 0, 'message' => $e->getMessage()], $e->status);
        }
    }

    public function participation(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $s = $this->surveys->find($t, (int) $id);
        if (! $s) {
            return $this->notFound('Survey');
        }
        $filters = $s->audience ? (array) json_decode((string) $s->audience, true) : [];
        $done = DB::table('alumni_survey_participants')->where('sub_institute_id', $t)->where('survey_id', (int) $id)->pluck('alumni_id');
        $pending = $this->messaging->audience($t, $filters)->whereNotIn('id', $done->all() ?: [0]);

        return $this->ok(['responded' => $done->count(), 'pending' => (int) (clone $pending)->count(),
            'pending_alumni' => $pending->orderBy('full_name')->limit(200)->get(['id', 'full_name', 'alumni_code', 'batch'])]);
    }

    /** Record a response on someone's behalf. */
    public function respond(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['alumni_id' => 'required|integer|min:1', 'answers' => 'required|array']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        try {
            $sid = $this->surveys->submit($t, (int) $id, (int) $request->input('alumni_id'), (array) $request->input('answers'), $this->userId($request));
        } catch (AlumniRuleException $e) {
            return $e->status === 422 && $e->field ? $this->validationError([$e->field => [$e->getMessage()]])
                : response()->json(['status_code' => 0, 'message' => $e->getMessage()] + ($e->field ? ['errors' => [$e->field => [$e->getMessage()]]] : []), $e->status);
        }
        AuditTrail::record($t, 'alumni', 'alumni.surveys', 'respond', ['entity_type' => 'alumni_survey_submission', 'entity_id' => $sid, 'after' => ['survey_id' => (int) $id]], $request);

        return $this->ok(['submission_id' => $sid], 'Response recorded.', 201);
    }

    /** Ask the survey's audience to respond, by creating a campaign. */
    public function send(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['channels' => 'required|array|min:1', 'channels.*' => ['string', Rule::in(array_keys(AlumniMessaging::CHANNELS))]]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $s = $this->surveys->find($t, (int) $id);
        if (! $s) {
            return $this->notFound('Survey');
        }
        if (! $this->surveys->isOpen($s)) {
            return response()->json(['status_code' => 0, 'message' => 'Only an open survey can be sent to alumni.'], 409);
        }
        $portal = trim((string) $this->alumni->setting($t, 'alumni_portal_url', ''));
        $link = $portal !== '' ? 'Respond here: ' . rtrim($portal, '/') . '/alumni/survey/' . $s->id : 'Please contact the alumni office to give your feedback.';
        $closing = $s->closes_at ? 'The survey closes on ' . date('j M Y', strtotime((string) $s->closes_at)) . '.' : '';
        try {
            $campaign = $this->messaging->createCampaign($t, ['name' => 'Survey: ' . $s->title, 'comm_type' => 'survey', 'subject' => 'Your feedback matters: ' . $s->title,
                'body' => "Dear {{name}},\n\nWe would value your feedback in our survey \"{$s->title}\".\n{$closing}\n{$link}\n\nThank you from {{institute}}.",
                'channels' => (array) $request->input('channels'), 'filters' => $s->audience ? (array) json_decode((string) $s->audience, true) : []], $this->userId($request));
        } catch (AlumniRuleException $e) {
            return $this->validationError([($e->field ?? 'filters') => [$e->getMessage()]]);
        }
        AuditTrail::record($t, 'alumni', 'alumni.surveys', 'send', ['entity_type' => 'alumni_survey', 'entity_id' => (int) $id, 'after' => ['campaign_id' => (int) $campaign->id]], $request);

        return $this->ok(DB::table('alumni_campaigns')->where('id', $campaign->id)->first(), $portal === '' ? 'Sent to the queue. No alumni portal address is configured, so the message asks them to contact the office.' : 'Sent to the queue.', 201);
    }

    // -------------------------------------------------------------------- helpers

    private function lifecycle(Request $request, callable $do, string $audit, int $id, string $message): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        try {
            $row = $do($t);
        } catch (AlumniRuleException $e) {
            return $e->status === 422 && $e->field ? $this->validationError([$e->field => [$e->getMessage()]]) : response()->json(['status_code' => 0, 'message' => $e->getMessage()], $e->status);
        }
        AuditTrail::record($t, 'alumni', 'alumni.surveys', $audit, ['entity_type' => 'alumni_survey', 'entity_id' => $id], $request);

        return $this->ok($row, $message);
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes|required';

        return [
            'title' => $req . '|string|max:190', 'survey_type' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(AlumniSurveyService::TYPES))],
            'description' => 'nullable|string|max:3000', 'anonymous' => 'nullable|boolean', 'filters' => 'nullable|array',
            'opens_at' => 'nullable|date', 'closes_at' => 'nullable|date|after:opens_at', 'questions' => 'nullable|array',
        ];
    }

    private function payload(Request $request, bool $partial = false): array
    {
        $out = [];
        foreach (['title', 'survey_type', 'description', 'opens_at', 'closes_at'] as $f) {
            if ($request->has($f) || ! $partial) {
                $val = $request->input($f);
                $out[$f] = (is_string($val) && trim($val) === '') ? null : $val;
            }
        }
        if ($request->has('anonymous') || ! $partial) {
            $out['anonymous'] = $request->boolean('anonymous') ? 1 : 0;
        }
        if ($request->has('filters') || ! $partial) {
            $filters = $this->messaging->cleanFilters((array) $request->input('filters', []));
            $out['audience'] = $filters === [] ? null : json_encode($filters);
            $out['audience_label'] = $filters === [] ? null : $this->messaging->describeAudience($filters);
        }

        return $out;
    }
}
