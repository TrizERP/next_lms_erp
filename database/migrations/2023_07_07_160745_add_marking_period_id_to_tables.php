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
        Schema::table('standard', function (Blueprint $table) {
            //
            $table->bigInteger('marking_period_id')->nullable();
        });
        Schema::table('batch', function (Blueprint $table) {
            //
            $table->bigInteger('marking_period_id')->nullable();
        }); Schema::table('subject', function (Blueprint $table) {
            //
            $table->bigInteger('marking_period_id')->nullable();
        }); Schema::table('period', function (Blueprint $table) {
            //
            $table->bigInteger('marking_period_id')->nullable();
        }); Schema::table('tblstudent', function (Blueprint $table) {
            //
            $table->bigInteger('marking_period_id')->nullable();
        }); Schema::table('timetable', function (Blueprint $table) {
            //
            $table->bigInteger('marking_period_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('standard', function (Blueprint $table) {
            //
            $table->dropIfExists('marking_period_id'); 
                       
        });
        Schema::table('batch', function (Blueprint $table) {
            //
            $table->dropIfExists('marking_period_id');
        }); 
        Schema::table('subject', function (Blueprint $table) {
            //
            $table->dropIfExists('marking_period_id');
        });
         Schema::table('period', function (Blueprint $table) {
            //
            $table->dropIfExists('marking_period_id');
        });
         Schema::table('tblstudent', function (Blueprint $table) {
            //
            $table->dropIfExists('marking_period_id');
        }); 
        Schema::table('timetable', function (Blueprint $table) {
            //
            $table->dropIfExists('marking_period_id');
        });
    }
};
