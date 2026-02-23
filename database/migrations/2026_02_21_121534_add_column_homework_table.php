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
         Schema::table('homework', function (Blueprint $table) {
            $table->longText('prompt')->nullable();
            $table->string('student_level',50)->nullable();
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
         Schema::table('homework', function (Blueprint $table) {
            $table->dropColumn(['prompt', 'student_level']);
         });
    }
};
