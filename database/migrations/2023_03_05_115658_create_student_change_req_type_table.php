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
        Schema::create('student_change_req_type', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('SYEAR', 10)->nullable();
            $table->integer('SUB_INSTITUTE_ID')->nullable();
            $table->string('REQUEST_TITLE', 50)->nullable();
            $table->enum('PROOF_DOCUMENT_REQUIED', ['Y', 'N'])->nullable()->default('N');
            $table->string('PROOF_DOCUMENT_NAME', 50)->nullable();
            $table->integer('CREATED_BY')->nullable();
            $table->timestamp('CREATED_ON')->nullable()->useCurrent();
            $table->string('AMOUNT', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_change_req_type');
    }
};
