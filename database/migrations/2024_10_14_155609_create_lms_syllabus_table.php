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
        Schema::create('lms_syllabus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sub_institute_id')->nullable();
            $table->bigInteger('grade_id')->nullable();
            $table->bigInteger('standard_id')->nullable();
            $table->bigInteger('subject_id')->nullable();
            $table->bigInteger('curriculum_id')->nullable();
            $table->string('title',255)->nullable();
            $table->mediumText('objectives')->nullable();
            $table->mediumText('learning_outcomes')->nullable();
            $table->mediumText('suggested_materials')->nullable();
            $table->mediumText('assessment_plan')->nullable();
            $table->decimal('progress_tracking', 5, 2);
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
        Schema::dropIfExists('lms_syllabus');
    }
};
