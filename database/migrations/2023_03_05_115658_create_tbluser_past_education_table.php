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
        Schema::create('tbluser_past_education', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable();
            $table->string('degree', 100)->nullable();
            $table->string('medium', 100)->nullable();
            $table->string('university_name', 100)->nullable();
            $table->string('passing_year', 100)->nullable();
            $table->string('main_subject', 100)->nullable();
            $table->string('secondary_subject', 100)->nullable();
            $table->string('percentage', 100)->nullable();
            $table->string('cpi', 100)->nullable();
            $table->string('cgpa', 100)->nullable();
            $table->string('remarks', 100)->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbluser_past_education');
    }
};
