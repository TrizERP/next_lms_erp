<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni portal sign-in: accounts, single-use tokens and login sessions. Additive only - no existing table is touched.
 *
 * Passwords are stored only as Laravel hashes. Invitation, email-verification and password-reset tokens are stored only as
 * their SHA-256 hash (the token itself exists only in the link that was emailed), and each can be used once. A login session
 * stores the hash of its current refresh token and of the previous one, which is how reuse of a stolen, already-rotated
 * refresh token is detected.
 *
 * NOT APPLIED TO PRODUCTION: needs explicit approval. See section 8 of docs/ALUMNI_MODULE_IMPLEMENTATION_PLAN.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni_accounts')) {
            Schema::create('alumni_accounts', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('email', 190);                                  // lower-case
                $t->string('password_hash', 255)->nullable();              // null until the person sets one (invited accounts)
                $t->dateTime('email_verified_at')->nullable();
                $t->string('status', 12)->default('unverified');           // invited|unverified|active|disabled
                $t->unsignedTinyInteger('failed_attempts')->default(0);
                $t->dateTime('locked_until')->nullable();
                $t->dateTime('last_login_at')->nullable();
                $t->dateTime('password_changed_at')->nullable();
                $t->string('created_via', 10)->default('self');            // invite|self
                $t->unsignedBigInteger('created_by')->nullable();          // the staff user who invited
                $t->unsignedBigInteger('disabled_by')->nullable();
                $t->string('disabled_reason', 255)->nullable();
                $t->timestamps();
                $t->unique(['sub_institute_id', 'email'], 'alumni_acct_email_uq');
                $t->unique(['sub_institute_id', 'alumni_id'], 'alumni_acct_alumni_uq');
            });
        }

        if (! Schema::hasTable('alumni_auth_tokens')) {
            Schema::create('alumni_auth_tokens', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('account_id');
                $t->string('purpose', 16);                                 // invite|verify_email|reset_password
                $t->char('token_hash', 64);                                // sha256 of the token; the token is never stored
                $t->dateTime('expires_at');
                $t->dateTime('used_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->string('created_ip', 45)->nullable();
                $t->timestamps();
                $t->unique('token_hash', 'alumni_tok_hash_uq');
                $t->index(['account_id', 'purpose'], 'alumni_tok_acct_ix');
            });
        }

        if (! Schema::hasTable('alumni_auth_sessions')) {
            Schema::create('alumni_auth_sessions', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('account_id');
                $t->char('refresh_hash', 64);
                $t->char('previous_refresh_hash', 64)->nullable();         // the token just replaced: presenting it again means it was stolen
                $t->dateTime('expires_at');                                // absolute end of this login
                $t->dateTime('last_used_at')->nullable();
                $t->dateTime('revoked_at')->nullable();
                $t->string('ip', 45)->nullable();
                $t->string('user_agent', 255)->nullable();
                $t->timestamps();
                $t->unique('refresh_hash', 'alumni_sess_refresh_uq');
                $t->index('previous_refresh_hash', 'alumni_sess_prev_ix');
                $t->index(['account_id', 'revoked_at'], 'alumni_sess_acct_ix');
            });
        }
    }

    public function down(): void
    {
        // Forward-only: sign-in records are part of the audit trail and are never dropped by a rollback.
    }
};
