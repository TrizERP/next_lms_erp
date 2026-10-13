<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniAuthService;
use App\Services\Alumni\AlumniEventService;
use App\Services\Alumni\AlumniPrivacy;
use App\Services\Alumni\AlumniRuleException;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The alumni portal API: what an alumnus can do for themselves. Prefix `api/alumni-portal`.
 *
 *  PUBLIC (rate-limited, they never reveal whether an email has an account)
 *   POST register | login | refresh | verify-email | resend-verification | forgot-password | reset-password
 *   POST invite/check | invite/accept
 *  SIGNED IN (`alumni.auth`)
 *   GET  me                       own profile and standing
 *   POST me/update                a short list of contact fields
 *   GET|POST me/privacy           own visibility choices and channel consents
 *   POST change-password | logout
 *   GET  events                   upcoming published events and the caller's own registration
 *   POST events/{id}/register | events/{id}/cancel
 *
 * OWNERSHIP. The institute and the alumnus come ONLY from the verified token (request attribute `alumni_auth`). No endpoint
 * accepts an alumni id, an account id or an institute id from the request, so there is nothing to tamper with: every query
 * below is scoped by those two values. Only the fields listed in SELF_EDITABLE can be changed - never name, date of birth,
 * batch, identifiers, status or email.
 *
 * AUTHORISATION. A self-registered alumnus who staff have not yet verified (`pending_verification`) can sign in and see their
 * own standing, but features (events, editing) need status registered/verified/active.
 */
class AlumniPortalApiController extends Controller
{
    /** The only columns an alumnus may change on their own record. */
    private const SELF_EDITABLE = ['mobile', 'alt_mobile', 'current_address', 'permanent_address', 'city', 'state', 'country', 'pincode', 'linkedin_url', 'employment_status'];

    public function __construct(
        private AlumniAuthService $auth,
        private AlumniService $alumni,
        private AlumniPrivacy $privacy,
        private AlumniEventService $events,
    ) {
    }

    // ------------------------------------------------------------------- public

