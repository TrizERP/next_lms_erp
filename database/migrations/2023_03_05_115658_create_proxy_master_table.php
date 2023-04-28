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
        Schema::create('proxy_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id');
            $table->integer('syear')->default(0);
            $table->integer('timetable_id');
            $table->integer('grade_id');
            $table->integer('standard_id');
            $table->integer('division_id');
            $table->integer('batch_id')->nullable();
            $table->integer('subject_id');
            $table->integer('teacher_id');
            $table->integer('proxy_teacher_id');
            $table->integer('period_id');
            $table->string('week_day', 50);
            $table->date('proxy_date');
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proxy_master');
    }
};
