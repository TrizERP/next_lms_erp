<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayphiFieldsToFeesPaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fees_payment', function (Blueprint $table) {
            $table->string('payphi_order_id')->nullable()->after('razorpay_payment_date');
            $table->text('payphi_request')->nullable()->after('payphi_order_id');
            $table->text('payphi_response')->nullable()->after('payphi_request');
            $table->string('payphi_payment_status')->nullable()->after('payphi_response');
            $table->timestamp('payphi_payment_date')->nullable()->after('payphi_payment_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fees_payment', function (Blueprint $table) {
            $table->dropColumn([
                'payphi_order_id',
                'payphi_request',
                'payphi_response',
                'payphi_payment_status',
                'payphi_payment_date',
            ]);
        });
    }
};
