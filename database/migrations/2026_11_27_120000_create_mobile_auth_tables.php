<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile number + mPIN sign-in for the app (new API: api/mobile-auth/*; the web login is untouched).
 *
 * A mobile number is not an account: one number can belong to a teacher (tbluser) and to several
 * children (tblstudent). So the credential belongs to the NUMBER, and after it is proven the person
 * picks which profile to open.
 *
 *  - mobile_auth_credentials: the mPIN (hashed) for a number, with failed-attempt lockout.
 *  - mobile_auth_otps:        one-time codes (hashed, expiring, attempt-limited) used to activate
 *                             a number the first time and to reset a forgotten mPIN.
 *  - mobile_auth_devices:     long-lived device tokens (hashed) behind fingerprint / Face ID. The
 *                             biometric check happens on the phone; the server only ever sees the
 *                             device token it handed out, which can be revoked.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_auth_credentials')) {
            Schema::create('mobile_auth_credentials', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('mobile', 10)->unique();
                $table->string('pin_hash', 100);
                $table->unsignedTinyInteger('failed_attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('pin_set_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mobile_auth_otps')) {
            Schema::create('mobile_auth_otps', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('mobile', 10)->index();
                $table->string('otp_hash', 100);
                $table->string('purpose', 20)->default('activate');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('mobile_auth_devices')) {
            Schema::create('mobile_auth_devices', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('mobile', 10)->index();
                $table->char('token_hash', 64)->unique();
                $table->string('label', 80)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_auth_devices');
        Schema::dropIfExists('mobile_auth_otps');
        Schema::dropIfExists('mobile_auth_credentials');
    }
};
