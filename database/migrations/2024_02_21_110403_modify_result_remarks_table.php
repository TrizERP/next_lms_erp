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
        Schema::table('result_remarks', function (Blueprint $table) {
            $table->integer('student_id')->change();
            $table->string('result_remarks', 500)->change();
            $table->integer('term_id')->change();
            $table->integer('syear')->change();
            $table->integer('sub_institute_id')->change();
            $table->integer('created_by')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
