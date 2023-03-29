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
        Schema::create('result_co_scholastic_marks_entries', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('grade_id');
            $table->string('standard_id');
            $table->string('term_id');
            $table->string('student_id');
            $table->string('co_scholastic_id');
            $table->string('grade');
            $table->decimal('points', 5);
            $table->string('sub_institute_id');
            $table->string('syear');
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
        Schema::dropIfExists('result_co_scholastic_marks_entries');
    }
};
