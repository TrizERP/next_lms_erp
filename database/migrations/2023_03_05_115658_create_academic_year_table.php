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
        Schema::create('academic_year', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('term_id');
            $table->integer('syear');
            $table->integer('sub_institute_id');
            $table->string('title', 255);
            $table->string('short_name', 255);
            $table->integer('sort_order');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('post_start_date');
            $table->date('post_end_date');
            $table->string('does_grades', 255);
            $table->string('does_exams', 255);
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
        Schema::dropIfExists('academic_year');
    }
};
