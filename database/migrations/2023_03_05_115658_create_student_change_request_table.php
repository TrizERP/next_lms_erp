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
        Schema::create('student_change_request', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->string('SYEAR', 10);
            $table->integer('SUB_INSTITUTE_ID');
            $table->bigInteger('CHANGE_REQUEST_ID');
            $table->bigInteger('STUDENT_ID');
            $table->string('REASON', 500);
            $table->mediumText('DESCRIPTION');
            $table->string('PROOF_OF_DOCUMENT', 1000);
            $table->integer('CREATED_BY');
            $table->timestamp('CREATED_ON')->useCurrent();
            $table->integer('STANDARD_ID');
            $table->integer('SECTION_ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_change_request');
    }
};
