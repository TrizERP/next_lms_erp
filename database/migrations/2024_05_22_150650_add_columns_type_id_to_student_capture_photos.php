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
        Schema::table('student_capture_photos', function (Blueprint $table) {
            //
            $table->integer('type_id')->nullable()->after('stu_image');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('student_capture_photos', function (Blueprint $table) {
            //
            $table->dropColumn('type_id');
        });
    }
};
