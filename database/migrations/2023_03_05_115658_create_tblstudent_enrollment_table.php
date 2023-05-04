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
        Schema::create('tblstudent_enrollment', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->bigInteger('syear')->nullable()->index('syear');
            $table->bigInteger('student_id')->nullable()->index('student_id');
            $table->bigInteger('grade_id')->nullable()->index('grade_id');
            $table->bigInteger('standard_id')->nullable()->index('standard_id');
            $table->bigInteger('section_id')->nullable()->index('section_id');
            $table->string('student_quota', 50)->nullable()->index('student_quota');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('enrollment_code', 50)->nullable();
            $table->integer('drop_code')->nullable();
            $table->string('drop_remarks', 50)->nullable();
            $table->integer('term_id')->nullable();
            $table->string('remarks', 50)->nullable();
            $table->string('admission_fees', 50)->nullable();
            $table->integer('house_id')->nullable();
            $table->string('lc_number', 50)->nullable();
            $table->string('adhar', 50)->nullable();
            $table->bigInteger('sub_institute_id')->nullable()->index('sub_institute_id');
            $table->timestamp('created_on')->nullable()->useCurrent();
            $table->dateTime('updated_on')->nullable();

            $table->unique(['syear', 'student_id', 'term_id', 'sub_institute_id'], 'key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblstudent_enrollment');
    }
};
