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
        Schema::create('result_html', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable();
            $table->integer('grade_id')->nullable();
            $table->integer('standard_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('term_id')->nullable();
            $table->integer('syear')->nullable();
            $table->longText('html')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->char('is_allowed', 1)->default('N');
            $table->timestamp('created_on')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('result_html');
    }
};
