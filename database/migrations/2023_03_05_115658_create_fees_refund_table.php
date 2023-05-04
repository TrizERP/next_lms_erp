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
        Schema::create('fees_refund', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('student_id')->nullable()->index('student_id');
            $table->bigInteger('syear')->nullable();
            $table->bigInteger('sub_institute_id')->nullable();
            $table->string('receipt_no', 50)->nullable();
            $table->mediumText('fees_html')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->string('created_ip_address', 150)->nullable();
            $table->string('payment_mode', 150)->nullable();
            $table->string('bank_branch', 150)->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('cheque_no', 50)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('cheque_bank_name', 50)->nullable();
            $table->mediumText('remarks')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->decimal('amount', 10, 0)->nullable()->default(0);
            $table->decimal('tution_fee', 10, 0)->nullable()->default(0);
            $table->decimal('admission_fee', 10, 0)->nullable()->default(0);
            $table->decimal('activity_fee', 10, 0)->nullable()->default(0);
            $table->decimal('term_fee', 10, 0)->nullable()->default(0);
            $table->decimal('deposit', 10, 0)->nullable()->default(0);
            $table->decimal('co_curriculam_fees', 10, 0)->nullable()->default(0);
            $table->decimal('computer_fees', 10, 0)->nullable()->default(0);
            $table->decimal('smart_class', 10, 0)->nullable()->default(0);
            $table->decimal('security_charges', 10, 0)->nullable()->default(0);
            $table->decimal('photograph', 10, 0)->nullable()->default(0);
            $table->decimal('cal_misc', 10, 0)->nullable()->default(0);
            $table->decimal('title_1', 10, 0)->nullable()->default(0);
            $table->decimal('title_2', 10, 0)->nullable()->default(0);
            $table->decimal('title_3', 10, 0)->nullable()->default(0);
            $table->decimal('title_4', 10, 0)->nullable()->default(0);
            $table->decimal('title_5', 10, 0)->nullable()->default(0);
            $table->decimal('title_6', 10, 0)->nullable()->default(0);
            $table->decimal('title_7', 10, 0)->nullable()->default(0);
            $table->decimal('title_8', 10, 0)->nullable()->default(0);
            $table->decimal('title_9', 10, 0)->nullable()->default(0);
            $table->decimal('title_10', 10, 0)->nullable()->default(0);
            $table->decimal('title_11', 10, 0)->nullable()->default(0);
            $table->decimal('title_12', 10, 0)->nullable()->default(0);

            $table->index(['id'], 'id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_refund');
    }
};
