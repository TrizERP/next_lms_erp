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
        Schema::create('result_create_exam', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->integer('sub_institute_id')->default(0);
            $table->integer('term_id')->default(0);
            $table->string('medium');
            $table->integer('exam_id')->default(0);
            $table->integer('standard_id')->default(0);
            $table->char('app_disp_status', 1)->default('Y');
            $table->integer('subject_id')->default(0);
            $table->string('title');
            $table->integer('points')->default(0);
            $table->integer('con_point')->nullable();
            $table->string('marks_type');
            $table->char('report_card_status', 1)->default('Y');
            $table->integer('sort_order')->nullable()->default(0);
            $table->date('exam_date')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
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
        Schema::dropIfExists('result_create_exam');
    }
};
