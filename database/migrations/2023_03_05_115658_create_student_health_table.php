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
        Schema::create('student_health', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('student_id')->nullable()->index('FK_STUDENT_HEALTH_tblstudent');
            $table->decimal('syear', 4, 0)->nullable();
            $table->bigInteger('marking_period_id')->nullable();
            $table->string('doctor_name', 100)->nullable();
            $table->string('doctor_contact', 11)->nullable();
            $table->date('date')->nullable();
            $table->mediumText('file')->nullable();
            $table->text('file_size');
            $table->text('file_type');
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->integer('created_by')->nullable();
            $table->bigInteger('sub_institute_id')->nullable()->index('FK_student_health_school_setup');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_health');
    }
};
