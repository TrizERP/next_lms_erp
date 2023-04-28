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
        Schema::create('lms_offline_exam', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable();
            $table->integer('question_paper_id')->nullable();
            $table->integer('assignment_id')->nullable();
            $table->integer('total_right')->nullable();
            $table->integer('total_wrong')->nullable();
            $table->integer('obtain_marks')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_offline_exam');
    }
};
