<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniAuthService;
use App\Services\Alumni\AlumniRuleException;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Staff control of alumni portal accounts (the staff side of section 8 in the plan). Behind `api.session` + `staff.only` and the
 * `manage` role gate like the rest of the alumni staff API.
 *
 *   POST alumni/{id}/invite                     invite one alumnus; returns the link ONCE and what happened to the email
 *   GET  alumni/{id}/account                    that alumnus's portal account (never a password, hash or token)
 *   GET  alumni/accounts                        ?status= &q= - accounts of this institute
 *   POST alumni/accounts/{id}/disable           {reason?}  signs the person out everywhere at once
 *   POST alumni/accounts/{id}/enable
 *   POST alumni/accounts/{id}/revoke-sessions   signs the person out everywhere without disabling them
 *   POST alumni/accounts/{id}/resend-verification
 *
 * Staff verify WHO a self-registered person is through the existing alumni verification (status pending_verification ->
 * verified); portal sign-in itself is the person's own. Nothing here can read or set a password.
 */
class AlumniAccountApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniAuthService $auth, private AlumniService $alumni)
    {
    }

    public function invite(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }

        return $this->guard(function () use ($request, $id) {
            $r = $this->auth->invite($this->tenant($request), (int) $id, $this->userId($request), $request->ip(), $request);

            return $this->ok($r, $r['email_status'] === 'sent' ? 'Invitation emailed. The link below is shown only now.' : 'The invitation email could not be delivered, so give this link to the alumnus yourself. It is shown only now.', 201);
        });
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->alumni->find($t, (int) $id)) {
            return $this->notFound();
        }

        return $this->ok($this->present(DB::table('alumni_accounts')->where('sub_institute_id', $t)->where('alumni_id', (int) $id)->first()));
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['status' => 'nullable|in:invited,unverified,active,disabled', 'q' => 'nullable|string|max:120', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = DB::table('alumni_accounts as c')->join('alumni as a', fn ($j) => $j->on('a.id', '=', 'c.alumni_id')->on('a.sub_institute_id', '=', 'c.sub_institute_id'))->where('c.sub_institute_id', $t)
            ->when($request->input('status'), fn ($x, $s) => $x->where('c.status', $s));
        if ($request->filled('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';
            $q->where(fn ($w) => $w->where('a.full_name', 'like', $like)->orWhere('c.email', 'like', $like)->orWhere('a.alumni_code', 'like', $like));
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByDesc('c.id')->forPage($page, $per)->get(['c.*', 'a.full_name', 'a.alumni_code', 'a.status as alumni_status'])->map(fn ($r) => $this->present($r) + ['full_name' => $r->full_name, 'alumni_code' => $r->alumni_code, 'alumni_status' => $r->alumni_status]);

        return $this->ok(['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function disable(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['reason' => 'nullable|string|max:255']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->guard(fn () => $this->ok($this->present($this->auth->setDisabled($this->tenant($request), (int) $id, true, $request->input('reason'), $this->userId($request), $request)), 'Portal account disabled and signed out everywhere.'));
    }

    public function enable(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }

        return $this->guard(fn () => $this->ok($this->present($this->auth->setDisabled($this->tenant($request), (int) $id, false, null, $this->userId($request), $request)), 'Portal account enabled.'));
    }

    public function revokeSessions(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $account = DB::table('alumni_accounts')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $account) {
            return $this->notFound('Portal account');
        }
        $n = $this->auth->revokeSessions((int) $account->id);
        AuditTrail::record($t, 'alumni', 'alumni.auth', 'sessions_revoked', ['entity_type' => 'alumni_account', 'entity_id' => (int) $account->id, 'after' => ['sessions' => $n]], $request);

        return $this->ok(['signed_out' => $n], 'Signed out everywhere.');
    }

    public function resendVerification(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        $account = DB::table('alumni_accounts')->where('sub_institute_id', $t)->where('id', (int) $id)->first();
        if (! $account) {
            return $this->notFound('Portal account');
        }
        if ($account->status !== 'unverified') {
            return response()->json(['status_code' => 0, 'message' => 'Only an account that still needs its email confirmed can be sent a new link.'], 409);
        }
        $this->auth->resendVerification((string) $this->auth->instituteCode($t), (string) $account->email, $request->ip());

        return $this->ok(null, 'A new confirmation link has been sent.');
    }

    /** The account as staff may see it: standing and dates only. */
    private function present(?object $a): ?array
    {
        if (! $a) {
            return null;
        }

        return ['id' => (int) $a->id, 'alumni_id' => (int) $a->alumni_id, 'email' => $a->email, 'status' => $a->status, 'email_verified' => $a->email_verified_at !== null, 'created_via' => $a->created_via,
            'last_login_at' => $a->last_login_at, 'locked' => $a->locked_until !== null && strtotime((string) $a->locked_until) > time(), 'disabled_reason' => $a->disabled_reason, 'created_at' => $a->created_at];
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AlumniRuleException $e) {
            if ($e->status === 404) {
                return $this->notFound('Alumni record');
            }

            return response()->json(['status_code' => 0, 'message' => $e->getMessage()] + ($e->field ? ['errors' => [$e->field => [$e->getMessage()]]] : []), $e->status);
        }
    }
}
