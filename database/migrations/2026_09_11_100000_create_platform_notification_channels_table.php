<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Institute-wide delivery-channel switches for the Communication service.
 *
 * WHAT THIS IS FOR
 * A row here is the master switch for one channel in one institute: WhatsApp off
 * means no module sends WhatsApp, whatever any individual notification's row in
 * platform_notification_preferences says. It is the switch a school reaches for
 * when the SMS credit runs out at 11am on a Tuesday, and it has to work without
 * anyone editing sixty notification rows one at a time.
 *
 * WHY ONLY OVERRIDES ARE STORED
 * A channel a school has never touched has NO ROW here; the default in
 * config/platform_services.php answers for it. That is what makes a default a
 * default — improving one reaches every school that never disagreed with it, and
 * reaches none that did. Seeding a row per (institute × channel) would freeze
 * today's opinion into 56 tenants and quietly make the config file decorative.
 *
 * SMS AND WHATSAPP DEFAULT TO OFF, in the config, not here. Both bill per message
 * and a service that starts spending a school's money the day it is installed is
 * one nobody trusts again.
 *
 * TENANCY
 * `sub_institute_id` is written from the JWT by the controller and never from
 * request input, so a caller cannot name another school's row. The unique index
 * is (sub_institute_id, channel) — one switch per channel per institute, which
 * makes the upsert on save unambiguous.
 *
 * ROLLBACK
 * Forward-only. AppServiceProvider installs a DB::listen guard that throws on any
 * statement containing DROP TABLE, so down() cannot run in this application; it
 * is written out for readability and for a future environment without the guard.
 * The same is true of the other three platform_* migrations in this series.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_notification_channels')) {
            return;
        }

        Schema::create('platform_notification_channels', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id');

            // web | email | mobile | sms | whatsapp — validated against
            // config('platform_services.channels'), never free text from a client.
            $table->string('channel', 32);

            $table->boolean('enabled')->default(true);

            // Who last flipped it, as "Name (id)". Denormalised on purpose: the
            // audit column has to keep reading correctly after the user is
            // renamed, transferred or deactivated.
            $table->string('updated_by', 191)->nullable();

            $table->timestamps();

            $table->unique(['sub_institute_id', 'channel'], 'pnc_tenant_channel_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notification_channels');
    }
};
