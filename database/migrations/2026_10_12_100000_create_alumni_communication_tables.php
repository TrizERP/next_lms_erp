<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni Management - communication (Phase D).
 *
 *   alumni_message_templates  reusable subject/body per communication type, with {{placeholders}}
 *   alumni_campaigns          one send to an audience: filters, channels, counts, schedule
 *   alumni_message_log        one row per message attempt per alumnus per channel: the communication history
 *
 * Delivery itself is done by the platform NotificationSender, which records platform_notification_log; each row here
 * keeps the status and the reason it reported, so the history says "skipped - no SMS provider configured", never "sent".
 *
 * IDEMPOTENCY. unique(campaign_id, alumni_id, channel) stops a retried campaign job from messaging anyone twice;
 * unique(sub_institute_id, alumni_id, channel, dedupe_key) does the same for automatic messages (a birthday wish, an event
 * reminder), so running the daily job twice sends once. Both allow many NULLs, so ad-hoc rows are unaffected.
 *
 * Idempotent and forward-only (guarded by Schema::hasTable; empty down()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni_message_templates')) {
            Schema::create('alumni_message_templates', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('name', 150);
                $t->string('comm_type', 40);
                $t->string('subject', 255);
                $t->text('body');
                $t->unsignedTinyInteger('is_system')->default(0);       // seeded defaults; staff may edit but not delete
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['sub_institute_id', 'name'], 'alumni_tpl_name_uq');
                $t->index(['sub_institute_id', 'comm_type'], 'alumni_tpl_type_ix');
            });
        }

        if (! Schema::hasTable('alumni_campaigns')) {
            Schema::create('alumni_campaigns', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('name', 190);
                $t->string('comm_type', 40);
                $t->string('subject', 255);
                $t->text('body');
                $t->string('channels', 120);                              // CSV: email,sms,whatsapp,mobile,web
                $t->text('audience')->nullable();                         // JSON of the filters used (the "recipient group")
                $t->string('audience_label', 500)->nullable();            // human summary for the history list
                $t->string('status', 20)->default('draft');               // draft|scheduled|sending|sent|failed|cancelled
                $t->dateTime('scheduled_at')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->unsignedInteger('recipient_count')->default(0);       // alumni matched and eligible
                $t->unsignedInteger('sent_count')->default(0);
                $t->unsignedInteger('skipped_count')->default(0);         // provider not configured, mailer writes to a file, ...
                $t->unsignedInteger('failed_count')->default(0);
                $t->unsignedInteger('no_consent_count')->default(0);      // alumni who did not consent to a chosen channel
                $t->string('last_error', 500)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'status'], 'alumni_camp_status_ix');
                $t->index(['sub_institute_id', 'status', 'scheduled_at'], 'alumni_camp_due_ix');
            });
        }

        if (! Schema::hasTable('alumni_message_log')) {
            Schema::create('alumni_message_log', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('campaign_id')->nullable();
                $t->unsignedBigInteger('alumni_id');
                $t->string('comm_type', 40);
                $t->string('channel', 20);
                $t->string('recipient', 191)->nullable();
                $t->string('subject', 255)->nullable();
                $t->string('status', 20)->default('queued');              // queued|sent|skipped|failed
                $t->string('reason', 500)->nullable();                    // why it was skipped or failed, verbatim from the sender
                $t->unsignedBigInteger('notification_log_id')->nullable();
                $t->string('dedupe_key', 120)->nullable();
                $t->unsignedTinyInteger('is_transactional')->default(0);
                $t->timestamp('read_at')->nullable();                     // set when the alumnus opens it (portal), later
                $t->string('response', 255)->nullable();                  // for example the event registration it led to
                $t->unsignedBigInteger('sent_by')->nullable();
                $t->timestamps();
                $t->unique(['campaign_id', 'alumni_id', 'channel'], 'alumni_msg_camp_uq');
                $t->unique(['sub_institute_id', 'alumni_id', 'channel', 'dedupe_key'], 'alumni_msg_dedupe_uq');
                $t->index(['sub_institute_id', 'alumni_id', 'created_at'], 'alumni_msg_alumni_ix');
                $t->index(['sub_institute_id', 'campaign_id', 'status'], 'alumni_msg_camp_ix');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty - dropping would destroy the communication history.
    }
};
