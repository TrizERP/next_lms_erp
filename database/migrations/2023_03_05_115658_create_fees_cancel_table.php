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
        Schema::create('fees_cancel', function (Blueprint $table) {
            $table->comment('');
            $table->integer('id', true);
            $table->string('reciept_id', 100)->index('reciept_id');
            $table->decimal('syear', 4, 0)->index('syear');
            $table->integer('sub_institute_id')->index('sub_institute_id');
            $table->integer('student_id')->index('student_id');
            $table->integer('standard_id')->index('standard_id');
            $table->integer('term_id')->index('term_id');
            $table->decimal('amountpaid', 10, 0);
            $table->dateTime('received_date')->nullable();
            $table->dateTime('cancel_date')->nullable();
            $table->string('cancel_type', 100)->nullable();
            $table->string('cancel_remark', 255)->nullable();
            $table->string('cancelled_by', 50);
            $table->string('ip_address', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_cancel');
    }
};
