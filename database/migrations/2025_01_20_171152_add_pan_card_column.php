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
            $table->string('pan_card',255);  
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
            $table->dropColumn('created_by');  
        });
    }
};
