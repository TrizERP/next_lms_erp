<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Issues and redeems the single-use tickets that let the mobile app open a
 * WebView-backed menu (render_type = 'webview') already logged in.
 *
 * There are two different pages a ticket can point at, redeemed two different
 * ways, because they authenticate two different ways:
 *
 *  - An ERP Blade page (fees/fees_collect, etc) lives on this same Laravel
 *    app and reads its identity from the browser's session COOKIE. Its
 *    ticket is redeemed by MobileWebBridgeController@enter, which hydrates a
 *    real cookie session via HydratesLegacyApiSession and redirects.
 *
 *  - A page in a SEPARATE frontend (lms_k12, a Next.js app on its own
 *    origin -- see contexts/AuthContext.tsx there) reads its identity from
 *    that origin's own localStorage, the same way it would after a normal
 *    email/password login. A Laravel session cookie on THIS host means
 *    nothing to it. Its ticket is redeemed by claims(), which returns the
 *    identity as JSON instead of a cookie; a small bootstrap page on that
 *    origin (app/mobile-bridge/page.tsx) writes the JSON into localStorage
 *    itself and then navigates.
 *
 * create() decides which of the two a given web_url needs and returns the
 * matching bridge URL, so the app never has to know or care which kind of
 * page it is opening.
 */
class MobileWebHandoffApiController extends Controller
{
    /**
     * How long a ticket stays redeemable. Long enough to cover a slow handset
     * opening a WebView, short enough that a ticket captured from a log or a
     * screen recording is almost always already dead.
     */
    public const TICKET_TTL_SECONDS = 60;

