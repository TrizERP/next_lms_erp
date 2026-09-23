<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use tickets that let the mobile app open an ERP web page already
 * logged in.
 *
 * The app holds a long-lived JWT. Putting that JWT in the URL the WebView
 * loads would work, and would also write it into the web server access log,
 * the WebView's history and the Referer header of every third-party asset the
 * page pulls -- and it is the same token that authorises every API call the
 * app makes, so one leak is a full account compromise rather than a
 * short-lived one.
 *
 * Instead the app exchanges its JWT for a row here: a random ticket, bound to
 * one user, one tenant and one target URL, valid for
 * MobileWebHandoffApiController::TICKET_TTL_SECONDS and redeemable exactly
 * once. The bridge burns it, starts a normal browser session and redirects;
 * the ticket never survives that redirect, so what ends up in history is an
 * already-dead value.
 *
 * `token_hash` stores a SHA-256 of the ticket, never the ticket itself, for
 * the same reason password-reset tokens are hashed: whoever can read this
 * table still cannot mint a working URL from it.
 *
 * The claims are copied in rather than re-derived at redemption time because
 * they were verified when the JWT was validated. Redemption trusts this row
 * and nothing the browser sends.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_web_handoff_token')) {
            return;
        }

        Schema::create('mobile_web_handoff_token', function (Blueprint $table) {
            $table->bigIncrements('id');

            // SHA-256 hex of the ticket handed to the app.
            $table->char('token_hash', 64)->unique();

            // The verified JWT claims, replayed into the browser session at
            // redemption. Names match the payload ApiLoginController issues.
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_profile_id')->nullable();
            $table->string('is_admin', 10)->nullable();
            $table->string('client_id', 100)->nullable();
            $table->boolean('is_student')->default(false);

            // The academic year/term the app is currently showing, so the web
            // page opens in the same one instead of snapping to today's term.
            // Null means "resolve the current term", exactly as an API request
            // without these does.
            $table->integer('syear')->nullable();
            $table->integer('term_id')->nullable();

            // Always same-origin -- MobileWebHandoffApiController rejects any
            // other host, so this can never become an open redirect.
            $table->text('target_url');

            $table->timestamp('expires_at')->index();
            // Set the moment the ticket is redeemed. A row with this set is
            // spent and can never be redeemed again.
            $table->timestamp('used_at')->nullable();

            $table->string('created_ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_web_handoff_token');
    }
};
