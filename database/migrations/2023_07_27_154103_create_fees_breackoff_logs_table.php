<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fees_breackoff_logs', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->index('syear');
            $table->integer('admission_year')->index('admission_year');
            $table->bigInteger('fee_type_id');
            $table->bigInteger('quota')->index('quota');
            $table->bigInteger('grade_id')->index('grade_id');
            $table->bigInteger('standard_id')->index('standard_id');
            $table->bigInteger('section_id')->index('section_id');
            $table->bigInteger('month_id');
            $table->integer('amount');
            $table->integer('sub_institute_id')->index('sub_institute_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_breackoff_logs');
    }
};
