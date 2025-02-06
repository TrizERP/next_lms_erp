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
        Schema::table('tblstudent', function (Blueprint $table) {
            //
            $table->string('father_image',255)->nullable();
            $table->string('mother_image',255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tblstudent', function (Blueprint $table) {
            //
            $table->dropColumn('father_image');
            $table->dropColumn('mother_image');
        });
    }
};
