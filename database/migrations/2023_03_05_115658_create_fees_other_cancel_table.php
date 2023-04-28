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
        Schema::create('fees_other_cancel', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('receipt_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('fees_other_collection_id')->nullable();
            $table->bigInteger('deduction_head_id')->nullable();
            $table->bigInteger('student_id')->nullable();
            $table->date('cancellation_date')->nullable();
            $table->string('cancellation_remarks', 250)->nullable();
            $table->string('cancellation_amount', 250)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('created_ip', 250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_other_cancel');
    }
};
