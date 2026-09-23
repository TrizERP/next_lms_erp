<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Issues the single-use tickets the mobile app uses to open an ERP web page
 * already logged in -- the WebView half of the render_type = 'webview' menu
 * rows served by apiController@homescreen.
 *
 * The route runs behind the `api.session` middleware, so by the time anything
 * here executes the caller's JWT has been validated and the session hydrated
 * from its verified claims. Identity is therefore read from the session, never
 * from the request body: a caller cannot ask for a ticket as another user or
 * another school.
 *
 * See the create_mobile_web_handoff_token_table migration for why a ticket
 * exists at all rather than the app simply appending its JWT to the URL.
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

        $target = $this->sameOriginPath($request, (string) $request->input('web_url'));

        if ($target === null) {
            // Refusing a foreign host is what stops this being an open
            // redirect that hands out a live ERP session cookie.
            return response()->json([
                'status' => '0',
                'message' => 'The requested page does not belong to this ERP.',
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
                'url' => route('mobile.bridge.enter', ['ticket' => $ticket]),
                'expires_in' => self::TICKET_TTL_SECONDS,
            ],
        ]);
    }

    /**
     * Normalises `$webUrl` to a path (plus query and fragment) on THIS host,
     * or null when it points somewhere else.
     *
     * apiController@homescreen already resolves a relative web_url against the
     * request host before serving it, so the app normally sends back an
     * absolute URL on this origin. A relative one is still accepted, because
     * an admin may have typed one straight into the menu row.
     */
    private function sameOriginPath(Request $request, string $webUrl): ?string
    {
        $webUrl = trim($webUrl);

        if ($webUrl === '') {
            return null;
        }

        // A protocol-relative URL (//evil.example/x) is a foreign host wearing
        // a relative URL's clothes. Backslashes count too: several browsers
        // normalise a leading /\ or \\ to //, so treating those as a plain
        // path is how a "relative" URL turns into an off-site redirect.
        if (preg_match('#^[/\\\\]{2}#', $webUrl)) {
            return null;
        }

        $isHttp = (bool) preg_match('#^https?://#i', $webUrl);

        // Anything else carrying its own scheme -- javascript:, data:,
        // mailto: -- is not a page on this ERP.
        if (! $isHttp && preg_match('#^[a-z][a-z0-9+.\-]*:#i', $webUrl)) {
            return null;
        }

        if (! $isHttp) {
            return '/' . ltrim($webUrl, '/\\\\');
        }

        $parts = parse_url($webUrl);

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        if (strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $path = '/' . ltrim($path, '/\\\\');

        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $path .= '#' . $parts['fragment'];
        }

        return $path;
    }
}
