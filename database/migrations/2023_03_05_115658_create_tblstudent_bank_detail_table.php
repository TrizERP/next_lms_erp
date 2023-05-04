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
        Schema::create('tblstudent_bank_detail', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable()->unique('student_id');
            $table->integer('sub_institute_id')->nullable();
            $table->string('ac_holder_name', 255)->nullable();
            $table->string('ac_number', 100)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('ifsc_code', 100)->nullable();
            $table->enum('is_registered', ['Y', 'N'])->nullable()->default('N');
            $table->string('ac_type', 100)->nullable();
            $table->string('UMRN', 100)->nullable();
            $table->date('registration_date')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent_bank_detail');
    }
};
