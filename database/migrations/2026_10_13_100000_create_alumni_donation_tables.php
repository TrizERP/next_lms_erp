<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E: donation campaigns, donations and refunds.
 *
 * There is NO payment gateway here. A donation is recorded by finance staff as money that was received offline (cash,
 * cheque, bank transfer, UPI to the institute account...) and only becomes `verified` - and only then gets a receipt
 * number - when a finance user confirms it. Online payment needs a gateway and credentials the product owner has not
 * chosen yet; nothing in this schema fakes one.
 *
 * Money is decimal(12,2). Receipt numbers are unique per institute (unique index), so a retry or a race cannot issue the
 * same number twice. Nothing is ever deleted: refunds are separate rows and the donation keeps its history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumni_donation_campaigns')) {
            Schema::create('alumni_donation_campaigns', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->string('name', 190);
                $t->string('purpose', 190)->nullable();                    // scholarship fund, library, infrastructure...
                $t->text('description')->nullable();
                $t->decimal('goal_amount', 12, 2)->nullable();
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->string('status', 15)->default('draft');                // draft|active|closed
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'status'], 'alumni_dcamp_status_ix');
            });
        }

        if (! Schema::hasTable('alumni_donations')) {
            Schema::create('alumni_donations', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('alumni_id');
                $t->unsignedBigInteger('campaign_id')->nullable();
                $t->decimal('amount', 12, 2);
                $t->string('currency', 3)->default('INR');
                $t->string('purpose', 190)->nullable();
                $t->string('payment_mode', 20)->nullable();                // cash|cheque|bank_transfer|upi|card|other - set when money is received
                $t->string('reference_no', 120)->nullable();               // cheque no / UTR / transaction id
                $t->date('pledged_on')->nullable();
                $t->date('received_on')->nullable();
                $t->string('status', 25)->default('pledged');              // pledged|pending_verification|verified|rejected|cancelled|partially_refunded|refunded
                $t->unsignedTinyInteger('anonymous')->default(0);          // hide the donor's name in public/summary listings
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('recorded_by')->nullable();
                $t->unsignedBigInteger('verified_by')->nullable();
                $t->dateTime('verified_at')->nullable();
                $t->string('decision_note', 500)->nullable();              // why it was rejected / cancelled
                $t->string('receipt_no', 40)->nullable();
                $t->dateTime('receipt_issued_at')->nullable();
                $t->decimal('refunded_amount', 12, 2)->default(0);
                $t->timestamps();
                $t->unique(['sub_institute_id', 'receipt_no'], 'alumni_don_receipt_uq');   // nullable column: many unreceipted rows are fine
                $t->index(['sub_institute_id', 'status', 'received_on'], 'alumni_don_status_ix');
                $t->index(['sub_institute_id', 'alumni_id'], 'alumni_don_alumni_ix');
                $t->index(['sub_institute_id', 'campaign_id'], 'alumni_don_campaign_ix');
            });
        }

        if (! Schema::hasTable('alumni_donation_refunds')) {
            Schema::create('alumni_donation_refunds', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('sub_institute_id');
                $t->unsignedBigInteger('donation_id');
                $t->decimal('amount', 12, 2);
                $t->string('reason', 500);
                $t->date('refunded_on');
                $t->string('payment_mode', 20)->nullable();
                $t->string('reference_no', 120)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sub_institute_id', 'donation_id'], 'alumni_dref_don_ix');
            });
        }
    }

    public function down(): void
    {
        // Forward-only: these tables hold financial records and must never be dropped by a rollback.
    }
};
