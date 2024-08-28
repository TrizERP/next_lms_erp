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
        Schema::create('subject_elective', function (Blueprint $table) {
            $table->id();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->string('elective_sub_no', 20)->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->timestamps();
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subject_elective');
    }
};
