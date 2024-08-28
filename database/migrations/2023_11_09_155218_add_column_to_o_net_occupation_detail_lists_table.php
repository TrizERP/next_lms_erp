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
        Schema::table('o_net_occupation_detail_lists', function (Blueprint $table) {
            $table->integer('o_net_data_category_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('o_net_occupation_detail_lists', function (Blueprint $table) {
            $table->dropColumn('o_net_data_category_id');
        });
    }
};
