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
        Schema::table('lms_question_master', function (Blueprint $table) {
            //
            $table->string('learning_outcome')->nullable();
            
        });
        Schema::table('lms_question_mapping', function (Blueprint $table) {
            //
            $table->string('reasons')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lms_questionmaster', function (Blueprint $table) {
            //
        });
    }
};