    public function register(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->requireConfigured();
            $v = Validator::make($request->all(), [
                'institute_code' => 'required|string|max:40', 'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:190', 'password' => 'required|string|max:200', 'mobile' => 'nullable|string|max:30',
                'batch' => 'nullable|string|max:40', 'passing_year' => 'nullable|integer|between:1950,2100',
            ]);
            if ($v->fails()) {
                return $this->invalid($v->errors()->messages());
            }
            if ($problems = $this->auth->passwordProblems((string) $request->input('password'), (string) $request->input('email'))) {
                return $this->invalid($problems);
            }
            $this->auth->register((string) $request->input('institute_code'), $request->only(['first_name', 'last_name', 'email', 'password', 'mobile', 'batch', 'passing_year']), $request->ip(), $request);

            // the same answer whether an account was created, the person already exists, or the institute code was wrong
            return $this->accepted('Thank you. If this email can be used to register, we have sent a message to confirm it. If you studied here before and are already in the institute\'s records, ask them to send you an invitation instead.');
        });
    }

    public function login(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $v = Validator::make($request->all(), ['institute_code' => 'required|string|max:40', 'email' => 'required|string|max:190', 'password' => 'required|string|max:200']);
            if ($v->fails()) {
                return $this->invalid($v->errors()->messages());
            }
            $tokens = $this->auth->login((string) $request->input('institute_code'), (string) $request->input('email'), (string) $request->input('password'), $request->ip(), $request->userAgent(), $request);

            return $this->ok($tokens, 'Signed in.');
        });
    }

    public function refresh(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $v = Validator::make($request->all(), ['refresh_token' => 'required|string|max:128']);
            if ($v->fails()) {
                return $this->invalid($v->errors()->messages());
            }

            return $this->ok($this->auth->refresh((string) $request->input('refresh_token'), $request));
        });
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), ['token' => 'required|string|max:128']);
        if ($v->fails()) {
            return $this->invalid($v->errors()->messages());
        }

        return $this->auth->verifyEmail((string) $request->input('token'), $request)
            ? $this->ok(null, 'Your email address is confirmed. You can sign in now.')
            : $this->invalid(['token' => ['This link is invalid or has expired. Ask for a new one.']]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->requireConfigured();
            $v = Validator::make($request->all(), ['institute_code' => 'required|string|max:40', 'email' => 'required|email|max:190']);
            if ($v->fails()) {
                return $this->invalid($v->errors()->messages());
            }
            $this->auth->resendVerification((string) $request->input('institute_code'), (string) $request->input('email'), $request->ip());

            return $this->accepted('If that email has an account that still needs confirming, we have sent a new link.');
        });
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->requireConfigured();
            $v = Validator::make($request->all(), ['institute_code' => 'required|string|max:40', 'email' => 'required|email|max:190']);
            if ($v->fails()) {
                return $this->invalid($v->errors()->messages());
            }
            $this->auth->forgotPassword((string) $request->input('institute_code'), (string) $request->input('email'), $request->ip(), $request);

            return $this->accepted('If that email has an account, we have sent a link to choose a new password.');
        });
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->requireConfigured();
            $v = Validator::make($request->all(), ['token' => 'required|string|max:128', 'password' => 'required|string|max:200']);
            if ($v->fails()) {
                return $this->invalid($v->errors()->messages());
            }
            $problems = $this->auth->resetPassword((string) $request->input('token'), (string) $request->input('password'), $request);

            return $problems ? $this->invalid($problems) : $this->ok(null, 'Your password has been changed. Sign in with the new one.');
        });
    }

    public function inviteCheck(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), ['token' => 'required|string|max:128']);
        if ($v->fails()) {
            return $this->invalid($v->errors()->messages());
        }
        $info = $this->auth->checkInvite((string) $request->input('token'));

        return $info ? $this->ok($info) : $this->invalid(['token' => ['This invitation is invalid or has expired. Ask the institute to send a new one.']]);
    }

    public function inviteAccept(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->requireConfigured();
            $v = Validator::make($request->all(), ['token' => 'required|string|max:128', 'password' => 'required|string|max:200']);
            if ($v->fails()) {
                return $this->invalid($v->errors()->messages());
            }
            $problems = $this->auth->acceptInvite((string) $request->input('token'), (string) $request->input('password'), $request->ip(), $request);

            return $problems ? $this->invalid($problems) : $this->ok(null, 'Your password is set. We have emailed you a link to confirm your email address; after that you can sign in.');
        });
    }

    // ---------------------------------------------------------------- signed in

    public function me(Request $request): JsonResponse
    {
        [$account, $alumnus] = $this->who($request);

        return $this->ok([
            'profile' => [
                'alumni_code' => $alumnus->alumni_code, 'first_name' => $alumnus->first_name, 'middle_name' => $alumnus->middle_name, 'last_name' => $alumnus->last_name, 'full_name' => $alumnus->full_name,
                'batch' => $alumnus->batch, 'passing_year' => $alumnus->passing_year, 'course' => $alumnus->course, 'email' => $account->email, 'mobile' => $alumnus->mobile,
                'alt_mobile' => $alumnus->alt_mobile, 'current_address' => $alumnus->current_address, 'permanent_address' => $alumnus->permanent_address, 'city' => $alumnus->city,
                'state' => $alumnus->state, 'country' => $alumnus->country, 'pincode' => $alumnus->pincode, 'linkedin_url' => $alumnus->linkedin_url,
                'employment_status' => $alumnus->employment_status, 'current_company' => $alumnus->current_company, 'current_job_title' => $alumnus->current_job_title,
                'profile_completion' => (int) $alumnus->profile_completion,
            ],
            'standing' => [
                'status' => $alumnus->status, 'features_enabled' => $this->featuresOn($alumnus),
                'message' => $this->featuresOn($alumnus) ? null : 'Your registration is waiting for the institute to verify it. You can use the portal fully once they have.',
            ],
            'editable_fields' => self::SELF_EDITABLE,
            'institute_code' => $this->auth->instituteCode((int) $account->sub_institute_id),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        [$account, $alumnus] = $this->who($request);
        if (! $this->featuresOn($alumnus)) {
            return $this->denied('Your registration is waiting for the institute to verify it, so your details cannot be edited yet.');
        }
        $rules = array_intersect_key($this->alumni->rules(false), array_flip(self::SELF_EDITABLE));
        $v = Validator::make($request->only(self::SELF_EDITABLE), $rules);
        if ($v->fails()) {
            return $this->invalid($v->errors()->messages());
        }
        $changes = $request->only(self::SELF_EDITABLE);   // anything else in the body is dropped here, not "ignored later"
        if ($changes === []) {
            return $this->invalid(['fields' => ['Nothing to change.']]);
        }
        $t = (int) $account->sub_institute_id;
        $this->alumni->update($t, (int) $alumnus->id, $changes, null);
        AuditTrail::record($t, 'alumni', 'alumni.portal', 'self_update', ['entity_type' => 'alumni', 'entity_id' => (int) $alumnus->id, 'actor_name' => 'Alumnus ' . $alumnus->alumni_code,
            'after' => ['fields' => array_keys($changes)]], $request);

        return $this->me($request);
    }

    public function privacy(Request $request): JsonResponse
    {
        [$account, $alumnus] = $this->who($request);

        return $this->ok($this->privacy->payload((int) $account->sub_institute_id, $alumnus));
    }

    public function savePrivacy(Request $request): JsonResponse
    {
        [$account, $alumnus] = $this->who($request);
        $v = Validator::make($request->all(), ['fields' => 'nullable|array', 'fields.*' => ['string', Rule::in([...AlumniPrivacy::LEVELS, 'default'])], 'consents' => 'nullable|array', 'consents.*' => 'boolean']);
        if ($v->fails()) {
            return $this->invalid($v->errors()->messages());
        }
        $unknown = array_diff(array_keys((array) $request->input('fields', [])), array_keys(AlumniPrivacy::GROUPS));
        $badConsent = array_diff(array_keys((array) $request->input('consents', [])), ['consent_email', 'consent_sms', 'consent_whatsapp', 'consent_push']);
        if ($unknown !== [] || $badConsent !== []) {
            return $this->invalid(['fields' => ['Unknown setting: ' . implode(', ', array_merge($unknown, $badConsent))]]);
        }
        $t = (int) $account->sub_institute_id;
        $before = $this->privacy->payload($t, $alumnus);
        $this->privacy->saveChoices($t, $alumnus, (array) $request->input('fields', []), (array) $request->input('consents', []), null);
        $after = $this->privacy->payload($t, $this->alumni->find($t, (int) $alumnus->id));
        AuditTrail::record($t, 'alumni', 'alumni.privacy', 'update', ['entity_type' => 'alumni_privacy', 'entity_id' => (int) $alumnus->id, 'actor_name' => 'Alumnus ' . $alumnus->alumni_code,
            'before' => ['levels' => array_column($before['fields'], 'level', 'key'), 'consents' => $before['consents']],
            'after' => ['levels' => array_column($after['fields'], 'level', 'key'), 'consents' => $after['consents']]], $request);

        return $this->ok($after, 'Your choices are saved.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        [$account, , $session] = $this->who($request);
        $v = Validator::make($request->all(), ['current_password' => 'required|string|max:200', 'password' => 'required|string|max:200']);
        if ($v->fails()) {
            return $this->invalid($v->errors()->messages());
        }
        $problems = $this->auth->changePassword($account, (int) $session->id, (string) $request->input('current_password'), (string) $request->input('password'), $request);

        return $problems ? $this->invalid($problems) : $this->ok(null, 'Your password has been changed. Other devices have been signed out.');
    }

    public function logout(Request $request): JsonResponse
    {
        [, , $session] = $this->who($request);
        $this->auth->logout((int) $session->id);

        return $this->ok(null, 'Signed out.');
    }

    public function events(Request $request): JsonResponse
    {
        [$account, $alumnus] = $this->who($request);
        if (! $this->featuresOn($alumnus)) {
            return $this->denied('Events open up once the institute has verified your registration.');
        }
        $t = (int) $account->sub_institute_id;
        $mine = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('alumni_id', $alumnus->id)->get()->keyBy('event_id');
        $rows = DB::table('alumni_events')->where('sub_institute_id', $t)->where('status', 'published')->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')->limit(100)->get()->filter(fn ($e) => $this->events->mayAttend($t, $e, (int) $alumnus->id))->map(function ($e) use ($mine) {
                $r = $mine[$e->id] ?? null;

                return ['id' => (int) $e->id, 'name' => $e->name, 'description' => $e->description, 'event_type' => $e->event_type, 'event_date' => $e->event_date, 'start_time' => $e->start_time,
                    'end_time' => $e->end_time, 'venue' => $e->venue, 'mode' => $e->mode, 'online_link' => $r && in_array($r->status, ['registered', 'confirmed'], true) ? $e->online_link : null,
                    'fee_amount' => (float) $e->fee_amount, 'registration_deadline' => $e->registration_deadline,
                    'my_registration' => $r && $r->status !== 'cancelled' ? ['status' => $r->status, 'participants' => (int) $r->participants, 'registration_no' => $r->registration_no] : null];
            })->values();

        return $this->ok($rows);
    }

    public function registerForEvent(Request $request, $id): JsonResponse
    {
        [$account, $alumnus] = $this->who($request);
        if (! $this->featuresOn($alumnus)) {
            return $this->denied('You can register for events once the institute has verified your registration.');
        }
        $v = Validator::make($request->all(), ['participants' => 'nullable|integer|between:1,20', 'guest_details' => 'nullable|string|max:2000']);
        if ($v->fails()) {
            return $this->invalid($v->errors()->messages());
        }

        return $this->guard(function () use ($request, $id, $account, $alumnus) {
            // a portal registration can never claim a payment: the payment argument is always null here
            [$reg, $outcome] = $this->events->register((int) $account->sub_institute_id, (int) $id, (int) $alumnus->id, (int) ($request->input('participants') ?: 1), $request->input('guest_details'), null, null);
            AuditTrail::record((int) $account->sub_institute_id, 'alumni', 'alumni.portal', 'event_register', ['entity_type' => 'alumni_event_registration', 'entity_id' => (int) $reg->id,
                'actor_name' => 'Alumnus ' . $alumnus->alumni_code, 'after' => ['event_id' => (int) $id, 'outcome' => $outcome]], $request);

            return $this->ok(['status' => $outcome, 'registration_no' => $reg->registration_no], $outcome === 'waitlisted' ? 'The event is full. You are on the waiting list.' : 'You are registered.', 201);
        });
    }

    public function cancelEventRegistration(Request $request, $id): JsonResponse
    {
        [$account, $alumnus] = $this->who($request);
        $t = (int) $account->sub_institute_id;
        // ownership: the registration is looked up by BOTH the event and the signed-in alumnus, so someone else's cannot be named
        $reg = DB::table('alumni_event_registrations')->where('sub_institute_id', $t)->where('event_id', (int) $id)->where('alumni_id', $alumnus->id)->first();
        if (! $reg) {
            return response()->json(['status_code' => 0, 'message' => 'You are not registered for this event.'], 404);
        }

        return $this->guard(function () use ($t, $id, $reg, $request, $alumnus) {
            $this->events->cancelRegistration($t, (int) $id, (int) $reg->id);
            AuditTrail::record($t, 'alumni', 'alumni.portal', 'event_cancel', ['entity_type' => 'alumni_event_registration', 'entity_id' => (int) $reg->id, 'actor_name' => 'Alumnus ' . $alumnus->alumni_code], $request);

            return $this->ok(null, 'Your registration is cancelled.');
        });
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{0: object, 1: object, 2: object} account, alumnus, session - from the verified token only */
    private function who(Request $request): array
    {
        $c = $request->attributes->get('alumni_auth');
        abort_unless(is_array($c), 401);   // the alumni.auth middleware always sets it; this is a belt for a route registered without it

        return [$c['account'], $c['alumnus'], $c['session']];
    }

    private function featuresOn(object $alumnus): bool
    {
        return in_array($alumnus->status, AlumniAuthService::FEATURE_STATUSES, true);
    }

    private function requireConfigured(): void
    {
        if (! $this->auth->isConfigured()) {
            throw new AlumniRuleException('Alumni sign-in is not available right now.', 503);
        }
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AlumniRuleException $e) {
            return response()->json(['status_code' => 0, 'message' => $e->getMessage()] + ($e->field && $e->status === 422 ? ['errors' => [$e->field => [$e->getMessage()]]] : []), $e->status);
        }
    }

    private function ok($data, string $message = 'SUCCESS', int $code = 200): JsonResponse
    {
        return response()->json(['status_code' => 1, 'message' => $message, 'data' => $data], $code);
    }

    private function accepted(string $message): JsonResponse
    {
        return response()->json(['status_code' => 1, 'message' => $message, 'data' => null], 202);
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
    }

    private function denied(string $message): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => $message], 403);
    }
}
