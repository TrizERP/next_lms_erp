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
        Schema::create('front_desk', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('ID');
            $table->integer('SUB_INSTITUTE_ID')->nullable()->index('SUB_INSTITUTE_ID');
            $table->string('VISITOR_TYPE', 50)->nullable();
            $table->date('DATE')->nullable();
            $table->time('IN_TIME')->nullable();
            $table->time('OUT_TIME')->nullable();
            $table->date('OUT_DATE')->nullable();
            $table->string('TITLE', 250)->nullable();
            $table->mediumText('DESCRIPTION')->nullable();
            $table->integer('STUDENT_ID')->nullable();
            $table->string('VISITOR_PHOTO', 150)->nullable();
            $table->text('FILE_SIZE')->nullable();
            $table->text('FILE_TYPE')->nullable();
            $table->string('TO_WHOM_MEET', 50)->nullable();
            $table->timestamp('CREATED_ON')->useCurrent();
            $table->string('CREATED_BY', 50)->nullable();
            $table->string('CREATED_IP', 150)->nullable();
            $table->integer('SYEAR')->nullable();
            $table->integer('MARKING_PERIOD_ID')->nullable();

            $table->index(['ID'], 'ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('front_desk');
    }
};
