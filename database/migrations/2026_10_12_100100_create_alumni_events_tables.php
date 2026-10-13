<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumni Management - events (Phase D): events, registrations with attendance, feedback, attached files.
 *
 * Every table carries sub_institute_id; the service layer scopes every query by it.
 *
 * RULES THAT THE DATABASE ITSELF ENFORCES (so a race or a bug in a caller cannot break them):
 *   unique(event_id, alumni_id)        one registration row per alumnus per event (a cancelled one is reused, not duplicated)
 *   unique(qr_token)                   a QR code can only ever identify one registration
 *   unique(sub_institute_id, registration_no)
 * Capacity is enforced in AlumniEventService under a row lock on the event, because a sum over participants cannot be a
 * unique key.
 *
 * Idempotent and forward-only (guarded by Schema::hasTable; empty down()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni_events')) {
            Schema::create('alumni_events', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('name', 190);
                $t->text('description')->nullable();
                $t->string('event_type', 30);                              // alumni_meet|batch_reunion|annual_function|sports_event|career_talk|guest_lecture|mentorship_session|networking_event|fundraising_event|webinar|workshop|convocation|social_service
                $t->date('event_date');
                $t->time('start_time')->nullable();
                $t->time('end_time')->nullable();
                $t->string('venue', 255)->nullable();
                $t->string('mode', 10)->default('offline');                // offline|online|hybrid
                $t->string('online_link', 500)->nullable();
                $t->unsignedInteger('capacity')->nullable();               // participants; null = unlimited
                $t->unsignedTinyInteger('registration_required')->default(1);
                $t->unsignedTinyInteger('auto_confirm')->default(1);       // free events with a seat free are confirmed at once
                $t->dateTime('registration_deadline')->nullable();
                $t->string('organizer', 190)->nullable();
                $t->string('contact_person', 190)->nullable();
                $t->string('contact_phone', 40)->nullable();
                $t->string('contact_email', 190)->nullable();
                $t->decimal('fee_amount', 10, 2)->default(0);              // informational until online payment is enabled
                $t->string('status', 20)->default('draft');                // draft|published|cancelled|completed
                $t->text('audience')->nullable();                          // JSON filters: who may register; null = every eligible alumnus
                $t->string('audience_label', 500)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'status', 'event_date'], 'alumni_event_status_ix');
            });
        }

        if (! Schema::hasTable('alumni_event_registrations')) {
            Schema::create('alumni_event_registrations', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('event_id');
                $t->unsignedBigInteger('alumni_id');
                $t->string('registration_no', 40);
                $t->unsignedSmallInteger('participants')->default(1);     // the alumnus plus guests
                $t->text('guest_details')->nullable();
                $t->string('payment_status', 20)->default('none');         // none|pending|paid|waived
                $t->string('status', 20)->default('registered');           // registered|confirmed|waitlisted|cancelled|attended|no_show
                $t->string('qr_token', 64);
                $t->timestamp('registered_at')->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->timestamp('attended_at')->nullable();
                $t->string('attendance_method', 20)->nullable();           // manual|qr|mobile|registration_no|alumni_id
                $t->unsignedBigInteger('checked_in_by')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['event_id', 'alumni_id'], 'alumni_ereg_uq');
                $t->unique(['qr_token'], 'alumni_ereg_qr_uq');
                $t->unique(['sub_institute_id', 'registration_no'], 'alumni_ereg_no_uq');
                $t->index(['sub_institute_id', 'event_id', 'status'], 'alumni_ereg_status_ix');
                $t->index(['sub_institute_id', 'alumni_id'], 'alumni_ereg_alumni_ix');
            });
        }

        if (! Schema::hasTable('alumni_event_feedback')) {
            Schema::create('alumni_event_feedback', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('event_id');
                $t->unsignedBigInteger('alumni_id');
                $t->unsignedTinyInteger('rating');                         // 1-5
                $t->text('comments')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['event_id', 'alumni_id'], 'alumni_efb_uq');
            });
        }

        if (! Schema::hasTable('alumni_event_files')) {
            // images and documents for an event; stored on the PRIVATE disk and served only through an authorised endpoint
            Schema::create('alumni_event_files', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('event_id');
                $t->string('original_name', 255);
                $t->string('file_path', 500);
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size_bytes')->default(0);
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'event_id'], 'alumni_efile_event_ix');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty - dropping would destroy registrations and attendance.
    }
};
