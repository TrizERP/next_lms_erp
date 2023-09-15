<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('result_exam_master', function (Blueprint $table) {
            //
            $table->bigInteger('standard_id')->nullable();
            $table->bigInteger('term_id')->nullable();
            $table->Integer('weightage')->nullable();
            $table->bigInteger('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('result_exam_master', function (Blueprint $table) {
            //
            $table->dropIfExists('standard_id');
            $table->dropIfExists('term_id');
            $table->dropIfExists('weightage');
            $table->dropIfExists('created_by');
        });
    }
};
