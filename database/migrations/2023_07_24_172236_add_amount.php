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
        //
        Schema::table('transport_school_shift', function (Blueprint $table) {
            //
            $table->Integer('shift_rate')->nullable();
            $table->Integer('km_amount')->nullable();            
        });
        Schema::table('transport_map_student', function (Blueprint $table) {
            //
            $table->Integer('distance')->nullable();
            $table->Integer('amount')->nullable();            
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
        Schema::table('transport_school_shift', function (Blueprint $table) {
            //
            $table->dropIfExists('shift_rate');
            $table->dropIfExists('km_amount');
            
        }); 
        Schema::table('transport_map_student', function (Blueprint $table) {
            //
            $table->dropIfExists('distance');
            $table->dropIfExists('amount');            
        });
    }
};
