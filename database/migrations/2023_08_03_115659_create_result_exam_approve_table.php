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
        Schema::create('result_exam_approve', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('subject_id');
            $table->bigInteger('standard_id');
            $table->bigInteger('division_id');
            $table->bigInteger('exam_id');
            $table->bigInteger('term_id');
            $table->bigInteger('status');
            $table->bigInteger('sub_institute_id');
            $table->bigInteger('created_by');            
            $table->string('module_name',20);
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
        Schema::dropIfExists('result_exam_approve');
    }
};
