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
        Schema::create('onet_institute_courses', function (Blueprint $table) {
            $table->integer('id')->unsigned()->autoIncrement(); // Primary Key INT(11)
            $table->integer('institute_id')->unsigned(); // Foreign Key to "onet_institute_data" table (INT(11))
            $table->string('aicte_id', 50); // VARCHAR(50)
            $table->string('college_name', 255); // VARCHAR(255)
            $table->text('description')->nullable(); // TEXT
            $table->string('programme', 255); // VARCHAR(255)
            $table->string('university', 255); // VARCHAR(255)
            $table->string('course_level', 50); // VARCHAR(50)
            $table->string('course_name', 255); // VARCHAR(255)
            $table->string('course_type', 25); // VARCHAR(25)
            $table->string('course_fees', 25); // VARCHAR(25)
            $table->integer('intake'); // INT(11)
            $table->string('enrollment', 25); // VARCHAR(25)
            $table->string('placement', 25); // VARCHAR(25)
            $table->timestamps(); // created_at and updated_at

            // Foreign key constraint
            $table->foreign('institute_id')->references('id')->on('onet_institute_data')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('onet_institute_courses');
    }
};
