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
        Schema::create('lms_curriculum', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('grade_id')->nullable();
            $table->bigInteger('standard_id')->nullable();
            $table->bigInteger('subject_id')->nullable();
            $table->bigInteger('board_id')->nullable();
            $table->string('curriculum_name',255)->nullable();
            $table->mediumText('curriculum_alignment')->nullable();
            $table->mediumText('holistic_curriculum')->nullable();
            $table->mediumText('subject_curricula')->nullable();
            $table->mediumText('model_integration')->nullable();
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
        Schema::dropIfExists('lms_curriculum');
    }
};