    public function create(Request $request): JsonResponse
    {
        if (! Schema::hasTable('mobile_web_handoff_token')) {
            return response()->json([
                'status' => '0',
                'message' => 'Web handoff is not available on this installation.',
                'data' => [],
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'web_url' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => '0',
                'message' => $validator->messages()->first(),
                'data' => [],
            ], 422);
        }

        $target = $this->resolveTargetUrl($request, (string) $request->input('web_url'));

        if ($target === null) {
            // Refusing an untrusted host is what stops this becoming an open
            // redirect that hands out a live session (cookie or claims) to
            // an arbitrary site.
            return response()->json([
                'status' => '0',
                'message' => 'The requested page is not a trusted destination.',
                'data' => [],
            ], 422);
        }

        $userId = (int) session()->get('user_id');
        $subInstituteId = (int) session()->get('sub_institute_id');

        if ($userId <= 0 || $subInstituteId <= 0) {
            return response()->json([
                'status' => '2',
                'message' => 'Token Auth Failed',
                'data' => [],
            ], 401);
        }

        // 32 random bytes, hex encoded. Only the hash is stored, so the value
        // returned below is the only copy that ever exists.
        $ticket = bin2hex(random_bytes(32));
        $expiresAt = now()->addSeconds(self::TICKET_TTL_SECONDS);

        DB::table('mobile_web_handoff_token')->insert([
            'token_hash' => hash('sha256', $ticket),
            'user_id' => $userId,
            'sub_institute_id' => $subInstituteId,
            'user_profile_id' => session()->get('user_profile_id'),
            'is_admin' => (string) session()->get('is_admin'),
            'client_id' => session()->get('client_id'),
            'is_student' => (bool) session()->get('is_student'),
            'syear' => session()->get('syear'),
            'term_id' => session()->get('term_id'),
            'target_url' => $target,
            'expires_at' => $expiresAt,
            'created_ip' => $request->ip(),
            'created_at' => now(),
        ]);

        // Opportunistic cleanup, so the table cannot grow without bound on an
        // installation with no scheduler running. Bounded to keep the request
        // cheap; whatever is left is picked up by the next call.
        DB::table('mobile_web_handoff_token')
            ->where('expires_at', '<', now()->subDay())
            ->limit(500)
            ->delete();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'data' => [
                // The only URL the app should load. The ticket is spent the
                // moment this is opened.
                'url' => $this->bridgeUrlFor($target, $ticket),
                'expires_in' => self::TICKET_TTL_SECONDS,
            ],
        ]);
    }

    /**
     * Redeems a ticket for a cross-origin frontend and hands back its
     * identity as JSON, in place of a session cookie -- see the class doc for
     * why a cookie on this host cannot authenticate a different origin.
     *
     * No `api.session` here: the caller has no session yet, by definition.
     * The single-use, 60-second, tenant/user-bound ticket IS the credential
     * for this one call, exactly as it is for MobileWebBridgeController.
     */
    public function claims(Request $request): JsonResponse
    {
        if (! Schema::hasTable('mobile_web_handoff_token')) {
            return response()->json(['status' => 0, 'message' => 'Web handoff is not available on this installation.'], 503);
        }

        $ticket = (string) $request->query('ticket', '');
        if ($ticket === '') {
            return response()->json(['status' => 0, 'message' => 'A ticket is required.'], 422);
        }

        $row = DB::table('mobile_web_handoff_token')->where('token_hash', hash('sha256', $ticket))->first();

        // Burn it first, and only proceed if THIS request is the one that
        // burned it. Doing the update conditionally on used_at being null
        // makes the database the arbiter, so two requests racing with the
        // same ticket cannot both be let through.
        $burned = $row && DB::table('mobile_web_handoff_token')
            ->where('id', $row->id)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->update(['used_at' => now()]);

        if (! $burned) {
            // Deliberately the same answer whether the ticket was unknown,
            // expired, or already redeemed: there is nothing the person
            // holding the phone can do differently for any of those, and
            // distinguishing them only helps someone probing for live
            // tickets.
            return response()->json(['status' => 0, 'message' => 'This link has expired. Please open it again from the app.'], 401);
        }

        return response()->json($this->identityPayload($row));
    }

    /**
     * The redeemed ticket's identity, shaped like /api/api-login's response
     * (see ApiLoginController::login) so the Next.js frontend's
     * persistLoginPayload() -- which already knows how to store that exact
     * shape -- can consume it unchanged, through a third sibling method next
     * to its existing login()/loginWithGoogle() (see
     * AuthContext::loginFromHandoffTicket).
     *
     * Deliberately NOT the same code as
     * HydratesLegacyApiSession::hydrateSessionFromClaims(), which this
     * duplicates a small part of: that method mutates the (cookie-backed)
     * Laravel session store, which does not exist for this stateless,
     * cross-origin, JSON-only exchange. Keep the two in sync by hand if the
     * session shape they both derive ever changes.
     */
    private function identityPayload(object $row): array
    {
        $userId = (int) $row->user_id;
        $subInstituteId = (int) $row->sub_institute_id;
        $isAdmin = $row->is_admin;
        $isStudent = (bool) $row->is_student;

        $syear = $row->syear;
        $termId = $row->term_id;

        if (empty($syear) || empty($termId)) {
            $currentTerm = DB::table('academic_year')
                ->where('sub_institute_id', $subInstituteId)
                ->whereRaw('"' . date('Y-m-d') . '" between start_date and end_date')
                ->first();

            $syear = $syear ?: ($currentTerm->syear ?? null);
            $termId = $termId ?: ($currentTerm->term_id ?? null);
        }

        $profile = DB::table('tbluserprofilemaster')->where('id', $row->user_profile_id)->first();
        $userProfileName = ((int) $isAdmin === 1 || (int) $isAdmin === 2)
            ? 'Super Admin'
            : ($profile->name ?? '');

        $school = DB::table('school_setup')->where('Id', $subInstituteId)->first();

        $academicTerms = DB::table('academic_year')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->orderBy('sort_order')
            ->get();

        $academicYears = DB::table('academic_year')
            ->where('sub_institute_id', $subInstituteId)
            ->groupBy('syear')
            ->get();

        $userRow = null;
        if (! $isStudent) {
            $userRow = DB::table('tbluser')->where('id', $userId)->first();
        }

        $jwt = app(JwtToken::class);
        $token = $jwt->createToken([
            'id' => $userId,
            'sub_institute_id' => $subInstituteId,
            'is_admin' => $isAdmin,
            'client_id' => $row->client_id,
            'user_profile_id' => $row->user_profile_id,
            'is_student' => $isStudent,
        ]);

        return [
            'status' => 1,
            'message' => 'Success',
            // Where the bootstrap page navigates once it has stored this
            // payload -- the path (plus query/fragment) the admin originally
            // configured on the menu row, never the ERP's own host.
            'redirect_path' => $this->pathOf((string) $row->target_url),
            'data' => [
                'id' => $userId,
                'user_name' => $userRow->user_name ?? '',
                'first_name' => $userRow->first_name ?? '',
                'last_name' => $userRow->last_name ?? '',
                'email' => $userRow->email ?? '',
                'user_profile' => $userProfileName,
                'user_profile_id' => $row->user_profile_id,
                'sub_institute_id' => $subInstituteId,
                'client_id' => $row->client_id,
                'is_admin' => $isAdmin,
                'term_id' => $termId,
                'mobile_syear' => $syear,
                'school_name' => $school->SchoolName ?? '',
                'short_code' => $school->ShortCode ?? '',
                'institute_type' => $school->institute_type ?? '',
                'host_name' => rtrim((string) config('app.url'), '/'),
                'user_token' => $token,
            ],
            'academicTerms' => $academicTerms,
            'academicYears' => $academicYears,
        ];
    }

    /** The path (+query+fragment) of an absolute URL, or the string itself if it already was one. */
    private function pathOf(string $url): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        $path = '/' . ltrim($parts['path'] ?? '/', '/');
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $path .= '#' . $parts['fragment'];
        }

        return $path;
    }

    /**
     * Same-host targets go through the existing Laravel-session bridge
     * (mobile.bridge.enter); a trusted cross-origin target goes to ITS OWN
     * bootstrap page instead, carrying the ticket -- see the class doc.
     */
    private function bridgeUrlFor(string $target, string $ticket): string
    {
        if (! preg_match('#^https?://#i', $target)) {
            return route('mobile.bridge.enter', ['ticket' => $ticket]);
        }

        $parts = parse_url($target);

        return sprintf('%s://%s/mobile-bridge?ticket=%s', $parts['scheme'], $parts['host'], $ticket);
    }

    /**
     * Normalises `$webUrl` to a target this ticket is allowed to point at:
     * a path on this host (a relative value, or an absolute one matching
     * $request's own host), or an absolute URL on a CORS-trusted origin
     * (config/cors.php's allowed_origins / allowed_origins_patterns -- if
     * this ERP already trusts a browser on that origin to call its APIs
     * directly with a bearer token, trusting it as a WebView redirect target
     * for one scoped, single-use ticket is a strictly smaller grant, not a
     * new one, so the same list is reused rather than kept separately).
     *
     * Anything else returns null. apiController@homescreen already resolves
     * a relative web_url against the request host before serving it, so the
     * app normally sends back an absolute same-host URL; a relative one is
     * still accepted here because an admin may have typed one straight into
     * the menu row.
     */
    private function resolveTargetUrl(Request $request, string $webUrl): ?string
    {
        $webUrl = trim($webUrl);

        if ($webUrl === '') {
            return null;
        }

        // A protocol-relative URL (//evil.example/x) is a foreign host
        // wearing a relative URL's clothes. Backslashes count too: several
        // browsers normalise a leading /\ or \\ to //, so treating those as a
        // plain path is how a "relative" URL turns into an off-site
        // redirect.
        if (preg_match('#^[/\\\\]{2}#', $webUrl)) {
            return null;
        }

        $isHttp = (bool) preg_match('#^https?://#i', $webUrl);

        // Anything else carrying its own scheme -- javascript:, data:,
        // mailto: -- is not a page anywhere trusted.
        if (! $isHttp && preg_match('#^[a-z][a-z0-9+.\-]*:#i', $webUrl)) {
            return null;
        }

        if (! $isHttp) {
            return '/' . ltrim($webUrl, '/\\');
        }

        $parts = parse_url($webUrl);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        if (strcasecmp($parts['host'], $request->getHost()) === 0) {
            $path = '/' . ltrim($parts['path'] ?? '/', '/\\');
            if (isset($parts['query'])) {
                $path .= '?' . $parts['query'];
            }
            if (isset($parts['fragment'])) {
                $path .= '#' . $parts['fragment'];
            }

            return $path;
        }

        $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if ($this->isTrustedOrigin($origin, $parts['host'])) {
            // Kept absolute (not reduced to a path) -- this is the value
            // stored as target_url and later returned as redirect_path by
            // claims(), which strips it back to a path for that origin's OWN
            // router. bridgeUrlFor() also reads the host straight off this.
            return $webUrl;
        }

        return null;
    }

    private function isTrustedOrigin(string $origin, string $host): bool
    {
        $allowed = (array) config('cors.allowed_origins', []);
        if (in_array($origin, $allowed, true)) {
            return true;
        }

        foreach ((array) config('cors.allowed_origins_patterns', []) as $pattern) {
            if ($pattern !== '' && @preg_match($pattern, $origin) === 1) {
                return true;
            }
        }

        return false;
    }
}
