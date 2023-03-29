<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fees_payment', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('student_id', 150);
            $table->string('syear', 150);
            $table->string('amount', 150);
            $table->longText('hdfc_order_id')->nullable();
            $table->longText('hdfc_transaction_id')->nullable();
            $table->longText('hdfc_payment_status')->nullable();
            $table->longText('hdfc_payment_date')->nullable();
            $table->longText('axis_order_id')->nullable();
            $table->longText('axis_plain_request')->nullable();
            $table->longText('axis_encrypt_request')->nullable();
            $table->longText('axis_payment_status')->nullable();
            $table->longText('axis_bank_res')->nullable();
            $table->dateTime('axis_payment_date')->nullable();
            $table->longText('aggre_pay_order_id')->nullable();
            $table->longText('aggre_pay_plain_request')->nullable();
            $table->longText('aggre_pay_payment_status')->nullable();
            $table->longText('aggre_pay_bank_res')->nullable();
            $table->dateTime('aggre_pay_payment_date')->nullable();
            $table->longText('icici_order_id')->nullable();
            $table->longText('icici_plain_request')->nullable();
            $table->longText('icici_encrypt_request')->nullable();
            $table->longText('icici_payment_status')->nullable();
            $table->string('icici_bank_res', 150)->nullable();
            $table->dateTime('icici_payment_date')->nullable();
            $table->string('razorpay_payment_status', 150)->nullable();
            $table->longText('razorpay_bank_res')->nullable();
            $table->string('razorpay_order_id', 150)->nullable();
            $table->string('razorpay_dashboard_ps', 150)->nullable();
            $table->dateTime('razorpay_payment_date')->nullable();
            $table->bigInteger('sub_institute_id');
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_payment');
    }
};
