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
        Schema::create('result_exam_type_master', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('Id');
            $table->string('Code', 255);
            $table->string('ExamType', 255);
            $table->string('ShortName', 255)->nullable();
            $table->string('SortOrder', 255);
            $table->string('SubInstituteId', 255);
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
        Schema::dropIfExists('result_exam_type_master');
    }
};
