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
        Schema::create('fees_other_collection', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('receipt_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->bigInteger('student_id')->nullable();
            $table->date('deduction_date')->nullable();
            $table->bigInteger('deduction_head_id')->nullable();
            $table->string('deduction_remarks', 150)->nullable();
            $table->decimal('deduction_amount', 10, 0)->nullable();
            $table->string('payment_mode', 50)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_branch', 150)->nullable();
            $table->string('cheque_dd_no', 50)->nullable();
            $table->date('cheque_dd_date')->nullable();
            $table->longText('paid_fees_html')->nullable();
            $table->char('is_deleted', 5)->nullable()->default('N');
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->dateTime('updated_on')->nullable();
            $table->string('created_ip', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_other_collection');
    }
};
