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
        Schema::create('tblstudent_fees_failure', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable()->default(0);
            $table->integer('month_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->double('amount')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->string('status', 100)->nullable();
            $table->string('return_code', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent_fees_failure');
    }
};
