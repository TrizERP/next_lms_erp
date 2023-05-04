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
        Schema::create('tblstudent_parent_feedback', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('student_id')->nullable();
            $table->string('person_name', 50)->nullable();
            $table->string('purpose', 150)->nullable();
            $table->string('response', 150)->nullable();
            $table->string('comments', 150)->nullable();
            $table->date('date')->nullable();
            $table->integer('sub_institute_id')->nullable();
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('tblstudent_parent_feedback');
    }
};
