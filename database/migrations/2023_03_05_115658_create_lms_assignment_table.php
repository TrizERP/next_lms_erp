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
        Schema::create('lms_assignment', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->integer('student_id')->nullable();
            $table->string('title', 50)->nullable();
            $table->string('description', 50)->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->integer('exam_id')->nullable();
            $table->string('exam_pdf', 250)->nullable();
            $table->date('created_date')->nullable();
            $table->integer('syear')->nullable();
            $table->date('submission_date')->nullable();
            $table->string('submission_image', 100)->nullable();
            $table->date('student_submitted_date')->nullable();
            $table->char('student_submission_status', 4)->nullable()->default('N');
            $table->integer('student_submitted_by')->nullable();
            $table->string('teacher_remarks', 250)->nullable();
            $table->date('teacher_submission_date')->nullable();
            $table->integer('teacher_id')->nullable();
            $table->char('teacher_submission_status', 4)->nullable()->default('N');
            $table->integer('created_by')->nullable();
            $table->string('created_ip', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->longText('json_annotation')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_assignment');
    }
};
