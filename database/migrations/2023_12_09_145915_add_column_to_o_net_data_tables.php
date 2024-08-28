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
        Schema::table('o_net_data_tables', function (Blueprint $table) {
            $table->string('employee_by_this_industry')->nullable();
            $table->string('projected_growth')->nullable();
            $table->string('projected_growth_openings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('o_net_data_tables', function (Blueprint $table) {
            $table->dropColumn('employee_by_this_industry');
            $table->dropColumn('projected_growth');
            $table->dropColumn('projected_growth_openings');
        });
    }
};
