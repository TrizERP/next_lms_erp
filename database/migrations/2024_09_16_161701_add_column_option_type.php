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
        Schema::table('sub_std_map', function (Blueprint $table) {
            //
            $table->integer('optional_type')->after('load')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sub_std_map', function (Blueprint $table) {
            //
            $table->dropColumn('optional_type');
        });
    }
};
