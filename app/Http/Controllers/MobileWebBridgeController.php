<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Concerns\HydratesLegacyApiSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Redeems a mobile web-handoff ticket and turns it into a real browser
 * session, so a render_type = 'webview' menu row opens its ERP page already
 * logged in.
 *
 * This route deliberately sits OUTSIDE the `session` middleware: that
 * middleware bounces anyone without a session to the login page, and having no
 * session yet is the entire point of arriving here. It is inside the `web`
 * group, so cookies and the session store are live by the time enter() runs
 * and everything put into the session is persisted for the WebView's
 * subsequent navigations.
 *
 * The session is built through HydratesLegacyApiSession, the same code path an
 * API request goes through, so a page reached this way sees exactly the
 * session a web login would have produced.
 */
class MobileWebBridgeController extends Controller
{
    use HydratesLegacyApiSession;

    public function enter(Request $request)
    {
        if (! Schema::hasTable('mobile_web_handoff_token')) {
            return redirect()->route('home');
        }

        $ticket = (string) $request->query('ticket', '');

        if ($ticket === '') {
            return redirect()->route('home');
        }

        $row = DB::table('mobile_web_handoff_token')
            ->where('token_hash', hash('sha256', $ticket))
            ->first();

        if (! $row) {
            return $this->rejected();
        }

        // Burn it first, and only proceed if THIS request is the one that
        // burned it. Doing the update conditionally on used_at being null
        // makes the database the arbiter, so two requests racing with the same
        // ticket cannot both be let through.
        $burned = DB::table('mobile_web_handoff_token')
            ->where('id', $row->id)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->update(['used_at' => now()]);

        if ($burned !== 1) {
            return $this->rejected();
        }

        // The academic year/term the app was showing. Merged into the request
        // because that is where hydrateSessionFromClaims() looks for them;
        // leaving them out makes it resolve the current term instead.
        if (! empty($row->syear)) {
            $request->merge(['syear' => $row->syear]);
        }

        if (! empty($row->term_id)) {
            $request->merge(['term_id' => $row->term_id]);
        }

        // Claims verified when the ticket was issued, not anything the browser
        // just sent. Key names match the JWT payload ApiLoginController mints.
        $claims = [
            'id' => $row->user_id,
            'sub_institute_id' => $row->sub_institute_id,
            'user_profile_id' => $row->user_profile_id,
            'client_id' => $row->client_id,
            'is_admin' => $row->is_admin,
            'is_student' => (bool) $row->is_student,
        ];

        // A fresh id for the authenticated session, so a cookie the WebView
        // was handed before redemption cannot be replayed afterwards.
        $request->session()->regenerate();

        if ($this->hydrateSessionFromClaims($claims, $request)) {
            // hydrateSessionFromClaims answers in JSON, which is no use to a
            // WebView. The ticket is already spent, so the app has to ask for
            // a new one.
            return $this->rejected();
        }

        // The ticket is gone from the URL from here on: what lands in the
        // WebView's history is the target page, not the credential.
        return redirect()->to($row->target_url);
    }

    /**
     * What an expired, unknown or already-redeemed ticket gets.
     *
     * Deliberately the same answer in every case, and deliberately not an
     * error page: there is nothing the person holding the phone can do about
     * it, and distinguishing "expired" from "never existed" only helps someone
     * probing for valid tickets.
     */
    private function rejected()
    {
        return redirect()->route('home');
    }
}
