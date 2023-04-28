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
        Schema::create('erptour', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('dashboard')->nullable();
            $table->integer('school_sidebar')->nullable();
            $table->integer('student_quota')->nullable();
            $table->integer('fees_title')->nullable();
            $table->integer('fees_structure')->nullable();
            $table->integer('fees_receipt')->nullable();
            $table->integer('fees_map')->nullable();
            $table->integer('fees_collect')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('sub_institute_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('erptour');
    }
};
