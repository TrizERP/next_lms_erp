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
        Schema::create('result_remarks', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->string('result_remarks', 500);
            $table->string('term_id');
            $table->string('syear');
            $table->string('sub_institute_id');
            $table->string('created_by');
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
        Schema::dropIfExists('result_remarks');
    }
};
