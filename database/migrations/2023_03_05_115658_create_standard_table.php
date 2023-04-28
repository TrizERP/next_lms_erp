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
        Schema::create('standard', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('grade_id');
            $table->string('name', 255);
            $table->string('short_name', 255);
            $table->integer('sort_order');
            $table->string('medium', 255)->nullable();
            $table->bigInteger('sub_institute_id')->default(0);
            $table->string('course_duration', 255)->nullable();
            $table->integer('next_grade_id')->nullable();
            $table->integer('next_standard_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->string('school_stream', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('standard');
    }
};
