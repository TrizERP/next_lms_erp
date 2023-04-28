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
        Schema::create('homework', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();
            $table->integer('student_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('subject_id')->nullable();
            $table->string('title', 50)->nullable();
            $table->text('description')->nullable();
            $table->date('date')->nullable();
            $table->string('image', 50)->nullable();
            $table->text('image_size')->nullable();
            $table->text('image_type')->nullable();
            $table->string('type', 50)->nullable();
            $table->date('submission_date')->nullable();
            $table->char('completion_status', 4)->nullable()->default('N');
            $table->string('submission_remarks', 50)->nullable();
            $table->string('submission_image', 100)->nullable();
            $table->text('submission_image_size')->nullable();
            $table->text('submission_image_type')->nullable();
            $table->integer('created_by')->nullable();
            $table->string('created_ip', 50)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('homework');
    }
};
