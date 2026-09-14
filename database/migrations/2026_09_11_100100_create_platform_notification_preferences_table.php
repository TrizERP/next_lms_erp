<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-notification, per-channel settings — the matrix the Communication screen
 * edits, one row per notification a school has actually changed.
 *
 * THE ADDRESS IS THE JOIN
 * `event_key` is `module.component.event`, e.g. `fees.collection.payment_received`.
 * `module` and `component` are stored alongside it, derived from that same key at
 * write time, purely so the screen can filter and group without a LIKE across the
 * table. The key remains authoritative; the two columns are a denormalised index,
 * and PlatformRegistry refuses a key it has not declared, so they cannot disagree.
 *
 * WHY `channels` IS JSON AND NOT FIVE COLUMNS, OR A CHILD TABLE
 * The five channels are always read and written together — the screen saves a
 * whole row of the matrix, never one cell — so a child table would buy a join and
 * five times the rows for nothing. Five pairs of columns would mean a migration
 * every time a channel is added, and channels are configuration
 * (config/platform_services.php), not schema. The shape is fixed and validated in
 * the controller: {"web":{"enabled":true,"locked":false}, ...}.
 *
 * WHAT `locked` MEANS, AND WHY IT IS THE POINT
 * `enabled` decides what a recipient gets by default. `locked` decides whether
 * they may change it. A fee receipt is locked on for email because a school
 * cannot allow someone to opt out of the record of money they paid — that is a
 * legal document, not a preference. Without `locked` this table would be a
 * defaults screen; with it, it is a central policy.
 *
 * ONLY OVERRIDES ARE STORED — same rule as the channels table. A notification a
 * school has never touched has no row, and config/platform_services.php answers.
 *
 * WHAT THIS TABLE IS NOT
 * It is not a message log and not a queue. Nothing here records that anything was
 * sent; it records what the institute has decided should be sent. Delivery,
 * retries and receipts belong to the sending service and its own tables.
 *
 * Forward-only; see the DROP TABLE guard note in the channels migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_notification_preferences')) {
            return;
        }

        Schema::create('platform_notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id');

            $table->string('event_key', 191)->comment('module.component.event, from config platform_services.notifications');

            // Derived from event_key at write time so the screen can filter
            // module- and component-wise without scanning strings.
            $table->string('module', 64);
            $table->string('component', 128);

            // False = the notification is not raised at all, on any channel.
            // Refused by the controller for events the registry marks mandatory.
            $table->boolean('enabled')->default(true);

            // {"web":{"enabled":true,"locked":false},"email":{...}, ...}
            $table->json('channels');

            $table->string('updated_by', 191)->nullable();

            $table->timestamps();

            $table->unique(['sub_institute_id', 'event_key'], 'pnp_tenant_event_uq');
            $table->index(['sub_institute_id', 'module'], 'pnp_tenant_module_idx');
            $table->index(['sub_institute_id', 'component'], 'pnp_tenant_component_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notification_preferences');
    }
};
