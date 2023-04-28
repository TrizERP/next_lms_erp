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
        Schema::create('fees_paid_other', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('reciept_id', 100)->nullable();
            $table->decimal('syear', 4, 0)->index('syear');
            $table->string('sub_institute_id', 50);
            $table->integer('student_id')->index('student_id');
            $table->integer('month_id')->index('marking_period_id');
            $table->string('fine', 50)->nullable();
            $table->string('bank_name', 250)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('cheque_dd_no', 50)->nullable();
            $table->date('cheque_dd_date')->nullable();
            $table->string('payment_mode', 50)->nullable();
            $table->decimal('actual_amountpaid', 10, 0);
            $table->decimal('fees_discount', 10, 0)->default(0);
            $table->string('receivedby', 50);
            $table->string('comment', 255);
            $table->dateTime('received_date')->nullable();
            $table->mediumText('paid_fees_html')->nullable();
            $table->string('is_deleted', 50)->nullable()->default('N');
            $table->string('is_waved', 150)->nullable();
            $table->timestamp('created_date')->nullable()->useCurrent();
            $table->date('receiptdate')->nullable();
            $table->mediumText('remarks')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->decimal('1', 10, 0)->default(0);
            $table->decimal('2', 10, 0)->default(0);
            $table->decimal('3', 10, 0)->default(0);
            $table->decimal('4', 10, 0)->default(0);
            $table->decimal('5', 10, 0)->default(0);
            $table->decimal('6', 10, 0)->default(0);
            $table->decimal('7', 10, 0)->default(0);
            $table->decimal('8', 10, 0)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_paid_other');
    }
};
